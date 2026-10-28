<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Model\Dashboard;

use BigCartEnergy\CatalogCompleteness\Api\Data\RuleSetInterface;
use BigCartEnergy\CatalogCompleteness\Model\Band\Resolver as BandResolver;
use BigCartEnergy\CatalogCompleteness\Model\Catalog\Lookup;
use BigCartEnergy\CatalogCompleteness\Model\Config;
use BigCartEnergy\CatalogCompleteness\Model\ResourceModel\Failure as FailureResource;
use BigCartEnergy\CatalogCompleteness\Model\ResourceModel\Rule as RuleResource;
use BigCartEnergy\CatalogCompleteness\Model\ResourceModel\RuleSet as RuleSetResource;
use BigCartEnergy\CatalogCompleteness\Model\ResourceModel\Score as ScoreResource;
use BigCartEnergy\CatalogCompleteness\Model\Score\RuleLabels;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Select;

/**
 * The dashboard's aggregates: summary tiles, distribution, breakdown and most-missing rules, all from the
 * score and failure tables under the current filter. Bands come from the thresholds at read time.
 */
class Statistics
{
    public const BY_ATTRIBUTE_SET = 'set';
    public const BY_CATEGORY = 'category';
    public const BY_BRAND = 'brand';
    public const BY_TYPE = 'type';
    public const GROUPINGS = [self::BY_ATTRIBUTE_SET, self::BY_CATEGORY, self::BY_BRAND, self::BY_TYPE];

    /**
     * @var ResourceConnection
     */
    private $resource;

    /**
     * @var FilterApplier
     */
    private $applier;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var BandResolver
     */
    private $bands;

    /**
     * @var Lookup
     */
    private $lookup;

    /**
     * @var RuleLabels
     */
    private $ruleLabels;

    /**
     * @var CategoryCollectionFactory
     */
    private $categoryCollectionFactory;

    /**
     * @var array<string, int|null>
     */
    private $averageCache = [];

    /**
     * @var Coverage
     */
    private $coverage;

    public function __construct(
        ResourceConnection $resource,
        FilterApplier $applier,
        Config $config,
        BandResolver $bands,
        Lookup $lookup,
        RuleLabels $ruleLabels,
        CategoryCollectionFactory $categoryCollectionFactory,
        Coverage $coverage
    ) {
        $this->resource = $resource;
        $this->applier = $applier;
        $this->config = $config;
        $this->bands = $bands;
        $this->lookup = $lookup;
        $this->ruleLabels = $ruleLabels;
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->coverage = $coverage;
    }

