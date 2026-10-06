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

namespace Rollerworks\Component\Search\Doctrine\Orm;

use Doctrine\ORM\QueryBuilder;
use Psr\SimpleCache\CacheInterface as Cache;
use Rollerworks\Component\Search\SearchCondition;

/**
 * @final
 */
class DoctrineOrmFactory
{
    public function __construct(
        private readonly ?Cache $cacheDriver = null,
    ) {}

    /**
     * Creates a new ConditionGenerator for the SearchCondition.
     *
     * Conversions are applied using the 'doctrine_dbal_conversion' option (when present).
     */
    public function createConditionGenerator(QueryBuilder $query, SearchCondition $searchCondition): QueryBuilderConditionGenerator
    {
        return new QueryBuilderConditionGenerator($query, $searchCondition);
    }

    /**
     * Creates a new CachedConditionGenerator for the SearchCondition.
     *
     * @param \DateInterval|int|null $ttl Optional. The TTL value of this item. If no value is sent and
     *                                    the driver supports TTL then the library may set a default value
     *                                    for it or let the driver take care of that.
     */
    public function createCachedConditionGenerator(QueryBuilder $query, SearchCondition $searchCondition, \DateInterval | int | null $ttl = null): ConditionGenerator
    {
        if ($this->cacheDriver === null) {
            return $this->createConditionGenerator($query, $searchCondition);
        }

        return new CachedDqlConditionGenerator($query, $searchCondition, $this->cacheDriver, $ttl);
    }
}
