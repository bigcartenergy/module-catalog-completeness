<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Model\ResourceModel\RuleSet;

use BigCartEnergy\CatalogCompleteness\Api\Data\RuleSetInterface;
use BigCartEnergy\CatalogCompleteness\Model\ResourceModel\RuleSet as RuleSetResource;
use BigCartEnergy\CatalogCompleteness\Model\RuleSet;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'ruleset_id';

    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init(RuleSet::class, RuleSetResource::class);
    }

    /**
     * Load the attribute sets and rules of every item in two queries.
     *
     * @inheritDoc
     */
    protected function _afterLoad()
    {
        parent::_afterLoad();
        $ids = [];
        foreach ($this->getItems() as $item) {
            $ids[] = (int)$item->getId();
        }
        if (!$ids) {
            return $this;
        }
        /** @var RuleSetResource $resource */
        $resource = $this->getResource();
        $sets = $resource->fetchAttributeSetIds($ids);
        $rules = $resource->fetchRules($ids);
        foreach ($this->getItems() as $item) {
            $id = (int)$item->getId();
            $item->setData(RuleSetInterface::ATTRIBUTE_SET_IDS, $sets[$id] ?? []);
            $item->setData(RuleSetInterface::RULES, $rules[$id] ?? []);
            $item->setDataChanges(false);
        }
        return $this;
    }

    public function addEnabledFilter(): self
    {
        $this->addFieldToFilter('is_enabled', 1);
        return $this;
    }

    public function addKindFilter(string $kind): self
    {
        $this->addFieldToFilter('kind', $kind);
        return $this;
    }
}
