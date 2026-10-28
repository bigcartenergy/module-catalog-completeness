<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Model\Indexer;

use BigCartEnergy\CatalogCompleteness\Api\Data\RuleSetInterface;
use BigCartEnergy\CatalogCompleteness\Model\ResourceModel\Failure as FailureResource;
use BigCartEnergy\CatalogCompleteness\Model\ResourceModel\RuleSet\CollectionFactory as RuleSetCollectionFactory;
use BigCartEnergy\CatalogCompleteness\Model\ResourceModel\Score as ScoreResource;
use BigCartEnergy\CatalogCompleteness\Model\RuleSet;
use BigCartEnergy\CatalogCompleteness\Model\Scoring\Context;
use BigCartEnergy\CatalogCompleteness\Model\Scoring\ContextBuilder;
use BigCartEnergy\CatalogCompleteness\Model\Scoring\ProductData;
use BigCartEnergy\CatalogCompleteness\Model\Scoring\ProductDataLoader;
use BigCartEnergy\CatalogCompleteness\Model\Scoring\Scorer;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Psr\Log\LoggerInterface;

/**
 * The engine behind the indexer, the consumer, the CLI and the periodic rescore. Walks product ids in chunks,
 * loads only the attributes the enabled rule sets need, writes scores and failures in bulk, and treats a
 * product id that no longer exists as "delete its rows", never as an error.
 */
class Reindexer
{
    public const CHUNK_SIZE = 500;

    /**
     * @var RuleSetCollectionFactory
     */
    private $ruleSetCollectionFactory;

    /**
     * @var ContextBuilder
     */
    private $contextBuilder;

    /**
     * @var ProductDataLoader
     */
    private $loader;

    /**
     * @var Scorer
     */
    private $scorer;

    /**
     * @var ScoreResource
     */
    private $scoreResource;

    /**
     * @var FailureResource
     */
    private $failureResource;

    /**
     * @var ResourceConnection
     */
    private $resource;

    /**
     * @var Json
     */
    private $json;

    /**
     * @var DateTime
     */
    private $dateTime;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var callable|null function (int $done, int $total): void
     */
    private $progress;

    public function __construct(
        RuleSetCollectionFactory $ruleSetCollectionFactory,
        ContextBuilder $contextBuilder,
        ProductDataLoader $loader,
        Scorer $scorer,
        ScoreResource $scoreResource,
        FailureResource $failureResource,
        ResourceConnection $resource,
        Json $json,
        DateTime $dateTime,
        LoggerInterface $logger
    ) {
        $this->ruleSetCollectionFactory = $ruleSetCollectionFactory;
        $this->contextBuilder = $contextBuilder;
        $this->loader = $loader;
        $this->scorer = $scorer;
        $this->scoreResource = $scoreResource;
        $this->failureResource = $failureResource;
        $this->resource = $resource;
        $this->json = $json;
        $this->dateTime = $dateTime;
        $this->logger = $logger;
    }

    /**
     * Progress is reported after every chunk as (done, total). The CLI draws a bar, the consumer updates the run row.
     */
    public function setProgressCallback(?callable $progress): void
    {
        $this->progress = $progress;
    }

    /**
     * Score every product, or every product one rule set covers.
     *
     * @param int|null $rulesetId null for all enabled rule sets
     */
    public function reindexAll(?int $rulesetId = null): void
    {
        $plan = $this->plan($rulesetId);
        $total = $this->countInScope($plan);
        $done = 0;
        $this->report($done, $total);
        if (!$plan->ruleSets) {
            // Nothing is enabled: the index holds nothing for this scope.
            if ($rulesetId === null) {
                $this->scoreResource->deleteForOtherRuleSets([]);
                $this->failureResource->deleteForOtherRuleSets([]);
            } else {
                $this->scoreResource->deleteForOtherRuleSets($this->enabledIdsExcept($rulesetId));
                $this->failureResource->deleteForOtherRuleSets($this->enabledIdsExcept($rulesetId));
            }
            $this->report($total, $total);
            return;
        }
        $last = 0;
        while (true) {
            $ids = $this->nextIds($last, $plan);
            if (!$ids) {
                break;
            }
            $this->processChunk($ids, $plan);
            $last = (int)end($ids);
            $done += count($ids);
            $this->report(min($done, $total), $total);
        }
        if ($rulesetId === null) {
            $this->scoreResource->deleteForOtherRuleSets($plan->ruleSetIds);
            $this->failureResource->deleteForOtherRuleSets($plan->ruleSetIds);
            $this->scoreResource->deleteOrphans();
        } else {
            $this->deleteOutOfScope($plan);
        }
        $this->report($total, $total);
    }

