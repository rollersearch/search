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

namespace Rollerworks\Component\Search\Value;

/**
 * @author Sebastiaan Stok <s.stok@rollerscapes.net>
 */
class Range implements RequiresComparatorValueHolder
{
    public function __construct(
        public readonly mixed $lower,
        public readonly mixed $upper,
        public readonly bool $inclusiveLower = true,
        public readonly bool $inclusiveUpper = true,
    ) {}

    /**
     * @deprecated Since RollerworksSearch 2.1 use the property $lower
     */
    public function getLower(): mixed
    {
        return $this->lower;
    }

    /**
     * @deprecated Since RollerworksSearch 2.1 use the property $upper
     */
    public function getUpper(): mixed
    {
        return $this->upper;
    }

    /**
     * @deprecated Since RollerworksSearch 2.1 use the property $inclusiveLower
     */
    public function isLowerInclusive(): bool
    {
        return $this->inclusiveLower;
    }

    /**
     * @deprecated Since RollerworksSearch 2.1 use the property $inclusiveUpper
     */
    public function isUpperInclusive(): bool
    {
        return $this->inclusiveUpper;
    }
}
