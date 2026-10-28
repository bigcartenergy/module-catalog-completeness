<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Controller\Adminhtml\Ruleset;

use BigCartEnergy\CatalogCompleteness\Api\Data\RuleInterface;
use BigCartEnergy\CatalogCompleteness\Api\Data\RuleSetInterface;
use BigCartEnergy\CatalogCompleteness\Api\RuleSetRepositoryInterface;
use BigCartEnergy\CatalogCompleteness\Model\Queue\RunManager;
use BigCartEnergy\CatalogCompleteness\Model\RuleFactory;
use BigCartEnergy\CatalogCompleteness\Model\RuleSetFactory;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Save, or Save and recalculate. POST with the form key; every value goes through the validator.
 */
class Save extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'BigCartEnergy_CatalogCompleteness::rulesets_edit';
    public const PERSIST_KEY = 'bce_completeness_ruleset';

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

    /**
     * @var DataPersistorInterface
     */
    private $dataPersistor;

    /**
     * @var RunManager
     */
    private $runManager;

    public function __construct(
        Context $context,
        RuleSetRepositoryInterface $repository,
        RuleSetFactory $ruleSetFactory,
        RuleFactory $ruleFactory,
        DataPersistorInterface $dataPersistor,
        RunManager $runManager
    ) {
        parent::__construct($context);
        $this->repository = $repository;
        $this->ruleSetFactory = $ruleSetFactory;
        $this->ruleFactory = $ruleFactory;
        $this->dataPersistor = $dataPersistor;
        $this->runManager = $runManager;
    }

    /**
     * @inheritDoc
     */
    public function execute()
    {
        $redirect = $this->resultRedirectFactory->create();
        $request = $this->getRequest();
        $id = (int)$request->getParam('ruleset_id');
        $recalculate = (string)$request->getParam('recalculate') === '1';

        try {
            $ruleSet = $id > 0 ? $this->repository->getById($id) : $this->ruleSetFactory->create();
        } catch (NoSuchEntityException $e) {
            $this->messageManager->addErrorMessage(__('This rule set no longer exists.'));
            return $redirect->setPath('bce_completeness/ruleset/index');
        }

        $ruleSet->setName((string)$request->getParam('name'));
        $kind = (string)$request->getParam('kind');
        $ruleSet->setKind(in_array($kind, RuleSetInterface::KINDS, true) ? $kind : RuleSetInterface::KIND_ATTRIBUTE_SET);
        $channel = $request->getParam('channel_code');
        $ruleSet->setChannelCode(is_string($channel) && $channel !== '' ? $channel : null);
        $description = $request->getParam('description');
        $ruleSet->setDescription(is_string($description) ? trim($description) : null);
        $ruleSet->setIsEnabled((string)$request->getParam('is_enabled') === '1');
        $sets = $request->getParam('attribute_set_ids');
        if (is_string($sets)) {
            $sets = $sets === '' ? [] : explode(',', $sets);
        }
        $ruleSet->setAttributeSetIds(is_array($sets) ? array_map('intval', $sets) : []);
        $ruleSet->setRules($this->rulesFromRequest($request->getParam('rules')));

        try {
            $saved = $this->repository->save($ruleSet);
        } catch (LocalizedException $e) {
            foreach (array_filter(array_map('trim', explode("\n", (string)$e->getMessage()))) as $line) {
                $this->messageManager->addErrorMessage($line);
            }
            $this->dataPersistor->set(self::PERSIST_KEY, $this->persistable($request->getParams()));
            return $redirect->setPath('bce_completeness/ruleset/edit', $id > 0 ? ['id' => $id] : []);
        }
        $this->dataPersistor->clear(self::PERSIST_KEY);

        $savedId = (int)$saved->getRulesetId();
        if ($recalculate && $this->_authorization->isAllowed('BigCartEnergy_CatalogCompleteness::recalculate')) {
            $this->runManager->queue($savedId);
            return $redirect->setPath('bce_completeness/ruleset/edit', ['id' => $savedId, 'saved' => 'recalculating']);
        }
        return $redirect->setPath('bce_completeness/ruleset/edit', ['id' => $savedId, 'saved' => 'stale']);
    }

    /**
     * @param mixed $raw
     * @return RuleInterface[]
     */
    private function rulesFromRequest($raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $rules = [];
        foreach ($raw as $row) {
            if (!is_array($row)) {
                continue;
            }
            $rule = $this->ruleFactory->create();
            $rule->setRuleId(isset($row['rule_id']) && (int)$row['rule_id'] > 0 ? (int)$row['rule_id'] : null);
            $rule->setRole(isset($row['role']) && is_string($row['role']) ? $row['role'] : null);
            $rule->setAttributeCode(isset($row['attribute_code']) && is_string($row['attribute_code']) ? $row['attribute_code'] : '');
            $rule->setRuleType(isset($row['rule_type']) && is_string($row['rule_type']) ? $row['rule_type'] : '');
            $rule->setParameter(isset($row['parameter']) && is_string($row['parameter']) ? $row['parameter'] : null);
            $rule->setWeight(isset($row['weight']) && is_numeric($row['weight']) ? (int)$row['weight'] : 0);
            $rule->setAppliesTo(\BigCartEnergy\CatalogCompleteness\Model\Rule::normalizeTypes($row['applies_to'] ?? []));
            $rules[] = $rule;
        }
        return $rules;
    }

    /**
     * Only the form's own fields are kept for redisplay.
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function persistable(array $params): array
    {
        $keep = ['ruleset_id', 'name', 'kind', 'channel_code', 'description', 'is_enabled', 'attribute_set_ids', 'rules'];
        $out = [];
        foreach ($keep as $key) {
            if (array_key_exists($key, $params)) {
                $out[$key] = $params[$key];
            }
        }
        return $out;
    }
}
