<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Model\Source;

use BigCartEnergy\CatalogCompleteness\Api\Data\RuleInterface;
use BigCartEnergy\CatalogCompleteness\Model\Rule\Describer;
use Magento\Framework\Data\OptionSourceInterface;

class RuleType implements OptionSourceInterface
{
    /**
     * @var Describer
     */
    private $describer;

    public function __construct(Describer $describer)
    {
        $this->describer = $describer;
    }

    /**
     * @inheritDoc
     */
    public function toOptionArray()
    {
        $options = [];
        foreach (RuleInterface::TYPES as $type) {
            $options[] = ['value' => $type, 'label' => $this->describer->typeLabel($type)];
        }
        return $options;
    }
}
