<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Api;

use BigCartEnergy\CatalogCompleteness\Api\Data\RuleSetInterface;
use BigCartEnergy\CatalogCompleteness\Api\Data\RuleSetSearchResultsInterface;
use Magento\Framework\Api\SearchCriteriaInterface;

/**
 * @api
 */
interface RuleSetRepositoryInterface
{
    /**
     * Validate and save a rule set with its attribute sets and rules. Invalidates the completeness indexer.
     *
     * @param \BigCartEnergy\CatalogCompleteness\Api\Data\RuleSetInterface $ruleSet
     * @return \BigCartEnergy\CatalogCompleteness\Api\Data\RuleSetInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function save(RuleSetInterface $ruleSet);

    /**
     * @param int $rulesetId
     * @return \BigCartEnergy\CatalogCompleteness\Api\Data\RuleSetInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getById($rulesetId);

    /**
     * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
     * @return \BigCartEnergy\CatalogCompleteness\Api\Data\RuleSetSearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria);

    /**
     * @param \BigCartEnergy\CatalogCompleteness\Api\Data\RuleSetInterface $ruleSet
     * @return bool
     * @throws \Magento\Framework\Exception\CouldNotDeleteException
     */
    public function delete(RuleSetInterface $ruleSet);

    /**
     * @param int $rulesetId
     * @return bool
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\CouldNotDeleteException
     */
    public function deleteById($rulesetId);
}
