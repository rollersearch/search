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

use Rollerworks\Component\Search\Extension\Core\DataTransformer\NumberToLocalizedStringTransformer;
use Rollerworks\Component\Search\Extension\Core\DataTransformer\NumberToStringTransformer;
use Rollerworks\Component\Search\Extension\Core\ValueComparator\NumberValueComparator;
use Rollerworks\Component\Search\Field\AbstractFieldType;
use Rollerworks\Component\Search\Field\FieldConfig;
use Rollerworks\Component\Search\Field\SearchFieldView;
use Rollerworks\Component\Search\Value\Compare;
use Rollerworks\Component\Search\Value\Range;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @author Sebastiaan Stok <s.stok@rollerscapes.net>
 */
final class NumberType extends AbstractFieldType
{
    private NumberValueComparator $valueComparator;

    public function __construct()
    {
        $this->valueComparator = new NumberValueComparator();
    }

    public function buildType(FieldConfig $config, array $options): void
    {
        $config->setValueComparator($this->valueComparator);
        $config->setValueTypeSupport(Range::class, true);
        $config->setValueTypeSupport(Compare::class, true);

        $config->setViewTransformer(
            new NumberToLocalizedStringTransformer(
                $options['scale'],
                $options['grouping'],
                $options['rounding_mode'],
                $options['locale']
            )
        );

        $config->setNormTransformer(
            new NumberToStringTransformer(
                $options['scale'],
                $options['grouping'],
                $options['rounding_mode']
            )
        );
    }

    public function buildView(SearchFieldView $view, FieldConfig $config, array $options): void
    {
        $view->vars['scale'] = $options['scale'];
        $view->vars['grouping'] = $options['grouping'];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'locale' => \Locale::getDefault(),
            // default scale is locale specific (usually around 3)
            'scale' => null,
            'grouping' => false,
            'rounding_mode' => NumberToLocalizedStringTransformer::ROUND_HALF_UP,
            'html5' => false,
        ]);

        $resolver->setAllowedTypes('locale', ['null', 'string']);
        $resolver->setAllowedValues('rounding_mode', [
            NumberToLocalizedStringTransformer::ROUND_FLOOR,
            NumberToLocalizedStringTransformer::ROUND_DOWN,
            NumberToLocalizedStringTransformer::ROUND_HALF_DOWN,
            NumberToLocalizedStringTransformer::ROUND_HALF_EVEN,
            NumberToLocalizedStringTransformer::ROUND_HALF_UP,
            NumberToLocalizedStringTransformer::ROUND_UP,
            NumberToLocalizedStringTransformer::ROUND_CEILING,
        ]);

        $resolver->setAllowedTypes('scale', ['null', 'int']);
        $resolver->setAllowedTypes('html5', 'bool');
        $resolver->setNormalizer('grouping', static function (Options $options, $value) {
            if ($value === true && $options['html5']) {
                throw new \LogicException('Cannot use the "grouping" option when the "html5" option is enabled.');
            }

            return $value;
        });
    }

    public function getBlockPrefix(): string
    {
        return 'number';
    }
}
