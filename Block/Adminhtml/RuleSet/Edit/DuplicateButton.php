<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Block\Adminhtml\RuleSet\Edit;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

class DuplicateButton extends GenericButton implements ButtonProviderInterface
{
    /**
     * @inheritDoc
     */
    public function getButtonData()
    {
        $id = $this->getRuleSetId();
        if ($id <= 0 || !$this->canEdit()) {
            return [];
        }
        return [
            'label' => __('Duplicate'),
            'class' => 'duplicate',
            'on_click' => '',
            'data_attribute' => $this->jsButton('post', [
                'url' => $this->getUrl('bce_completeness/ruleset/duplicate', ['id' => $id]),
            ]),
            'sort_order' => 30,
        ];
    }
}
