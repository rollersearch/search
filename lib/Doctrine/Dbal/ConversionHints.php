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

namespace Rollerworks\Component\Search\Doctrine\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Type;
use Rollerworks\Component\Search\Doctrine\Dbal\Query\QueryField;
use Rollerworks\Component\Search\Doctrine\Dbal\QueryPlatform\AbstractQueryPlatform;
use Rollerworks\Component\Search\Value\ValueHolder;

class ConversionHints
{
    public const CONTEXT_RANGE_LOWER_BOUND = 'range.lower_bound';
    public const CONTEXT_RANGE_UPPER_BOUND = 'range.upper_bound';
    public const CONTEXT_SIMPLE_VALUE = 'simple_value';
    public const CONTEXT_COMPARISON = 'comparison';

    public QueryField $field;
    public Connection $connection;
    public string $column;

    /** @var self::CONTEXT_* */
    public string $context;

    /** @var mixed|ValueHolder */
    public mixed $originalValue;

    public function __construct(
        private readonly AbstractQueryPlatform $queryPlatform,
    ) {}

    /**
     * Returns a parameter-name to reference a value.
     */
    public function createParamReferenceFor($value, string | Type | null $type = 'string'): string
    {
        if (\is_object($type)) {
            $type = Type::lookupName($type);

            trigger_deprecation(
                'rollerworks/search-doctrine-dbal',
                'v2.0.0-BETA2',
                \sprintf(
                    'passing a %s object is deprecated and will no longer be accepted in v3.0.0, pass the type actual name "%s" instead.',
                    Type::class,
                    $type
                )
            );
        }

        if ($type === null) {
            trigger_deprecation(
                'rollerworks/search-doctrine-dbal',
                'v2.0.0-BETA9',
                'passing null as type is deprecated and will no longer be accepted in v3.0.0, pass a type name instead, defaults to "string" for now.".'
            );
        }

        return $this->queryPlatform->createParamReferenceFor($value, $type ?? 'string');
    }

    /**
     * Returns the value that is currently being processed (in context).
     *
     * The $this->originalValue might return a value-holder or actual
     * processing value depending on the context.
     */
    public function getProcessingValue(): mixed
    {
        switch ($this->context) {
            case self::CONTEXT_SIMPLE_VALUE:
                return $this->originalValue;

            case self::CONTEXT_COMPARISON:
                return $this->originalValue->value;

            case self::CONTEXT_RANGE_LOWER_BOUND:
                return $this->originalValue->lower;

            case self::CONTEXT_RANGE_UPPER_BOUND:
                return $this->originalValue->upper;

            default:
                throw new \LogicException(\sprintf('Unknown context "%s".', $this->context));
        }
    }

    /**
     * Returns the platform name, returns a class-name if the platform could not be detected.
     *
     * @return 'mysql'|'sqlite'|'pgsql'|'oci'|'sqlsrv'|'mock'|string
     */
    public function getPlatformName(): string
    {
        return $this->queryPlatform->platformName;
    }
}
