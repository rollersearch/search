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

namespace Rollerworks\Component\Search\Extension\Core\DataTransformer;

use Rollerworks\Component\Search\Exception\TransformationFailedException;

/**
 * Transforms between an integer and a localized number with grouping
 * (each thousand) and comma separators.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
final class IntegerToLocalizedStringTransformer extends NumberToLocalizedStringTransformer
{
    /**
     * @param bool          $grouping     Whether thousands should be grouped
     * @param self::ROUND_* $roundingMode One of the ROUND_ constants in this class
     */
    public function __construct(?bool $grouping = false, ?int $roundingMode = self::ROUND_DOWN, ?string $locale = null)
    {
        parent::__construct(0, $grouping, $roundingMode, $locale);
    }

    public function reverseTransform($value): ?int
    {
        $decimalSeparator = $this->formatter->getSymbol(\NumberFormatter::DECIMAL_SEPARATOR_SYMBOL);

        if (\is_string($value) && mb_strpos($value, $decimalSeparator) !== false) {
            throw new TransformationFailedException(\sprintf('The value "%s" is not a valid integer.', $value));
        }

        $result = parent::reverseTransform($value);

        return $result !== null ? (int) $result : null;
    }

    /**
     * @internal
     */
    protected function castParsedValue(float | int $value): float | int
    {
        return $value;
    }
}
