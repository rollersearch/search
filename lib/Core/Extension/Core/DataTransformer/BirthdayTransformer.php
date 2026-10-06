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

/**
 * Transforms between a date string and a DateTime object
 * and between a ISO string and an integer.
 */
final class BirthdayTransformer implements DataTransformer
{
    public function __construct(
        private readonly DataTransformer $transformer,
        private readonly bool $allowAge = true,
        private readonly bool $allowFutureDate = false,
    ) {}

    public function transform($value)
    {
        if (\is_int($value)) {
            if (! $this->allowAge) {
                throw new TransformationFailedException('Age support is not enabled.');
            }

            return $value;
        }

        return $this->transformer->transform($value);
    }

    public function reverseTransform($value)
    {
        $value = $this->transformWhenInteger($value);

        if (\is_int($value)) {
            if (! $this->allowAge) {
                throw new TransformationFailedException('Age support is not enabled.');
            }

            return $value;
        }

        $value = $this->transformer->reverseTransform($value);

        // Force the UTC timezone with 00:00:00 for correct comparison.
        $value = $value->setTimezone(new \DateTimeZone('UTC'));
        $value = $value->setTime(0, 0, 0);

        if (! $this->allowFutureDate) {
            $this->validateDate($value);
        }

        return $value;
    }

    private function transformWhenInteger($value)
    {
        if (ctype_digit((string) $value)) {
            return (int) $value;
        }

        return $value;
    }

    private function validateDate(\DateTimeImmutable $value): void
    {
        static $currentDate;

        if (! $currentDate) {
            $currentDate = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $currentDate = $currentDate->setTime(0, 0);
        }

        if ($value > $currentDate) {
            throw new TransformationFailedException(
                \sprintf(
                    'Date "%s" is higher then current date "%s". Are you a time traveler?',
                    $value->format('Y-m-d'),
                    $currentDate->format('Y-m-d')
                )
            );
        }
    }
}
