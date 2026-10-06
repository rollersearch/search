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

final class BooleanToNormValueTransformer implements DataTransformer
{
    public function __construct(
        private readonly string $trueValue = 'true',
        private readonly string $falseValue = 'false',
    ) {}

    public function transform(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (! \is_bool($value)) {
            throw new TransformationFailedException(\sprintf('Expected a boolean value, got "%s".', \gettype($value)));
        }

        return $value ? $this->trueValue : $this->falseValue;
    }

    public function reverseTransform(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (\is_bool($value)) {
            return $value;
        }

        if (! \is_string($value)) {
            throw new TransformationFailedException(\sprintf('Expected a string or boolean value, got "%s".', \gettype($value)));
        }

        if ($value === $this->trueValue) {
            return true;
        }

        if ($value === $this->falseValue) {
            return false;
        }

        throw new TransformationFailedException(\sprintf('Expected one of "%s" or "%s", got "%s".', $this->trueValue, $this->falseValue, $value));
    }
}
