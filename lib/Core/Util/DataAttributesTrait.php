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

namespace Rollerworks\Component\Search\Util;

use Rollerworks\Component\Search\Exception\InvalidArgumentException;

/**
 * @internal using this trait outside of the RollerworksSearch package is not covered by the backward compatibility promise
 */
trait DataAttributesTrait
{
    /** @var array<string, mixed> */
    private array $attributes = [];

    /**
     * @param string|\UnitEnum $name Uses the name of the Enum case (not the value)
     *
     * @return $this
     */
    public function setAttribute(string | \UnitEnum $name, mixed $value): static
    {
        if ($name instanceof \UnitEnum) {
            $name = $name::class . '::' . $name->name;
        }

        $this->attributes[$name] = $value;

        return $this;
    }

    /**
     * @param string|\UnitEnum $name Uses the name of the Enum case (not the value)
     *
     * @return $this
     */
    public function getAttribute(string | \UnitEnum $name, mixed $default = null): mixed
    {
        if ($name instanceof \UnitEnum) {
            $name = $name::class . '::' . $name->name;
        }

        return \array_key_exists($name, $this->attributes) ? $this->attributes[$name] : $default;
    }

    /**
     * @param string|\UnitEnum $name Uses the name of the Enum case (not the value)
     */
    public function hasAttribute(string | \UnitEnum $name): bool
    {
        if ($name instanceof \UnitEnum) {
            $name = $name::class . '::' . $name->name;
        }

        return \array_key_exists($name, $this->attributes);
    }

    /**
     * @param string|\UnitEnum $name Uses the name of the Enum case (not the value)
     *
     * @return $this
     *
     * @throws InvalidArgumentException when the attribute does not exist
     */
    public function getAttributeOrFail(string | \UnitEnum $name): mixed
    {
        if ($name instanceof \UnitEnum) {
            $name = $name::class . '::' . $name->name;
        }

        if (! \array_key_exists($name, $this->attributes)) {
            throw new InvalidArgumentException(\sprintf('Attribute "%s" does not exist.', $name));
        }

        return $this->attributes[$name];
    }

    /**
     * @param string|\UnitEnum $name Uses the name of the Enum case (not the value)
     *
     * @return $this
     */
    public function removeAttribute(string | \UnitEnum $name): static
    {
        if ($name instanceof \UnitEnum) {
            $name = $name::class . '::' . $name->name;
        }

        unset($this->attributes[$name]);

        return $this;
    }

    /**
     * Gets all attributes, note that attributes set with Enums are returned as "EnumClass:EnumCase".
     *
     * @return array<string, mixed>
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }
}
