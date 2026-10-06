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

namespace Rollerworks\Component\Search\Input;

use Rollerworks\Component\Search\ConditionErrorMessage;
use Rollerworks\Component\Search\Exception\TranslatedArgument;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The ErrorPathTranslator translates an error-path from the {@see ConditionErrorMessage} path property.
 *
 * The following paths are translated as follow (positions starting at 1):
 *
 * - `[2]`                : `At root level, group 3:`
 * - `[email][0]`         : `At root level, field "email" value position 1:`
 * - `[email][2]`         : `At root level, field "email" value position 3:`
 * - `[0][email][1]`      : `In group 1, field "email" value position 2:`
 * - `[2][email][1]`      : `In group 3, field "email" value position 2:`
 * - `[2][email][5]`      : `In group 3, field "email" value position 2:`
 * - `[0][0][0][email][1]`: `In group 1.1.1, field "email" value position 2:`
 * - `[id][0][upper]`     : `Field "email" value position 1 (upper):`
 * - `[@id]`              : `Field @id:`
 */
class ErrorPathTranslator
{
    public function __construct(
        protected TranslatorInterface $translator,
    ) {}

    public function translateFromQueryString(string $path): string
    {
        /**
         * @var array<int, array<int, mixed>> $parts each root-index holds a path-chunk '[part]'
         *                                    [index] => ['matched', 'chunk']
         */
        $parts = [];

        if ($path === '' || preg_match_all('/\[([^]]+)]/', $path, $parts, \PREG_SET_ORDER) === 0) {
            return '';
        }

        if (! isset($parts[1])) {
            // [2]
            if (self::isGroup($parts[0])) {
                return $this->translator->trans('At root level, group {{ group }}:', ['{{ group }}' => self::getPos($parts[0])], 'RollerworksSearch');
            }

            // [field]
            return $this->translator->trans('At root level, field {{ field }}:', ['{{ field }}' => self::getFieldName($parts[0])], 'RollerworksSearch');
        }

        if (self::isField($parts[0])) {
            // [field][0][upper]
            if (isset($parts[2])) {
                return $this->translator->trans(
                    'At root level, field {{ field }} value position {{ position }} {{ type }}:',
                    [
                        '{{ field }}' => self::getFieldName($parts[0]),
                        '{{ position }}' => self::getPos($parts[1]),
                        '{{ type }}' => new TranslatedArgument(self::getChunk($parts[2]), [], 'RollerworksSearch'),
                    ],
                    'RollerworksSearch',
                );
            }

            // [field][0]
            return $this->translator->trans(
                'At root level, field {{ field }} value position {{ position }}:',
                [
                    '{{ field }}' => self::getFieldName($parts[0]),
                    '{{ position }}' => self::getPos($parts[1]),
                ],
                'RollerworksSearch',
            );
        }

        // In (nested) group.
        $groupsPath = [];

        foreach ($parts as $i => $chunk) {
            if (self::isField($chunk)) {
                $messagePrefix = \count($groupsPath) > 1 ? 'In nested group {{ group }}' : 'In group {{ group }}';

                // [field][0][upper]
                if (isset($parts[$i + 2])) {
                    return $this->translator->trans(
                        $messagePrefix . ', field {{ field }} value position {{ position }} {{ type }}:',
                        [
                            '{{ field }}' => self::getFieldName($chunk),
                            '{{ position }}' => self::getPos($parts[$i + 1]),
                            '{{ type }}' => new TranslatedArgument(self::getChunk($parts[$i + 2]), [], 'RollerworksSearch'),
                            '{{ group }}' => implode('.', $groupsPath),
                        ],
                        'RollerworksSearch',
                    );
                }

                // [field][0]
                if (isset($parts[$i + 1])) {
                    return $this->translator->trans(
                        $messagePrefix . ', field {{ field }} value position {{ position }}:',
                        [
                            '{{ field }}' => self::getFieldName($chunk),
                            '{{ position }}' => self::getPos($parts[$i + 1]),
                            '{{ group }}' => implode('.', $groupsPath),
                        ],
                        'RollerworksSearch',
                    );
                }

                // [field]
                return $this->translator->trans(
                    $messagePrefix . ', field {{ field }}:',
                    [
                        '{{ field }}' => self::getFieldName($chunk),
                        '{{ group }}' => implode('.', $groupsPath),
                    ],
                    'RollerworksSearch',
                );
            }

            // Not a field, so it can only a group.
            $groupsPath[] = self::getPos($chunk);
        }

        // No field, so the last chunk is a group.
        return $this->translator->trans(
            \count($groupsPath) > 1 ? 'Nested group {{ group }}:' : 'Group {{ group }}:',
            ['{{ group }}' => implode('.', $groupsPath)],
            'RollerworksSearch',
        );
    }

    /**
     * Get the index is human friendly format (1 indexed, rather than 0 indexed).
     *
     * @param array{0: string, 1: string} $part
     */
    protected static function getPos(array $part): int
    {
        return ((int) self::getChunk($part)) + 1;
    }

    /**
     * @param array{0: string, 1: string} $part
     */
    protected static function getChunk(array $part): string
    {
        return $part[1];
    }

    /**
     * @param array{0: string, 1: string} $part
     */
    protected static function isGroup(array $part): bool
    {
        return ctype_digit(self::getChunk($part));
    }

    /**
     * @param array{0: string, 1: string} $part
     */
    protected static function isField(array $part): bool
    {
        return ! ctype_digit(self::getChunk($part));
    }

    /**
     * @param array{0: string, 1: string} $part
     */
    protected static function getFieldName(array $part): string
    {
        return '"' . self::getChunk($part) . '"';
    }
}
