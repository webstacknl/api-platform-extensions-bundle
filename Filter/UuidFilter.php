<?php

declare(strict_types=1);

namespace Webstack\ApiPlatformExtensionsBundle\Filter;

use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\AbstractContextAwareFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use Doctrine\ORM\QueryBuilder;
use Ramsey\Uuid\Codec\OrderedTimeCodec;
use Ramsey\Uuid\UuidFactory;

/**
 * Class UuidFilter
 */
class UuidFilter extends AbstractContextAwareFilter
{
    /**
     * @param string $property
     * @param $value
     * @param QueryBuilder $queryBuilder
     * @param QueryNameGeneratorInterface $queryNameGenerator
     * @param string $resourceClass
     * @param string|null $operationName
     */
    protected function filterProperty(
        string $property,
        $value,
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        string $operationName = null
    ): void {
        if (
            !$this->isPropertyEnabled($property, $resourceClass) ||
            !$this->isPropertyMapped($property, $resourceClass)
        ) {
            return;
        }

        $alias = $queryBuilder->getRootAliases()[0];
        $field = $property;

        if ($this->isPropertyNested($property, $resourceClass)) {
            [$alias, $field] = $this->addJoinsForNestedProperty($property, $alias, $queryBuilder, $queryNameGenerator,
                                                                $resourceClass);
        }

        $valueParameter = $queryNameGenerator->generateParameterName($field);

        if (is_array($value)) {
            $uuidFactory = new UuidFactory();
            $uuidFactory->setCodec(new OrderedTimeCodec($uuidFactory->getUuidBuilder()));

            $queryBuilder
                ->andWhere(sprintf('%s.%s IN (:%s)', $alias, $field, $valueParameter))
                ->setParameter($valueParameter, array_map(static function ($uuid) use ($uuidFactory) {
                    preg_match('/[a-f\d]{8}(-[a-f\d]{4}){4}[a-f\d]{8}$/i', $uuid, $match);

                    if (!empty($match[0])) {
                        $uuid = $match[0];
                    }

                    return $uuidFactory->fromString($uuid)->getBytes();
                }, $value));
        } else {
            preg_match('/[a-f\d]{8}(-[a-f\d]{4}){4}[a-f\d]{8}$/i', $value, $match);

            if (!empty($match[0])) {
                $value = $match[0];
            }

            $queryBuilder
                ->andWhere(sprintf('%s.%s IN (:%s)', $alias, $field, $valueParameter))
                ->setParameter($valueParameter, $value, 'uuid_binary_ordered_time');
        }
    }

    /**
     * @param string $resourceClass
     * @return array
     */
    public function getDescription(string $resourceClass): array
    {
        $description = [];

        $properties = $this->getProperties();

        if (null === $properties) {
            $properties = array_fill_keys($this->getClassMetadata($resourceClass)->getFieldNames(), null);
        }

        foreach ($properties as $property => $unused) {
            if (!$this->isPropertyMapped($property, $resourceClass)) {
                continue;
            }

            $filterParameterNames = [$property, $property . '[]'];

            foreach ($filterParameterNames as $filterParameterName) {
                $description[$filterParameterName] = [
                    'property' => $property,
                    'type' => 'uuid',
                    'required' => false,
                    'strategy' => 'exact',
                    'is_collection' => '[]' === substr((string)$filterParameterName, -2),
                    'swagger' => [
                        'type' => 'uuid',
                    ],
                ];
            }
        }

        return $description;
    }
}
