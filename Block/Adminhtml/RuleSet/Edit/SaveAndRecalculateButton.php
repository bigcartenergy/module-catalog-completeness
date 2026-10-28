<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Block\Adminhtml\RuleSet\Edit;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

/**
 * Save and recalculate: stores the rules and queues a run for this rule set.
 */
class SaveAndRecalculateButton extends GenericButton implements ButtonProviderInterface
{
    public const ACL_RECALCULATE = 'BigCartEnergy_CatalogCompleteness::recalculate';

    /**
     * @inheritDoc
     */
    public function getButtonData()
    {
        if (!$this->canEdit() || !$this->authorization->isAllowed(self::ACL_RECALCULATE)) {
            return [];
        }
        return [
            'label' => __('Save and recalculate'),
            'class' => 'save primary',
            'data_attribute' => [
                'mage-init' => [
                    'buttonAdapter' => [
                        'actions' => [
                            [
                                'targetName' => self::FORM_TARGET,
                                'actionName' => 'save',
                                'params' => [false, ['recalculate' => '1']],
                            ],
                        ],
                    ],
                ],
            ],
            'sort_order' => 50,
        ];
    }
}
