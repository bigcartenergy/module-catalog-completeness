<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Model\Source;

use BigCartEnergy\CatalogCompleteness\Api\Data\RuleSetInterface;
use Magento\Framework\Data\OptionSourceInterface;

class Kind implements OptionSourceInterface
{
    /**
     * @inheritDoc
     */
    public function toOptionArray()
    {
        return [
            ['value' => RuleSetInterface::KIND_ATTRIBUTE_SET, 'label' => __('Attribute set rules')],
            ['value' => RuleSetInterface::KIND_CHANNEL, 'label' => __('Channel profile')],
        ];
    }

    public function getLabel(string $kind): string
    {
        return $kind === RuleSetInterface::KIND_CHANNEL
            ? (string)__('Channel profile')
            : (string)__('Attribute set rules');
    }
}
