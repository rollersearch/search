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

namespace Rollerworks\Component\Search\Tests\Doctrine\Dbal\Functional;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Schema\Schema as DbSchema;
use Doctrine\DBAL\SQL\Builder\DropSchemaObjectsSQLBuilder;
use Doctrine\DBAL\Types\Type;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Exception;
use Rollerworks\Component\Search\Doctrine\Dbal\ConditionGenerator;
use Rollerworks\Component\Search\Doctrine\Dbal\Test\QueryBuilderAssertion;
use Rollerworks\Component\Search\Doctrine\Dbal\Tests\TestUtil;
use Rollerworks\Component\Search\SearchCondition;
use Rollerworks\Component\Search\Tests\Doctrine\Dbal\DbalTestCase;
use Rollerworks\Component\Search\Tests\Doctrine\Dbal\SchemaRecord;

abstract class FunctionalDbalTestCase extends DbalTestCase
{
    /**
     * Shared connection for setting up the database in a single place
     * during class setup and tear down.
     */
    private static ?Connection $sharedConn = null;

    protected Connection $conn;

    protected static function resetSharedConn(): void
    {
        if (self::$sharedConn) {
            self::$sharedConn->close();
            self::$sharedConn = null;
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (! isset(
            $GLOBALS['db_driver'],
            $GLOBALS['db_host'],
            $GLOBALS['db_user'],
            $GLOBALS['db_password'],
            $GLOBALS['db_dbname'],
            $GLOBALS['db_port']
        )) {
            self::markTestSkipped('GLOBAL variables not enabled');
        }

        if (! isset(self::$sharedConn)) {
            self::$sharedConn = TestUtil::getConnection();

            $schema = new DbSchema();
            $this->setUpDbSchema($schema);

            foreach ((new DropSchemaObjectsSQLBuilder(self::$sharedConn->getDatabasePlatform()))->buildSQL($schema) as $s) {
                try {
                    $this->conn->executeStatement($s);
                } catch (\Throwable) {
                    // Noop - tables might not exist yet
                }
            }

            $this->updateSchema(self::$sharedConn, $schema);

            foreach ($this->getDbRecords() as $set) {
                $set->executeRecords(self::$sharedConn);
            }
        }

        $this->conn = self::$sharedConn;
    }

    private function updateSchema(Connection $connection, DbSchema $providedSchema): void
    {
        $schemaManager = $connection->createSchemaManager();
        $schemaDiff = $schemaManager->createComparator()
            ->compareSchemas($schemaManager->introspectSchema(), $providedSchema)
        ;

        $platform = $connection->getDatabasePlatform();

        if ($platform->supportsSchemas()) {
            foreach ($schemaDiff->getCreatedSchemas() as $schema) {
                $connection->executeStatement($platform->getCreateSchemaSQL($schema));
            }
        }

        if ($platform->supportsSequences()) {
            foreach ($schemaDiff->getAlteredSequences() as $sequence) {
                $connection->executeStatement($platform->getAlterSequenceSQL($sequence));
            }

            foreach ($schemaDiff->getCreatedSequences() as $sequence) {
                $connection->executeStatement($platform->getCreateSequenceSQL($sequence));
            }
        }

        foreach ($platform->getCreateTablesSQL($schemaDiff->getCreatedTables()) as $sql) {
            $connection->executeStatement($sql);
        }

        foreach ($schemaDiff->getAlteredTables() as $tableDiff) {
            foreach ($platform->getAlterTableSQL($tableDiff) as $sql) {
                $connection->executeStatement($sql);
            }
        }
    }

    public static function tearDownAfterClass(): void
    {
        // There were errors recorded so don't reset the connection.
        if (\count(self::$sharedConn->queryLog->queries ?? []) > 0) {
            return;
        }

        // Ensure the connection is reset between class-runs
        self::resetSharedConn();
    }

    protected function setUpDbSchema(DbSchema $schema): void
    {
        $invoiceTable = $schema->createTable('invoice');
        $invoiceTable->addOption('collation', 'utf8_bin');
        $invoiceTable->addColumn('id', 'integer', ['notNull' => false]);
        $invoiceTable->addColumn('status', 'integer', ['notNull' => false]);
        $invoiceTable->addColumn('label', 'string', ['notNull' => false, 'length' => 255]);
        $invoiceTable->addColumn('customer', 'integer', ['notNull' => false]);
        $invoiceTable->setPrimaryKey(['id']);

        $customerTable = $schema->createTable('customer');
        $customerTable->addOption('collation', 'utf8_bin');
        $customerTable->addColumn('id', 'integer', ['notNull' => false]);
        $customerTable->addColumn('name', 'string', ['notNull' => false, 'length' => 255]);
        $customerTable->addColumn('birthday', 'date_immutable', ['notNull' => false]);
        $customerTable->setPrimaryKey(['id']);
    }

    /**
     * @return SchemaRecord[]
     */
    protected function getDbRecords(): array
    {
        return [];
    }

    /**
     * Returns the QueryBuilder for the ConditionGenerator.
     */
    abstract protected function getQuery(): QueryBuilder;

    /**
     * Configure fields of the ConditionGenerator.
     */
    protected function configureConditionGenerator(ConditionGenerator $conditionGenerator): void {}

    /**
     * @param (int|string)[] $ids
     *
     * @throws \Doctrine\DBAL\Exception
     */
    protected function assertRecordsAreFound(SearchCondition $condition, array $ids): void
    {
        $conditionGenerator = $this->getDbalFactory()->createConditionGenerator($this->getQuery(), $condition);
        $this->configureConditionGenerator($conditionGenerator);

        $qb = $conditionGenerator->getQueryBuilder();

        $conditionGenerator->apply();
        $result = $qb->executeQuery();

        $paramsString = '';
        $platform = $this->conn->getDatabasePlatform();

        // Doctrine DBAL 4.0 introduced a new ParameterType class
        if (is_a(ParameterType::class, \UnitEnum::class, true)) {
            foreach ($qb->getParameters() as $name => $value) {
                $type = $qb->getParameterType($name);

                if ($type instanceof \UnitEnum) {
                    $type = match ($type) {
                        ParameterType::NULL => 'string',
                        ParameterType::STRING => 'string',
                        ParameterType::INTEGER => 'integer',
                        ParameterType::BINARY => 'binary',
                        ParameterType::BOOLEAN => 'boolean',
                        ParameterType::ASCII => 'string',
                        ParameterType::LARGE_OBJECT => 'blob',
                        ArrayParameterType::INTEGER => 'integer',
                        ArrayParameterType::STRING => 'string',
                        ArrayParameterType::BINARY => 'binary',
                        ArrayParameterType::ASCII => 'string',
                        default => 'string',
                    };
                } elseif (\is_object($type)) {
                    $type = Type::lookupName($type);
                }

                $paramsString .= \sprintf("%s = '%s'\n", $name, Type::getType($type)->convertToDatabaseValue($value, $platform));
            }
        } else {
            static $bindingToType = [
                ParameterType::NULL => 'string',
                ParameterType::STRING => 'string',
                ParameterType::INTEGER => 'integer',
                ParameterType::BINARY => 'binary',
                ParameterType::BOOLEAN => 'boolean',
                ParameterType::ASCII => 'string',
                ParameterType::LARGE_OBJECT => 'blob',
            ];

            foreach ($qb->getParameters() as $name => $value) {
                $type = $qb->getParameterType($name) ?? 'string';

                if (\is_int($type)) {
                    $type = $bindingToType[$type];
                } elseif (\is_object($type)) {
                    $type = Type::lookupName($type);
                }

                $paramsString .= \sprintf("%s = '%s'\n", $name, Type::getType($type)->convertToDatabaseValue($value, $platform));
            }
        }

        $rows = $result->fetchAllAssociative();
        $idRows = array_map(
            static fn ($value) => $value['id'],
            $rows
        );

        sort($ids);
        sort($idRows);

        self::assertEquals(
            $ids,
            array_merge([], array_unique($idRows)),
            \sprintf("Found these records instead: \n%s\nWith Query: %s\nAnd params: %s", print_r($rows, true), $qb->getSQL(), $paramsString)
        );
    }

    /**
     * @param ConditionGenerator|SearchCondition $conditionOrWhere Either a ConditionGenerator or a SearchCondition
     * @param string                             $expectedSql      Expected generated SQL for the query
     * @param array<string, mixed>|null          $parameters       Expected parameters for the query
     */
    protected function assertQueryIsExecutable(ConditionGenerator | SearchCondition $conditionOrWhere, string $expectedSql = '', ?array $parameters = null): void
    {
        if ($conditionOrWhere instanceof SearchCondition) {
            $conditionGenerator = $this->getDbalFactory()->createConditionGenerator($this->getQuery(), $conditionOrWhere);
            $this->configureConditionGenerator($conditionGenerator);
        } else {
            $conditionGenerator = $conditionOrWhere;
        }

        $qb = $conditionGenerator->getQueryBuilder();

        if ($expectedSql !== '') {
            QueryBuilderAssertion::assertQueryBuilderEquals(
                $conditionGenerator,
                $expectedSql,
                $parameters
            );
        } else {
            $conditionGenerator->apply();

            $this->addToAssertionCount(1);
        }

        $qb->executeQuery();
    }

    protected function onNotSuccessfulTest(\Throwable $t): never
    {
        // Ignore deprecation warnings.
        if ($t instanceof AssertionFailedError) {
            throw $t;
        }

        $i = \count(self::$sharedConn->queryLog->queries ?? []);

        if ($i) {
            $queries = '';

            foreach (array_reverse(self::$sharedConn->queryLog->queries) as $query) {
                $params = array_map(
                    static function ($p) {
                        if (\is_object($p)) {
                            return $p::class;
                        }

                        return "'" . var_export($p, true) . "'";
                    },
                    $query['params'] ?: []
                );

                $queries .= ($i + 1) . ". SQL: '" . $query['sql'] . "' Params: " . implode(', ', $params) . \PHP_EOL;
                --$i;
            }

            $trace = $t->getTrace();
            $traceMsg = '';

            foreach ($trace as $part) {
                if (isset($part['file'])) {
                    if (mb_strpos($part['file'], 'PHPUnit/') !== false) {
                        // Beginning with PHPUnit files we don't print the trace anymore.
                        break;
                    }

                    $traceMsg .= $part['file'] . ':' . $part['line'] . \PHP_EOL;
                }
            }

            $message =
                '[' . $t::class . '] ' .
                $t->getMessage() .
                \PHP_EOL . \PHP_EOL .
                'With queries:' . \PHP_EOL .
                $queries . \PHP_EOL .
                'Trace:' . \PHP_EOL .
                $traceMsg;

            throw new Exception($message, (int) $t->getCode(), $t);
        }

        throw $t;
    }
}
