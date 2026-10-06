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

use Rollerworks\Component\Search\DataTransformer;
use Rollerworks\Component\Search\Exception\TransformationFailedException;

final class BooleanToLocalizedValueTransformer implements DataTransformer
{
    /**
     * @param array<scalar> $trueValues
     * @param array<scalar> $falseValues
     */
    public function __construct(
        private readonly string $trueLabel = 'yes',
        private readonly string $falseLabel = 'no',
        private readonly array $trueValues = ['true', '1', 1, 'on', 'yes'],
        private readonly array $falseValues = ['false', '0', 0, 'off', 'no'],
    ) {}

    public function transform(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (! \is_bool($value)) {
            throw new TransformationFailedException(\sprintf('Expected a boolean value, got "%s".', get_debug_type($value)));
        }

        return $value ? $this->trueLabel : $this->falseLabel;
    }

    public function reverseTransform(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! \is_scalar($value)) {
            throw new TransformationFailedException(\sprintf('Expected a scalar value, got "%s".', get_debug_type($value)));
        }

        if (\is_string($value)) {
            $value = mb_strtolower($value);
        }

        if (\in_array($value, $this->trueValues, true)) {
            return true;
        }

        if (\in_array($value, $this->falseValues, true)) {
            return false;
        }

        throw new TransformationFailedException(\sprintf('Expected one of ("%s") or ("%s"), got "%s".', implode('", "', $this->trueValues), implode('", "', $this->falseValues), $value));
    }
}
