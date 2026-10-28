<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Model;

use BigCartEnergy\CatalogCompleteness\Api\Data\RuleSetInterface;
use BigCartEnergy\CatalogCompleteness\Api\Data\RuleSetSearchResultsInterface;
use BigCartEnergy\CatalogCompleteness\Api\Data\RuleSetSearchResultsInterfaceFactory;
use BigCartEnergy\CatalogCompleteness\Api\RuleSetRepositoryInterface;
use BigCartEnergy\CatalogCompleteness\Model\Indexer\Status as IndexerStatus;
use BigCartEnergy\CatalogCompleteness\Model\ResourceModel\RuleSet as RuleSetResource;
use BigCartEnergy\CatalogCompleteness\Model\ResourceModel\RuleSet\CollectionFactory;
use BigCartEnergy\CatalogCompleteness\Model\RuleSet\Validator;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\ValidatorException;

class RuleSetRepository implements RuleSetRepositoryInterface
{
    /**
     * @var RuleSetResource
     */
    private $resource;

    /**
     * @var RuleSetFactory
     */
    private $ruleSetFactory;

    /**
     * @var CollectionFactory
     */
    private $collectionFactory;

    /**
     * @var CollectionProcessorInterface
     */
    private $collectionProcessor;

    /**
     * @var RuleSetSearchResultsInterfaceFactory
     */
    private $searchResultsFactory;

    /**
     * @var Validator
     */
    private $validator;

    /**
     * @var IndexerStatus
     */
    private $indexerStatus;

    /**
     * @var array<int, RuleSetInterface>
     */
    private $byId = [];

    public function __construct(
        RuleSetResource $resource,
        RuleSetFactory $ruleSetFactory,
        CollectionFactory $collectionFactory,
        CollectionProcessorInterface $collectionProcessor,
        RuleSetSearchResultsInterfaceFactory $searchResultsFactory,
        Validator $validator,
        IndexerStatus $indexerStatus
    ) {
        $this->resource = $resource;
        $this->ruleSetFactory = $ruleSetFactory;
        $this->collectionFactory = $collectionFactory;
        $this->collectionProcessor = $collectionProcessor;
        $this->searchResultsFactory = $searchResultsFactory;
        $this->validator = $validator;
        $this->indexerStatus = $indexerStatus;
    }

    /**
     * @inheritDoc
     */
    public function save(RuleSetInterface $ruleSet)
    {
        $errors = $this->validator->validate($ruleSet);
        if ($errors) {
            throw new ValidatorException(__(implode(' ', $errors)));
        }
        $wasEnabled = $this->isStoredEnabled((int)$ruleSet->getRulesetId());
        try {
            $this->resource->save($ruleSet);
        } catch (LocalizedException $e) {
            throw new CouldNotSaveException(__('The rule set could not be saved: %1', $e->getMessage()), $e);
        } catch (\Exception $e) {
            throw new CouldNotSaveException(__('The rule set could not be saved.'), $e);
        }
        unset($this->byId[(int)$ruleSet->getRulesetId()]);
        // Stored scores depend on the rules only while a set is enabled. A set that is enabled now, or was until
        // this save, leaves them stale until a recalculation or the scheduled run; saving a disabled set (a
        // duplicate, for example) changes no score and leaves the index as it is.
        if ($wasEnabled || $ruleSet->getIsEnabled()) {
            $this->indexerStatus->invalidate();
        }
        return $this->getById((int)$ruleSet->getRulesetId());
    }

    /**
     * The enabled flag as stored before a save, read fresh so an edited instance cannot answer for its old state.
     */
    private function isStoredEnabled(int $rulesetId): bool
    {
        if ($rulesetId <= 0) {
            return false;
        }
        $stored = $this->ruleSetFactory->create();
        $this->resource->load($stored, $rulesetId);
        return (int)$stored->getRulesetId() > 0 && (bool)$stored->getIsEnabled();
    }

    /**
     * @inheritDoc
     */
    public function getById($rulesetId)
    {
        $rulesetId = (int)$rulesetId;
        if (isset($this->byId[$rulesetId])) {
            return $this->byId[$rulesetId];
        }
        $ruleSet = $this->ruleSetFactory->create();
        $this->resource->load($ruleSet, $rulesetId);
        if (!$ruleSet->getId()) {
            throw new NoSuchEntityException(__('The rule set with id %1 does not exist.', $rulesetId));
        }
        $this->byId[$rulesetId] = $ruleSet;
        return $ruleSet;
    }

    /**
     * @inheritDoc
     */
    public function getList(SearchCriteriaInterface $searchCriteria)
    {
        $collection = $this->collectionFactory->create();
        $this->collectionProcessor->process($searchCriteria, $collection);
        /** @var RuleSetSearchResultsInterface $results */
        $results = $this->searchResultsFactory->create();
        $results->setSearchCriteria($searchCriteria);
        $results->setItems(array_values($collection->getItems()));
        $results->setTotalCount($collection->getSize());
        return $results;
    }

    /**
     * Every rule set, enabled or not, in name order.
     *
     * @return RuleSetInterface[]
     */
    public function getAll(): array
    {
        $collection = $this->collectionFactory->create();
        $collection->setOrder('name', 'ASC');
        return array_values($collection->getItems());
    }

    /**
     * @inheritDoc
     */
    public function delete(RuleSetInterface $ruleSet)
    {
        try {
            $this->resource->delete($ruleSet);
        } catch (\Exception $e) {
            throw new CouldNotDeleteException(__('The rule set could not be deleted.'), $e);
        }
        unset($this->byId[(int)$ruleSet->getRulesetId()]);
        return true;
    }

    /**
     * @inheritDoc
     */
    public function deleteById($rulesetId)
    {
        return $this->delete($this->getById($rulesetId));
    }

    public function clearCache(): void
    {
        $this->byId = [];
    }
}
