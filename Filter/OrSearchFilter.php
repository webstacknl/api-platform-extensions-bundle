<?php

declare(strict_types=1);

namespace Webstack\ApiPlatformExtensionsBundle\Filter;

use ApiPlatform\Core\Bridge\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use Doctrine\ORM\QueryBuilder;

/**
 * Class OrSearchFilter
 */
class OrSearchFilter extends AbstractOrFilter
{
    public const OR_SEARCH_QUERY_PARAMETER_NAME = '_search';

    /**
     * @param string $property
     * @param $value
     * @param QueryBuilder $queryBuilder
     * @param QueryNameGeneratorInterface $queryNameGenerator
     * @param string $resourceClass
     * @param string|null $operationName
     * @param array $context
     */
    protected function filterProperty(
        string $property,
        $value,
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        string $operationName = null,
        array $context = []
    ): void {
        if ($property !== self::OR_SEARCH_QUERY_PARAMETER_NAME) {
            return;
        }

        if (!empty($context['filters'][self::OR_SEARCH_QUERY_PARAMETER_NAME])) {
            $searchProperties = $context['filters'][self::OR_SEARCH_QUERY_PARAMETER_NAME];
            $allowedProperties = array_keys($this->getProperties());

            if (!empty($searchProperties)) {
                $allowedProperties = array_intersect($allowedProperties, array_keys($searchProperties));
            }

            $alias = $queryBuilder->getRootAliases()[0];
            $orX = [];

            foreach ($allowedProperties as $allowedProperty) {
                $field = $allowedProperty;

                if ($this->isPropertyNested($allowedProperty, $resourceClass)) {
                    [$alias, $field] = $this->addJoinsForNestedProperty($allowedProperty, $alias, $queryBuilder,
                        $queryNameGenerator,
                        $resourceClass);
                }

                foreach ($searchProperties[$allowedProperty] as $propertyValue) {
                    $valueParameter = $queryNameGenerator->generateParameterName($field);

                    $orX[] = $this->getExpressionByStrategy($queryBuilder, $alias, $field, $valueParameter, $propertyValue,
                        $this->getProperties()[$allowedProperty]);
                }
            }

            $queryBuilder->andWhere(call_user_func_array([$queryBuilder->expr(), 'orX'], $orX));
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

            $propertyKey = self::OR_SEARCH_QUERY_PARAMETER_NAME. '[' . $property . '][]';

            $description[$propertyKey] = [
                'property' => $propertyKey,
                'type' => 'string',
                'required' => false,
                'is_collection' => true,
            ];
        }

        return $description;
    }
}
