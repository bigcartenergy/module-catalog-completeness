<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Model\ResourceModel\Score;

use BigCartEnergy\CatalogCompleteness\Model\ResourceModel\Score;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * The score table as a plain resource model keyed by product, so the work list's search result names the
 * same id field that its selections carry (Magento's mass-action filter matches the selected ids against the
 * collection's resource id field).
 */
class Row extends AbstractDb
{
    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        // The work list shows one row per product, and the grid's selections are product ids.
        $this->_init(Score::TABLE, 'product_id');
    }
}
