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

namespace Rollerworks\Component\Search\Extension\Doctrine\Dbal\Type;

use Rollerworks\Component\Search\Extension\Core\Type\MoneyType;
use Rollerworks\Component\Search\Extension\Doctrine\Dbal\Conversion\MoneyValueConversion;
use Rollerworks\Component\Search\Field\AbstractFieldTypeExtension;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Configures the MoneyValueConversion for Doctrine DBAL.
 *
 * Note: Due to technical limitations currently the option
 * "doctrine_dbal_with_currency" has no effect and the
 * currency comparisons is always ignored.
 *
 * @author Sebastiaan Stok <s.stok@rollerscapes.net>
 */
final class MoneyTypeExtension extends AbstractFieldTypeExtension
{
    public function __construct(
        private readonly MoneyValueConversion $conversion = new MoneyValueConversion(),
    ) {}

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(
            [
                'doctrine_dbal_conversion' => $this->conversion,
                'doctrine_dbal_with_currency' => false,
            ]
        );

        $resolver->setAllowedTypes('doctrine_dbal_with_currency', ['bool']);
    }

    public function getExtendedType(): string
    {
        return MoneyType::class;
    }
}
