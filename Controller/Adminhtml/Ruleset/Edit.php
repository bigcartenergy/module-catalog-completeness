<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Controller\Adminhtml\Ruleset;

use BigCartEnergy\CatalogCompleteness\Api\RuleSetRepositoryInterface;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Result\PageFactory;

/**
 * New (no id) and Edit. Viewing needs the rule sets permission; the form renders read-only without the
 * edit permission (the Save controller enforces it again).
 */
class Edit extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'BigCartEnergy_CatalogCompleteness::rulesets';

    /**
     * @var PageFactory
     */
    private $pageFactory;

    /**
     * @var RuleSetRepositoryInterface
     */
    private $repository;

    public function __construct(Context $context, PageFactory $pageFactory, RuleSetRepositoryInterface $repository)
    {
        parent::__construct($context);
        $this->pageFactory = $pageFactory;
        $this->repository = $repository;
    }

    /**
     * @inheritDoc
     */
    public function execute()
    {
        $id = (int)$this->getRequest()->getParam('id');
        $title = __('New rule set');
        if ($id > 0) {
            try {
                $title = $this->repository->getById($id)->getName();
            } catch (NoSuchEntityException $e) {
                $this->messageManager->addErrorMessage(
                    __('This rule set could not be loaded. It may have been deleted. Nothing was changed.')
                );
                return $this->resultRedirectFactory->create()->setPath('bce_completeness/ruleset/index');
            }
        } elseif (!$this->_authorization->isAllowed('BigCartEnergy_CatalogCompleteness::rulesets_edit')) {
            $this->messageManager->addErrorMessage(__('Creating a rule set needs the Edit rule sets permission.'));
            return $this->resultRedirectFactory->create()->setPath('bce_completeness/ruleset/index');
        }
        $page = $this->pageFactory->create();
        $page->setActiveMenu('BigCartEnergy_CatalogCompleteness::rulesets');
        $page->getConfig()->getTitle()->prepend($title);
        return $page;
    }
}
