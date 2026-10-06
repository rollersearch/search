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

namespace Rollerworks\Component\Search\Extension\Core\ChoiceList\View;

/**
 * Represents a choice in templates.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ChoiceView
{
    /**
     * @param mixed                $data  The original choice
     * @param string               $value The view representation of the choice
     * @param string               $label The label displayed to humans
     * @param array<string, mixed> $attr  Additional attributes for the HTML tag
     */
    public function __construct(
        public mixed $data,
        public string $value,
        public string $label,
        public array $attr = [],
    ) {}
}
