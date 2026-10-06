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

namespace Rollerworks\Bundle\SearchBundle;

use Rollerworks\Component\Search\Field\FieldConfig;
use Rollerworks\Component\Search\Field\OrderField;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class TranslatorBasedAliasResolver
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {}

    public function __invoke(FieldConfig $field): string
    {
        $label = $field->getOption('label');

        if ($label === null) {
            return $field->getName();
        }

        if ($label instanceof TranslatableInterface) {
            $label = $label->trans($this->translator);
        }

        if ($field instanceof OrderField && $label[0] !== '@') {
            $label = '@' . $label;
        }

        return $label;
    }
}
