<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Model\ResourceModel\WorkList;

use BigCartEnergy\CatalogCompleteness\Model\Catalog\Lookup;
use BigCartEnergy\CatalogCompleteness\Model\Dashboard\Coverage;
use BigCartEnergy\CatalogCompleteness\Model\Dashboard\Filter;
use BigCartEnergy\CatalogCompleteness\Model\Dashboard\FilterApplier;
use Magento\Framework\Data\Collection\Db\FetchStrategyInterface as FetchStrategy;
use Magento\Framework\Data\Collection\EntityFactoryInterface as EntityFactory;
use Magento\Framework\DB\Select;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Psr\Log\LoggerInterface as Logger;

/**
 * The work list's "No rules assigned" view: the products of the scope that no rule covers, one row each and
 * without a score. It reads the product entity (as main_table, and again as e for the filters it shares with
 * the scored list), so a product with no score row at all is listed. The score columns read as empty.
 */
class UncoveredCollection extends Collection
{
    private const NO_VALUE = 'NULL';

    /**
     * The columns that are empty in this view: a filter on one of them matches nothing, a sort by it is ignored.
     */
    private const EMPTY_FIELDS = ['score_id', 'ruleset_id', 'score', 'missing', 'scored_at'];

    /**
     * @var FilterApplier
     */
    private $productFilters;

    /**
     * @var Coverage
     */
    private $coverage;

    public function __construct(
        EntityFactory $entityFactory,
        Logger $logger,
        FetchStrategy $fetchStrategy,
        EventManager $eventManager,
        Lookup $lookup,
        FilterApplier $applier,
        Coverage $coverage,
        $mainTable = 'catalog_product_entity',
        $resourceModel = \BigCartEnergy\CatalogCompleteness\Model\ResourceModel\Score\Row::class,
        $identifierName = 'product_id',
        $connectionName = null
    ) {
        $this->productFilters = $applier;
        $this->coverage = $coverage;
        parent::__construct(
            $entityFactory,
            $logger,
            $fetchStrategy,
            $eventManager,
            $lookup,
            $applier,
            $mainTable,
            $resourceModel,
            $identifierName,
            $connectionName
        );
    }

    /**
     * @inheritDoc
     */
    protected function _initSelect()
    {
        $e = FilterApplier::ENTITY_ALIAS;
        $this->getSelect()
            ->from(['main_table' => $this->getMainTable()], [
                'product_id' => 'main_table.entity_id',
                'score_id' => new \Zend_Db_Expr(self::NO_VALUE),
                'ruleset_id' => new \Zend_Db_Expr(self::NO_VALUE),
                'score' => new \Zend_Db_Expr(self::NO_VALUE),
                'missing' => new \Zend_Db_Expr(self::NO_VALUE),
                'scored_at' => new \Zend_Db_Expr(self::NO_VALUE),
                'coverage' => new \Zend_Db_Expr($this->getConnection()->quote(Filter::COVERAGE_NONE)),
            ])
            ->join(
                [$e => $this->getMainTable()],
                $e . '.entity_id = main_table.entity_id',
                [
                    'entity_id' => $e . '.entity_id',
                    'sku' => $e . '.sku',
                    'type_id' => $e . '.type_id',
                    'attribute_set_id' => $e . '.attribute_set_id',
                ]
            )
            ->joinLeft(
                ['bce_set' => $this->getTable('eav_attribute_set')],
                'bce_set.attribute_set_id = ' . $e . '.attribute_set_id',
                ['attribute_set_name' => 'bce_set.attribute_set_name']
            );
        $this->joinVarchar('name', 'bce_name');
        $this->joinVarchar('thumbnail', 'bce_thumb');

        $this->addFilterToMap('product_id', 'main_table.entity_id');
        $this->addFilterToMap('entity_id', $e . '.entity_id');
        $this->addFilterToMap('score_id', self::NO_VALUE);
        $this->addFilterToMap('ruleset_id', self::NO_VALUE);
        $this->addFilterToMap('score', self::NO_VALUE);
        $this->addFilterToMap('scored_at', self::NO_VALUE);
        $this->addFilterToMap('sku', $e . '.sku');
        $this->addFilterToMap('type_id', $e . '.type_id');
        $this->addFilterToMap('attribute_set_id', $e . '.attribute_set_id');
        $this->addFilterToMap('attribute_set_name', 'bce_set.attribute_set_name');
        $this->addFilterToMap('name', 'bce_name.value');
        return $this;
    }

    /**
     * The filters on the products themselves and the scope's coverage; the score filters do not apply.
     */
    protected function applyFilterToSelect(Filter $filter, ?int $belowAverage): void
    {
        $this->productFilters->applyProductFilters($this->getSelect(), $filter, FilterApplier::ENTITY_ALIAS);
        $this->coverage->applyUncovered($this->getSelect(), $filter->getRulesetId(), FilterApplier::ENTITY_ALIAS);
    }

    /**
     * Product ids of every row, for the select-all mass actions (the rows carry no score row id).
     *
     * @inheritDoc
     */
    public function getAllIds()
    {
        $idsSelect = clone $this->getSelect();
        $idsSelect->reset(Select::ORDER);
        $idsSelect->reset(Select::LIMIT_COUNT);
        $idsSelect->reset(Select::LIMIT_OFFSET);
        $idsSelect->reset(Select::COLUMNS);
        $idsSelect->columns('entity_id', 'main_table');
        return $this->getConnection()->fetchCol($idsSelect, $this->_bindParams);
    }

    /**
     * A filter on a column that is empty in this view matches nothing. The condition never names the column:
     * Magento quotes a mapped filter field as a column name, and none of these is a column of a joined table.
     *
     * @inheritDoc
     */
    protected function _translateCondition($field, $condition)
    {
        if (is_string($field) && in_array($field, self::EMPTY_FIELDS, true)) {
            return '1 = 0';
        }
        return parent::_translateCondition($field, $condition);
    }

    /**
     * A sort by a column that is empty in this view keeps the product id order.
     *
     * @inheritDoc
     */
    public function setOrder($field, $direction = self::SORT_ORDER_DESC)
    {
        if (in_array((string)$field, self::EMPTY_FIELDS, true)) {
            return $this;
        }
        return parent::setOrder($field, $direction);
    }

    /**
     * @inheritDoc
     */
    public function addOrder($field, $direction = self::SORT_ORDER_DESC)
    {
        if (in_array((string)$field, self::EMPTY_FIELDS, true)) {
            return $this;
        }
        return parent::addOrder($field, $direction);
    }

    /**
     * @inheritDoc
     */
    public function unshiftOrder($field, $direction = self::SORT_ORDER_DESC)
    {
        if (in_array((string)$field, self::EMPTY_FIELDS, true)) {
            return $this;
        }
        return parent::unshiftOrder($field, $direction);
    }

    /**
     * Every sort ends on the product id, so pages stay stable.
     *
     * @inheritDoc
     */
    protected function _renderOrders()
    {
        if (!$this->_isOrdersRendered) {
            parent::_renderOrders();
            $this->getSelect()->order('main_table.entity_id ASC');
        }
        return $this;
    }
}
