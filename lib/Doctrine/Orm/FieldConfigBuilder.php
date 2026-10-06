<?php

declare(strict_types=1);

/*
 * This file is part of the RollerworksSearch package.
 *
 * (c) Sebastiaan Stok <s.stok@rollerscapes.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Rollerworks\Component\Search\Doctrine\Orm;

use Doctrine\ORM\EntityManagerInterface;
use Rollerworks\Component\Search\Field\OrderField;
use Rollerworks\Component\Search\FieldSet;

/**
 * @internal
 */
final class FieldConfigBuilder
{
    /** @var array<string, array<string, OrmQueryField>> ['fieldName'][mappingIndex] => {OrmQueryField} */
    private array $fields = [];

    private ?string $defaultEntity = null;
    private ?string $defaultAlias = null;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly FieldSet $fieldSet,
    ) {}

    public function setDefaultEntity(string $entity, string $alias): void
    {
        $this->defaultEntity = $this->entityManager->getClassMetadata($entity)->getName();
        $this->defaultAlias = $alias;
    }

    public function setField(string $mappingName, string $property, ?string $alias = null, ?string $entity = null, ?string $type = null): void
    {
        $mappingIdx = '';
        $fieldName = $mappingName;

        if ($entity === null && $this->defaultEntity === null) {
            throw new \RuntimeException('No default entity is set, either provide the entity or set a default entity first.');
        }

        if (str_contains($mappingName, '#')) {
            [$fieldName, $mappingIdx] = explode('#', $mappingName, 2);
            unset($this->fields[$fieldName]['']);
        } else {
            $this->fields[$fieldName] = [];
        }

        if (OrderField::isOrder($fieldName) && str_contains($mappingName, '#')) {
            throw new \RuntimeException(\sprintf('Ordering field "%s" cannot be registered with multiple mapping.', $fieldName));
        }

        [$entity, $property] = $this->getEntityAndProperty(
            $mappingName,
            $entity ?? $this->defaultEntity,
            $property
        );

        $this->fields[$fieldName][$mappingIdx] = new OrmQueryField(
            $mappingName,
            $this->fieldSet->get($fieldName),
            $this->getMappingType($mappingName, $entity, $property, $type),
            $property,
            $alias ?? $this->defaultAlias,
            $entity
        );
    }

    /**
     * @return array<string, array<string, OrmQueryField>> ['fieldName'][mappingIndex] => {OrmQueryField}
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    /**
     * @return array<int, string>
     */
    private function getEntityAndProperty(string $fieldName, string $entity, string $property): array
    {
        $metadata = $this->entityManager->getClassMetadata($entity);

        if (! $metadata->hasAssociation($property)) {
            return [$entity, $property];
        }

        throw new \RuntimeException(
            \sprintf(
                'Entity field "%s"#%s is a JOIN association, you must explicitly set the ' .
                'entity alias and column mapping for search field "%s" to point to the (head) reference and the ' .
                'entity field you want to use, this entity field must be owned by the entity ' .
                '(not reference another entity). If the entity field is used in a many-to-many relation you must ' .
                'reference the targetEntity that is set on the ManyToMany mapping and use the entity field of ' .
                'that entity.',
                $entity,
                $property,
                $fieldName
            )
        );
    }

    private function getMappingType(string $fieldName, string $entity, string $propertyName, ?string $type = null): string
    {
        $type ??= $this->entityManager->getClassMetadata($entity)->getTypeOfField($propertyName);

        if ($type === null) {
            throw new \RuntimeException(
                \sprintf(
                    'Unable to determine DBAL type of field-mapping "%s" with entity reference "%s"#%s. ' .
                    'Configure an explicit dbal type for the field.',
                    $fieldName,
                    $entity,
                    $propertyName
                )
            );
        }

        return $type;
    }
}
