<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Plugin;

use BigCartEnergy\CatalogCompleteness\Model\Indexer\Status as IndexerStatus;
use Magento\Catalog\Model\Product\Action;
use Psr\Log\LoggerInterface;

/**
 * Update on Save mode: the mass "Update attributes" action writes values without saving product models,
 * so it is rescored here. A scoring error never fails the update.
 */
class ReindexAfterMassAttributeUpdate
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
     * @param mixed $result
     * @param mixed $productIds
     * @param mixed $attrData
     * @param mixed $storeId
     * @return mixed
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterUpdateAttributes(Action $subject, $result, $productIds, $attrData, $storeId)
    {
        try {
            if (is_array($productIds) && $productIds && !$this->indexerStatus->isScheduled()) {
                $this->indexerStatus->reindexList(array_map('intval', $productIds));
            }
        } catch (\Throwable $e) {
            $this->logger->error('Catalog completeness: rescoring after a mass update failed: ' . $e->getMessage());
        }
        return $result;
    }
}
