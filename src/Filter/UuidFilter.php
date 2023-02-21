<?php

declare(strict_types=1);

namespace Webstack\ApiPlatformExtensionsBundle\Filter;

use Doctrine\DBAL\Types\Type;
use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Ramsey\Uuid\Codec\OrderedTimeCodec;
use Ramsey\Uuid\UuidFactory;
use Ramsey\Uuid\UuidInterface;
use Symfony\Bridge\Doctrine\Types\AbstractUidType;

class UuidFilter extends AbstractFilter
{
    /**
     * @throws Exception
     */
    protected function filterProperty(string $property, $value, QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, Operation $operation = null, array $context = []): void
    {
        if (
            !$this->isPropertyEnabled($property, $resourceClass) || !$this->isPropertyMapped($property, $resourceClass)
        ) {
            return;
        }

        $alias = $queryBuilder->getRootAliases()[0];
        $field = $property;

        if ($this->isPropertyNested($property, $resourceClass)) {
            [$alias, $field] = $this->addJoinsForNestedProperty($property, $alias, $queryBuilder, $queryNameGenerator, $resourceClass, Join::INNER_JOIN);
        }

        $valueParameter = $queryNameGenerator->generateParameterName($field);

        $type = $this->managerRegistry->getManagerForClass($resourceClass)->getClassMetadata($resourceClass)->getTypeOfField($field);
        $typeObject = Type::getType($type);

        if (is_array($value)) {
            if ($typeObject instanceof AbstractUidType) {
                $platform = $this->managerRegistry->getManagerForClass($resourceClass)->getConnection()->getDatabasePlatform();

                $queryBuilder
                    ->andWhere(sprintf('%s.%s IN (:%s)', $alias, $field, $valueParameter))
                    ->setParameter($valueParameter, array_map(static function (string $uuid) use ($typeObject, $platform) {
                        return $typeObject->convertToDatabaseValue($uuid, $platform);
                    }, $value));
            } elseif ($type instanceof UuidInterface) {
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
            }
        } else {
            preg_match('/[a-f\d]{8}(-[a-f\d]{4}){4}[a-f\d]{8}$/i', $value, $match);

            if (!empty($match[0])) {
                $value = $match[0];
            }

            $queryBuilder
                ->andWhere(sprintf('%s.%s IN (:%s)', $alias, $field, $valueParameter))
                ->setParameter($valueParameter, $value, $type);
        }
    }

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
                    'is_collection' => str_ends_with((string)$filterParameterName, '[]'),
                    'swagger' => [
                        'type' => 'uuid',
                    ],
                ];
            }
        }

        return $description;
    }
}
