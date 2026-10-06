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

namespace Rollerworks\Component\Search\Tests\Doctrine\Dbal;

use Doctrine\DBAL\Connection;

final class SchemaRecord
{
    /** @var array<int, mixed[]> */
    private array $records = [];

    /**
     * @param array<string, string> $columns ['column1' => 'type']
     */
    public function __construct(
        private readonly string $table,
        private readonly array $columns,
    ) {}

    /**
     * @param string                $tableName Fully qualified table-name
     * @param array<string, string> $columns   ['column1' => 'type']
     *
     * @return SchemaRecord
     */
    public static function create(string $tableName, array $columns)
    {
        return new self($tableName, $columns);
    }

    public function getTable(): string
    {
        return $this->table;
    }

    /**
     * @param mixed[] $values Values are expected in the same order as the columns
     */
    public function add(array $values)
    {
        if (\count($values) !== \count($this->columns)) {
            throw new \InvalidArgumentException(
                \sprintf(
                    'Values count mismatch, expected %d got %d on table "%s" with record: %s',
                    \count($this->columns),
                    \count($values),
                    $this->table,
                    var_export($values, true)
                )
            );
        }

        $this->records[] = $values;

        return $this;
    }

    /**
     * @return $this
     */
    public function end(): self
    {
        return $this;
    }

    /**
     * @return $this
     */
    public function records(): self
    {
        return $this;
    }

    /**
     * @return array<string, string> ['column1' => 'type']
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    public function executeRecords(Connection $connection): void
    {
        foreach ($this->records as $values) {
            $connection->insert(
                $this->table,
                array_combine(array_keys($this->columns), $values),
                $this->columns
            );
        }
    }
}
