<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Test\Unit\Model\ResourceModel\WorkList;

use BigCartEnergy\CatalogCompleteness\Model\ResourceModel\WorkList\UncoveredCollection;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use PHPUnit\Framework\TestCase;

/**
 * The guard on the "No rules assigned" list. Its score columns are empty there and are no column of any joined
 * table, and Magento quotes a mapped filter field as a column name, so a filter on one of them turns into a
 * condition that matches nothing and never names the column; a sort by one keeps the product id order. A
 * filter on a product column still reaches the SQL.
 */
class UncoveredCollectionTest extends TestCase
{
    private const EMPTY_FIELDS = ['score', 'scored_at', 'score_id', 'ruleset_id', 'missing'];

    public function testAFilterOnAnEmptyColumnMatchesNothing(): void
    {
        // No connection at all: the guard answers before Magento would quote the field.
        $collection = $this->collection(null);
        $conditions = [
            ['gteq' => '10'],
            ['lteq' => '90'],
            ['from' => '2026-01-01 00:00:00', 'to' => '2026-12-31 23:59:59'],
            ['eq' => '5'],
            ['in' => ['1', '2']],
            ['null' => true],
            ['notnull' => true],
            '7',
            null,
        ];
        foreach (self::EMPTY_FIELDS as $field) {
            foreach ($conditions as $condition) {
                self::assertSame(
                    '1 = 0',
                    $this->translate($collection, $field, $condition),
                    $field . ' ' . json_encode($condition)
                );
            }
        }
    }

    public function testAFilterOnAProductColumnStillReachesTheSql(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects(self::once())->method('quoteIdentifier')->with('e.sku')->willReturn('`e`.`sku`');
        $connection->expects(self::once())->method('prepareSqlCondition')
            ->with('`e`.`sku`', ['like' => '%M8%'])
            ->willReturn("`e`.`sku` LIKE '%M8%'");
        $collection = $this->collection($connection);
        $collection->addFilterToMap('sku', 'e.sku');
        self::assertSame("`e`.`sku` LIKE '%M8%'", $this->translate($collection, 'sku', ['like' => '%M8%']));
    }

    public function testASortByAnEmptyColumnIsIgnored(): void
    {
        $collection = $this->collection(null);
        $collection->addFilterToMap('sku', 'e.sku');
        foreach (self::EMPTY_FIELDS as $field) {
            $collection->setOrder($field, 'ASC');
            $collection->addOrder($field, 'DESC');
            $collection->unshiftOrder($field, 'ASC');
        }
        self::assertSame([], $this->orders($collection), 'no ORDER BY names an empty column');
        $collection->setOrder('sku', 'ASC');
        self::assertSame(['e.sku' => 'ASC'], $this->orders($collection), 'a product column still sorts');
    }

    /**
     * addFieldToFilter() itself, for one field and for several in an OR, on Magento's Select (needs Zend_Db, so
     * this one runs inside a Magento installation).
     */
    public function testAddFieldToFilterPutsTheGuardIntoTheWhereClause(): void
    {
        if (!class_exists(\Zend_Db_Select::class)) {
            self::markTestSkipped('Needs Zend_Db: run inside a Magento installation.');
        }
        $where = [];
        $select = $this->createStub(Select::class);
        $select->method('where')->willReturnCallback(function ($condition) use (&$where, $select) {
            $where[] = (string)$condition;
            return $select;
        });
        $connection = $this->createStub(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->method('quoteIdentifier')->willReturnCallback(function ($identifier) {
            return '`' . str_replace('.', '`.`', (string)$identifier) . '`';
        });
        $connection->method('prepareSqlCondition')->willReturnCallback(function ($field, $condition) {
            return $field . " LIKE '" . $condition['like'] . "'";
        });
        $collection = $this->collection($connection);
        $collection->addFilterToMap('sku', 'e.sku');
        $collection->addFieldToFilter('score', ['gteq' => '10']);
        $collection->addFieldToFilter('scored_at', ['lteq' => '2026-12-31 23:59:59']);
        $collection->addFieldToFilter(['score', 'sku'], [['gteq' => '10'], ['like' => '%M8%']]);
        self::assertSame(['1 = 0', '1 = 0', "(1 = 0) OR (`e`.`sku` LIKE '%M8%')"], $where);
    }

    /**
     * The list without its constructor (which needs the resource connection and the catalog lookup), with the
     * given connection or none.
     *
     * @param AdapterInterface|null $connection
     */
    private function collection($connection): UncoveredCollection
    {
        if (!class_exists(AbstractDb::class)) {
            self::markTestSkipped('Magento framework not available (set BCE_MAGENTO_ROOT).');
        }
        /** @var UncoveredCollection $collection */
        $collection = (new \ReflectionClass(UncoveredCollection::class))->newInstanceWithoutConstructor();
        if ($connection !== null) {
            $collection->setConnection($connection);
        }
        return $collection;
    }

    /**
     * @param mixed $condition
     */
    private function translate(UncoveredCollection $collection, string $field, $condition): string
    {
        $method = new \ReflectionMethod(UncoveredCollection::class, '_translateCondition');
        if (PHP_VERSION_ID < 80100) {
            $method->setAccessible(true);
        }
        return (string)$method->invoke($collection, $field, $condition);
    }

    /**
     * @return array<string, string>
     */
    private function orders(UncoveredCollection $collection): array
    {
        $property = new \ReflectionProperty(UncoveredCollection::class, '_orders');
        if (PHP_VERSION_ID < 80100) {
            $property->setAccessible(true);
        }
        return $property->getValue($collection);
    }
}
