<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Model\Score;

use BigCartEnergy\CatalogCompleteness\Api\Data\RuleInterface;
use BigCartEnergy\CatalogCompleteness\Model\Channel\ChannelRegistry;
use BigCartEnergy\CatalogCompleteness\Model\ResourceModel\Rule as RuleResource;
use BigCartEnergy\CatalogCompleteness\Model\Rule\CatalogLookupInterface;
use BigCartEnergy\CatalogCompleteness\Model\Rule\Describer;
use BigCartEnergy\CatalogCompleteness\Model\RuleFactory;
use BigCartEnergy\CatalogCompleteness\Model\Rule as RuleModel;

/**
 * Turns stored rule ids into the words a merchant reads: the attribute label and the rule in words.
 * Rules are read once per rule set and kept for the request.
 */
class RuleLabels
{
    /**
     * @var RuleResource
     */
    private $ruleResource;

    /**
     * @var RuleFactory
     */
    private $ruleFactory;

    /**
     * @var CatalogLookupInterface
     */
    private $lookup;

    /**
     * @var Describer
     */
    private $describer;

    /**
     * @var ChannelRegistry
     */
    private $channels;

    /**
     * @var array<int, array<int, RuleInterface>> ruleset id => [rule id => rule]
     */
    private $rulesBySet = [];

    public function __construct(
        RuleResource $ruleResource,
        RuleFactory $ruleFactory,
        CatalogLookupInterface $lookup,
        Describer $describer,
        ChannelRegistry $channels
    ) {
        $this->ruleResource = $ruleResource;
        $this->ruleFactory = $ruleFactory;
        $this->lookup = $lookup;
        $this->describer = $describer;
        $this->channels = $channels;
    }

    /**
     * Preload the rules of several rule sets in one query.
     *
     * @param int[] $rulesetIds
     */
    public function preload(array $rulesetIds): void
    {
        $missing = [];
        foreach (array_unique(array_map('intval', $rulesetIds)) as $id) {
            if (!isset($this->rulesBySet[$id])) {
                $missing[] = $id;
                $this->rulesBySet[$id] = [];
            }
        }
        if (!$missing) {
            return;
        }
        foreach ($this->ruleResource->fetchRows($missing) as $row) {
            $rule = $this->ruleFactory->create();
            $row[RuleInterface::APPLIES_TO] = RuleModel::normalizeTypes($row[RuleInterface::APPLIES_TO] ?? '');
            $rule->setData($row);
            $this->rulesBySet[(int)$row['ruleset_id']][(int)$row['rule_id']] = $rule;
        }
    }

    /**
     * @return array<int, RuleInterface> rule id => rule, in rule set order
     */
    public function getRules(int $rulesetId): array
    {
        $this->preload([$rulesetId]);
        return $this->rulesBySet[$rulesetId] ?? [];
    }

    public function getRule(int $rulesetId, int $ruleId): ?RuleInterface
    {
        $rules = $this->getRules($rulesetId);
        return $rules[$ruleId] ?? null;
    }

    /**
     * The attribute's label, or the role label when the rule maps no attribute.
     */
    public function attributeLabel(RuleInterface $rule): string
    {
        $code = (string)$rule->getAttributeCode();
        if ($code !== '') {
            $descriptor = $this->lookup->getDescriptor($code);
            if ($descriptor !== null) {
                return $descriptor->getLabel();
            }
            return $code;
        }
        $role = $this->channels->getRoleLabel($rule->getRole());
        return $role !== '' ? $role : (string)__('No attribute');
    }

    /**
     * The rule in words, e.g. "Description at least 200 characters".
     */
    public function describe(RuleInterface $rule): string
    {
        $code = (string)$rule->getAttributeCode();
        $descriptor = $code !== '' ? $this->lookup->getDescriptor($code) : null;
        return $this->describer->describe(
            (string)$rule->getRuleType(),
            $rule->getParameter(),
            $this->attributeLabel($rule),
            $descriptor !== null && $descriptor->isGallery()
        );
    }

    /**
     * Descriptions of the given rule ids of one rule set, in the order given; unknown ids are skipped.
     *
     * @param int[] $ruleIds
     * @return string[]
     */
    public function describeIds(int $rulesetId, array $ruleIds): array
    {
        $labels = [];
        foreach ($ruleIds as $ruleId) {
            $rule = $this->getRule($rulesetId, (int)$ruleId);
            if ($rule !== null) {
                $labels[] = $this->describe($rule);
            }
        }
        return $labels;
    }

    /**
     * Attribute labels of the given rule ids, for the work list tags.
     *
     * @param int[] $ruleIds
     * @return string[]
     */
    public function attributeLabelsOfIds(int $rulesetId, array $ruleIds): array
    {
        $labels = [];
        foreach ($ruleIds as $ruleId) {
            $rule = $this->getRule($rulesetId, (int)$ruleId);
            if ($rule !== null) {
                $labels[] = $this->attributeLabel($rule);
            }
        }
        return $labels;
    }

    public function weightLabel(RuleInterface $rule): string
    {
        return $this->describer->weightLabel((int)$rule->getWeight());
    }
}
