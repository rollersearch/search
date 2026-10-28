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

namespace Rollerworks\Component\Search\Value;

use Rollerworks\Component\Search\Exception\InvalidArgumentException;

/**
 * @author Sebastiaan Stok <s.stok@rollerscapes.net>
 */
final class PatternMatch implements ValueHolder
{
    public const PATTERN_CONTAINS = 'CONTAINS';
    public const PATTERN_STARTS_WITH = 'STARTS_WITH';
    public const PATTERN_ENDS_WITH = 'ENDS_WITH';
    public const PATTERN_NOT_CONTAINS = 'NOT_CONTAINS';
    public const PATTERN_NOT_STARTS_WITH = 'NOT_STARTS_WITH';
    public const PATTERN_NOT_ENDS_WITH = 'NOT_ENDS_WITH';
    public const PATTERN_EQUALS = 'EQUALS';
    public const PATTERN_NOT_EQUALS = 'NOT_EQUALS';

    public readonly string $patternType;

    /**
     * @throws \InvalidArgumentException When the pattern-match type is invalid
     */
    public function __construct(
        public readonly string $value,
        string $patternType,
        public readonly bool $caseInsensitive = false,
    ) {
        $typeConst = self::class . '::PATTERN_' . mb_strtoupper($patternType);

        if (! \defined($typeConst)) {
            throw new InvalidArgumentException(\sprintf('Unknown PatternMatch type "%s".', $patternType));
        }
        $this->patternType = mb_strtoupper($patternType);
    }

    /**
     * @deprecated Since RollerworksSearch 2.1 use the property $value
     */
    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * @deprecated Since RollerworksSearch 2.1 use the property $patternType
     */
    public function getType(): string
    {
        return $this->patternType;
    }

    /**
     * @deprecated Since RollerworksSearch 2.1 use the property $caseInsensitive
     */
    public function isCaseInsensitive(): bool
    {
        return $this->caseInsensitive;
    }

    public function isExclusive(): bool
    {
        return \in_array(
            $this->patternType,
            [
                self::PATTERN_NOT_STARTS_WITH,
                self::PATTERN_NOT_CONTAINS,
                self::PATTERN_NOT_ENDS_WITH,
                self::PATTERN_NOT_EQUALS,
            ],
            true
        );
    }
}
