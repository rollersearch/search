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

namespace Rollerworks\Component\Search\Elasticsearch\Extension\Type;

use Rollerworks\Component\Search\Elasticsearch\Extension\Conversion\DateChildOrderConversion;
use Rollerworks\Component\Search\Elasticsearch\Extension\Conversion\DateTimeConversion;
use Rollerworks\Component\Search\Extension\Core\Type\DateTimeType;
use Rollerworks\Component\Search\Field\AbstractFieldTypeExtension;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DateTimeTypeExtension extends AbstractFieldTypeExtension
{
    public function __construct(
        private readonly DateTimeConversion $conversion = new DateTimeConversion(
        ),
    ) {}

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(
            [
                'elasticsearch_conversion' => $this->conversion,
                'elasticsearch_child_order_conversion' => new DateChildOrderConversion(),
            ]
        );
    }

    public function getExtendedType(): string
    {
        return DateTimeType::class;
    }
}
