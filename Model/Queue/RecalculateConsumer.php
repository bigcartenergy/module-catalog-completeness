<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Model\Queue;

use BigCartEnergy\CatalogCompleteness\Model\Indexer\Reindexer;
use BigCartEnergy\CatalogCompleteness\Model\Indexer\Status as IndexerStatus;
use BigCartEnergy\CatalogCompleteness\Model\ResourceModel\RuleSet\CollectionFactory as RuleSetCollectionFactory;
use BigCartEnergy\CatalogCompleteness\Model\Run;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Psr\Log\LoggerInterface;

/**
 * Works one queued run: a full reindex through the indexer framework, one rule set, or a list of products.
 * Progress is written to the run row after every chunk; a failure keeps the exception message only.
 */
class RecalculateConsumer
{
    /**
     * @var RunManager
     */
    private $runManager;

    /**
     * @var Reindexer
     */
    private $reindexer;

    /**
     * @var IndexerStatus
     */
    private $indexerStatus;

    /**
     * @var RuleSetCollectionFactory
     */
    private $ruleSetCollectionFactory;

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

    public function __construct(
        RunManager $runManager,
        Reindexer $reindexer,
        IndexerStatus $indexerStatus,
        RuleSetCollectionFactory $ruleSetCollectionFactory,
        Json $json,
        DateTime $dateTime,
        LoggerInterface $logger
    ) {
        $this->runManager = $runManager;
        $this->reindexer = $reindexer;
        $this->indexerStatus = $indexerStatus;
        $this->ruleSetCollectionFactory = $ruleSetCollectionFactory;
        $this->json = $json;
        $this->dateTime = $dateTime;
        $this->logger = $logger;
    }

    /**
     * @param string $message JSON with the run id
     */
    public function process(string $message): void
    {
        try {
            $payload = $this->json->unserialize($message);
        } catch (\InvalidArgumentException $e) {
            $this->logger->warning('Catalog completeness: unreadable queue message skipped.');
            return;
        }
        $runId = is_array($payload) && isset($payload['run_id']) ? (int)$payload['run_id'] : 0;
        $run = $runId > 0 ? $this->runManager->load($runId) : null;
        if ($run === null || $run->getState() !== Run::STATE_QUEUED) {
            return;
        }
        $this->runManager->markRunning($run, $this->dateTime->gmtDate());
        $this->reindexer->setProgressCallback(function (int $done, int $total) use ($run): void {
            $this->runManager->updateProgress($run, $done, $total);
        });
        try {
            $rulesetId = $run->getRulesetId();
            $productIds = $run->getProductIds();
            if ($productIds !== null) {
                $this->reindexer->reindexIds($productIds, $rulesetId);
            } elseif ($rulesetId !== null) {
                $this->reindexer->reindexAll($rulesetId);
                $this->revalidateIfEverythingElseIsCurrent($rulesetId);
            } else {
                // Through the framework so the indexer state becomes valid and the changelog position moves on.
                $this->indexerStatus->reindexAll();
            }
            $this->runManager->markDone($run, $this->dateTime->gmtDate());
        } catch (\Throwable $e) {
            $this->logger->error('Catalog completeness: recalculation run ' . $runId . ' failed: ' . $e->getMessage());
            $this->runManager->markFailed($run, $e->getMessage(), $this->dateTime->gmtDate());
        } finally {
            $this->reindexer->setProgressCallback(null);
        }
    }

    /**
     * After one rule set was rescored: the index is current again only if no other enabled rule set changed
     * since the index was last valid. Otherwise it stays marked stale, which is the honest state.
     */
    private function revalidateIfEverythingElseIsCurrent(int $rulesetId): void
    {
        if (!$this->indexerStatus->isInvalid()) {
            return;
        }
        $validSince = $this->indexerStatus->getLatestUpdated();
        $validSinceTs = $validSince !== null ? strtotime($validSince) : false;
        if ($validSinceTs === false) {
            return;
        }
        $collection = $this->ruleSetCollectionFactory->create();
        $collection->addEnabledFilter();
        foreach ($collection->getItems() as $ruleSet) {
            if ((int)$ruleSet->getId() === $rulesetId) {
                continue;
            }
            $updated = strtotime((string)$ruleSet->getUpdatedAt());
            if ($updated !== false && $updated > $validSinceTs) {
                return;
            }
        }
        $this->indexerStatus->markValid($this->dateTime->gmtDate());
    }
}
