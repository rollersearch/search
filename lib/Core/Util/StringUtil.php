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

namespace Rollerworks\Component\Search\Util;

/**
 * @author Issei Murasawa <issei.m7@gmail.com>
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
final class StringUtil
{
    /**
     * This class should not be instantiated.
     */
    private function __construct() {}

    /**
     * Converts a fully-qualified class name to a block prefix.
     *
     * @param string $fqcn The fully-qualified class name
     *
     * @return string|null The block prefix or null if not a valid FQCN
     */
    public static function fqcnToBlockPrefix(string $fqcn): ?string
    {
        // Non-greedy ("+?") to match "type" suffix, if present
        if (preg_match('~([^\\\]+?)(type)?$~i', $fqcn, $matches)) {
            return mb_strtolower((string) preg_replace(['/([A-Z]+)([A-Z][a-z])/', '/([a-z\d])([A-Z])/'], ['\1_\2', '\1_\2'], $matches[1]));
        }

        return null;
    }
}
