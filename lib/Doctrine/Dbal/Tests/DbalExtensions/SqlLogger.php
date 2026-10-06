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

use Psr\Log\AbstractLogger;

/**
 * @internal
 *
 * @see \Doctrine\Tests\DbalExtensions\SqlLogger
 */
final class SqlLogger extends AbstractLogger
{
    public function __construct(
        private readonly QueryLog $queryLog,
    ) {}

    public function log($level, $message, array $context = []): void
    {
        if (! isset($context['sql'])) {
            return;
        }

        $this->queryLog->logQuery(
            $context['sql'],
            $context['params'] ?? null,
            $context['types'] ?? null
        );
    }
}
