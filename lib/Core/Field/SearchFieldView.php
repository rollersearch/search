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

use Rollerworks\Component\Search\FieldSetView;

/**
 * @author Sebastiaan Stok <s.stok@rollerscapes.net>
 */
class SearchFieldView
{
    /**
     * The variables assigned to this view.
     *
     * @var array<string, mixed>
     */
    public array $vars = [
        'attr' => [],
    ];

    public function __construct(
        public FieldSetView $fieldSet,
    ) {}
}
