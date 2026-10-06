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

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
final class ChoiceToValueTransformer implements DataTransformer
{
    public function __construct(
        private readonly ChoiceList $choiceList,
    ) {}

    public function transform(mixed $value): mixed
    {
        $value = $this->choiceList->getValuesForChoices([$value]);

        return (string) current($value);
    }

    public function reverseTransform(mixed $value): mixed
    {
        if ($value !== null && ! \is_string($value)) {
            throw new TransformationFailedException('Expected a string or null.');
        }

        $choices = $this->choiceList->getChoicesForValues([(string) $value]);

        if (\count($choices) !== 1) {
            if ($value === null || $value === '') {
                return null;
            }

            throw new TransformationFailedException(\sprintf('The choice "%s" does not exist or is not unique', $value));
        }

        return current($choices);
    }
}
