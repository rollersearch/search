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

namespace Rollerworks\Component\Search\Extension\Core\Type;

use Carbon\CarbonInterval;
use Rollerworks\Component\Search\Extension\Core\DataTransformer\DateIntervalTransformer;
use Rollerworks\Component\Search\Extension\Core\DataTransformer\DateTimeToLocalizedStringTransformer;
use Rollerworks\Component\Search\Extension\Core\DataTransformer\DateTimeToRfc3339Transformer;
use Rollerworks\Component\Search\Extension\Core\DataTransformer\MultiTypeDataTransformer;
use Rollerworks\Component\Search\Extension\Core\ValueComparator\DateTimeIntervalValueComparator;
use Rollerworks\Component\Search\Extension\Core\ValueComparator\DateTimeValueComparator;
use Rollerworks\Component\Search\Field\FieldConfig;
use Rollerworks\Component\Search\Field\SearchFieldView;
use Rollerworks\Component\Search\Value\Compare;
use Rollerworks\Component\Search\Value\Range;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @author Sebastiaan Stok <s.stok@rollerscapes.net>
 */
final class DateTimeType extends BaseDateTimeType
{
    public const DEFAULT_DATE_FORMAT = \IntlDateFormatter::MEDIUM;
    public const DEFAULT_TIME_FORMAT = \IntlDateFormatter::MEDIUM;

    private readonly DateTimeValueComparator $valueComparator;
    private readonly DateTimeIntervalValueComparator $valueComparatorInterval;

    public function __construct()
    {
        $this->valueComparator = new DateTimeValueComparator();
        $this->valueComparatorInterval = new DateTimeIntervalValueComparator();
    }

    public function buildType(FieldConfig $config, array $options): void
    {
        $config->setValueComparator($this->valueComparator);
        $config->setValueTypeSupport(Range::class, true);
        $config->setValueTypeSupport(Compare::class, true);

        if ($options['pattern'] === null) {
            $this->validateFormat('date_format', $options['date_format']);
            $this->validateFormat('time_format', $options['time_format']);
        }

        if ($options['allow_relative']) {
            $config->setValueComparator($this->valueComparatorInterval);

            $config->setViewTransformer(
                new MultiTypeDataTransformer(
                    [
                        CarbonInterval::class => new DateIntervalTransformer($options['locale']),
                        \DateTimeImmutable::class => new DateTimeToLocalizedStringTransformer(
                            $options['model_timezone'],
                            $options['view_timezone'],
                            $options['date_format'],
                            $options['time_format'],
                            \IntlDateFormatter::GREGORIAN,
                            $options['pattern'],
                            $options['locale'],
                        ),
                    ]
                )
            );

            $config->setNormTransformer(
                new MultiTypeDataTransformer(
                    [
                        CarbonInterval::class => new DateIntervalTransformer('en'),
                        \DateTimeImmutable::class => new DateTimeToRfc3339Transformer(
                            $options['model_timezone'],
                            $options['view_timezone']
                        ),
                    ]
                )
            );
        } else {
            $config->setViewTransformer(
                new DateTimeToLocalizedStringTransformer(
                    $options['model_timezone'],
                    $options['view_timezone'],
                    $options['date_format'],
                    $options['time_format'],
                    \IntlDateFormatter::GREGORIAN,
                    $options['pattern'],
                    $options['locale']
                )
            );

            $config->setNormTransformer(
                new DateTimeToRfc3339Transformer(
                    $options['model_timezone'],
                    $options['view_timezone']
                )
            );
        }
    }

    public function buildView(SearchFieldView $view, FieldConfig $config, array $options): void
    {
        $pattern = $options['pattern'];

        if ($pattern === null) {
            $pattern = \IntlDateFormatter::create(
                $options['locale'],
                $options['date_format'],
                $options['time_format'],
                $options['view_timezone'],
                \IntlDateFormatter::GREGORIAN
            )->getPattern();
        }

        $view->vars['timezone'] = $options['view_timezone'] ?: date_default_timezone_get();
        $view->vars['allow_relative'] = $options['allow_relative'];
        $view->vars['pattern'] = str_replace("\u{202f}", ' ', $pattern);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(
            [
                'locale' => \Locale::getDefault(),
                'model_timezone' => 'UTC',
                'view_timezone' => null,
                'pattern' => null,
                'allow_relative' => false,
                'date_format' => self::DEFAULT_DATE_FORMAT,
                'time_format' => self::DEFAULT_TIME_FORMAT,
                'invalid_message' => static function (Options $options) {
                    if ($options['allow_relative']) {
                        return 'This value is not a valid datetime or date interval.';
                    }

                    return 'This value is not a valid datetime.';
                },
            ]
        );

        $resolver->setAllowedTypes('locale', ['string']);
        $resolver->setAllowedTypes('model_timezone', ['string']);
        $resolver->setAllowedTypes('view_timezone', ['string', 'null']);
        $resolver->setAllowedTypes('pattern', ['string', 'null']);
        $resolver->setAllowedTypes('allow_relative', 'bool');
        $resolver->setAllowedTypes('date_format', ['int']);
        $resolver->setAllowedTypes('time_format', ['int']);
    }

    public function getBlockPrefix(): string
    {
        return 'datetime';
    }
}
