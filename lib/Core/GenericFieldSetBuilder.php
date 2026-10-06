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
use Rollerworks\Component\Search\Field\FieldConfig;

/**
 * The FieldSetBuilder helps with building a {@link FieldSet}.
 *
 * @author Sebastiaan Stok <s.stok@rollerscapes.net>
 */
final class GenericFieldSetBuilder implements FieldSetBuilder
{
    /** @var array<string, FieldConfig> */
    private array $fields = [];

    /** @var array<string, array{type: string, options: array}> */
    private array $unresolvedFields = [];

    public function __construct(
        private readonly SearchFactory $searchFactory,
    ) {}

    public function set(FieldConfig $field): self
    {
        $name = $field->getName();

        unset($this->unresolvedFields[$name]);
        $this->fields[$name] = $field;

        return $this;
    }

    public function add(string $name, string $type, array $options = []): self
    {
        $this->unresolvedFields[$name] = [
            'type' => $type,
            'options' => $options,
        ];

        return $this;
    }

    public function remove(string $name): self
    {
        unset($this->fields[$name], $this->unresolvedFields[$name]);

        return $this;
    }

    public function has(string $name): bool
    {
        return isset($this->unresolvedFields[$name]) || isset($this->fields[$name]);
    }

    public function get(string $name): FieldConfig
    {
        if (isset($this->unresolvedFields[$name])) {
            $this->fields[$name] = $this->searchFactory->createField(
                $name,
                $this->unresolvedFields[$name]['type'],
                $this->unresolvedFields[$name]['options']
            );

            unset($this->unresolvedFields[$name]);
        }

        if (isset($this->fields[$name])) {
            return $this->fields[$name];
        }

        throw new InvalidArgumentException(\sprintf('The field with the name "%s" does not exist.', $name));
    }

    public function getFieldSet(?string $name = null): FieldSet
    {
        foreach ($this->unresolvedFields as $fieldName => $config) {
            $this->fields[$fieldName] = $this->searchFactory->createField(
                $fieldName,
                $config['type'],
                $config['options']
            );

            unset($this->unresolvedFields[$fieldName]);
        }

        return new GenericFieldSet($this->fields, $name);
    }
}
