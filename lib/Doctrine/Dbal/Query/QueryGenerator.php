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

namespace Rollerworks\Component\Search\Doctrine\Dbal\Query;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Rollerworks\Component\Search\Doctrine\Dbal\ConversionHints;
use Rollerworks\Component\Search\Doctrine\Dbal\QueryPlatform\AbstractQueryPlatform;
use Rollerworks\Component\Search\Exception\BadMethodCallException;
use Rollerworks\Component\Search\SearchCondition;
use Rollerworks\Component\Search\SearchOrder;
use Rollerworks\Component\Search\Value\Compare;
use Rollerworks\Component\Search\Value\ExcludedRange;
use Rollerworks\Component\Search\Value\PatternMatch;
use Rollerworks\Component\Search\Value\Range;
use Rollerworks\Component\Search\Value\ValuesBag;
use Rollerworks\Component\Search\Value\ValuesGroup;

/**
 * This class is only to be used by packages of RollerworksSearch
 * and is considered internal.
 *
 * @author Sebastiaan Stok <s.stok@rollerscapes.net>
 *
 * @internal
 */
final class QueryGenerator
{
    /**
     * @param array<string, array<string|null, QueryField>> $fields [field-name][mapping-index] => {QueryField}
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly AbstractQueryPlatform $queryPlatform,
        private readonly array $fields,
    ) {}

    /**
     * @param array<string, array<string, QueryField>> $fields
     */
    public static function applySortingTo(?SearchOrder $order, QueryBuilder $qb, array $fields): void
    {
        if ($order === null) {
            return;
        }

        foreach ($order->getFields() as $fieldName => $direction) {
            if (! isset($fields[$fieldName])) {
                continue;
            }

            if (\count($fields[$fieldName]) > 1) {
                throw new BadMethodCallException(\sprintf('Field "%s" is registered as multiple mapping and cannot be used for sorting.', $fieldName));
            }

            $qb->addOrderBy($fields[$fieldName]['']->column, mb_strtoupper($direction));
        }
    }

    public function getWhereClause(SearchCondition $searchCondition): string
    {
        $primaryCondition = $searchCondition->getPrimaryCondition();
        $conditions = [];

        if ($primaryCondition !== null) {
            $conditions[] = $this->getGroupQuery($primaryCondition->getValuesGroup());
        }

        $conditions[] = $this->getGroupQuery($searchCondition->getValuesGroup());

        return self::implodeWithValue(' AND ', $conditions);
    }

    public function getGroupQuery(ValuesGroup $valuesGroup): string
    {
        $query = [];

        foreach ($valuesGroup->getFields() as $mappingConfig => $values) {
            if (! isset($this->fields[$mappingConfig])) {
                continue;
            }

            $groupSql = [];
            $inclusiveSqlGroup = [];
            $exclusiveSqlGroup = [];

            foreach ($this->fields[$mappingConfig] as $mappingsConfig) {
                $this->processFieldValues($values, $mappingsConfig, $inclusiveSqlGroup, $exclusiveSqlGroup);
            }

            $groupSql[] = self::implodeValuesWithWrapping(' OR ', $inclusiveSqlGroup, '(', ')');
            $groupSql[] = self::implodeValuesWithWrapping(' AND ', $exclusiveSqlGroup, '(', ')');
            $query[] = self::wrapIfNotEmpty(self::implodeWithValue(' AND ', $groupSql), '(', ')');
        }

        $finalQuery = [];

        // Wrap all the fields as a group
        $finalQuery[] = self::wrapIfNotEmpty(
            self::implodeWithValue(' ' . mb_strtoupper($valuesGroup->getGroupLogical()) . ' ', $query),
            '(',
            ')'
        );

        $this->processGroups($valuesGroup->getGroups(), $finalQuery);

        return self::implodeValuesWithWrapping(' AND ', $finalQuery, '(', ')');
    }

