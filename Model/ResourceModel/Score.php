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
 * Reads and writes of the score table. Every write comes from the indexer; nothing here takes a score from a caller.
 */
class Score
{
    public const TABLE = 'bce_completeness_score';

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
     * Multi-row upsert keyed on (product_id, ruleset_id).
     *
     * @param array<int, array<string, mixed>> $rows
     */
    public function upsert(array $rows): void
    {
        if (!$rows) {
            return;
        }
        $this->getConnection()->insertOnDuplicate(
            $this->getTable(),
            $rows,
            ['is_primary', 'score', 'passed_weight', 'total_weight', 'missing', 'scored_at']
        );
    }

    /**
     * @param int[] $productIds
     * @param int[]|null $rulesetIds restrict to these rule sets; null means every rule set
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
     * Existing (product_id, ruleset_id) pairs with their row ids, for the given products and rule sets.
     *
     * @param int[] $productIds
     * @param int[] $rulesetIds
     * @return array<string, int> "productId:rulesetId" => score_id
     */
    public function fetchPairs(array $productIds, array $rulesetIds): array
    {
        $productIds = array_values(array_unique(array_map('intval', $productIds)));
        $rulesetIds = array_values(array_unique(array_map('intval', $rulesetIds)));
        if (!$productIds || !$rulesetIds) {
            return [];
        }
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getTable(), ['score_id', 'product_id', 'ruleset_id'])
            ->where('product_id IN (?)', $productIds)
            ->where('ruleset_id IN (?)', $rulesetIds);
        $pairs = [];
        foreach ($connection->fetchAll($select) as $row) {
            $pairs[$row['product_id'] . ':' . $row['ruleset_id']] = (int)$row['score_id'];
        }
        return $pairs;
    }

    /**
     * @param int[] $scoreIds
     */
    public function deleteByIds(array $scoreIds): void
    {
        $scoreIds = array_values(array_unique(array_map('intval', $scoreIds)));
        if ($scoreIds) {
            $this->getConnection()->delete($this->getTable(), ['score_id IN (?)' => $scoreIds]);
        }
    }

    /**
     * Rows of rule sets that no longer exist or are no longer enabled.
     *
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

    /**
     * Rows whose product no longer exists. The foreign key already cascades; this is the belt to that braces.
     */
    public function deleteOrphans(): void
    {
        $connection = $this->getConnection();
        $entityTable = $this->resource->getTableName('catalog_product_entity');
        $select = $connection->select()
            ->from(['s' => $this->getTable()], ['score_id'])
            ->joinLeft(['e' => $entityTable], 'e.entity_id = s.product_id', [])
            ->where('e.entity_id IS NULL');
        $ids = array_map('intval', $connection->fetchCol($select));
        $this->deleteByIds($ids);
    }

    /**
     * Stored rows of one product, with the rule set name and kind.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchForProduct(int $productId): array
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from(['s' => $this->getTable()])
            ->join(
                ['r' => $this->resource->getTableName(RuleSet::TABLE)],
                'r.ruleset_id = s.ruleset_id',
                ['ruleset_name' => 'r.name', 'kind' => 'r.kind', 'channel_code' => 'r.channel_code']
            )
            ->where('s.product_id = ?', $productId)
            ->order(['s.is_primary DESC', 'r.name ASC']);
        return $connection->fetchAll($select);
    }

    /**
     * Primary (attribute set rule set) rows of several products.
     *
     * @param int[] $productIds
     * @return array<int, array<string, mixed>> product id => row
     */
    public function fetchPrimary(array $productIds): array
    {
        $productIds = array_values(array_unique(array_map('intval', $productIds)));
        if (!$productIds) {
            return [];
        }
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getTable())
            ->where('product_id IN (?)', $productIds)
            ->where('is_primary = 1');
        $rows = [];
        foreach ($connection->fetchAll($select) as $row) {
            $rows[(int)$row['product_id']] = $row;
        }
        return $rows;
    }

    /**
     * The most recent scored_at across the table, or null when nothing was scored yet.
     */
    public function fetchLatestScoredAt(?int $rulesetId = null): ?string
    {
        $connection = $this->getConnection();
        $select = $connection->select()->from($this->getTable(), ['latest' => new \Zend_Db_Expr('MAX(scored_at)')]);
        if ($rulesetId !== null) {
            $select->where('ruleset_id = ?', $rulesetId);
        }
        $value = $connection->fetchOne($select);
        return $value === false || $value === null || $value === '' ? null : (string)$value;
    }

    public function countRows(): int
    {
        $connection = $this->getConnection();
        $select = $connection->select()->from($this->getTable(), ['n' => new \Zend_Db_Expr('COUNT(*)')]);
        return (int)$connection->fetchOne($select);
    }
}
