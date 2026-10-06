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

use Rollerworks\Component\Search\Value\ValuesBag;

/**
 * Helper class for the SearchConditionBuilder.
 */
class ValuesBagBuilder extends ValuesBag
{
    public function __construct(
        private readonly SearchConditionBuilder $parent,
    ) {}

    public function end(): SearchConditionBuilder
    {
        return $this->parent;
    }

    /**
     * @internal
     */
    public function toValuesBag(): ValuesBag
    {
        $valuesBag = new ValuesBag();
        $valuesBag->__unserialize($this->__serialize());

        return $valuesBag;
    }
}
