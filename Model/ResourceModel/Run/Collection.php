<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Model\ResourceModel\Run;

use BigCartEnergy\CatalogCompleteness\Model\ResourceModel\Run as RunResource;
use BigCartEnergy\CatalogCompleteness\Model\Run;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'run_id';

    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init(Run::class, RunResource::class);
    }
}
