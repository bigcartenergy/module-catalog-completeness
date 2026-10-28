<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Model\Source;

use BigCartEnergy\CatalogCompleteness\Api\Data\RuleInterface;
use BigCartEnergy\CatalogCompleteness\Model\Channel\ChannelRegistry;
use Magento\Framework\Data\OptionSourceInterface;

/**
 * Every role of every channel, once each. The rule row's Role select starts with this full list so a stored
 * or prefilled role is always among its options; the row then narrows the list to the profile's channel.
 */
class Role implements OptionSourceInterface
{
    /**
     * @var ChannelRegistry
     */
    private $channels;

    public function __construct(ChannelRegistry $channels)
    {
        $this->channels = $channels;
    }

    /**
     * @inheritDoc
     */
    public function toOptionArray()
    {
        $options = [];
        $seen = [];
        foreach ($this->channels->getChannelCodes() as $channelCode) {
            foreach ($this->channels->getRoles($channelCode) as $roleCode => $label) {
                if ($roleCode === RuleInterface::ROLE_CUSTOM || isset($seen[$roleCode])) {
                    continue;
                }
                $seen[$roleCode] = true;
                $options[] = ['value' => (string)$roleCode, 'label' => (string)$label];
            }
        }
        $options[] = [
            'value' => RuleInterface::ROLE_CUSTOM,
            'label' => $this->channels->getRoleLabel(RuleInterface::ROLE_CUSTOM),
        ];
        return $options;
    }
}
