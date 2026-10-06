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
use Rollerworks\Component\Search\Field\FieldType;
use Rollerworks\Component\Search\Field\FieldTypeExtension;

/**
 * @author Sebastiaan Stok <s.stok@rollerscapes.net>
 */
final readonly class PreloadedExtension implements SearchExtension
{
    /**
     * @param array<string, FieldType>            $types
     * @param array<string, FieldTypeExtension[]> $typeExtensions ['typeName' => [new MyTypeExtension()]
     */
    public function __construct(
        private array $types,
        private array $typeExtensions = [],
    ) {}

    public function getType(string $name): FieldType
    {
        if (! isset($this->types[$name])) {
            throw new InvalidArgumentException(
                \sprintf('Type "%s" can not be loaded by this extension', $name)
            );
        }

        return $this->types[$name];
    }

    public function hasType(string $name): bool
    {
        return isset($this->types[$name]);
    }

    public function getTypeExtensions(string $name): array
    {
        return $this->typeExtensions[$name] ?? [];
    }
}
