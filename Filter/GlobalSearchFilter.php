<?php

declare(strict_types=1);

namespace Webstack\ApiPlatformExtensionsBundle\Filter;

use ApiPlatform\Core\Bridge\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use Doctrine\ORM\QueryBuilder;

/**
 * Class GlobalSearchFilter
 */
class GlobalSearchFilter extends AbstractOrFilter
{
    public const GLOBAL_SEARCH_QUERY_PARAMETER_NAME = '_global_search';
    public const GLOBAL_SEARCH_QUERY_PROPERTIES_PARAMETER_NAME = '_global_search.properties';

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
        if ($property !== self::GLOBAL_SEARCH_QUERY_PARAMETER_NAME) {
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

        $alias = $queryBuilder->getRootAliases()[0];
        $orX = [];

        foreach ($allowedProperties as $allowedProperty) {
            $field = $allowedProperty;

            if ($this->isPropertyNested($allowedProperty, $resourceClass)) {
                [$alias, $field] = $this->addJoinsForNestedProperty($allowedProperty, $alias, $queryBuilder,
                    $queryNameGenerator,
                    $resourceClass);
            }

            $valueParameter = $queryNameGenerator->generateParameterName($field);

            $orX[] = $this->getExpressionByStrategy($queryBuilder, $alias, $field, $valueParameter, $value,
                $this->getProperties()[$allowedProperty]);
        }

        $queryBuilder->andWhere(call_user_func_array([$queryBuilder->expr(), 'orX'], $orX));
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
