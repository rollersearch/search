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

namespace Rollerworks\Component\Search\Doctrine\Dbal\Tests\DbalExtensions;

use Doctrine\DBAL\Logging\SQLLogger;

/**
 * @internal
 *
 * @see \Doctrine\Tests\DbalExtensions\LegacySqlLogger
 */
final class LegacySqlLogger implements SQLLogger
{
    public function __construct(
        private readonly QueryLog $queryLog,
    ) {}

    public function startQuery($sql, ?array $params = null, ?array $types = null): void
    {
        $this->queryLog->logQuery($sql, $params, $types);
    }

    public function stopQuery(): void {}
}
