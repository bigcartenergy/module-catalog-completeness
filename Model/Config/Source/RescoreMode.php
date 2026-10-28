<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Model\Config\Source;

use BigCartEnergy\CatalogCompleteness\Model\Config;
use Magento\Framework\Data\OptionSourceInterface;

class RescoreMode implements OptionSourceInterface
{
    /**
     * @inheritDoc
     */
    public function toOptionArray()
    {
        return [
            ['value' => Config::RESCORE_MODE_FULL, 'label' => __('Every product')],
            [
                'value' => Config::RESCORE_MODE_CHANGED,
                'label' => __('Products changed since the last run (updated_at)'),
            ],
        ];
    }
}
