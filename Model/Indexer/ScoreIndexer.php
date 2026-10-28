<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Model\Indexer;

use Magento\Framework\Indexer\ActionInterface as IndexerActionInterface;
use Magento\Framework\Mview\ActionInterface as MviewActionInterface;

/**
 * The indexer action for "bce_completeness_score", in both modes: Update on Save calls executeRow and
 * executeList; Update by Schedule calls execute with changelog ids, deleted products included.
 */
class ScoreIndexer implements IndexerActionInterface, MviewActionInterface
{
    public const INDEXER_ID = 'bce_completeness_score';

    /**
     * @var Reindexer
     */
    private $reindexer;

    public function __construct(Reindexer $reindexer)
    {
        $this->reindexer = $reindexer;
    }

    /**
     * @inheritDoc
     */
    public function executeFull()
    {
        $this->reindexer->reindexAll(null);
    }

    /**
     * @inheritDoc
     */
    public function executeList(array $ids)
    {
        $this->reindexer->reindexIds($ids, null);
    }

    /**
     * @inheritDoc
     */
    public function executeRow($id)
    {
        $this->reindexer->reindexIds([(int)$id], null);
    }

    /**
     * @inheritDoc
     */
    public function execute($ids)
    {
        $this->reindexer->reindexIds(is_array($ids) ? $ids : [(int)$ids], null);
    }
}
