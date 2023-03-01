<?php

declare(strict_types=1);

namespace Webstack\ApiPlatformExtensionsBundle\Filter;

use ApiPlatform\Api\IriConverterInterface;
use ApiPlatform\Doctrine\Common\Filter\SearchFilterInterface;
use ApiPlatform\Doctrine\Common\Filter\SearchFilterTrait;
use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Exception\InvalidArgumentException;
use ApiPlatform\Metadata\Operation;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

class GlobalSearchFilter extends AbstractFilter implements SearchFilterInterface
{
    use SearchFilterTrait;

    public const DOCTRINE_INTEGER_TYPE = Types::INTEGER;

    final public const GLOBAL_SEARCH_QUERY_PARAMETER_NAME = '_global_search';

    final public const GLOBAL_SEARCH_QUERY_PROPERTIES_PARAMETER_NAME = '_global_search.properties';

    protected function getIriConverter(): IriConverterInterface
    {
        return $this->iriConverter;
    }

    protected function getPropertyAccessor(): PropertyAccessorInterface
    {
        return $this->propertyAccessor;
    }

    /**
     * {@inheritdoc}
     */
    protected function filterProperty(string $property, $value, QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, Operation $operation = null, array $context = []): void
    {
        if (self::GLOBAL_SEARCH_QUERY_PARAMETER_NAME !== $property) {
            return;
        }

        $searchProperties = [];

        if (!empty($context['filters'][self::GLOBAL_SEARCH_QUERY_PROPERTIES_PARAMETER_NAME])) {
            $searchProperties = $context['filters'][self::GLOBAL_SEARCH_QUERY_PROPERTIES_PARAMETER_NAME];
            $searchProperties = explode(',', $searchProperties);
            $searchProperties = array_map('trim', $searchProperties);
        }

        $allowedProperties = array_keys((array) $this->getProperties());

        if (!empty($searchProperties)) {
            $allowedProperties = array_intersect($allowedProperties, $searchProperties);
        }

        $allowedProperties = array_filter($allowedProperties, function ($property) use ($resourceClass) {
            return $this->isPropertyEnabled($property, $resourceClass) && $this->isPropertyMapped($property, $resourceClass, true);
        });

        $alias = $queryBuilder->getRootAliases()[0];
        $orX = [];

        foreach ($allowedProperties as $allowedProperty) {
            $field = $allowedProperty;

            $values = $this->normalizeValues((array) $value, $allowedProperty);

            if (null === $values) {
                return;
            }

            $associations = [];
            if ($this->isPropertyNested($allowedProperty, $resourceClass)) {
                [$alias, $field, $associations] = $this->addJoinsForNestedProperty($allowedProperty, $alias, $queryBuilder, $queryNameGenerator, $resourceClass, Join::INNER_JOIN);
            }

            $caseSensitive = true;
            $strategy = $this->properties[$allowedProperty] ?? sprintf('i%s', self::STRATEGY_PARTIAL);

            // prefixing the strategy with i makes it case-insensitive
            if (str_starts_with($strategy, 'i')) {
                $strategy = substr($strategy, 1);
                $caseSensitive = false;
            }

            $metadata = $this->getNestedMetadata($resourceClass, $associations);

            if ($metadata->hasField($field)) {
                if (!$this->hasValidValues($values, $this->getDoctrineFieldType($allowedProperty, $resourceClass))) {
                    $this->logger->notice('Invalid filter ignored', [
                        'exception' => new InvalidArgumentException(sprintf('Values for field "%s" are not valid according to the doctrine type.', $field)),
                    ]);

                    return;
                }

                $orX[] = $this->addWhereByStrategy($strategy, $queryBuilder, $queryNameGenerator, $alias, $field, $values[0], $caseSensitive);
            }
        }

        $queryBuilder->andWhere(call_user_func_array([$queryBuilder->expr(), 'orX'], $orX));
    }

