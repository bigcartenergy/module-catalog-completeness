<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Model\Dashboard;

use BigCartEnergy\CatalogCompleteness\Api\Data\RuleSetInterface;
use BigCartEnergy\CatalogCompleteness\Model\ResourceModel\RuleSet\CollectionFactory as RuleSetCollectionFactory;
use BigCartEnergy\CatalogCompleteness\Model\RuleSet;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Select;

/**
 * Products with no rules assigned, so no score measures them. In the attribute set rules scope that is a product
 * whose attribute set no enabled attribute set rule set covers, or whose product type none of that rule set's
 * rules applies to; in a channel profile's scope, a product whose type none of the profile's rules applies to.
 * Read from the rule sets as they are now, not from stored scores, so it holds before any recalculation.
 */
class Coverage
{
    /**
     * @var RuleSetCollectionFactory
     */
    private $ruleSetCollectionFactory;

    /**
     * @var ResourceConnection
     */
    private $resource;

    /**
     * @var array<string, CoverageMap>
     */
    private $maps = [];

    public function __construct(RuleSetCollectionFactory $ruleSetCollectionFactory, ResourceConnection $resource)
    {
        $this->ruleSetCollectionFactory = $ruleSetCollectionFactory;
        $this->resource = $resource;
    }

    /**
     * @param int|null $rulesetId the dashboard scope: null for the attribute set rules, or one rule set
     */
    public function map(?int $rulesetId): CoverageMap
    {
        $key = $rulesetId === null ? '' : (string)$rulesetId;
        if (!isset($this->maps[$key])) {
            $this->maps[$key] = $rulesetId === null ? $this->attributeSetRulesMap() : $this->ruleSetMap($rulesetId);
        }
        return $this->maps[$key];
    }

    /**
     * Keep only the products of the scope with no rules assigned, in a select over the product entity ($e).
     */
    public function applyUncovered(Select $select, ?int $rulesetId, string $e = FilterApplier::ENTITY_ALIAS): void
    {
        $connection = $this->resource->getConnection();
        $condition = $this->map($rulesetId)->condition($e, function (string $text, $value) use ($connection): string {
            return (string)$connection->quoteInto($text, $value);
        });
        if ($condition !== '') {
            $select->where($condition);
        }
    }

    public function isUncovered(int $attributeSetId, string $typeId, ?int $rulesetId = null): bool
    {
        return $this->map($rulesetId)->isUncovered($attributeSetId, $typeId);
    }

    private function attributeSetRulesMap(): CoverageMap
    {
        $collection = $this->ruleSetCollectionFactory->create();
        $collection->addEnabledFilter();
        $collection->addKindFilter(RuleSetInterface::KIND_ATTRIBUTE_SET);
        $typesBySet = [];
        /** @var RuleSet $ruleSet */
        foreach ($collection->getItems() as $ruleSet) {
            $types = CoverageMap::typesOf($ruleSet->getRules());
            foreach ($ruleSet->getAttributeSetIds() as $setId) {
                $typesBySet[(int)$setId] = $types;
            }
        }
        return CoverageMap::perAttributeSet($typesBySet);
    }

    private function ruleSetMap(int $rulesetId): CoverageMap
    {
        $collection = $this->ruleSetCollectionFactory->create();
        $collection->addFieldToFilter('ruleset_id', $rulesetId);
        $ruleSet = null;
        foreach ($collection->getItems() as $item) {
            $ruleSet = $item;
        }
        if (!$ruleSet instanceof RuleSet) {
            // An unknown rule set reaches no product.
            return CoverageMap::single([], []);
        }
        $types = $ruleSet->getIsEnabled() ? CoverageMap::typesOf($ruleSet->getRules()) : [];
        $universe = $ruleSet->getKind() === RuleSetInterface::KIND_CHANNEL ? null : $ruleSet->getAttributeSetIds();
        return CoverageMap::single($universe, $types);
    }
}
