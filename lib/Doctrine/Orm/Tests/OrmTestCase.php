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

namespace Rollerworks\Component\Search\Tests\Doctrine\Orm;

use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Query\Parameter;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Exception;
use Psr\SimpleCache\CacheInterface;
use Rollerworks\Component\Search\Doctrine\Dbal\Tests\DbalExtensions\Connection;
use Rollerworks\Component\Search\Doctrine\Dbal\Tests\TestUtil;
use Rollerworks\Component\Search\Doctrine\Orm\DoctrineOrmFactory;
use Rollerworks\Component\Search\Doctrine\Orm\DqlConditionGenerator;
use Rollerworks\Component\Search\Doctrine\Orm\Extension\Functions\AgeFunction;
use Rollerworks\Component\Search\Doctrine\Orm\Extension\Functions\CastFunction;
use Rollerworks\Component\Search\Doctrine\Orm\Extension\Functions\CastIntervalFunction;
use Rollerworks\Component\Search\Doctrine\Orm\Extension\Functions\CountChildrenFunction;
use Rollerworks\Component\Search\Doctrine\Orm\Extension\Functions\MoneyCastFunction;
use Rollerworks\Component\Search\Doctrine\Orm\FieldConfigBuilder;
use Rollerworks\Component\Search\Extension\Doctrine\Orm\Type\BirthdayTypeExtension;
use Rollerworks\Component\Search\Extension\Doctrine\Orm\Type\ChildCountType;
use Rollerworks\Component\Search\Extension\Doctrine\Orm\Type\DateTimeTypeExtension;
use Rollerworks\Component\Search\Extension\Doctrine\Orm\Type\FieldTypeExtension;
use Rollerworks\Component\Search\Extension\Doctrine\Orm\Type\MoneyTypeExtension;
use Rollerworks\Component\Search\SearchCondition;
use Rollerworks\Component\Search\Tests\Doctrine\Dbal\DbalTestCase;
use Rollerworks\Component\Search\Tests\Doctrine\Dbal\SchemaRecord;

abstract class OrmTestCase extends DbalTestCase
{
    protected ?EntityManager $em;
    protected Connection $conn;
    private static ?Connection $sharedConn;
    private static ?EntityManager $sharedEm;

    protected function setUp(): void
    {
        parent::setUp();

        if (! isset(self::$sharedConn)) {
            $config = ORMSetup::createAttributeMetadataConfiguration([__DIR__ . '/Fixtures/Entity'], true, null, null);

            if (method_exists($config, 'enableNativeLazyObjects') && \PHP_VERSION_ID >= 80400) {
                $config->enableNativeLazyObjects(true);
            }

            self::$sharedConn = TestUtil::getConnection();
            self::$sharedEm = new EntityManager(self::$sharedConn, $config);

            $emConfig = self::$sharedEm->getConfiguration();

            $emConfig->addCustomStringFunction('SEARCH_CONVERSION_CAST', CastFunction::class);
            $emConfig->addCustomNumericFunction('SEARCH_CONVERSION_AGE', AgeFunction::class);
            $emConfig->addCustomNumericFunction('SEARCH_COUNT_CHILDREN', CountChildrenFunction::class);
            $emConfig->addCustomNumericFunction('SEARCH_MONEY_AS_NUMERIC', MoneyCastFunction::class);
            $emConfig->addCustomNumericFunction('SEARCH_CAST_INTERVAL', CastIntervalFunction::class);

            $schemaTool = new SchemaTool(self::$sharedEm);
            $schemaTool->dropDatabase();
            $schemaTool->updateSchema(self::$sharedEm->getMetadataFactory()->getAllMetadata(), false);

            $recordSets = $this->getDbRecords();

            foreach ($recordSets as $set) {
                $set->executeRecords(self::$sharedConn);
            }
        }

        $this->conn = self::$sharedConn;
        $this->em = self::$sharedEm;
    }

    protected static function resetSharedConn(): void
    {
        if (self::$sharedConn) {
            self::$sharedConn->close();
            self::$sharedConn = null;
            self::$sharedEm = null;
        }
    }

