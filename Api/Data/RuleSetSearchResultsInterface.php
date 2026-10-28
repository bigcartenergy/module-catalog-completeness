<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Api\Data;

use Magento\Framework\Api\SearchResultsInterface;

/**
 * @api
 */
interface RuleSetSearchResultsInterface extends SearchResultsInterface
{
    /**
     * @return \BigCartEnergy\CatalogCompleteness\Api\Data\RuleSetInterface[]
     */
    public function getItems();

    /**
     * @param \BigCartEnergy\CatalogCompleteness\Api\Data\RuleSetInterface[] $items
     * @return $this
     */
    public function setItems(array $items);
}
