<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Controller\Adminhtml\Score;

use BigCartEnergy\CatalogCompleteness\Model\Config;
use BigCartEnergy\CatalogCompleteness\Model\Dashboard\Filter;
use BigCartEnergy\CatalogCompleteness\Model\Dashboard\Statistics;
use BigCartEnergy\CatalogCompleteness\Model\Export\CsvExport;
use BigCartEnergy\CatalogCompleteness\Model\ResourceModel\WorkList\Collection;
use BigCartEnergy\CatalogCompleteness\Model\ResourceModel\WorkList\CollectionFactory as WorkListCollectionFactory;
use BigCartEnergy\CatalogCompleteness\Model\ResourceModel\WorkList\UncoveredCollectionFactory;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Ui\Component\MassAction\Filter as MassActionFilter;
use Psr\Log\LoggerInterface;

/**
 * CSV of the filtered work list (page button) or of the selected rows (mass action). POST with the form key.
 * With the coverage "none", the rows are the products of the scope with no rules assigned.
 */
class Export extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'BigCartEnergy_CatalogCompleteness::export';

    /**
     * @var FileFactory
     */
    private $fileFactory;

    /**
     * @var CsvExport
     */
    private $csvExport;

    /**
     * @var MassActionFilter
     */
    private $massActionFilter;

    /**
     * @var WorkListCollectionFactory
     */
    private $collectionFactory;

    /**
     * @var Statistics
     */
    private $statistics;

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
        FileFactory $fileFactory,
        CsvExport $csvExport,
        MassActionFilter $massActionFilter,
        WorkListCollectionFactory $collectionFactory,
        Statistics $statistics,
        Config $config,
        UncoveredCollectionFactory $uncoveredCollectionFactory,
        LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->fileFactory = $fileFactory;
        $this->csvExport = $csvExport;
        $this->massActionFilter = $massActionFilter;
        $this->collectionFactory = $collectionFactory;
        $this->statistics = $statistics;
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
        try {
            $filter = Filter::fromParams($request->getParams(), $this->config->isEnabledOnlyDefault());
            $collection = $filter->isUncoveredOnly()
                ? $this->uncoveredCollectionFactory->create()
                : $this->collectionFactory->create();
            if ($request->getParam('selected') !== null || $request->getParam('excluded') !== null) {
                // The grid's scope (the primary rows, or the chosen rule set's) keeps each product to one row; the
                // selected ids come from the grid's data provider, every other filter applied.
                $collection->applyDashboardFilter($filter->cleared(), null);
                /** @var Collection $collection */
                $collection = $this->massActionFilter->getCollection($collection);
            } else {
                $average = $filter->getTile() === Filter::TILE_BELOW_AVERAGE ? $this->statistics->getAverage($filter) : null;
                $collection->applyDashboardFilter($filter, $average);
            }
            $file = $this->csvExport->export($collection);
            return $this->fileFactory->create(
                'catalog-completeness-' . gmdate('Y-m-d') . '.csv',
                $file,
                DirectoryList::VAR_DIR,
                'text/csv'
            );
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Throwable $e) {
            // The exception text can hold an SQL statement or a server path: it goes to the log, not the page.
            $this->logger->critical('Catalog completeness: the export failed.', ['exception' => $e]);
            $this->messageManager->addErrorMessage(__('The export failed. The details are in the Magento exception log.'));
        }
        return $this->resultRedirectFactory->create()->setRefererOrBaseUrl();
    }
}