    /**
     * Score the given products against every enabled rule set, or against one.
     *
     * @param int[] $productIds
     */
    public function reindexIds(array $productIds, ?int $rulesetId = null): void
    {
        $productIds = array_values(array_unique(array_filter(array_map('intval', $productIds))));
        $total = count($productIds);
        $done = 0;
        $this->report($done, $total);
        if (!$productIds) {
            return;
        }
        $plan = $this->plan($rulesetId);
        foreach (array_chunk($productIds, self::CHUNK_SIZE) as $chunk) {
            if (!$plan->ruleSets) {
                $this->scoreResource->deleteByProductIds($chunk, $rulesetId === null ? null : [$rulesetId]);
                $this->failureResource->deleteByProductIds($chunk, $rulesetId === null ? null : [$rulesetId]);
            } else {
                $this->processChunk($chunk, $plan);
            }
            $done += count($chunk);
            $this->report($done, $total);
        }
    }

    /**
     * Score one batch of product ids as a single chunk, against a plan built once for a whole run. The periodic
     * rescore walks the catalog with it one id range at a time, so its batch size is the unit of memory.
     *
     * @param int[] $productIds
     */
    public function reindexBatch(array $productIds, Plan $plan): void
    {
        $productIds = array_values(array_unique(array_filter(array_map('intval', $productIds))));
        if ($productIds && $plan->ruleSets) {
            $this->processChunk($productIds, $plan);
        }
    }

    /**
     * Number of products a full run over the scope would score.
     */
    public function countInScope(?Plan $plan = null, ?int $rulesetId = null): int
    {
        if ($plan === null) {
            $plan = $this->plan($rulesetId);
        }
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from($this->resource->getTableName('catalog_product_entity'), ['n' => new \Zend_Db_Expr('COUNT(*)')]);
        if ($plan->attributeSetScope !== null) {
            if (!$plan->attributeSetScope) {
                return 0;
            }
            $select->where('attribute_set_id IN (?)', $plan->attributeSetScope);
        }
        return (int)$connection->fetchOne($select);
    }

    /**
     * Enabled rule sets in scope, the primary map and the shared context.
     */
    public function plan(?int $rulesetId): Plan
    {
        $collection = $this->ruleSetCollectionFactory->create();
        $collection->addEnabledFilter();
        if ($rulesetId !== null) {
            $collection->addFieldToFilter('ruleset_id', $rulesetId);
        }
        $plan = new Plan();
        /** @var RuleSet $ruleSet */
        foreach ($collection->getItems() as $ruleSet) {
            if (!$ruleSet->getRules()) {
                // A rule set without rules scores nothing; its rows are removed like a disabled one.
                continue;
            }
            $plan->ruleSets[] = $ruleSet;
            $plan->ruleSetIds[] = (int)$ruleSet->getId();
            if ($ruleSet->getKind() === RuleSetInterface::KIND_CHANNEL) {
                $plan->channels[] = $ruleSet;
            } else {
                foreach ($ruleSet->getAttributeSetIds() as $setId) {
                    $plan->primaryBySet[$setId] = $ruleSet;
                }
            }
        }
        if ($rulesetId !== null) {
            $single = $plan->ruleSets[0] ?? null;
            if ($single !== null && $single->getKind() === RuleSetInterface::KIND_ATTRIBUTE_SET) {
                $plan->attributeSetScope = $single->getAttributeSetIds();
            }
        }
        $plan->context = $this->contextBuilder->build($plan->ruleSets);
        return $plan;
    }

    /**
     * Score one loaded product against the rule sets that apply to it; used by the preview as well.
     *
     * @return array<int, array{ruleSet: RuleSetInterface, result: \BigCartEnergy\CatalogCompleteness\Model\Scoring\ScoreResult}>
     */
    public function scoreProduct(ProductData $product, Plan $plan): array
    {
        $results = [];
        $primary = $plan->primaryBySet[$product->getAttributeSetId()] ?? null;
        if ($primary !== null) {
            $results[] = ['ruleSet' => $primary, 'result' => $this->scorer->score($product, $primary, $plan->context)];
        }
        foreach ($plan->channels as $channel) {
            $results[] = ['ruleSet' => $channel, 'result' => $this->scorer->score($product, $channel, $plan->context)];
        }
        return $results;
    }

