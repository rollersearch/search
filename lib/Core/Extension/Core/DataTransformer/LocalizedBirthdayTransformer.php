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
    public function __construct(
        private readonly DataTransformer $dateTransformer,
        private readonly bool $allowAge = true,
        private readonly bool $allowFutureDate = false,
    ) {}

    public function transform($value)
    {
        if (\is_int($value)) {
            if (! $this->allowAge) {
                throw new TransformationFailedException('Age support is not enabled.');
            }

            $formatter = $this->getNumberFormatter();
            $result = $formatter->format($value);

            if (intl_is_failure($formatter->getErrorCode())) {
                throw new TransformationFailedException($formatter->getErrorMessage());
            }

            return $result;
        }

        return $this->dateTransformer->transform($value);
    }

    /**
     * @param int|string $value
     *
     * @return \DateTimeImmutable|int
     */
    public function reverseTransform($value)
    {
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

    private function transformWhenInteger($value)
    {
        if (! preg_match('/^\p{N}+$/u', (string) $value)) {
            return $value;
        }

        $position = 0;
        $formatter = $this->getNumberFormatter();
        $result = $formatter->parse($value, \NumberFormatter::TYPE_INT32, $position);

        if (intl_is_failure($formatter->getErrorCode())) {
            throw new TransformationFailedException($formatter->getErrorMessage());
        }

        if ($result >= \PHP_INT_MAX || $result <= -\PHP_INT_MAX) {
            throw new TransformationFailedException('I don\'t have a clear idea what infinity looks like.');
        }

        return $result;
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

    private function getNumberFormatter(): \NumberFormatter
    {
        /** @var \NumberFormatter $formatter */
        static $formatter;

        if (! $formatter || $formatter->getLocale() !== \Locale::getDefault()) {
            $formatter = new \NumberFormatter(\Locale::getDefault(), \NumberFormatter::TYPE_INT32);
            $formatter->setAttribute(\NumberFormatter::GROUPING_USED, 0);
        }

        return $formatter;
    }
}
