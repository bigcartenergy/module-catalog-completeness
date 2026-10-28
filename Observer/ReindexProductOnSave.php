<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Observer;

use BigCartEnergy\CatalogCompleteness\Model\Indexer\Status as IndexerStatus;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;

/**
 * Update on Save mode: rescore a product right after it is saved. In Update by Schedule mode the database
 * triggers record the change and the mview cron picks it up instead. A scoring error never fails the save.
 */
class ReindexProductOnSave implements ObserverInterface
{
    /**
     * @var IndexerStatus
     */
    private $indexerStatus;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(IndexerStatus $indexerStatus, LoggerInterface $logger)
    {
        $this->indexerStatus = $indexerStatus;
        $this->logger = $logger;
    }

    /**
     * @inheritDoc
     */
    public function execute(Observer $observer)
    {
        $product = $observer->getEvent()->getData('product');
        if (!is_object($product) || !method_exists($product, 'getId') || !$product->getId()) {
            return;
        }
        try {
            if ($this->indexerStatus->isScheduled()) {
                return;
            }
            $this->indexerStatus->reindexRow((int)$product->getId());
        } catch (\Throwable $e) {
            $this->logger->error('Catalog completeness: rescoring product after save failed: ' . $e->getMessage());
        }
    }
}
