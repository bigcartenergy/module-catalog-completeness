<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Controller\Adminhtml\Score;

use BigCartEnergy\CatalogCompleteness\Model\Config;
use BigCartEnergy\CatalogCompleteness\Model\Dashboard\Filter;
use BigCartEnergy\CatalogCompleteness\Model\Indexer\Status as IndexerStatus;
use BigCartEnergy\CatalogCompleteness\Model\Queue\RunManager;
use BigCartEnergy\CatalogCompleteness\Model\ResourceModel\WorkList\CollectionFactory as WorkListCollectionFactory;
use BigCartEnergy\CatalogCompleteness\Model\ResourceModel\WorkList\UncoveredCollectionFactory;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Ui\Component\MassAction\Filter as MassActionFilter;
use Psr\Log\LoggerInterface;

/**
 * Recalculate: everything (queued), one rule set (queued), one product (now), or the selected products
 * (now up to the page size, else queued). POST with the form key in every case. Products with no rules
 * assigned (the coverage "none") are not rescored: a notice says that nothing covers them yet.
 */
class Recalculate extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'BigCartEnergy_CatalogCompleteness::recalculate';

    /**
     * @var JsonFactory
     */
    private $jsonFactory;

    /**
     * @var RunManager
     */
    private $runManager;

    /**
     * @var IndexerStatus
     */
    private $indexerStatus;

    /**
     * @var MassActionFilter
     */
    private $massActionFilter;

    /**
     * @var WorkListCollectionFactory
     */
    private $collectionFactory;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var UncoveredCollectionFactory
     */
    private $uncoveredCollectionFactory;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        Context $context,
        JsonFactory $jsonFactory,
        RunManager $runManager,
        IndexerStatus $indexerStatus,
        MassActionFilter $massActionFilter,
        WorkListCollectionFactory $collectionFactory,
        Config $config,
        UncoveredCollectionFactory $uncoveredCollectionFactory,
        LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->jsonFactory = $jsonFactory;
        $this->runManager = $runManager;
        $this->indexerStatus = $indexerStatus;
        $this->massActionFilter = $massActionFilter;
        $this->collectionFactory = $collectionFactory;
        $this->config = $config;
        $this->uncoveredCollectionFactory = $uncoveredCollectionFactory;
        $this->logger = $logger;
    }

    /**
     * @inheritDoc
     */
    public function execute()
    {
        $request = $this->getRequest();
        $isAjax = (bool)$request->getParam('isAjax');
        try {
            $productId = (int)$request->getParam('product_id');
            if ($productId > 0) {
                $this->indexerStatus->reindexRow($productId);
                return $this->respond($isAjax, ['ok' => true, 'product_id' => $productId], __('The product was rescored.'));
            }
            if ($request->getParam('selected') !== null || $request->getParam('excluded') !== null) {
                return $this->recalculateSelection($isAjax);
            }
            $rulesetId = (int)$request->getParam('ruleset_id');
            $run = $this->runManager->queue($rulesetId > 0 ? $rulesetId : null);
            return $this->respond(
                $isAjax,
                ['ok' => true, 'run_id' => (int)$run->getId(), 'state' => $run->getState()],
                __('Recalculation queued. The status line shows its progress.')
            );
        } catch (LocalizedException $e) {
            return $this->fail($isAjax, (string)$e->getMessage());
        } catch (\Throwable $e) {
            // The exception text can hold an SQL statement or a server path: it goes to the log, not the page.
            $this->logger->critical('Catalog completeness: the recalculation could not be started.', ['exception' => $e]);
            return $this->fail(
                $isAjax,
                (string)__('The recalculation could not be started. The details are in the Magento exception log.')
            );
        }
    }

    /**
     * @return \Magento\Framework\Controller\ResultInterface
     */
    private function recalculateSelection(bool $isAjax)
    {
        // The grid's scope (the primary rows, or the chosen rule set's) keeps each product to one row.
        $filter = Filter::fromParams($this->getRequest()->getParams(), $this->config->isEnabledOnlyDefault())
            ->cleared();
        $collection = $filter->isUncoveredOnly()
            ? $this->uncoveredCollectionFactory->create()
            : $this->collectionFactory->create();
        $collection->applyDashboardFilter($filter, null);
        $collection = $this->massActionFilter->getCollection($collection);
        $ids = [];
        foreach ($collection->getItems() as $item) {
            $ids[] = (int)$item->getData('product_id');
        }
        $ids = array_values(array_unique(array_filter($ids)));
        if (!$ids) {
            return $this->fail($isAjax, (string)__('Select at least one product.'));
        }
        if ($filter->isUncoveredOnly()) {
            // No rule covers these products, so a recalculation would give them no score: say so, rescore nothing.
            return $this->notice(
                $isAjax,
                ['ok' => true, 'count' => 0, 'uncovered' => count($ids)],
                count($ids) === 1
                    ? __('The product has no rules assigned, so recalculating it gives no score. Add its attribute set to an attribute set rule set, or a rule for its product type, then recalculate.')
                    : __('These %1 products have no rules assigned, so recalculating them gives no score. Add their attribute sets to an attribute set rule set, or rules for their product types, then recalculate.', count($ids))
            );
        }
        if (count($ids) <= $this->config->getWorkListPageSize()) {
            $this->indexerStatus->reindexList($ids);
            return $this->respond(
                $isAjax,
                ['ok' => true, 'count' => count($ids)],
                count($ids) === 1 ? __('The product was rescored.') : __('%1 products were rescored.', count($ids))
            );
        }
        $run = $this->runManager->queue(null, $ids);
        return $this->respond(
            $isAjax,
            ['ok' => true, 'run_id' => (int)$run->getId(), 'state' => $run->getState(), 'count' => count($ids)],
            count($ids) === 1
                ? __('Recalculation of 1 product queued. The status line shows its progress.')
                : __('Recalculation of %1 products queued. The status line shows its progress.', count($ids))
        );
    }

    /**
     * @param array<string, mixed> $data
     * @param \Magento\Framework\Phrase|string $message
     * @return \Magento\Framework\Controller\ResultInterface
     */
    private function respond(bool $isAjax, array $data, $message)
    {
        if ($isAjax) {
            return $this->jsonFactory->create()->setData($data);
        }
        $this->messageManager->addSuccessMessage($message);
        return $this->resultRedirectFactory->create()->setRefererOrBaseUrl();
    }

    /**
     * Like respond(), with a notice instead of a success message.
     *
     * @param array<string, mixed> $data
     * @param \Magento\Framework\Phrase|string $message
     * @return \Magento\Framework\Controller\ResultInterface
     */
    private function notice(bool $isAjax, array $data, $message)
    {
        if ($isAjax) {
            return $this->jsonFactory->create()->setData($data + ['message' => (string)$message]);
        }
        $this->messageManager->addNoticeMessage($message);
        return $this->resultRedirectFactory->create()->setRefererOrBaseUrl();
    }

    /**
     * @return \Magento\Framework\Controller\ResultInterface
     */
    private function fail(bool $isAjax, string $message)
    {
        if ($isAjax) {
            return $this->jsonFactory->create()->setHttpResponseCode(400)->setData(['ok' => false, 'error' => $message]);
        }
        $this->messageManager->addErrorMessage($message);
        return $this->resultRedirectFactory->create()->setRefererOrBaseUrl();
    }
}
