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

namespace Rollerworks\Component\Search\Field;

use Rollerworks\Component\Search\Extension\Core\DataTransformer\OrderToLocalizedTransformer;
use Rollerworks\Component\Search\Extension\Core\DataTransformer\OrderTransformer;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @author Dalibor Karlović <dalibor@flexolabs.io>
 */
final class OrderFieldType implements FieldType
{
    public function getParent(): ?string
    {
        return null;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'default' => null,
            'always' => null,
            'case' => OrderTransformer::CASE_UPPERCASE,
            'alias' => ['ASC' => 'ASC', 'DESC' => 'DESC'],
            'view_label' => ['ASC' => 'asc', 'DESC' => 'desc'],
            'type' => null,
            'type_options' => [],
            'label' => null,
        ]);

        $resolver->setAllowedValues('case', [
            OrderTransformer::CASE_LOWERCASE,
            OrderTransformer::CASE_UPPERCASE,
        ]);
        $resolver->setAllowedValues('default', ['asc', 'desc', 'ASC', 'DESC', null]);
        $resolver->setAllowedValues('always', ['append', 'prepend', null]);
        $resolver->setAllowedTypes('alias', 'array');
        $resolver->setAllowedTypes('view_label', ['array']);
        $resolver->setAllowedTypes('type', ['string', 'null']);
        $resolver->setAllowedTypes('type_options', ['array']);
        $resolver->setAllowedTypes('label', ['null', 'string']);

        // Ensure view-labels are part of the alias list.
        $resolver->addNormalizer('alias', static function (Options $options, array $value): mixed {
            // Must always exist for interoperability, but it's still possible to overwrite.
            $value = array_merge(
                $value,
                $options['case'] === OrderTransformer::CASE_LOWERCASE ? ['asc' => 'ASC', 'desc' => 'DESC'] : ['ASC' => 'ASC', 'DESC' => 'DESC']
            );

            foreach ($options['view_label'] as $direction => $label) {
                switch ($options['case']) {
                    case OrderTransformer::CASE_LOWERCASE:
                        $label = mb_strtolower($label);

                        break;

                    case OrderTransformer::CASE_UPPERCASE:
                        $label = mb_strtoupper($label);

                        break;
                }

                if (isset($value[$label])) {
                    continue;
                }

                $value[$label] = $direction;
            }

            return $value;
        });
    }

    public function buildType(FieldConfig $config, array $options): void
    {
        if ($options['always'] !== null && $options['default'] === null) {
            throw new InvalidOptionsException('Setting "always" requires the "default" option is set with a direction.');
        }

        $config->setNormTransformer(new OrderTransformer($options['alias'], $options['case']));
        $config->setViewTransformer(new OrderToLocalizedTransformer($options['alias'], $options['view_label'], $options['case']));
    }

    public function buildView(SearchFieldView $view, FieldConfig $config, array $options): void {}

    public function getBlockPrefix(): string
    {
        return 'order';
    }
}
