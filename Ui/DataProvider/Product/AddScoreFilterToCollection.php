<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Ui\DataProvider\Product;

use Magento\Framework\Data\Collection;
use Magento\Ui\DataProvider\AddFilterToCollectionInterface;

/**
 * The score range filter on the product grid. The range filter sends gteq and lteq (investigation D3);
 * products without a rule set have a null score and fall outside any range, as a range filter should.
 */
class AddScoreFilterToCollection implements AddFilterToCollectionInterface
{
    /**
     * @inheritDoc
     */
    public function addFilter(Collection $collection, $field, $condition = null)
    {
        if (!is_array($condition) || !method_exists($collection, 'getSelect')) {
            return;
        }
        AddScoreFieldToCollection::join($collection);
        $column = AddScoreFieldToCollection::tableAlias() . '.score';
        if (isset($condition['gteq']) && is_numeric($condition['gteq'])) {
            $collection->getSelect()->where($column . ' >= ?', (int)$condition['gteq']);
        }
        if (isset($condition['lteq']) && is_numeric($condition['lteq'])) {
            $collection->getSelect()->where($column . ' <= ?', (int)$condition['lteq']);
        }
        if (isset($condition['eq']) && is_numeric($condition['eq'])) {
            $collection->getSelect()->where($column . ' = ?', (int)$condition['eq']);
        }
    }
}