    /**
     * @param ValuesGroup[] $groups
     *
     * @param-out string[]  $query
     */
    private function processGroups(array $groups, array &$query): void
    {
        /** @var string[] $groupSql */
        $groupSql = [];

        foreach ($groups as $group) {
            $groupSql[] = $this->getGroupQuery($group);
        }

        $query[] = self::wrapIfNotEmpty(self::implodeWithValue(' OR ', $groupSql), '(', ')');
    }

    /**
     * @param mixed[] $values
     *
     * @param-out string[] $query
     */
    private function processSingleValues(array $values, QueryField $mappingConfig, array &$query, bool $exclude, ConversionHints $hints): void
    {
        // NOTE. Using OR/AND seems to be less-performant on some vendors (*namely MySQL*) but this heavily depends
        // on the engine's configuration and other aspects.

        $patterns = ['%s = %s', '%s <> %s'];

        $hints->context = ConversionHints::CONTEXT_SIMPLE_VALUE;

        foreach ($values as $value) {
            $hints->originalValue = $value;
            $column = $this->queryPlatform->getFieldColumn($mappingConfig, $mappingConfig->column, $hints);

            $query[] = \sprintf(
                $patterns[(int) $exclude],
                $column,
                $this->queryPlatform->getValueAsSql($value, $mappingConfig, $hints)
            );
        }
    }

    /**
     * @param Range[] $ranges
     *
     * @param-out string[] $query
     */
    private function processRanges(array $ranges, QueryField $mappingConfig, array &$query, bool $exclude, ConversionHints $hints): void
    {
        foreach ($ranges as $range) {
            $hints->originalValue = $range;
            $hints->context = ConversionHints::CONTEXT_RANGE_LOWER_BOUND;

            $column = $this->queryPlatform->getFieldColumn($mappingConfig, $mappingConfig->column, $hints);
            $lowerBound = $this->queryPlatform->getValueAsSql($range->lower, $mappingConfig, $hints);

            $hints->context = ConversionHints::CONTEXT_RANGE_UPPER_BOUND;
            $upperBound = $this->queryPlatform->getValueAsSql($range->upper, $mappingConfig, $hints);

            $query[] = \sprintf(
                $this->getRangePattern($range, $exclude),
                $column,
                $lowerBound,
                $column,
                $upperBound
            );
        }
    }

    /**
     * @return string either "(%s >= %s AND %s <= %s)"
     */
    private function getRangePattern(Range $range, bool $exclude = false): string
    {
        $pattern = '(%s ';

        if ($exclude) {
            $pattern .= ($range->inclusiveLower ? '<=' : '<');
            $pattern .= ' %s OR %s '; // lower-bound value, AND fieldname
            $pattern .= ($range->inclusiveUpper ? '>=' : '>');
            $pattern .= ' %s'; // upper-bound value
        } else {
            $pattern .= ($range->inclusiveLower ? '>=' : '>');
            $pattern .= ' %s AND %s '; // lower-bound value, AND fieldname
            $pattern .= ($range->inclusiveUpper ? '<=' : '<');
            $pattern .= ' %s'; // upper-bound value
        }

        $pattern .= ')';

        return $pattern;
    }

    /**
     * @param Compare[] $compares
     *
     * @param-out string[] $query
     */
    private function processCompares(array $compares, QueryField $mappingConfig, array &$query, bool $exclude, ConversionHints $hints): void
    {
        $valuesQuery = [];
        $hints->context = ConversionHints::CONTEXT_COMPARISON;

        foreach ($compares as $comparison) {
            if ($exclude !== ($comparison->operator === '<>')) {
                continue;
            }

            $hints->originalValue = $comparison;
            $column = $this->queryPlatform->getFieldColumn($mappingConfig, $mappingConfig->column, $hints);

            $valuesQuery[] = \sprintf(
                '%s %s %s',
                $column,
                $comparison->operator,
                $this->queryPlatform->getValueAsSql(
                    $comparison->value,
                    $mappingConfig,
                    $hints
                )
            );
        }

        if (\count($valuesQuery) > 1 && ! $exclude) {
            $query[] = self::implodeValuesWithWrapping(' AND ', $valuesQuery, '(', ')');
        } else {
            $query[] = self::implodeWithValue(' AND ', $valuesQuery);
        }
    }

