<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Ui\DataProvider\WorkList;

use BigCartEnergy\CatalogCompleteness\Model\ResourceModel\WorkList\Collection;
use Magento\Framework\Api\Filter;
use Magento\Framework\Data\Collection as DataCollection;
use Magento\Framework\View\Element\UiComponent\DataProvider\FilterApplierInterface;

/**
 * The listing's keyword search ("fulltext" condition) as a LIKE over SKU and name, since the score table
 * has no fulltext index to match against.
 */
class KeywordFilter implements FilterApplierInterface
{
    /**
     * @inheritDoc
     */
    public function apply(DataCollection $collection, Filter $filter)
    {
        if ($collection instanceof Collection) {
            $collection->addKeywordFilter((string)$filter->getValue());
        }
    }
}
