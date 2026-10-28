<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Controller\Adminhtml\Ruleset;

use BigCartEnergy\CatalogCompleteness\Api\Data\RuleSetInterface;
use BigCartEnergy\CatalogCompleteness\Api\RuleSetRepositoryInterface;
use BigCartEnergy\CatalogCompleteness\Model\RuleFactory;
use BigCartEnergy\CatalogCompleteness\Model\RuleSetFactory;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Exception\LocalizedException;

/**
 * Copies a rule set with its rules. The copy starts disabled; an attribute set copy starts with no
 * attribute sets, since a set belongs to one rule set only.
 */
class Duplicate extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'BigCartEnergy_CatalogCompleteness::rulesets_edit';

    /**
     * @var RuleSetRepositoryInterface
     */
    private $repository;

    /**
     * @var RuleSetFactory
     */
    private $ruleSetFactory;

    /**
     * @var RuleFactory
     */
    private $ruleFactory;

    public function __construct(
        Context $context,
        RuleSetRepositoryInterface $repository,
        RuleSetFactory $ruleSetFactory,
        RuleFactory $ruleFactory
    ) {
        parent::__construct($context);
        $this->repository = $repository;
        $this->ruleSetFactory = $ruleSetFactory;
        $this->ruleFactory = $ruleFactory;
    }

    /**
     * @inheritDoc
     */
    public function execute()
    {
        $redirect = $this->resultRedirectFactory->create();
        $id = (int)$this->getRequest()->getParam('id');
        try {
            $source = $this->repository->getById($id);
            $copy = $this->ruleSetFactory->create();
            $copy->setName((string)__('Copy of %1', $source->getName()));
            $copy->setKind($source->getKind());
            $copy->setChannelCode($source->getChannelCode());
            $copy->setDescription($source->getDescription());
            $copy->setIsEnabled(false);
            $copy->setAttributeSetIds([]);
            $rules = [];
            foreach ($source->getRules() as $rule) {
                $clone = $this->ruleFactory->create();
                $clone->setRole($rule->getRole());
                $clone->setAttributeCode($rule->getAttributeCode());
                $clone->setRuleType($rule->getRuleType());
                $clone->setParameter($rule->getParameter());
                $clone->setWeight($rule->getWeight());
                $clone->setAppliesTo($rule->getAppliesTo());
                $rules[] = $clone;
            }
            $copy->setRules($rules);
            $saved = $this->repository->save($copy);
            $this->messageManager->addSuccessMessage(
                $source->getKind() === RuleSetInterface::KIND_ATTRIBUTE_SET
                    ? __('You duplicated the rule set. The copy is disabled and has no attribute sets yet; choose them, then enable it.')
                    : __('You duplicated the rule set. The copy is disabled until you enable it.')
            );
            return $redirect->setPath('bce_completeness/ruleset/edit', ['id' => (int)$saved->getRulesetId()]);
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }
        return $redirect->setPath('bce_completeness/ruleset/index');
    }
}
