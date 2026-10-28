<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Ui\DataProvider\WorkList;

use BigCartEnergy\CatalogCompleteness\Model\Band\Resolver as BandResolver;
use BigCartEnergy\CatalogCompleteness\Model\Config;
use BigCartEnergy\CatalogCompleteness\Model\Dashboard\Filter;
use BigCartEnergy\CatalogCompleteness\Model\Dashboard\Statistics;
use BigCartEnergy\CatalogCompleteness\Model\ResourceModel\WorkList\Collection;
use BigCartEnergy\CatalogCompleteness\Model\ResourceModel\WorkList\UncoveredCollectionFactory;
use Magento\Framework\Api\Filter as ApiFilter;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\SearchCriteriaBuilder;
use Magento\Framework\Api\Search\SearchResultInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Element\UiComponent\DataProvider\DataProvider as UiDataProvider;
use Magento\Framework\View\Element\UiComponent\DataProvider\FilterPool;
use Magento\Framework\Api\Search\ReportingInterface;

/**
 * Data provider of the work list. The dashboard filter travels as request parameters on the grid's
 * update URL, so the page and every grid reload agree; a "band" column filter becomes a score range.
 * With the coverage "none", the rows are the products of the scope with no rules assigned instead.
 */
class DataProvider extends UiDataProvider
{
    /**
     * The grid's columns that only a score row fills; with the band, their filters step aside in the
     * "No rules assigned" view, where no row has a score.
     */
    private const SCORE_FIELDS = ['score', 'scored_at'];

    /**
     * @var Config
     */
    private $config;

    /**
     * @var Statistics
     */
    private $statistics;

    /**
     * @var BandResolver
     */
    private $bands;

    /**
     * @var Filter|null
     */
    private $dashboardFilter;

    /**
     * @var UncoveredCollectionFactory
     */
    private $uncoveredCollectionFactory;

    /**
     * @var FilterPool
     */
    private $filterPool;

    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        ReportingInterface $reporting,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        RequestInterface $request,
        FilterBuilder $filterBuilder,
        Config $config,
        Statistics $statistics,
        BandResolver $bands,
        UncoveredCollectionFactory $uncoveredCollectionFactory,
        FilterPool $filterPool,
        array $meta = [],
        array $data = []
    ) {
        $this->config = $config;
        $this->statistics = $statistics;
        $this->bands = $bands;
        $this->uncoveredCollectionFactory = $uncoveredCollectionFactory;
        $this->filterPool = $filterPool;
        parent::__construct(
            $name,
            $primaryFieldName,
            $requestFieldName,
            $reporting,
            $searchCriteriaBuilder,
            $request,
            $filterBuilder,
            $meta,
            $data
        );
    }

    /**
     * Carry the dashboard parameters on the grid's update URL, URL-safe.
     *
     * @inheritDoc
     */
    protected function prepareUpdateUrl()
    {
        if (!isset($this->data['config']['update_url'])) {
            return;
        }
        foreach ($this->getDashboardFilter()->toParams() as $name => $value) {
            $this->data['config']['update_url'] = sprintf(
                '%s%s/%s/',
                $this->data['config']['update_url'],
                $name,
                rawurlencode($value)
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function addFilter(ApiFilter $filter)
    {
        if ($filter->getField() === 'band') {
            $band = (string)$filter->getValue();
            // A product with no rules assigned has no band, so the column filter does not apply to that view.
            if (in_array($band, BandResolver::CODES, true) && !$this->getDashboardFilter()->isUncoveredOnly()) {
                $range = $this->bands->getRange($band);
                parent::addFilter($this->filterBuilder->setField('score')->setConditionType('gteq')->setValue($range[0])->create());
                parent::addFilter($this->filterBuilder->setField('score')->setConditionType('lteq')->setValue($range[1])->create());
            }
            return;
        }
        // It has no score or last-scored date either, so the Score and Last scored filters step aside there too.
        if (in_array($filter->getField(), self::SCORE_FIELDS, true) && $this->getDashboardFilter()->isUncoveredOnly()) {
            return;
        }
        parent::addFilter($filter);
    }

    /**
     * @inheritDoc
     */
    public function getSearchResult()
    {
        $dashboardFilter = $this->getDashboardFilter();
        if ($dashboardFilter->isUncoveredOnly()) {
            // The products with no rules assigned have no score row, so the rows come from the product entity;
            // the search criteria apply to them the way the reporting applies them to the scored list.
            $collection = $this->uncoveredCollectionFactory->create();
            $criteria = $this->getSearchCriteria();
            $collection->setPageSize($criteria->getPageSize());
            $collection->setCurPage($criteria->getCurrentPage());
            $this->filterPool->applyFilters($collection, $criteria);
            foreach ($criteria->getSortOrders() as $sortOrder) {
                if ($sortOrder->getField()) {
                    $collection->setOrder($sortOrder->getField(), $sortOrder->getDirection());
                }
            }
            $collection->applyDashboardFilter($dashboardFilter, null);
            return $collection;
        }
        $result = parent::getSearchResult();
        if ($result instanceof Collection) {
            $filter = $this->getDashboardFilter();
            $average = $filter->getTile() === Filter::TILE_BELOW_AVERAGE
                ? $this->statistics->getAverage($filter)
                : null;
            $result->applyDashboardFilter($filter, $average);
        }
        return $result;
    }

    /**
     * @inheritDoc
     */
    public function getData()
    {
        $data = parent::getData();
        $data['bce_params'] = $this->getDashboardFilter()->toParams();
        return $data;
    }

    public function getDashboardFilter(): Filter
    {
        if ($this->dashboardFilter === null) {
            $params = [];
            foreach (Filter::PARAMS as $name) {
                $value = $this->request->getParam($name);
                if ($value !== null && $value !== '') {
                    $params[$name] = $value;
                }
            }
            $this->dashboardFilter = Filter::fromParams($params, $this->config->isEnabledOnlyDefault());
        }
        return $this->dashboardFilter;
    }

    /**
     * Items come out of the collection as flat rows.
     *
     * @inheritDoc
     */
    protected function searchResultToOutput(SearchResultInterface $searchResult)
    {
        $items = [];
        foreach ($searchResult->getItems() as $item) {
            $items[] = $item->getData();
        }
        return [
            'items' => $items,
            'totalRecords' => $searchResult->getTotalCount(),
        ];
    }
}