    /**
     * Base select over scores joined to products with the filter applied, including the below-average tile.
     */
    public function baseSelect(Filter $filter, bool $withTile = true): Select
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from([FilterApplier::SCORE_ALIAS => $this->resource->getTableName(ScoreResource::TABLE)], [])
            ->join(
                [FilterApplier::ENTITY_ALIAS => $this->resource->getTableName('catalog_product_entity')],
                FilterApplier::ENTITY_ALIAS . '.entity_id = ' . FilterApplier::SCORE_ALIAS . '.product_id',
                []
            );
        $this->applier->apply($select, $withTile ? $filter : $filter->withoutTile());
        if ($withTile && $filter->getTile() === Filter::TILE_BELOW_AVERAGE) {
            $average = $this->getAverage($filter->withoutTile());
            $this->applier->applyBelowAverage($select, $average ?? 0);
        }
        return $select;
    }

    /**
     * Average score of the filtered set (tile shortcut excluded), null when nothing is scored.
     */
    public function getAverage(Filter $filter): ?int
    {
        $key = implode('|', $filter->withoutTile()->toParams());
        if (!array_key_exists($key, $this->averageCache)) {
            $select = $this->baseSelect($filter->withoutTile(), false)
                ->columns(['avg' => new \Zend_Db_Expr('AVG(' . FilterApplier::SCORE_ALIAS . '.score)')]);
            $value = $this->resource->getConnection()->fetchOne($select);
            $this->averageCache[$key] = $value === null || $value === false || $value === '' ? null : (int)round((float)$value);
        }
        return $this->averageCache[$key];
    }

    /**
     * Count, average and per-band counts of the filtered set.
     *
     * @return array{count: int, average: int|null, bands: array<string, int>, disabled: int}
     */
    public function getSummary(Filter $filter): array
    {
        $s = FilterApplier::SCORE_ALIAS;
        $thresholds = $this->config->getThresholds();
        $select = $this->baseSelect($filter)->columns([
            'n' => new \Zend_Db_Expr('COUNT(*)'),
            'avg' => new \Zend_Db_Expr('AVG(' . $s . '.score)'),
            'n_complete' => new \Zend_Db_Expr('SUM(CASE WHEN ' . $s . '.score >= 100 THEN 1 ELSE 0 END)'),
            'n_good' => new \Zend_Db_Expr(sprintf(
                'SUM(CASE WHEN %1$s.score >= %2$d AND %1$s.score < 100 THEN 1 ELSE 0 END)',
                $s,
                $thresholds->getGoodFrom()
            )),
            'n_needs_work' => new \Zend_Db_Expr(sprintf(
                'SUM(CASE WHEN %1$s.score >= %2$d AND %1$s.score < %3$d THEN 1 ELSE 0 END)',
                $s,
                $thresholds->getNeedsWorkFrom(),
                $thresholds->getGoodFrom()
            )),
            'n_poor' => new \Zend_Db_Expr(sprintf(
                'SUM(CASE WHEN %1$s.score < %2$d THEN 1 ELSE 0 END)',
                $s,
                $thresholds->getNeedsWorkFrom()
            )),
        ]);
        $row = $this->resource->getConnection()->fetchRow($select) ?: [];
        $count = (int)($row['n'] ?? 0);
        return [
            'count' => $count,
            'average' => $count > 0 && isset($row['avg']) && $row['avg'] !== null ? (int)round((float)$row['avg']) : null,
            'bands' => [
                BandResolver::COMPLETE => (int)($row['n_complete'] ?? 0),
                BandResolver::GOOD => (int)($row['n_good'] ?? 0),
                BandResolver::NEEDS_WORK => (int)($row['n_needs_work'] ?? 0),
                BandResolver::POOR => (int)($row['n_poor'] ?? 0),
            ],
            'disabled' => $filter->isEnabledOnly() ? $this->countDisabled($filter) : 0,
        ];
    }

    /**
     * Rows of the breakdown table for one grouping, sorted by average ascending.
     *
     * @return array<int, array{key: string, name: string, products: int, average: int, complete_pct: float, band: string|null}>
     */
    public function getBreakdown(Filter $filter, string $by): array
    {
        $s = FilterApplier::SCORE_ALIAS;
        $e = FilterApplier::ENTITY_ALIAS;
        $select = $this->baseSelect($filter);
        $connection = $this->resource->getConnection();
        switch ($by) {
            case self::BY_CATEGORY:
                $select->join(
                    ['bce_grp_ccp' => $this->resource->getTableName('catalog_category_product')],
                    'bce_grp_ccp.product_id = ' . $e . '.entity_id',
                    []
                );
                $groupColumn = 'bce_grp_ccp.category_id';
                break;
            case self::BY_BRAND:
                $this->applier->joinBrand($select, $e);
                $groupColumn = $this->applier->getBrandAlias() . '.value';
                break;
            case self::BY_TYPE:
                $groupColumn = $e . '.type_id';
                break;
            default:
                $by = self::BY_ATTRIBUTE_SET;
                $groupColumn = $e . '.attribute_set_id';
        }
        $select->columns([
            'grp' => new \Zend_Db_Expr($groupColumn),
            'n' => new \Zend_Db_Expr('COUNT(*)'),
            'avg' => new \Zend_Db_Expr('AVG(' . $s . '.score)'),
            'n_complete' => new \Zend_Db_Expr('SUM(CASE WHEN ' . $s . '.score >= 100 THEN 1 ELSE 0 END)'),
        ])->group($groupColumn)->order('avg ASC');
        $rows = $connection->fetchAll($select);
        $names = $this->groupNames($by, array_map(function (array $row) {
            return $row['grp'];
        }, $rows));
        $result = [];
        foreach ($rows as $row) {
            $key = $row['grp'] === null ? '' : (string)$row['grp'];
            $count = (int)$row['n'];
            $average = (int)round((float)$row['avg']);
            $result[] = [
                'key' => $key,
                'name' => $names[$key] ?? ($by === self::BY_BRAND ? (string)__('No brand set') : $key),
                'products' => $count,
                'average' => $average,
                'complete_pct' => $count > 0 ? round(100 * (int)$row['n_complete'] / $count, 1) : 0.0,
                'band' => $this->bands->resolve($average),
            ];
        }
        return $result;
    }

    /**
     * The rules most products fail under the filter, with the number of rules in scope.
     *
     * @return array{items: array<int, array<string, mixed>>, total_rules: int}
     */
    public function getMostMissing(Filter $filter, int $limit = 10): array
    {
        $s = FilterApplier::SCORE_ALIAS;
        $select = $this->baseSelect($filter)
            ->join(
                ['bce_mm' => $this->resource->getTableName(FailureResource::TABLE)],
                'bce_mm.product_id = ' . $s . '.product_id AND bce_mm.ruleset_id = ' . $s . '.ruleset_id',
                []
            )
            ->columns([
                'rule_id' => 'bce_mm.rule_id',
                'ruleset_id' => 'bce_mm.ruleset_id',
                'n' => new \Zend_Db_Expr('COUNT(*)'),
            ])
            ->group(['bce_mm.rule_id', 'bce_mm.ruleset_id'])
            ->order('n DESC')
            ->order('bce_mm.rule_id ASC')
            ->limit($limit);
        $rows = $this->resource->getConnection()->fetchAll($select);
        $rulesetIds = array_map(function (array $row) {
            return (int)$row['ruleset_id'];
        }, $rows);
        $this->ruleLabels->preload($rulesetIds);
        $names = $this->ruleSetNames($rulesetIds);
        $items = [];
        $rank = 1;
        foreach ($rows as $row) {
            $rule = $this->ruleLabels->getRule((int)$row['ruleset_id'], (int)$row['rule_id']);
            if ($rule === null) {
                continue;
            }
            $items[] = [
                'rank' => $rank++,
                'rule_id' => (int)$row['rule_id'],
                'ruleset_id' => (int)$row['ruleset_id'],
                'ruleset_name' => $names[(int)$row['ruleset_id']] ?? '',
                'label' => $this->ruleLabels->attributeLabel($rule),
                'description' => $this->ruleLabels->describe($rule),
                'weight' => (int)$rule->getWeight(),
                'weight_label' => $this->ruleLabels->weightLabel($rule),
                'failing' => (int)$row['n'],
            ];
        }
        return ['items' => $items, 'total_rules' => $this->countRulesInScope($filter)];
    }

    /**
     * Rule set names by id.
     *
     * @param int[] $rulesetIds
     * @return array<int, string>
     */
    private function ruleSetNames(array $rulesetIds): array
    {
        $rulesetIds = array_values(array_unique(array_filter(array_map('intval', $rulesetIds))));
        if (!$rulesetIds) {
            return [];
        }
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from($this->resource->getTableName(RuleSetResource::TABLE), ['ruleset_id', 'name'])
            ->where('ruleset_id IN (?)', $rulesetIds);
        $names = [];
        foreach ($connection->fetchAll($select) as $row) {
            $names[(int)$row['ruleset_id']] = (string)$row['name'];
        }
        return $names;
    }

    /**
     * The lowest-scoring products of the filtered set, for the preview panel's empty state.
     *
     * @return array<int, array{product_id: int, sku: string, name: string, score: int, band: string|null}>
     */
    public function getLowest(Filter $filter, int $limit = 3): array
    {
        $s = FilterApplier::SCORE_ALIAS;
        $e = FilterApplier::ENTITY_ALIAS;
        $name = $this->lookup->getDescriptor('name');
        $nameId = $name !== null ? (int)$name->getId() : 0;
        $select = $this->baseSelect($filter)
            ->joinLeft(
                ['bce_name' => $this->resource->getTableName('catalog_product_entity_varchar')],
                sprintf('bce_name.entity_id = %s.entity_id AND bce_name.attribute_id = %d AND bce_name.store_id = 0', $e, $nameId),
                []
            )
            ->columns([
                'product_id' => $s . '.product_id',
                'sku' => $e . '.sku',
                'name' => 'bce_name.value',
                'score' => $s . '.score',
            ])
            ->order($s . '.score ASC')
            ->order($s . '.product_id ASC')
            ->limit($limit);
        $rows = [];
        foreach ($this->resource->getConnection()->fetchAll($select) as $row) {
            $score = (int)$row['score'];
            $rows[] = [
                'product_id' => (int)$row['product_id'],
                'sku' => (string)$row['sku'],
                'name' => (string)($row['name'] ?? ''),
                'score' => $score,
                'band' => $this->bands->resolve($score),
            ];
        }
        return $rows;
    }

    /**
     * Products in the scope whose score rows exist, counted by status; used for "N disabled products not counted".
     */
    public function countDisabled(Filter $filter): int
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from([FilterApplier::SCORE_ALIAS => $this->resource->getTableName(ScoreResource::TABLE)], ['n' => new \Zend_Db_Expr('COUNT(*)')])
            ->join(
                [FilterApplier::ENTITY_ALIAS => $this->resource->getTableName('catalog_product_entity')],
                FilterApplier::ENTITY_ALIAS . '.entity_id = ' . FilterApplier::SCORE_ALIAS . '.product_id',
                []
            );
        $this->applier->applyScope($select, $filter);
        $select->where(FilterApplier::SCORE_ALIAS . '.score IS NOT NULL');
        $this->applier->joinStatus($select);
        $select->where($this->applier->getStatusAlias() . '.value = ?', Status::STATUS_DISABLED);
        return (int)$connection->fetchOne($select);
    }

    /**
     * Products of the scope with no rules assigned, under the filters on the products themselves (enabled only,
     * attribute set, product type, category, brand); the band and the tiles need a score and do not count.
     */
    public function countUncovered(Filter $filter): int
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()->from(
            [FilterApplier::ENTITY_ALIAS => $this->resource->getTableName('catalog_product_entity')],
            ['n' => new \Zend_Db_Expr('COUNT(*)')]
        );
        $this->applier->applyProductFilters($select, $filter);
        $this->coverage->applyUncovered($select, $filter->getRulesetId());
        return (int)$connection->fetchOne($select);
    }

    /**
     * Number of products the enabled rule sets in scope cover, for the "never calculated" message.
     */
    public function countCoveredProducts(?int $rulesetId): int
    {
        $connection = $this->resource->getConnection();
        $sets = $connection->select()
            ->from(['r' => $this->resource->getTableName(RuleSetResource::TABLE)], ['ruleset_id', 'kind'])
            ->where('r.is_enabled = 1');
        if ($rulesetId !== null) {
            $sets->where('r.ruleset_id = ?', $rulesetId);
        }
        $coversAll = false;
        $setIds = [];
        foreach ($connection->fetchAll($sets) as $row) {
            if ($row['kind'] === RuleSetInterface::KIND_CHANNEL) {
                $coversAll = true;
            } else {
                $setIds[] = (int)$row['ruleset_id'];
            }
        }
        if ($coversAll) {
            return $this->lookup->countProducts();
        }
        if (!$setIds) {
            return 0;
        }
        $attributeSets = $connection->fetchCol(
            $connection->select()
                ->from($this->resource->getTableName(RuleSetResource::TABLE_ATTRIBUTE_SET), ['attribute_set_id'])
                ->where('ruleset_id IN (?)', $setIds)
        );
        return $this->lookup->countProductsInSets(array_map('intval', $attributeSets));
    }

    public function countEnabledRuleSets(): int
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from($this->resource->getTableName(RuleSetResource::TABLE), ['n' => new \Zend_Db_Expr('COUNT(*)')])
            ->where('is_enabled = 1');
        return (int)$connection->fetchOne($select);
    }

    public function countRuleSets(): int
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from($this->resource->getTableName(RuleSetResource::TABLE), ['n' => new \Zend_Db_Expr('COUNT(*)')]);
        return (int)$connection->fetchOne($select);
    }

    private function countRulesInScope(Filter $filter): int
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from(['ru' => $this->resource->getTableName(RuleResource::TABLE)], ['n' => new \Zend_Db_Expr('COUNT(*)')])
            ->join(['r' => $this->resource->getTableName(RuleSetResource::TABLE)], 'r.ruleset_id = ru.ruleset_id', [])
            ->where('r.is_enabled = 1');
        if ($filter->getRulesetId() !== null) {
            $select->where('r.ruleset_id = ?', $filter->getRulesetId());
        } else {
            $select->where('r.kind = ?', RuleSetInterface::KIND_ATTRIBUTE_SET);
        }
        return (int)$connection->fetchOne($select);
    }

    /**
     * @param array<int, mixed> $keys
     * @return array<string, string>
     */
    private function groupNames(string $by, array $keys): array
    {
        $names = [];
        switch ($by) {
            case self::BY_ATTRIBUTE_SET:
                foreach ($this->lookup->getAttributeSetNames() as $id => $name) {
                    $names[(string)$id] = $name;
                }
                break;
            case self::BY_TYPE:
                $names = $this->lookup->getProductTypeLabels();
                break;
            case self::BY_BRAND:
                $descriptor = $this->lookup->getDescriptor($this->config->getBrandAttributeCode());
                if ($descriptor !== null) {
                    foreach ($descriptor->getOptions() as $value => $label) {
                        $names[(string)$value] = $label;
                    }
                }
                foreach ($keys as $key) {
                    $key = $key === null ? '' : (string)$key;
                    if (!isset($names[$key])) {
                        $names[$key] = $key === '' ? (string)__('No brand set') : $key;
                    }
                }
                break;
            case self::BY_CATEGORY:
                $ids = array_values(array_filter(array_map('intval', $keys)));
                if ($ids) {
                    $collection = $this->categoryCollectionFactory->create();
                    $collection->addAttributeToSelect('name')->addIdFilter($ids);
                    foreach ($collection->getItems() as $category) {
                        $names[(string)$category->getId()] = (string)$category->getName();
                    }
                }
                break;
        }
        return $names;
    }
}
