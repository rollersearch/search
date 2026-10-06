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

namespace Rollerworks\Component\Search\Extension\Core\DataTransformer;

use Rollerworks\Component\Search\DataTransformer;
use Rollerworks\Component\Search\Exception\TransformationFailedException;
use Rollerworks\Component\Search\Extension\Core\ChoiceList\ChoiceList;
use Rollerworks\Component\Search\Extension\Core\ChoiceList\View\ChoiceListView;

/**
 * @author Sebastiaan Stok <s.stok@rollerscapes.net>
 */
final class ChoiceToLabelTransformer implements DataTransformer
{
    public function __construct(
        private readonly ChoiceList $choiceList,
        private readonly ChoiceListView $choiceListView,
    ) {}

    public function transform($choice)
    {
        if ($this->choiceListView->choicesByLabel === null) {
            $this->choiceListView->initChoicesByLabel();
        }

        $value = $this->choiceList->getValuesForChoices([$choice]);
        $value = current($value);

        if (! \array_key_exists($value, $this->choiceListView->labelsByValue)) {
            throw new TransformationFailedException(\sprintf('The choice "%s" does not exist or is not unique', $choice));
        }

        return $this->choiceListView->labelsByValue[$value];
    }

    public function reverseTransform($value)
    {
        if ($value !== null && ! \is_string($value)) {
            throw new TransformationFailedException('Expected a string or null.');
        }

        if ($value === null || $value === '') {
            return null;
        }

        if ($this->choiceListView->choicesByLabel === null) {
            $this->choiceListView->initChoicesByLabel();
        }

        if (! \array_key_exists($value, $this->choiceListView->choicesByLabel)) {
            throw new TransformationFailedException(\sprintf('The choice "%s" does not exist or is not unique', $value));
        }

        return $this->choiceListView->choicesByLabel[$value]->data;
    }
}
