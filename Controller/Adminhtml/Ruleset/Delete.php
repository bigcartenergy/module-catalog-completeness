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
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Exception\LocalizedException;

/**
 * Delete is POST only, with the form key; never a link.
 */
class Delete extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'BigCartEnergy_CatalogCompleteness::rulesets_edit';

    /**
     * @var RuleSetRepositoryInterface
     */
    private $repository;

    public function __construct(Context $context, RuleSetRepositoryInterface $repository)
    {
        parent::__construct($context);
        $this->repository = $repository;
    }

    /**
     * @inheritDoc
     */
    public function execute()
    {
        $redirect = $this->resultRedirectFactory->create()->setPath('bce_completeness/ruleset/index');
        $id = (int)$this->getRequest()->getParam('id');
        if ($id <= 0) {
            $this->messageManager->addErrorMessage(__('Choose a rule set to delete.'));
            return $redirect;
        }
        try {
            $name = $this->repository->getById($id)->getName();
            $this->repository->deleteById($id);
            $this->messageManager->addSuccessMessage(__('You deleted the rule set "%1".', $name));
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }
        return $redirect;
    }
}
