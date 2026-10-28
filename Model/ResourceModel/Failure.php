<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Model\ResourceModel;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;

/**
 * The failure table: one row per (product, rule set, failed rule). Written by the indexer only.
 */
class Failure
{
    public const TABLE = 'bce_completeness_failure';

    /**
     * @var ResourceConnection
     */
    private $resource;

    public function __construct(ResourceConnection $resource)
    {
        $this->resource = $resource;
    }

    public function getConnection(): AdapterInterface
    {
        return $this->resource->getConnection();
    }

    public function getTable(): string
    {
        return $this->resource->getTableName(self::TABLE);
    }

    /**
     * @param int[] $productIds
     * @param int[]|null $rulesetIds null means every rule set
     */
    public function deleteByProductIds(array $productIds, ?array $rulesetIds = null): void
    {
        $productIds = array_values(array_unique(array_map('intval', $productIds)));
        if (!$productIds) {
            return;
        }
        $where = ['product_id IN (?)' => $productIds];
        if ($rulesetIds !== null) {
            if (!$rulesetIds) {
                return;
            }
            $where['ruleset_id IN (?)'] = array_values(array_map('intval', $rulesetIds));
        }
        $this->getConnection()->delete($this->getTable(), $where);
    }

    /**
     * @param array<int, array{product_id: int, ruleset_id: int, rule_id: int}> $rows
     */
    public function insert(array $rows): void
    {
        if ($rows) {
            $this->getConnection()->insertOnDuplicate($this->getTable(), $rows, ['rule_id']);
        }
    }

    /**
     * @param int[] $enabledRulesetIds
     */
    public function deleteForOtherRuleSets(array $enabledRulesetIds): void
    {
        $enabledRulesetIds = array_values(array_unique(array_map('intval', $enabledRulesetIds)));
        $connection = $this->getConnection();
        if (!$enabledRulesetIds) {
            $connection->delete($this->getTable());
            return;
        }
        $connection->delete($this->getTable(), ['ruleset_id NOT IN (?)' => $enabledRulesetIds]);
    }
}
