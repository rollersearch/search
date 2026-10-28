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

use Rollerworks\Component\Search\Extension\Core\DataTransformer\BirthdayTransformer;
use Rollerworks\Component\Search\Extension\Core\DataTransformer\LocalizedBirthdayTransformer;
use Rollerworks\Component\Search\Extension\Core\ValueComparator\BirthdayValueComparator;
use Rollerworks\Component\Search\Field\AbstractFieldType;
use Rollerworks\Component\Search\Field\FieldConfig;
use Rollerworks\Component\Search\Field\SearchFieldView;
use Rollerworks\Component\Search\Value\Compare;
use Rollerworks\Component\Search\Value\Range;
use Rollerworks\Component\Search\ValueComparator;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @author Sebastiaan Stok <s.stok@rollerscapes.net>
 */
final class BirthdayType extends AbstractFieldType
{
    private ValueComparator $valueComparator;

    public function __construct()
    {
        $this->valueComparator = new BirthdayValueComparator();
    }

    public function buildType(FieldConfig $config, array $options): void
    {
        $config->setValueComparator($this->valueComparator);
        $config->setValueTypeSupport(Range::class, true);
        $config->setValueTypeSupport(Compare::class, true);

        $config->setViewTransformer(
            new LocalizedBirthdayTransformer(
                $config->getViewTransformer(),
                $options['allow_age'],
                $options['allow_future_date'],
                $options['locale']
            )
        );

        $config->setNormTransformer(
            new BirthdayTransformer(
                $config->getNormTransformer(),
                $options['allow_age'],
                $options['allow_future_date']
            )
        );
    }

    public function buildView(SearchFieldView $view, FieldConfig $config, array $options): void
    {
        $view->vars['allow_age'] = $options['allow_age'];
        $view->vars['allow_future_date'] = $options['allow_future_date'];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'allow_age' => true,
            'allow_future_date' => false,
            'invalid_message' => static function (Options $options) {
                if ($options['allow_age']) {
                    return 'This value is not a valid birthday or age.';
                }

                return 'This value is not a valid birthday.';
            },
        ]);

        $resolver->setAllowedTypes('allow_age', ['bool']);
        $resolver->setAllowedTypes('allow_future_date', ['bool']);
    }

    public function getParent(): string
    {
        return DateType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'birthday';
    }
}
