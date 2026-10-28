<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Model\Queue;

use BigCartEnergy\CatalogCompleteness\Model\Indexer\Reindexer;
use BigCartEnergy\CatalogCompleteness\Model\ResourceModel\Run as RunResource;
use BigCartEnergy\CatalogCompleteness\Model\ResourceModel\Run\CollectionFactory as RunCollectionFactory;
use BigCartEnergy\CatalogCompleteness\Model\Run;
use BigCartEnergy\CatalogCompleteness\Model\RunFactory;
use Magento\Framework\MessageQueue\PublisherInterface;
use Magento\Framework\Serialize\Serializer\Json;

/**
 * Queues recalculation runs and reads their state. A run row is the dashboard's progress and error source.
 */
class RunManager
{
    public const TOPIC = 'bce_completeness.recalculate';

    /**
     * A queued run nobody picked up for this long is reported as waiting for the consumer.
     */
    public const WAITING_AFTER_SECONDS = 600;

    /**
     * @var RunFactory
     */
    private $runFactory;

    /**
     * @var RunResource
     */
    private $runResource;

    /**
     * @var RunCollectionFactory
     */
    private $collectionFactory;

    /**
     * @var PublisherInterface
     */
    private $publisher;

    /**
     * @var Reindexer
     */
    private $reindexer;

    /**
     * @var Json
     */
    private $json;

    public function __construct(
        RunFactory $runFactory,
        RunResource $runResource,
        RunCollectionFactory $collectionFactory,
        PublisherInterface $publisher,
        Reindexer $reindexer,
        Json $json
    ) {
        $this->runFactory = $runFactory;
        $this->runResource = $runResource;
        $this->collectionFactory = $collectionFactory;
        $this->publisher = $publisher;
        $this->reindexer = $reindexer;
        $this->json = $json;
    }

    /**
     * Queue a run: every product (null rule set, null ids), one rule set, or a list of products.
     *
     * @param int[]|null $productIds
     */
    public function queue(?int $rulesetId, ?array $productIds = null): Run
    {
        $active = $this->getActiveRun();
        if ($active !== null && $active->getRulesetId() === $rulesetId && $active->getProductIds() === null && $productIds === null) {
            // The same run is already waiting or running; do not pile a second one on.
            return $active;
        }
        $run = $this->runFactory->create();
        $run->setData('ruleset_id', $rulesetId);
        $run->setProductIds($productIds);
        $run->setData('state', Run::STATE_QUEUED);
        $run->setData('total', $productIds === null ? $this->reindexer->countInScope(null, $rulesetId) : count($productIds));
        $run->setData('done', 0);
        $this->runResource->save($run);
        $this->publisher->publish(self::TOPIC, $this->json->serialize(['run_id' => (int)$run->getId()]));
        return $run;
    }

    public function load(int $runId): ?Run
    {
        $run = $this->runFactory->create();
        $this->runResource->load($run, $runId);
        return $run->getId() ? $run : null;
    }

    /**
     * The newest queued or running run.
     */
    public function getActiveRun(?int $rulesetId = null): ?Run
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('state', ['in' => [Run::STATE_QUEUED, Run::STATE_RUNNING]]);
        if ($rulesetId !== null) {
            $collection->addFieldToFilter('ruleset_id', $rulesetId);
        }
        $collection->setOrder('run_id', 'DESC')->setPageSize(1);
        $run = $collection->getFirstItem();
        return $run->getId() ? $run : null;
    }

    /**
     * The newest run of any state.
     */
    public function getLastRun(): ?Run
    {
        $collection = $this->collectionFactory->create();
        $collection->setOrder('run_id', 'DESC')->setPageSize(1);
        $run = $collection->getFirstItem();
        return $run->getId() ? $run : null;
    }

    /**
     * The newest failed run, unless a later run finished.
     */
    public function getUnresolvedFailure(): ?Run
    {
        $last = $this->getLastRun();
        if ($last === null || $last->getState() !== Run::STATE_FAILED) {
            return null;
        }
        return $last;
    }

    /**
     * Whether a queued run has waited longer than the consumer should take to start it.
     */
    public function isWaiting(Run $run, int $nowTimestamp): bool
    {
        if ($run->getState() !== Run::STATE_QUEUED) {
            return false;
        }
        $created = strtotime((string)$run->getData('created_at'));
        if ($created === false) {
            return false;
        }
        return $nowTimestamp - $created > self::WAITING_AFTER_SECONDS;
    }

    public function markRunning(Run $run, string $gmtNow): void
    {
        $run->setData('state', Run::STATE_RUNNING);
        $run->setData('started_at', $gmtNow);
        $this->runResource->save($run);
    }

    public function updateProgress(Run $run, int $done, int $total): void
    {
        $this->runResource->updateProgress((int)$run->getId(), $done, $total);
    }

    public function markDone(Run $run, string $gmtNow): void
    {
        $run->setData('state', Run::STATE_DONE);
        $run->setData('done', $run->getTotal());
        $run->setData('finished_at', $gmtNow);
        $this->runResource->save($run);
    }

    public function markFailed(Run $run, string $message, string $gmtNow): void
    {
        $run->setData('state', Run::STATE_FAILED);
        $run->setData('message', mb_substr($message, 0, 2000));
        $run->setData('finished_at', $gmtNow);
        $this->runResource->save($run);
    }
}
