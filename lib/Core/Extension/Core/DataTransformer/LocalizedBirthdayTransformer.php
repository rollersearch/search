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
 * and between a localized string and a integer.
 */
final class LocalizedBirthdayTransformer implements DataTransformer
{
    private readonly \NumberFormatter $numberFormatter;

    public function __construct(
        private readonly DataTransformer $dateTransformer,
        private readonly bool $allowAge = true,
        private readonly bool $allowFutureDate = false,
        ?string $locale = null,
    ) {
        $this->numberFormatter = new \NumberFormatter($locale ?? \Locale::getDefault(), \NumberFormatter::TYPE_INT32);
        $this->numberFormatter->setAttribute(\NumberFormatter::GROUPING_USED, 0);
    }

    public function transform(mixed $value): mixed
    {
        if (\is_int($value)) {
            if (! $this->allowAge) {
                throw new TransformationFailedException('Age support is not enabled.');
            }

            $result = $this->numberFormatter->format($value);

            if (intl_is_failure($this->numberFormatter->getErrorCode())) {
                throw new TransformationFailedException($this->numberFormatter->getErrorMessage());
            }

            return $result;
        }

        return $this->dateTransformer->transform($value);
    }

    /**
     * @return \DateTimeImmutable|int
     */
    public function reverseTransform(mixed $value)
    {
        if (! \is_string($value)) {
            throw new TransformationFailedException('Expected a string.');
        }

        $value = $this->transformWhenInteger($value);

        if (\is_int($value)) {
            if (! $this->allowAge) {
                throw new TransformationFailedException('Age support is not enabled.');
            }

            return $value;
        }

        $value = $this->dateTransformer->reverseTransform($value);

        // Force the UTC timezone with 00:00:00 for correct comparison.
        $value = $value->setTimezone(new \DateTimeZone('UTC'));
        $value = $value->setTime(0, 0, 0);

        if (! $this->allowFutureDate) {
            $this->validateDate($value);
        }

        return $value;
    }

    private function transformWhenInteger(string $value): int | string
    {
        if (! preg_match('/^\p{N}+$/u', $value)) {
            return $value;
        }

        $result = $this->numberFormatter->parse($value, \NumberFormatter::TYPE_INT32);

        if (intl_is_failure($this->numberFormatter->getErrorCode())) {
            throw new TransformationFailedException($this->numberFormatter->getErrorMessage());
        }

        if ($result >= \PHP_INT_MAX || $result <= -\PHP_INT_MAX) {
            throw new TransformationFailedException('I don\'t have a clear idea what infinity looks like.');
        }

        return (int) $result;
    }

    private function validateDate(\DateTimeImmutable $value): void
    {
        static $currentDate;

        if (! $currentDate) {
            $currentDate = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $currentDate = $currentDate->setTime(0, 0, 0);
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
