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

namespace Rollerworks\Component\Search\Extension\Core\Model;

use Money\Money;

/**
 * @author Sebastiaan Stok <s.stok@rollerscapes.net>
 */
class MoneyValue
{
    /**
     * @param bool $withCurrency indicate the input was provided with a currency.
     *                           This is only used for exporting
     */
    public function __construct(
        public readonly Money $value,
        public readonly bool $withCurrency = true,
    ) {}
}
