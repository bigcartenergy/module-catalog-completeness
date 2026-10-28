<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Block\Adminhtml\RuleSet\Edit;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

class BackButton extends GenericButton implements ButtonProviderInterface
{
    /**
     * @inheritDoc
     */
    public function getButtonData()
    {
        return [
            'label' => __('Back'),
            'class' => 'back',
            'on_click' => '',
            'data_attribute' => $this->jsButton('back', ['url' => $this->getUrl('bce_completeness/ruleset/index')]),
            'sort_order' => 10,
        ];
    }
}
