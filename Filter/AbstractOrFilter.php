<?php

namespace Webstack\ApiPlatformExtensionsBundle\Filter;

use ApiPlatform\Core\Bridge\Doctrine\Common\Filter\SearchFilterInterface;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\AbstractContextAwareFilter;
use Doctrine\ORM\Query\Expr\Comparison;
use Doctrine\ORM\QueryBuilder;
use InvalidArgumentException;

/**
 * Class AbstractOrFilter
 */
abstract class AbstractOrFilter extends AbstractContextAwareFilter implements SearchFilterInterface
{
    /**
     * @param QueryBuilder $queryBuilder
     * @param string $alias
     * @param string $field
     * @param string $valueParameter
     * @param $value
     * @param string $strategy
     *
     * @return Comparison
     */
    protected function getExpressionByStrategy(
        QueryBuilder $queryBuilder,
        string $alias,
        string $field,
        string $valueParameter,
        $value,
        string $strategy
    ): ?Comparison {
        $x = sprintf('%s.%s', $alias, $field);

        $expression = null;

        switch ($strategy) {
            case null:
            case self::STRATEGY_EXACT:
                $expression = $queryBuilder->expr()->eq($x, ':' . $valueParameter);

                $queryBuilder->setParameter($valueParameter, $value);

                break;
            case self::STRATEGY_PARTIAL:
                $expression = $queryBuilder->expr()->like($x, ':' . $valueParameter);

                $queryBuilder->setParameter($valueParameter, '%' . $value . '%');

                break;
            case self::STRATEGY_START:
                $expression = $queryBuilder->expr()->like($x, ':' . $valueParameter);

                $queryBuilder->setParameter($valueParameter, '%' . $value);

                break;
            case self::STRATEGY_END:
                $expression = $queryBuilder->expr()->like($x, ':' . $valueParameter);

                $queryBuilder->setParameter($valueParameter, $value . '%');

                break;
            default:
                throw new InvalidArgumentException(sprintf('strategy %s is not supported or does not exist.',
                    $strategy));
        }

        return $expression;
    }
}
