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
use Magento\Framework\View\Result\PageFactory;

class Index extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'BigCartEnergy_CatalogCompleteness::rulesets';

    /**
     * @var PageFactory
     */
    private $pageFactory;

    public function __construct(Context $context, PageFactory $pageFactory)
    {
        parent::__construct($context);
        $this->pageFactory = $pageFactory;
    }

    /**
     * @inheritDoc
     */
    public function execute()
    {
        $page = $this->pageFactory->create();
        $page->setActiveMenu('BigCartEnergy_CatalogCompleteness::rulesets');
        $page->getConfig()->getTitle()->prepend(__('Rule sets'));
        return $page;
    }
}
