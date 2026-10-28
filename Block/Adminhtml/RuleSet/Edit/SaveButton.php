<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Block\Adminhtml\RuleSet\Edit;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

/**
 * Save: stores the rules and marks the scores stale until the next recalculation.
 */
class SaveButton extends GenericButton implements ButtonProviderInterface
{
    /**
     * @inheritDoc
     */
    public function getButtonData()
    {
        if (!$this->canEdit()) {
            return [];
        }
        return [
            'label' => __('Save'),
            'class' => 'save',
            'data_attribute' => [
                'mage-init' => [
                    'buttonAdapter' => [
                        'actions' => [
                            [
                                'targetName' => self::FORM_TARGET,
                                'actionName' => 'save',
                                'params' => [false, ['recalculate' => '0']],
                            ],
                        ],
                    ],
                ],
            ],
            'sort_order' => 40,
        ];
    }
}
