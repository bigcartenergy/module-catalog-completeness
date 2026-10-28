<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Model\ResourceModel\RuleSet\Grid;

use BigCartEnergy\CatalogCompleteness\Model\ResourceModel\Rule as RuleResource;
use BigCartEnergy\CatalogCompleteness\Model\ResourceModel\RuleSet as RuleSetResource;
use Magento\Framework\Data\Collection\Db\FetchStrategyInterface as FetchStrategy;
use Magento\Framework\Data\Collection\EntityFactoryInterface as EntityFactory;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\View\Element\UiComponent\DataProvider\SearchResult;
use Psr\Log\LoggerInterface as Logger;

/**
 * The rule set listing: rule sets with their rule count.
 */
class Collection extends SearchResult
{
    public function __construct(
        EntityFactory $entityFactory,
        Logger $logger,
        FetchStrategy $fetchStrategy,
        EventManager $eventManager,
        $mainTable = RuleSetResource::TABLE,
        $resourceModel = RuleSetResource::class,
        $identifierName = 'ruleset_id',
        $connectionName = null
    ) {
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
        $rules = $this->getConnection()->select()
            ->from($this->getTable(RuleResource::TABLE), ['ruleset_id', 'n' => new \Zend_Db_Expr('COUNT(*)')])
            ->group('ruleset_id');
        $this->getSelect()->joinLeft(
            ['bce_rules' => $rules],
            'bce_rules.ruleset_id = main_table.ruleset_id',
            ['rules_count' => new \Zend_Db_Expr('COALESCE(bce_rules.n, 0)')]
        );
        $this->addFilterToMap('ruleset_id', 'main_table.ruleset_id');
        $this->addFilterToMap('name', 'main_table.name');
        $this->addFilterToMap('kind', 'main_table.kind');
        $this->addFilterToMap('channel_code', 'main_table.channel_code');
        $this->addFilterToMap('is_enabled', 'main_table.is_enabled');
        $this->addFilterToMap('updated_at', 'main_table.updated_at');
        $this->addFilterToMap('rules_count', 'bce_rules.n');
        return $this;
    }
}
