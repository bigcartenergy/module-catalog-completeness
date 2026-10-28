<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Controller\Adminhtml\Ruleset;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\ForwardFactory;

class NewAction extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'BigCartEnergy_CatalogCompleteness::rulesets_edit';

    /**
     * @var ForwardFactory
     */
    private $forwardFactory;

    public function __construct(Context $context, ForwardFactory $forwardFactory)
    {
        parent::__construct($context);
        $this->forwardFactory = $forwardFactory;
    }

    /**
     * @inheritDoc
     */
    public function execute()
    {
        $forward = $this->forwardFactory->create();
        return $forward->forward('edit');
    }
}
