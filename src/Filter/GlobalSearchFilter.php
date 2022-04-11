<?php

declare(strict_types=1);

namespace Webstack\ApiPlatformExtensionsBundle\Filter;

use ApiPlatform\Core\Bridge\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;

class GlobalSearchFilter extends AbstractOrFilter
{
    public const GLOBAL_SEARCH_QUERY_PARAMETER_NAME = '_global_search';
    public const GLOBAL_SEARCH_QUERY_PROPERTIES_PARAMETER_NAME = '_global_search.properties';

    protected function filterProperty(string $property, $value, QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, string $operationName = null, array $context = []): void
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

        $allowedProperties = array_keys($this->getProperties());

        if (!empty($searchProperties)) {
            $allowedProperties = array_intersect($allowedProperties, $searchProperties);
        }

        $rootAlias = $queryBuilder->getRootAliases()[0];
        $orX = [];

        foreach ($allowedProperties as $allowedProperty) {
            $field = $allowedProperty;
            $alias = $rootAlias;

            if ($this->isPropertyNested($allowedProperty, $resourceClass)) {
                [$alias, $field] = $this->addJoinsForNestedProperty($allowedProperty, $rootAlias, $queryBuilder,
                    $queryNameGenerator,
                    $resourceClass,
                    Join::LEFT_JOIN,
                );
            }

            $valueParameter = $queryNameGenerator->generateParameterName($field);

            $orX[] = $this->getExpressionByStrategy($queryBuilder, $alias, $field, $valueParameter, $value,
                $this->getProperties()[$allowedProperty]);
        }

        $queryBuilder->andWhere(call_user_func_array([$queryBuilder->expr(), 'orX'], $orX));
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

        foreach ($properties as $property => $unused) {
            if (!$this->isPropertyMapped($property, $resourceClass)) {
                continue;
            }

            $availableProperties[] = $property;
        }

        $description[self::GLOBAL_SEARCH_QUERY_PROPERTIES_PARAMETER_NAME] = [
            'property' => self::GLOBAL_SEARCH_QUERY_PROPERTIES_PARAMETER_NAME,
            'type' => 'string',
            'required' => false,
            'swagger' => [
                'description' => implode(', ', $availableProperties),
            ],
        ];

        return $description;
    }
}
