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

use Rollerworks\Component\Search\Exception\InvalidArgumentException;
use Rollerworks\Component\Search\Exception\TransformationFailedException;
use Rollerworks\Component\Search\Exception\UnexpectedTypeException;

/**
 * Transforms between a normalized time and a localized time string.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 * @author Florian Eckerstorfer <florian@eckerstorfer.org>
 *
 * @psalm-type DateFormat \IntlDateFormatter::NONE|\IntlDateFormatter::FULL|\IntlDateFormatter::LONG|\IntlDateFormatter::MEDIUM|\IntlDateFormatter::SHORT|null
 * @psalm-type CalendarType \IntlDateFormatter::GREGORIAN|\IntlDateFormatter::TRADITIONAL|\IntlCalendar
 */
final class DateTimeToLocalizedStringTransformer extends BaseDateTimeTransformer
{
    private int $dateFormat;
    private int $timeFormat;
    private string $locale;
    private int | \IntlCalendar $calendar;
    private bool $isPatternDateOnly;
    private \IntlDateFormatter $formatter;
    private \IntlDateFormatter $parser;

    /**
     * @param int    $calendar One of the \IntlDateFormatter calendar constants
     * @param string $pattern  A pattern to pass to \IntlDateFormatter
     *
     * @psalm-param DateFormat   $dateFormat
     * @psalm-param DateFormat   $timeFormat
     * @psalm-param CalendarType $calendar
     *
     * @throws UnexpectedTypeException If a format is not supported or if a timezone is not a string
     */
    public function __construct(
        ?string $inputTimezone = null,
        ?string $outputTimezone = null,
        ?int $dateFormat = null,
        ?int $timeFormat = null,
        int | \IntlCalendar $calendar = \IntlDateFormatter::GREGORIAN,
        private readonly ?string $pattern = null,
        ?string $locale = null,
    ) {
        parent::__construct($inputTimezone, $outputTimezone);

        $dateFormat ??= \IntlDateFormatter::MEDIUM;
        $timeFormat ??= \IntlDateFormatter::SHORT;

        if (! \in_array($dateFormat, self::$formats, true)) {
            throw new UnexpectedTypeException($dateFormat, implode('", "', self::$formats));
        }

        if (! \in_array($timeFormat, self::$formats, true)) {
            throw new UnexpectedTypeException($timeFormat, implode('", "', self::$formats));
        }

        if (\is_int($calendar) && ! \in_array($calendar, [\IntlDateFormatter::GREGORIAN, \IntlDateFormatter::TRADITIONAL], true)) {
            throw new InvalidArgumentException('The "calendar" option should be either an \IntlDateFormatter constant or an \IntlCalendar instance.');
        }

        $this->dateFormat = $dateFormat;
        $this->timeFormat = $timeFormat;
        $this->calendar = $calendar;

        $this->locale = $locale ?? \Locale::getDefault();
        $this->isPatternDateOnly = $this->isPatternDateOnly();
        $this->parser = $this->getIntlDateFormatter(true);
        $this->formatter = $this->getIntlDateFormatter();
    }

    /**
     * Transforms a normalized date into a localized date string/array.
     *
     * @param \DateTimeImmutable|null $value
     *
     * @return string Localized date string
     *
     * @throws TransformationFailedException if the given value is not a \DateTimeImmutable
     *                                       or if the date could not be transformed
     */
    public function transform(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (! $value instanceof \DateTimeImmutable) {
            throw new TransformationFailedException('Expected a \DateTimeImmutable.');
        }

        $value = $this->formatter->format($value->getTimestamp());

        if ($this->parser->getErrorCode() !== 0) {
            throw new TransformationFailedException($this->parser->getErrorMessage(), $this->parser->getErrorCode());
        }

        // Convert non-breaking and narrow non-breaking spaces to normal ones
        return str_replace(["\xc2\xa0", "\xe2\x80\xaf"], ' ', $value);
    }

    /**
     * Transforms a localized date string/array into a normalized date.
     *
     * @param string $value Localized date string
     *
     * @throws TransformationFailedException if the given value is not a string,
     *                                       if the date could not be parsed
     */
    public function reverseTransform(mixed $value): ?\DateTimeImmutable
    {
        if (! \is_string($value)) {
            throw new TransformationFailedException('Expected a string.');
        }

        if ($value === '') {
            return null;
        }

        // Non-breaking lines are required instead of spaces.
        // And remove comma's to normalize the input
        $value = str_replace([' ', ','], ["\xe2\x80\xaf", ''], $value);

        try {
            $timestamp = $this->parser->parse($value);
        } catch (\Throwable $e) {
            throw new TransformationFailedException($e->getMessage(), $e->getCode(), $e);
        }

        if ($this->parser->getErrorCode() !== 0) {
            throw new TransformationFailedException($this->parser->getErrorMessage(), $this->parser->getErrorCode());
        }

        if ($timestamp > 253402214400) {
            // This timestamp represents UTC midnight of 9999-12-31 to prevent 5+ digit years
            throw new TransformationFailedException('Years beyond 9999 are not supported.');
        }

        if ($timestamp === false) {
            // the value couldn't be parsed but the Intl extension didn't report an error code, this
            // could be the case when the Intl polyfill is used which always returns 0 as the error code
            throw new TransformationFailedException(\sprintf('"%s" could not be parsed as a date.', $value));
        }

        try {
            if ($this->isPatternDateOnly) {
                // we only care about year-month-date, which has been delivered as a timestamp pointing to UTC midnight
                $dateTime = new \DateTimeImmutable(gmdate('Y-m-d', $timestamp), new \DateTimeZone($this->inputTimezone));
            } else {
                // read timestamp into DateTime object - the formatter delivers a timestamp
                $dateTime = new \DateTimeImmutable(\sprintf('@%s', $timestamp));
            }

            // set timezone separately, as it would be ignored if set via the constructor,
            // see https://php.net/datetime.construct
            $dateTime = $dateTime->setTimezone(new \DateTimeZone($this->outputTimezone));
        } catch (\Exception $e) {
            throw new TransformationFailedException($e->getMessage(), $e->getCode(), $e);
        }

        if ($this->outputTimezone !== $this->inputTimezone) {
            $dateTime = $dateTime->setTimezone(new \DateTimeZone($this->inputTimezone));
        }

        return $dateTime;
    }

    /**
     * @throws TransformationFailedException in case the date formatter can not be constructed
     */
    private function getIntlDateFormatter(bool $ignoreStrict = false): \IntlDateFormatter
    {
        $timezone = new \DateTimeZone($this->isPatternDateOnly ? 'UTC' : $this->outputTimezone);
        $intlDateFormatter = new \IntlDateFormatter(
            $this->locale,
            $this->dateFormat,
            $this->timeFormat,
            $timezone,
            $this->calendar,
            $this->pattern,
        );
        $intlDateFormatter->setLenient(false);

        if ($ignoreStrict) {
            // Some patterns use a comma for separation, but this interferes with string input.
            $intlDateFormatter->setPattern(str_replace(',', '', $intlDateFormatter->getPattern()));
        }

        return $intlDateFormatter;
    }

    /**
     * Date-only patterns require parsing to be done in UTC, as midnight might
     * not exist in the local timezone due to DST changes.
     */
    private function isPatternDateOnly(): bool
    {
        if ($this->pattern === null) {
            return false;
        }

        // strip escaped text
        $pattern = preg_replace("#'(.*?)'#", '', $this->pattern);

        // check for the absence of time-related placeholders
        return preg_match('#[ahHkKmsSAzZOvVxX]#', (string) $pattern) === 0;
    }
}
