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

use Rollerworks\Component\Search\Extension\Core\DataTransformer\DateTimeToLocalizedStringTransformer;
use Rollerworks\Component\Search\Extension\Core\ValueComparator\DateValueComparator;
use Rollerworks\Component\Search\Field\FieldConfig;
use Rollerworks\Component\Search\Field\SearchFieldView;
use Rollerworks\Component\Search\Value\Compare;
use Rollerworks\Component\Search\Value\Range;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @author Sebastiaan Stok <s.stok@rollerscapes.net>
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
final class DateType extends BaseDateTimeType
{
    public const DEFAULT_FORMAT = \IntlDateFormatter::MEDIUM;
    public const HTML5_FORMAT = 'yyyy-MM-dd';

    private DateValueComparator $valueComparator;

    public function __construct()
    {
        $this->valueComparator = new DateValueComparator();
    }

    public function buildType(FieldConfig $config, array $options): void
    {
        $config->setValueComparator($this->valueComparator);
        $config->setValueTypeSupport(Range::class, true);
        $config->setValueTypeSupport(Compare::class, true);

        if ($options['pattern'] === null) {
            $this->validateFormat('format', $options['format']);
        } else {
            $this->validateDateFormat('pattern', $options['pattern']);
        }

        $config->setViewTransformer(
            new DateTimeToLocalizedStringTransformer(
                $options['model_timezone'],
                $options['view_timezone'],
                $options['format'],
                \IntlDateFormatter::NONE,
                \IntlDateFormatter::GREGORIAN,
                $options['pattern'],
                $options['locale'],
            )
        );

        $config->setNormTransformer(
            new DateTimeToLocalizedStringTransformer(
                $options['model_timezone'],
                $options['view_timezone'],
                $options['format'],
                \IntlDateFormatter::NONE,
                \IntlDateFormatter::GREGORIAN,
                self::HTML5_FORMAT,
                'en_US'
            )
        );
    }

    public function buildView(SearchFieldView $view, FieldConfig $config, array $options): void
    {
        $pattern = $options['pattern'];

        if ($pattern === null) {
            $pattern = \IntlDateFormatter::create(
                $options['locale'],
                $options['format'],
                \IntlDateFormatter::NONE,
                $options['view_timezone'],
                \IntlDateFormatter::GREGORIAN
            )->getPattern();
        }

        $view->vars['html5'] = $options['html5'];
        $view->vars['timezone'] = $options['view_timezone'] ?? date_default_timezone_get();
        $view->vars['pattern'] = $pattern;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(
            [
                'locale' => \Locale::getDefault(),
                'model_timezone' => null,
                'view_timezone' => null,
                'pattern' => null,
                'format' => self::DEFAULT_FORMAT,
                'html5' => true,
            ]
        );

        $resolver->setAllowedTypes('locale', ['string']);
        $resolver->setAllowedTypes('model_timezone', ['string', 'null']);
        $resolver->setAllowedTypes('view_timezone', ['string', 'null']);
        $resolver->setAllowedTypes('pattern', ['string', 'null']);
        $resolver->setAllowedTypes('format', ['int']);
        $resolver->setAllowedTypes('html5', 'bool');
    }

    public function getBlockPrefix(): string
    {
        return 'date';
    }
}
