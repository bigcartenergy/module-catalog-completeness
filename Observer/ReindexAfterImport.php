<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Observer;

use BigCartEnergy\CatalogCompleteness\Model\Indexer\Status as IndexerStatus;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;

/**
 * Update on Save mode: rescore the products of an import bunch once it is written. The import writes the
 * EAV tables directly, so the product save observer never sees these rows.
 */
class ReindexAfterImport implements ObserverInterface
{
    /**
     * @var IndexerStatus
     */
    private $indexerStatus;

    /**
     * @var ResourceConnection
     */
    private $resource;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(IndexerStatus $indexerStatus, ResourceConnection $resource, LoggerInterface $logger)
    {
        $this->indexerStatus = $indexerStatus;
        $this->resource = $resource;
        $this->logger = $logger;
    }

    /**
     * @inheritDoc
     */
    public function execute(Observer $observer)
    {
        try {
            if ($this->indexerStatus->isScheduled()) {
                return;
            }
            $bunch = $observer->getEvent()->getData('bunch');
            if (!is_array($bunch)) {
                return;
            }
            $skus = [];
            foreach ($bunch as $row) {
                if (is_array($row) && isset($row['sku']) && trim((string)$row['sku']) !== '') {
                    $skus[] = trim((string)$row['sku']);
                }
            }
            $skus = array_values(array_unique($skus));
            if (!$skus) {
                return;
            }
            $connection = $this->resource->getConnection();
            $select = $connection->select()
                ->from($this->resource->getTableName('catalog_product_entity'), ['entity_id'])
                ->where('sku IN (?)', $skus);
            $this->indexerStatus->reindexList(array_map('intval', $connection->fetchCol($select)));
        } catch (\Throwable $e) {
            $this->logger->error('Catalog completeness: rescoring after import failed: ' . $e->getMessage());
        }
    }
}
