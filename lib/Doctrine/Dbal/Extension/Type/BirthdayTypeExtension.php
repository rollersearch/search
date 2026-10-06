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

use Rollerworks\Component\Search\Extension\Core\Type\BirthdayType;
use Rollerworks\Component\Search\Extension\Doctrine\Dbal\Conversion\AgeDateConversion;
use Rollerworks\Component\Search\Field\AbstractFieldTypeExtension;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Configures the AgeConversion for Doctrine ORM.
 *
 * @author Sebastiaan Stok <s.stok@rollerscapes.net>
 */
final class BirthdayTypeExtension extends AbstractFieldTypeExtension
{
    public function __construct(
        private readonly AgeDateConversion $conversion = new AgeDateConversion(),
    ) {}

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(
            ['doctrine_dbal_conversion' => $this->conversion]
        );
    }

    public function getExtendedType(): string
    {
        return BirthdayType::class;
    }
}
