<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Model\Source;

use BigCartEnergy\CatalogCompleteness\Model\Catalog\Lookup;
use Magento\Framework\Data\OptionSourceInterface;

class ProductType implements OptionSourceInterface
{
    /**
     * @var Lookup
     */
    private $lookup;

    public function __construct(Lookup $lookup)
    {
        $this->lookup = $lookup;
    }

    /**
     * @inheritDoc
     */
    public function toOptionArray()
    {
        $options = [];
        foreach ($this->lookup->getProductTypeLabels() as $typeId => $label) {
            $options[] = ['value' => $typeId, 'label' => $label];
        }
        return $options;
    }
}