    public static function tearDownAfterClass(): void
    {
        // There are errors recorded so don't reset the connection.
        if (\count(self::$sharedConn->queryLog->queries ?? []) > 0) {
            return;
        }

        // Ensure the connection is reset between class-runs
        self::resetSharedConn();
    }

    protected function getOrmFactory()
    {
        return new DoctrineOrmFactory($this->createMock(CacheInterface::class));
    }

    protected function getTypeExtensions(): array
    {
        return [
            new BirthdayTypeExtension(),
            new DateTimeTypeExtension(),
            new ChildCountType(),
            new FieldTypeExtension(),
            new MoneyTypeExtension(),
        ];
    }

    /**
     * @return SchemaRecord[]
     */
    protected function getDbRecords()
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
    protected function configureConditionGenerator(FieldConfigBuilder $conditionGenerator): void {}

    /**
     * @param (int|string)[] $ids
     *
     * @throws \Doctrine\DBAL\Exception
     */
    protected function assertRecordsAreFound(SearchCondition $condition, array $ids, string $input): void
    {
        $qb = $this->getQuery();

        $fieldsConfig = new FieldConfigBuilder($qb->getEntityManager(), $condition->getFieldSet());
        $this->configureConditionGenerator($fieldsConfig);

        $conditionGenerator = new DqlConditionGenerator($qb->getEntityManager(), $condition, $fieldsConfig);
        $whereClause = $conditionGenerator->getWhereClause();

        $primaryCondition = $condition->getPrimaryCondition();

        if ($primaryCondition !== null) {
            DqlConditionGenerator::applySortingTo($primaryCondition->getOrder(), $qb, $fieldsConfig);
        }

        DqlConditionGenerator::applySortingTo($condition->getOrder(), $qb, $fieldsConfig);

        // The return order is undefined with MySQL so make it explicit here.
        if (\count($qb->getDQLPart('orderBy')) === 0) {
            $qb->orderBy($qb->getRootAliases()[0] . '.id', 'ASC');
        }

        if ($whereClause !== '') {
            $qb->andWhere($whereClause);

            foreach ($conditionGenerator->getParameters() as $name => [$value, $type]) {
                $qb->setParameter($name, $value, $type);
            }
        }

        $paramsString = '';
        $platform = $this->conn->getDatabasePlatform();

        foreach ($conditionGenerator->getParameters() as $name => [$value, $type]) {
            $paramsString .= \sprintf("%s = '%s'\n", $name, $type === null ? (\is_scalar($value) ? (string) $value : get_debug_type($value)) : Type::getType($type)->convertToDatabaseValue($value, $platform));
        }

        $query = $qb->getQuery();
        $rows = $query->getArrayResult();
        $idRows = array_map(
            static fn ($value): mixed => $value['id'],
            $rows
        );

        self::assertSame(
            $ids,
            array_merge([], array_unique($idRows)),
            \sprintf(
                "With condition: `%s`\nFound these records instead: \n%s\nWith WHERE-clause: %s\nSQL: %s\nAnd params: %s",
                $input,
                print_r($rows, true),
                $whereClause,
                $query->getSQL(),
                $paramsString
            )
        );
    }

    /**
     * @param array<string, mixed>|null $parameters
     */
    protected static function assertQueryParametersEquals(?array $parameters, QueryBuilder $qb): void
    {
        if ($parameters === null) {
            return;
        }

        $actualParameters = $qb->getParameters()->toArray();

        if (\is_object(reset($actualParameters))) {
            /** @var Parameter $parameter */
            foreach ($actualParameters as $idx => $parameter) {
                unset($actualParameters[$idx]);

                $actualParameters[$parameter->getName()] = [$parameter->getValue(), $parameter->getType()];
            }
        }

        self::assertEquals($parameters, $actualParameters);
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

            throw new Exception($message, (int) $t->getCode(), $t instanceof \Exception ? $t : null);
        }

        throw $t;
    }
}
