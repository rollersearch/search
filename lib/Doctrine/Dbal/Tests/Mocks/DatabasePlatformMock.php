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

namespace Rollerworks\Component\Search\Tests\Doctrine\Dbal\Mocks;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\DateIntervalUnit;
use Doctrine\DBAL\Platforms\Keywords\KeywordList;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\TransactionIsolationLevel;

class DatabasePlatformMock extends AbstractPlatform
{
    private $_sequenceNextValSql = '';
    private $_prefersIdentityColumns = true;
    private $_prefersSequences = false;

    /**
     * @override
     */
    public function prefersIdentityColumns(): bool
    {
        return $this->_prefersIdentityColumns;
    }

    /**
     * @override
     */
    public function prefersSequences(): bool
    {
        return $this->_prefersSequences;
    }

    /**
     * @override
     */
    public function getSequenceNextValSQL($sequence): string
    {
        return $this->_sequenceNextValSql;
    }

    /**
     * @override
     */
    public function getBooleanTypeDeclarationSQL(array $column): string
    {
        throw new \LogicException(\sprintf('Method %s not implemented', __METHOD__));
    }

    /**
     * @override
     */
    public function getIntegerTypeDeclarationSQL(array $column): string
    {
        throw new \LogicException(\sprintf('Method %s not implemented', __METHOD__));
    }

    /**
     * @override
     */
    public function getBigIntTypeDeclarationSQL(array $column): string
    {
        throw new \LogicException(\sprintf('Method %s not implemented', __METHOD__));
    }

    /**
     * @override
     */
    public function getSmallIntTypeDeclarationSQL(array $column): string
    {
        throw new \LogicException(\sprintf('Method %s not implemented', __METHOD__));
    }

    /**
     * @override
     */
    protected function _getCommonIntegerTypeDeclarationSQL(array $column): string
    {
        throw new \LogicException(\sprintf('Method %s not implemented', __METHOD__));
    }

    /**
     * @override
     */
    public function getVarcharTypeDeclarationSQL(array $column): string
    {
        throw new \LogicException(\sprintf('Method %s not implemented', __METHOD__));
    }

    /**
     * @override
     */
    public function getClobTypeDeclarationSQL(array $column): string
    {
        throw new \LogicException(\sprintf('Method %s not implemented', __METHOD__));
    }

    /**
     * @override
     */
    public function getCurrentDatabaseExpression(): string
    {
        throw new \LogicException(\sprintf('Method %s not implemented', __METHOD__));
    }

    protected function getVarcharTypeDeclarationSQLSnippet($length): string
    {
        throw new \LogicException(\sprintf('Method %s not implemented', __METHOD__));
    }

    public function getBlobTypeDeclarationSQL(array $column): string
    {
        throw new \LogicException(\sprintf('Method %s not implemented', __METHOD__));
    }

    public function getLocateExpression(string $string, string $substring, ?string $start = null): string
    {
        throw new \LogicException(\sprintf('Method %s not implemented', __METHOD__));
    }

    public function getDateDiffExpression(string $date1, string $date2): string
    {
        throw new \LogicException(\sprintf('Method %s not implemented', __METHOD__));
    }

    protected function getDateArithmeticIntervalExpression(string $date, string $operator, string $interval, DateIntervalUnit $unit): string
    {
        throw new \LogicException(\sprintf('Method %s not implemented', __METHOD__));
    }

    public function getAlterTableSQL(TableDiff $diff): array
    {
        throw new \LogicException(\sprintf('Method %s not implemented', __METHOD__));
    }

    public function getListViewsSQL(string $database): string
    {
        throw new \LogicException(\sprintf('Method %s not implemented', __METHOD__));
    }

    public function getSetTransactionIsolationSQL(TransactionIsolationLevel $level): string
    {
        throw new \LogicException(\sprintf('Method %s not implemented', __METHOD__));
    }

    public function getDateTimeTypeDeclarationSQL(array $column): string
    {
        throw new \LogicException(\sprintf('Method %s not implemented', __METHOD__));
    }

    public function getDateTypeDeclarationSQL(array $column): string
    {
        throw new \LogicException(\sprintf('Method %s not implemented', __METHOD__));
    }

    public function getTimeTypeDeclarationSQL(array $column): string
    {
        throw new \LogicException(\sprintf('Method %s not implemented', __METHOD__));
    }

    protected function createReservedKeywordsList(): KeywordList
    {
        throw new \LogicException(\sprintf('Method %s not implemented', __METHOD__));
    }

    public function createSchemaManager(Connection $connection): AbstractSchemaManager
    {
        throw new \LogicException(\sprintf('Method %s not implemented', __METHOD__));
    }

    /* MOCK API */

    public function setPrefersIdentityColumns($bool): void
    {
        $this->_prefersIdentityColumns = $bool;
    }

    public function setPrefersSequences($bool): void
    {
        $this->_prefersSequences = $bool;
    }

    public function setSequenceNextValSql($sql): void
    {
        $this->_sequenceNextValSql = $sql;
    }

    public function getName(): string
    {
        return 'mock';
    }

    protected function initializeDoctrineTypeMappings(): void {}
}
