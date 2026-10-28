<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Model\Source;

use BigCartEnergy\CatalogCompleteness\Model\Channel\ChannelRegistry;
use Magento\Framework\Data\OptionSourceInterface;

class Channel implements OptionSourceInterface
{
    /**
     * @var ChannelRegistry
     */
    private $registry;

    public function __construct(ChannelRegistry $registry)
    {
        $this->registry = $registry;
    }

    /**
     * @inheritDoc
     */
    public function toOptionArray()
    {
        $options = [];
        foreach ($this->registry->getChannelLabels() as $code => $label) {
            $options[] = ['value' => $code, 'label' => $label];
        }
        return $options;
    }
}
