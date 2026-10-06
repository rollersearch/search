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

namespace Rollerworks\Component\Search;

use Rollerworks\Component\Search\Exception\InvalidArgumentException;

/**
 * SearchConditionSerializer, serializes a search condition for persistent storage.
 *
 * In practice this serializes the root ValuesGroup and of the condition
 * and bundles it with the FieldSet-name for further unserializing.
 *
 * @author Sebastiaan Stok <s.stok@rollerscapes.net>
 */
class SearchConditionSerializer
{
    public function __construct(
        private readonly SearchFactory $searchFactory,
    ) {
    }

    /**
     * Serialize a SearchCondition.
     *
     * The returned value is an array you can safely serialize yourself.
     * This is not done already because storing a serialized SearchCondition
     * in a php session would serialize the serialized result again.
     *
     * Caution: The FieldSet must be loadable from the SearchFactory.
     *
     * @return array{0: string, 1: string, 2: array<string, mixed>} ['FieldSet-name', 'serialized ValuesGroup object', [data-attributes]]
     */
    public function serialize(SearchCondition $searchCondition): array
    {
        $setName = $searchCondition->getFieldSet()->getSetName();

        return [$setName, serialize($searchCondition->getValuesGroup()), $searchCondition->getAttributes()];
    }

    /**
     * Unserialize a serialized SearchCondition.
     *
     * @param array{0: string, 1: string, 2?: array<string, mixed>} $searchCondition [FieldSet-name, serialized ValuesGroup object, [?data-attributes]]
     *
     * @throws InvalidArgumentException when serialized SearchCondition is invalid
     *                                  (invalid structure or failed to unserialize)
     */
    public function unserialize(array $searchCondition): SearchCondition
    {
        if (! array_is_list($searchCondition) || \count($searchCondition) < 2 || ! isset($searchCondition[0], $searchCondition[1])) {
            throw new InvalidArgumentException('Serialized search condition must be a numeric-array with at least keys [0, 1].');
        }

        $fieldSet = $this->searchFactory->createFieldSet($searchCondition[0]);

        if (isset($searchCondition[2]) && ! \is_array($searchCondition[2])) {
            throw new InvalidArgumentException(\sprintf('Serialized search condition index "2" is expected to be an "array" got "%s".', get_debug_type($searchCondition[2])));
        }

        set_error_handler(static function (int $errNo, string $errstr, string $errFile, int $errLine): void {
            throw new InvalidArgumentException('Unable to unserialize invalid value.', $errNo, new \ErrorException($errstr, $errNo, $errNo, $errFile, $errLine));
        });

        try {
            $condition = new SearchCondition($fieldSet, unserialize($searchCondition[1], ['allowed_classes' => true]));

            foreach ($searchCondition[2] ?? [] as $key => $value) {
                $condition->setAttribute($key, $value);
            }

            return $condition;
        } finally {
            restore_error_handler();
        }
    }
}
