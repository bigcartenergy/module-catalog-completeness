<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Model\ResourceModel\Rule;

use BigCartEnergy\CatalogCompleteness\Model\ResourceModel\Rule as RuleResource;
use BigCartEnergy\CatalogCompleteness\Model\Rule;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'rule_id';

    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init(Rule::class, RuleResource::class);
    }
}