    /**
     * @param PatternMatch[] $patternMatchers
     *
     * @param-out string[] $query
     */
    private function processPatternMatchers(array $patternMatchers, QueryField $mappingConfig, array &$query, bool $exclude, ConversionHints $hints): void
    {
        $hints->context = ConversionHints::CONTEXT_SIMPLE_VALUE;

        foreach ($patternMatchers as $patternMatch) {
            if ($exclude !== $patternMatch->isExclusive()) {
                continue;
            }

            $query[] = $this->queryPlatform->getPatternMatcher(
                $patternMatch,
                $this->queryPlatform->getFieldColumn($mappingConfig, $mappingConfig->column, $hints)
            );
        }
    }

    /**
     * @param string[] $values
     */
    private static function implodeWithValue(string $glue, array $values): string
    {
        // Remove the empty values
        $values = array_filter($values, static fn (string $val): bool => $val !== '');

        if (\count($values) === 0) {
            return '';
        }

        return implode($glue, $values);
    }

    /**
     * @param string[] $values
     */
    private static function implodeValuesWithWrapping(string $glue, array $values, string $prefix, string $suffix): string
    {
        // Remove the empty values
        $values = array_filter($values, static fn (string $val): bool => $val !== '');

        if (\count($values) === 0) {
            return '';
        }

        $value = implode($glue, $values);

        if (\count($values) > 1) {
            return $prefix . $value . $suffix;
        }

        return $value;
    }

    private static function wrapIfNotEmpty(string $value, string $prefix, string $suffix): string
    {
        if ($value === '') {
            return '';
        }

        return $prefix . $value . $suffix;
    }

    /**
     * @param-out string[] $inclusiveSqlGroup
     * @param-out string[] $exclusiveSqlGroup
     */
    private function processFieldValues(ValuesBag $values, QueryField $mappingConfig, array &$inclusiveSqlGroup, array &$exclusiveSqlGroup): void
    {
        $hints = new ConversionHints($this->queryPlatform);
        $hints->field = $mappingConfig;
        $hints->column = $mappingConfig->column;
        $hints->connection = $this->connection;

        $this->processSingleValues(
            $values->getSimpleValues(),
            $mappingConfig,
            $inclusiveSqlGroup,
            false,
            $hints
        );

        $this->processRanges(
            $values->get(Range::class),
            $mappingConfig,
            $inclusiveSqlGroup,
            false,
            $hints
        );

        $this->processCompares(
            $values->get(Compare::class),
            $mappingConfig,
            $inclusiveSqlGroup,
            false,
            $hints
        );

        $this->processPatternMatchers(
            $values->get(PatternMatch::class),
            $mappingConfig,
            $inclusiveSqlGroup,
            false,
            $hints
        );

        $this->processSingleValues(
            $values->getExcludedSimpleValues(),
            $mappingConfig,
            $exclusiveSqlGroup,
            true,
            $hints
        );

        $this->processRanges(
            $values->get(ExcludedRange::class),
            $mappingConfig,
            $exclusiveSqlGroup,
            true,
            $hints
        );

        $this->processPatternMatchers(
            $values->get(PatternMatch::class),
            $mappingConfig,
            $exclusiveSqlGroup,
            true,
            $hints
        );

        $this->processCompares(
            $values->get(Compare::class),
            $mappingConfig,
            $exclusiveSqlGroup,
            true,
            $hints
        );

        $hints = null;
    }

    public function getParameters(): ArrayCollection
    {
        return $this->queryPlatform->getParameters();
    }
}