    /**
     * Adds where clause according to the strategy.
     *
     * @throws InvalidArgumentException If strategy does not exist
     */
    protected function addWhereByStrategy(string $strategy, QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $alias, string $field, mixed $value, bool $caseSensitive)
    {
        $wrapCase = $this->createWrapCase($caseSensitive);
        $valueParameter = ':' . $queryNameGenerator->generateParameterName($field);
        $aliasedField = sprintf('%s.%s', $alias, $field);

        $queryBuilder->setParameter($valueParameter, $caseSensitive ? $value : strtolower($value));

        if (self::STRATEGY_EXACT === $strategy) {
            return $queryBuilder->expr()->eq($wrapCase($aliasedField), $wrapCase($valueParameter));
        }

        return match ($strategy) {
            self::STRATEGY_PARTIAL => $queryBuilder->expr()->like(
                $wrapCase($aliasedField),
                $wrapCase((string)$queryBuilder->expr()->concat("'%'", $valueParameter, "'%'"))
            ),
            self::STRATEGY_START => $queryBuilder->expr()->like(
                $wrapCase($aliasedField),
                $wrapCase((string)$queryBuilder->expr()->concat($valueParameter, "'%'"))
            ),
            self::STRATEGY_END => $queryBuilder->expr()->like(
                $wrapCase($aliasedField),
                $wrapCase((string)$queryBuilder->expr()->concat("'%'", $valueParameter))
            ),
            self::STRATEGY_WORD_START => $queryBuilder->expr()->orX(
                $queryBuilder->expr()->like(
                    $wrapCase($aliasedField),
                    $wrapCase((string)$queryBuilder->expr()->concat($valueParameter, "'%'"))
                ),
                $queryBuilder->expr()->like(
                    $wrapCase($aliasedField),
                    $wrapCase((string)$queryBuilder->expr()->concat("'% '", $valueParameter, "'%'"))
                )
            ),
            default => throw new InvalidArgumentException(sprintf('strategy %s does not exist.', $strategy)),
        };
    }

    /**
     * Creates a function that will wrap a Doctrine expression according to the
     * specified case sensitivity.
     *
     * For example, "o.name" will get wrapped into "LOWER(o.name)" when $caseSensitive
     * is false.
     */
    protected function createWrapCase(bool $caseSensitive): \Closure
    {
        return static function (string $expr) use ($caseSensitive): string {
            if ($caseSensitive) {
                return $expr;
            }

            return sprintf('LOWER(%s)', $expr);
        };
    }

    public function getDescription(string $resourceClass): array
    {
        $description = [];

        $properties = $this->getProperties();

        if (null === $properties) {
            $properties = array_fill_keys($this->getClassMetadata($resourceClass)->getFieldNames(), null);
        }

        $description[self::GLOBAL_SEARCH_QUERY_PARAMETER_NAME] = [
            'property' => self::GLOBAL_SEARCH_QUERY_PARAMETER_NAME,
            'type' => 'string',
            'required' => false,
        ];

        $availableProperties = [];

        foreach (array_keys($properties) as $property) {
            if (!$this->isPropertyMapped($property, $resourceClass)) {
                continue;
            }

            $availableProperties[] = $property;
        }

        $description[self::GLOBAL_SEARCH_QUERY_PROPERTIES_PARAMETER_NAME] = [
            'property' => self::GLOBAL_SEARCH_QUERY_PROPERTIES_PARAMETER_NAME,
            'type' => 'string',
            'required' => false,
            'description' => sprintf('Pass one or more of the following keys (comma separated): %s, or leave empty to search within all of the properties.', implode(', ', $availableProperties)),
            'openapi' => [
                'description' => implode(', ', $availableProperties),
            ],
            'swagger' => [
                'description' => implode(', ', $availableProperties),
            ],
        ];

        return $description;
    }

    /**
     * {@inheritdoc}
     */
    protected function getType(string $doctrineType): string
    {
        return match ($doctrineType) {
            Types::ARRAY => 'array',
            Types::BIGINT, Types::INTEGER, Types::SMALLINT => 'int',
            Types::BOOLEAN => 'bool',
            Types::DATE_MUTABLE, Types::TIME_MUTABLE, Types::DATETIME_MUTABLE, Types::DATETIMETZ_MUTABLE, Types::DATE_IMMUTABLE, Types::TIME_IMMUTABLE, Types::DATETIME_IMMUTABLE, Types::DATETIMETZ_IMMUTABLE => \DateTimeInterface::class,
            Types::FLOAT => 'float',
            default => 'string',
        };
    }
}
