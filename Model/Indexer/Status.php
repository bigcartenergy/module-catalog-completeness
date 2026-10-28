<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Model\Indexer;

use Magento\Framework\Indexer\IndexerInterface;
use Magento\Framework\Indexer\IndexerRegistry;
use Magento\Framework\Indexer\StateInterface;

/**
 * The completeness indexer's state as the dashboard and the editor read it, and the few writes the module makes.
 */
class Status
{
    /**
     * @var IndexerRegistry
     */
    private $indexerRegistry;

    public function __construct(IndexerRegistry $indexerRegistry)
    {
        $this->indexerRegistry = $indexerRegistry;
    }

    public function getIndexer(): IndexerInterface
    {
        return $this->indexerRegistry->get(ScoreIndexer::INDEXER_ID);
    }

    /**
     * Update by Schedule (true) or Update on Save (false).
     */
    public function isScheduled(): bool
    {
        return (bool)$this->getIndexer()->isScheduled();
    }

    public function isValid(): bool
    {
        return (bool)$this->getIndexer()->isValid();
    }

    public function isInvalid(): bool
    {
        return (bool)$this->getIndexer()->isInvalid();
    }

    public function isWorking(): bool
    {
        return (bool)$this->getIndexer()->isWorking();
    }

    /**
     * When the indexer last became valid, UTC, or null when it never did.
     */
    public function getLatestUpdated(): ?string
    {
        $updated = $this->getIndexer()->getLatestUpdated();
        return $updated === null || $updated === '' ? null : (string)$updated;
    }

    public function invalidate(): void
    {
        $this->getIndexer()->invalidate();
    }

    /**
     * Mark the index valid as of now; used after a run that provably covered everything that changed.
     */
    public function markValid(string $gmtNow): void
    {
        $state = $this->getIndexer()->getState();
        if (!method_exists($state, 'save')) {
            return;
        }
        // The state resource lets a row become "valid" only from "working" on 2.3 (2.4 also allows it from
        // "invalid"), the way a full reindex moves through the states. Go through "working" on every line.
        $state->setStatus(StateInterface::STATUS_WORKING);
        $state->save();
        $state->setStatus(StateInterface::STATUS_VALID);
        $state->setUpdated($gmtNow);
        $state->save();
    }

    public function reindexRow(int $productId): void
    {
        $this->getIndexer()->reindexRow($productId);
    }

    /**
     * @param int[] $productIds
     */
    public function reindexList(array $productIds): void
    {
        $productIds = array_values(array_unique(array_filter(array_map('intval', $productIds))));
        if ($productIds) {
            $this->getIndexer()->reindexList($productIds);
        }
    }

    public function reindexAll(): void
    {
        $this->getIndexer()->reindexAll();
    }
}
