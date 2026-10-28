<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Model\ResourceModel\WorkList;

use BigCartEnergy\CatalogCompleteness\Model\Catalog\Lookup;
use BigCartEnergy\CatalogCompleteness\Model\Dashboard\Filter;
use BigCartEnergy\CatalogCompleteness\Model\Dashboard\FilterApplier;
use BigCartEnergy\CatalogCompleteness\Model\ResourceModel\Score as ScoreResource;
use BigCartEnergy\CatalogCompleteness\Model\ResourceModel\Score\Row as ScoreRow;
use Magento\Framework\Data\Collection\Db\FetchStrategyInterface as FetchStrategy;
use Magento\Framework\Data\Collection\EntityFactoryInterface as EntityFactory;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\View\Element\UiComponent\DataProvider\SearchResult;
use Psr\Log\LoggerInterface as Logger;

/**
 * The work list: score rows joined to the product entity, attribute set name, name and thumbnail.
 * One row per product within a scope (the primary rows, or one rule set).
 */
class Collection extends SearchResult
{
    /**
     * @var Lookup
     */
    private $lookup;

    /**
     * @var FilterApplier
     */
    private $applier;

    /**
     * @var bool
     */
    private $dashboardFilterApplied = false;

    /**
     * @var int|null
     */
    private $belowAverage;

    public function __construct(
        EntityFactory $entityFactory,
        Logger $logger,
        FetchStrategy $fetchStrategy,
        EventManager $eventManager,
        Lookup $lookup,
        FilterApplier $applier,
        $mainTable = ScoreResource::TABLE,
        $resourceModel = ScoreRow::class,
        $identifierName = 'product_id',
        $connectionName = null
    ) {
        $this->lookup = $lookup;
        $this->applier = $applier;
        parent::__construct(
            $entityFactory,
            $logger,
            $fetchStrategy,
            $eventManager,
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
        parent::_initSelect();
        $select = $this->getSelect();
        $select->reset(\Magento\Framework\DB\Select::COLUMNS);
        $select->columns([
            'score_id' => 'main_table.score_id',
            'product_id' => 'main_table.product_id',
            'ruleset_id' => 'main_table.ruleset_id',
            'score' => 'main_table.score',
            'missing' => 'main_table.missing',
            'scored_at' => 'main_table.scored_at',
        ]);
        $select->join(
            [FilterApplier::ENTITY_ALIAS => $this->getTable('catalog_product_entity')],
            FilterApplier::ENTITY_ALIAS . '.entity_id = main_table.product_id',
            [
                'entity_id' => FilterApplier::ENTITY_ALIAS . '.entity_id',
                'sku' => FilterApplier::ENTITY_ALIAS . '.sku',
                'type_id' => FilterApplier::ENTITY_ALIAS . '.type_id',
                'attribute_set_id' => FilterApplier::ENTITY_ALIAS . '.attribute_set_id',
            ]
        );
        $select->joinLeft(
            ['bce_set' => $this->getTable('eav_attribute_set')],
            'bce_set.attribute_set_id = ' . FilterApplier::ENTITY_ALIAS . '.attribute_set_id',
            ['attribute_set_name' => 'bce_set.attribute_set_name']
        );
        $this->joinVarchar('name', 'bce_name');
        $this->joinVarchar('thumbnail', 'bce_thumb');

        $this->addFilterToMap('score_id', 'main_table.score_id');
        $this->addFilterToMap('product_id', 'main_table.product_id');
        $this->addFilterToMap('entity_id', FilterApplier::ENTITY_ALIAS . '.entity_id');
        $this->addFilterToMap('ruleset_id', 'main_table.ruleset_id');
        $this->addFilterToMap('score', 'main_table.score');
        $this->addFilterToMap('scored_at', 'main_table.scored_at');
        $this->addFilterToMap('sku', FilterApplier::ENTITY_ALIAS . '.sku');
        $this->addFilterToMap('type_id', FilterApplier::ENTITY_ALIAS . '.type_id');
        $this->addFilterToMap('attribute_set_id', FilterApplier::ENTITY_ALIAS . '.attribute_set_id');
        $this->addFilterToMap('attribute_set_name', 'bce_set.attribute_set_name');
        $this->addFilterToMap('name', 'bce_name.value');
        return $this;
    }

    /**
     * Apply the dashboard scope and filters once.
     */
    public function applyDashboardFilter(Filter $filter, ?int $belowAverage): self
    {
        if ($this->dashboardFilterApplied) {
            return $this;
        }
        $this->dashboardFilterApplied = true;
        $this->applyFilterToSelect($filter, $belowAverage);
        return $this;
    }

    /**
     * The scope and the filters on the score rows; the list of products with no rules assigned replaces it.
     */
    protected function applyFilterToSelect(Filter $filter, ?int $belowAverage): void
    {
        $this->applier->apply($this->getSelect(), $filter, 'main_table', FilterApplier::ENTITY_ALIAS);
        if ($filter->getTile() === Filter::TILE_BELOW_AVERAGE) {
            $this->applier->applyBelowAverage($this->getSelect(), $belowAverage ?? 0, 'main_table');
        }
    }

    /**
     * Keyword search over SKU and name; wildcards in the keyword are literal.
     */
    public function addKeywordFilter(string $keyword): self
    {
        $keyword = trim($keyword);
        if ($keyword === '') {
            return $this;
        }
        $like = '%' . addcslashes($keyword, '%_\\') . '%';
        $this->getSelect()->where(
            '(' . FilterApplier::ENTITY_ALIAS . '.sku LIKE ? OR bce_name.value LIKE ?)',
            $like
        );
        // Zend_Db_Select replaces every "?" with the same bound value when one value is given.
        return $this;
    }

    protected function joinVarchar(string $attributeCode, string $alias): void
    {
        $descriptor = $this->lookup->getDescriptor($attributeCode);
        $attributeId = $descriptor !== null && $descriptor->getId() !== null ? (int)$descriptor->getId() : 0;
        $this->getSelect()->joinLeft(
            [$alias => $this->getTable('catalog_product_entity_varchar')],
            sprintf(
                '%1$s.entity_id = %2$s.entity_id AND %1$s.attribute_id = %3$d AND %1$s.store_id = 0',
                $alias,
                FilterApplier::ENTITY_ALIAS,
                $attributeId
            ),
            [$attributeCode => $alias . '.value']
        );
    }
}
