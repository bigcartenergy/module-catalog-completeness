<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Model\Source;

use BigCartEnergy\CatalogCompleteness\Model\Band\Resolver;
use Magento\Framework\Data\OptionSourceInterface;

class Band implements OptionSourceInterface
{
    /**
     * @var Resolver
     */
    private $bands;

    public function __construct(Resolver $bands)
    {
        $this->bands = $bands;
    }

    /**
     * @inheritDoc
     */
    public function toOptionArray()
    {
        $options = [];
        foreach (Resolver::CODES as $code) {
            $options[] = ['value' => $code, 'label' => $this->bands->getLabel($code)];
        }
        return $options;
    }
}
