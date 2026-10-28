<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Api;

/**
 * REST surface. Reads stored scores and rule sets; the only write is a recalculation of one product.
 *
 * @api
 */
interface CompletenessManagementInterface
{
    /**
     * Stored scores of one product, one entry per rule set that applies to it.
     *
     * @param int $productId
     * @return \BigCartEnergy\CatalogCompleteness\Api\Data\ProductScoreInterface[]
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getProductScores($productId);

    /**
     * Rescore one product now and return its fresh scores.
     *
     * @param int $productId
     * @return \BigCartEnergy\CatalogCompleteness\Api\Data\ProductScoreInterface[]
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function recalculateProduct($productId);

    /**
     * Every rule set with its rules.
     *
     * @return \BigCartEnergy\CatalogCompleteness\Api\Data\RuleSetInterface[]
     */
    public function getRuleSets();
}