    /**
     * @param int[] $ids
     */
    private function processChunk(array $ids, Plan $plan): void
    {
        $products = $this->loader->load($ids, $plan->context);
        $missingIds = array_values(array_diff($ids, array_keys($products)));
        $now = $this->dateTime->gmtDate();
        $scoreRows = [];
        $failureRows = [];
        $computedPairs = [];
        foreach ($products as $product) {
            foreach ($this->scoreProduct($product, $plan) as $entry) {
                /** @var RuleSetInterface $ruleSet */
                $ruleSet = $entry['ruleSet'];
                $result = $entry['result'];
                $rulesetId = (int)$ruleSet->getRulesetId();
                $missing = $result->getMissingRuleIds();
                $scoreRows[] = [
                    'product_id' => $product->getId(),
                    'ruleset_id' => $rulesetId,
                    'is_primary' => $ruleSet->getKind() === RuleSetInterface::KIND_ATTRIBUTE_SET ? 1 : 0,
                    'score' => $result->getScore(),
                    'passed_weight' => $result->getPassedWeight(),
                    'total_weight' => $result->getTotalWeight(),
                    'missing' => $this->json->serialize($missing),
                    'scored_at' => $now,
                ];
                foreach ($missing as $ruleId) {
                    $failureRows[] = [
                        'product_id' => $product->getId(),
                        'ruleset_id' => $rulesetId,
                        'rule_id' => $ruleId,
                    ];
                }
                $computedPairs[$product->getId() . ':' . $rulesetId] = true;
            }
        }
        $existingIds = array_keys($products);
        $connection = $this->resource->getConnection();
        $connection->beginTransaction();
        try {
            if ($missingIds) {
                // D1: a deleted product is handled by deleting its rows, never by throwing.
                $this->scoreResource->deleteByProductIds($missingIds);
                $this->failureResource->deleteByProductIds($missingIds);
            }
            if ($existingIds) {
                $stale = [];
                foreach ($this->scoreResource->fetchPairs($existingIds, $plan->ruleSetIds) as $pair => $scoreId) {
                    if (!isset($computedPairs[$pair])) {
                        $stale[] = $scoreId;
                    }
                }
                $this->scoreResource->deleteByIds($stale);
                $this->failureResource->deleteByProductIds($existingIds, $plan->ruleSetIds);
                $this->scoreResource->upsert($scoreRows);
                $this->failureResource->insert($failureRows);
            }
            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            $this->logger->error('Catalog completeness: a chunk failed to index: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * @param int[] $ids
     * @return int[]
     */
    private function nextIds(int $afterId, Plan $plan): array
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from($this->resource->getTableName('catalog_product_entity'), ['entity_id'])
            ->where('entity_id > ?', $afterId)
            ->order('entity_id ASC')
            ->limit(self::CHUNK_SIZE);
        if ($plan->attributeSetScope !== null) {
            if (!$plan->attributeSetScope) {
                return [];
            }
            $select->where('attribute_set_id IN (?)', $plan->attributeSetScope);
        }
        return array_map('intval', $connection->fetchCol($select));
    }

    /**
     * After a single rule set run: drop its rows for products that left its scope.
     */
    private function deleteOutOfScope(Plan $plan): void
    {
        if (!$plan->ruleSetIds || $plan->attributeSetScope === null) {
            return;
        }
        $rulesetId = $plan->ruleSetIds[0];
        $connection = $this->resource->getConnection();
        $inScope = $connection->select()
            ->from($this->resource->getTableName('catalog_product_entity'), ['entity_id']);
        if ($plan->attributeSetScope) {
            $inScope->where('attribute_set_id IN (?)', $plan->attributeSetScope);
        } else {
            $inScope->where('1 = 0');
        }
        foreach ([$this->scoreResource->getTable(), $this->failureResource->getTable()] as $table) {
            $connection->delete($table, [
                'ruleset_id = ?' => $rulesetId,
                'product_id NOT IN (?)' => $inScope,
            ]);
        }
    }

    /**
     * @return int[]
     */
    private function enabledIdsExcept(int $rulesetId): array
    {
        $collection = $this->ruleSetCollectionFactory->create();
        $collection->addEnabledFilter();
        $ids = [];
        foreach ($collection->getItems() as $ruleSet) {
            if ((int)$ruleSet->getId() !== $rulesetId) {
                $ids[] = (int)$ruleSet->getId();
            }
        }
        return $ids;
    }

    private function report(int $done, int $total): void
    {
        if ($this->progress !== null) {
            call_user_func($this->progress, $done, $total);
        }
    }
}
