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

use Doctrine\DBAL\Query\QueryBuilder;
use Psr\SimpleCache\CacheInterface as Cache;
use Rollerworks\Component\Search\SearchCondition;

/**
 * @author Sebastiaan Stok <s.stok@rollerscapes.net>
 */
final class DoctrineDbalFactory
{
    public function __construct(
        private readonly ?Cache $cacheDriver = null,
    ) {}

    /**
     * Creates a new SqlConditionGenerator for the SearchCondition.
     *
     * Conversions are applied using the 'doctrine_dbal_conversion' option.
     */
    public function createConditionGenerator(QueryBuilder $queryBuilder, SearchCondition $searchCondition): ConditionGenerator
    {
        return new SqlConditionGenerator($queryBuilder, $searchCondition);
    }

    /**
     * Creates a new CachedConditionGenerator for the SearchCondition.
     *
     * Note: When no cache driver was configured the original ConditionGenerator
     * is returned instead.
     *
     * @param \DateInterval|int|null $ttl Optional. The TTL value of this item. If no value is sent and
     *                                    the driver supports TTL then the library may set a default value
     *                                    for it or let the driver take care of that.
     */
    public function createCachedConditionGenerator(QueryBuilder $queryBuilder, SearchCondition $searchCondition, \DateInterval | int | null $ttl = 0): ConditionGenerator
    {
        if ($this->cacheDriver === null) {
            return new SqlConditionGenerator($queryBuilder, $searchCondition);
        }

        return new CachedConditionGenerator($queryBuilder, $searchCondition, $this->cacheDriver, $ttl);
    }
}
