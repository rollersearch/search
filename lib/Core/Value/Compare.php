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

final class Compare implements RequiresComparatorValueHolder
{
    public const OPERATORS = ['>=', '<=', '<>', '<', '>'];

    public function __construct(
        public readonly mixed $value,
        public readonly string $operator,
    ) {
        if (! \in_array($operator, self::OPERATORS, true)) {
            throw new \InvalidArgumentException(
                \sprintf('Unknown operator "%s".', $operator)
            );
        }
    }

    /**
     * @deprecated Since RollerworksSearch 2.1 use the property $operator
     */
    public function getOperator(): string
    {
        return $this->operator;
    }

    /**
     * @deprecated Since RollerworksSearch 2.1 use the property $value
     */
    public function getValue(): mixed
    {
        return $this->value;
    }
}
