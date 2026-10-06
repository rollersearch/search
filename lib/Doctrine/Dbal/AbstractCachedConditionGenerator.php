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

use Doctrine\Common\Collections\ArrayCollection;
use Psr\SimpleCache\CacheInterface as Cache;
use Rollerworks\Component\Search\SearchCondition;

abstract class AbstractCachedConditionGenerator
{
    protected ?string $cacheKey = null;
    protected bool $isApplied = false;

    /**
     * @param Cache                  $cacheDriver   PSR-16 SimpleCache instance. Use a custom pool to ease
     *                                              purging invalidated items
     * @param \DateInterval|int|null $cacheLifeTime Optional. The TTL value of this item. If no value is sent and
     *                                              the driver supports TTL then the library may set a default
     *                                              value for it or let the driver take care of that.
     */
    protected function __construct(
        protected Cache $cacheDriver,
        protected SearchCondition $searchCondition,
        protected \DateInterval | int | null $cacheLifeTime = null,
    ) {}

    public function getSearchCondition(): SearchCondition
    {
        return $this->searchCondition;
    }

    protected function getCacheKey(array $config, string $prefix = 'sql'): string
    {
        if ($this->cacheKey === null) {
            $searchCondition = $this->getSearchCondition();

            $this->cacheKey = hash(
                'sha256',
                $prefix .
                $searchCondition->getFieldSet()->getSetName() .
                "\n" .
                serialize($searchCondition->getValuesGroup()) .
                "\n" .
                serialize($searchCondition->getPrimaryCondition()?->getValuesGroup()) .
                "\n" .
                serialize($config)
            );
        }

        return $this->cacheKey;
    }

    protected function getFromCache(string $cacheKey): ?array
    {
        $cached = $this->cacheDriver->get($cacheKey);

        if (! \is_array($cached) || ! isset($cached[0], $cached[1]) || ! \is_string($cached[0]) || ! \is_array($cached[1])) {
            return null;
        }

        try {
            $cached[1] = $this->unpackParameters($cached[1]);

            return $cached;
        } catch (\Throwable) {
            return null;
        }
    }

    private function unpackParameters(array $provided): ArrayCollection
    {
        $parameters = new ArrayCollection();

        foreach ($provided as $name => [$value, $type]) {
            $parameters->set($name, [$value, $type]);
        }

        return $parameters;
    }

    protected function storeInCache(string $whereClause, string $cacheKey, ArrayCollection $parameters): void
    {
        if ($whereClause === '') {
            return;
        }

        $this->cacheDriver->set(
            $cacheKey,
            [$whereClause, $this->packParameters($parameters)],
            $this->cacheLifeTime
        );
    }

    private function packParameters(ArrayCollection $provided): array
    {
        $parameters = [];

        foreach ($provided as $name => [$value, $type]) {
            $parameters[$name] = [$value, $type];
        }

        return $parameters;
    }
}
