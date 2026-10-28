<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Block\Adminhtml\RuleSet\Edit;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

/**
 * Delete posts with the form key after a confirmation; it is never a plain link.
 */
class DeleteButton extends GenericButton implements ButtonProviderInterface
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
            'label' => __('Delete'),
            'class' => 'delete',
            'on_click' => '',
            'data_attribute' => $this->jsButton('delete', [
                'url' => $this->getUrl('bce_completeness/ruleset/delete', ['id' => $id]),
                'title' => (string)__('Delete rule set'),
                'message' => (string)__('Delete this rule set and the scores calculated with it? This cannot be undone.'),
            ]),
            'sort_order' => 20,
        ];
    }
}
