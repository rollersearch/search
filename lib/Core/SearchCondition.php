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

namespace Rollerworks\Component\Search;

use Rollerworks\Component\Search\Exception\InvalidArgumentException;
use Rollerworks\Component\Search\Exception\UnsupportedFieldSetException;
use Rollerworks\Component\Search\Util\DataAttributesTrait;
use Rollerworks\Component\Search\Value\ValuesGroup;

/**
 * SearchCondition contains the searching conditions and FieldSet.
 *
 * Note: Data-attribute values must be serializable, array structures may not exceed 20 levels.
 *
 * @author Sebastiaan Stok <s.stok@rollerscapes.net>
 */
class SearchCondition
{
    use DataAttributesTrait {
        setAttribute as private setAttributeInternal;
    }

    private ?SearchPrimaryCondition $primaryCondition = null;
    private ?SearchOrder $order = null;

    public function __construct(
        private readonly FieldSet $fieldSet,
        private readonly ValuesGroup $values,
    ) {
    }

    public function getFieldSet(): FieldSet
    {
        return $this->fieldSet;
    }

    public function getValuesGroup(): ValuesGroup
    {
        return $this->values;
    }

    /**
     * @return $this
     */
    public function setPrimaryCondition(?SearchPrimaryCondition $condition): self
    {
        $this->primaryCondition = $condition;

        return $this;
    }

    public function getPrimaryCondition(): ?SearchPrimaryCondition
    {
        return $this->primaryCondition;
    }

    /**
     * @return $this
     */
    public function setOrder(?SearchOrder $order): self
    {
        $this->order = $order;

        return $this;
    }

    public function getOrder(): ?SearchOrder
    {
        return $this->order;
    }

    public function isEmpty(): bool
    {
        return $this->order === null && \count($this->values->getGroups()) === 0 && \count($this->values->getFields()) === 0;
    }

    /**
     * Checks that the FieldSet of this condition is supported
     * by the contexts it's used in.
     *
     * @param string ...$name One or more FieldSet names to check for
     */
    public function assertFieldSetName(string ...$name): void
    {
        if (! \in_array($providedName = $this->fieldSet->getSetName(), $name, true)) {
            throw new UnsupportedFieldSetException($name, $providedName);
        }
    }

    /**
     * Sets an attribute value.
     *
     * Note: Data-attribute values must be serializable, array structures may not exceed 20 levels.
     *
     * @throws InvalidArgumentException when the value is not serializable
     *
     * @override
     */
    public function setAttribute(string | \UnitEnum $name, mixed $value): static
    {
        if ($name instanceof \UnitEnum) {
            $name = $name::class . '::' . $name->name;
        }

        if (! \is_scalar($value)) {
            $this->validateAttribute($name, $value);
        }

        $this->setAttributeInternal($name, $value);

        return $this;
    }

    private function validateAttribute(string $name, mixed $value, int $nested = 0): void
    {
        if ($nested > 20) {
            throw new InvalidArgumentException(\sprintf('Attribute "%s" value (at nested level %d) is too deeply nested to allow type checking.', $name, $nested));
        }

        if (\is_array($value)) {
            ++$nested;

            foreach ($value as $item) {
                $this->validateAttribute($name, $item, $nested);
            }
        }

        // DateTimeInterface is automatically accepted as we know it can be serialized.
        if ($value === null || \is_scalar($value) || $value instanceof \DateTimeInterface) {
            return;
        }

        try {
            if (\is_resource($value)) {
                throw new \UnexpectedValueException('Resources are not accepted.');
            }

            // We don't care about the result, we just want to know if it can be serialized.
            unserialize(serialize($value), ['allowed_classes' => true]);
        } catch (\Throwable $e) {
            $valueStr = \is_object($value) ? \sprintf('%s object', $value::class) : get_debug_type($value);
            $nestedMsg = $nested > 0 ? " (at nesting level $nested)" : '';

            throw new InvalidArgumentException(\sprintf('Attribute "%s" value "%s"%s for a SearchCondition could not be serialized. Message: ' . $e->getMessage(), $name, $valueStr, $nestedMsg), 0, $e);
        }
    }

    public function __serialize(): never
    {
        throw new \BadMethodCallException(\sprintf('A SearchCondition cannot be serialized directly, use the "%s "instead.', SearchConditionSerializer::class));
    }
}
