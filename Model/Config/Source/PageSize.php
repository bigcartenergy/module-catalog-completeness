<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Model\Config\Source;

use BigCartEnergy\CatalogCompleteness\Model\Config;
use Magento\Framework\Data\OptionSourceInterface;

class PageSize implements OptionSourceInterface
{
    /**
     * @inheritDoc
     */
    public function toOptionArray()
    {
        $options = [];
        foreach (Config::PAGE_SIZES as $size) {
            $options[] = ['value' => $size, 'label' => (string)$size];
        }
        return $options;
    }
}
