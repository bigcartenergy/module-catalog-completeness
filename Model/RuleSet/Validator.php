<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Model\RuleSet;

use BigCartEnergy\CatalogCompleteness\Api\Data\RuleInterface;
use BigCartEnergy\CatalogCompleteness\Api\Data\RuleSetInterface;
use BigCartEnergy\CatalogCompleteness\Model\Channel\ChannelRegistry;
use BigCartEnergy\CatalogCompleteness\Model\ResourceModel\RuleSet as RuleSetResource;
use BigCartEnergy\CatalogCompleteness\Model\Rule\CatalogLookupInterface;
use BigCartEnergy\CatalogCompleteness\Model\Rule\PayloadValidator;
use BigCartEnergy\CatalogCompleteness\Model\Rule\Text;

/**
 * Validates a rule set before it is saved, whatever the caller: the admin form, REST or code.
 */
class Validator
{
    public const MAX_NAME_LENGTH = 255;
    public const MAX_DESCRIPTION_LENGTH = 2000;

    /**
     * @var PayloadValidator
     */
    private $payloadValidator;

    /**
     * @var CatalogLookupInterface
     */
    private $lookup;

    /**
     * @var ChannelRegistry
     */
    private $channels;

    /**
     * @var RuleSetResource
     */
    private $resource;

    public function __construct(
        PayloadValidator $payloadValidator,
        CatalogLookupInterface $lookup,
        ChannelRegistry $channels,
        RuleSetResource $resource
    ) {
        $this->payloadValidator = $payloadValidator;
        $this->lookup = $lookup;
        $this->channels = $channels;
        $this->resource = $resource;
    }

    /**
     * Error messages; an empty list means the rule set may be saved. The rules are normalised in place.
     *
     * @return string[]
     */
    public function validate(RuleSetInterface $ruleSet): array
    {
        $errors = [];
        $name = trim((string)$ruleSet->getName());
        if ($name === '') {
            $errors[] = (string)__('Enter a name for the rule set.');
        } elseif (Text::length($name) > self::MAX_NAME_LENGTH) {
            $errors[] = (string)__('The name is too long (at most %1 characters).', self::MAX_NAME_LENGTH);
        }
        $ruleSet->setName($name);

        $kind = (string)$ruleSet->getKind();
        if (!in_array($kind, RuleSetInterface::KINDS, true)) {
            $errors[] = (string)__('Choose a kind: attribute set rules or channel profile.');
            return $errors;
        }

        $description = $ruleSet->getDescription();
        if ($description !== null && Text::length($description) > self::MAX_DESCRIPTION_LENGTH) {
            $errors[] = (string)__('The description is too long (at most %1 characters).', self::MAX_DESCRIPTION_LENGTH);
        }

        if ($kind === RuleSetInterface::KIND_CHANNEL) {
            $channel = $ruleSet->getChannelCode();
            if (!$this->channels->isChannel($channel)) {
                $errors[] = (string)__('Choose a channel for the profile.');
            }
            $ruleSet->setAttributeSetIds([]);
        } else {
            $ruleSet->setChannelCode(null);
            $errors = array_merge($errors, $this->validateAttributeSets($ruleSet));
        }

        $rows = [];
        foreach ($ruleSet->getRules() as $rule) {
            $rows[] = [
                'rule_id' => $rule->getRuleId(),
                'role' => $rule->getRole(),
                'attribute_code' => $rule->getAttributeCode(),
                'rule_type' => $rule->getRuleType(),
                'parameter' => $rule->getParameter(),
                'weight' => $rule->getWeight(),
                'applies_to' => $rule->getAppliesTo(),
            ];
        }
        $result = $this->payloadValidator->validateRules($rows, $kind, $ruleSet->getChannelCode());
        foreach ($result->getErrors() as $error) {
            $errors[] = $error['row'] === null
                ? $error['message']
                : (string)__('Rule %1: %2', $error['row'] + 1, $error['message']);
        }
        if (!$errors) {
            $rules = $ruleSet->getRules();
            foreach ($result->getRules() as $index => $normalised) {
                if (!isset($rules[$index])) {
                    continue;
                }
                $rule = $rules[$index];
                $rule->setRole($normalised['role']);
                $rule->setAttributeCode($normalised['attribute_code']);
                $rule->setRuleType($normalised['rule_type']);
                $rule->setParameter($normalised['parameter']);
                $rule->setWeight($normalised['weight']);
                $rule->setAppliesTo($normalised['applies_to']);
                $rule->setSortOrder($index);
            }
        }
        return $errors;
    }

    /**
     * @return string[]
     */
    private function validateAttributeSets(RuleSetInterface $ruleSet): array
    {
        $errors = [];
        $ids = $ruleSet->getAttributeSetIds();
        if (!$ids) {
            // A rule set without attribute sets covers no product (a duplicate starts this way); it may be saved.
            return $errors;
        }
        $known = $this->lookup->getAttributeSetIds();
        foreach ($ids as $id) {
            if (!in_array($id, $known, true)) {
                $errors[] = (string)__('The attribute set with id %1 does not exist.', $id);
            }
        }
        $conflicts = $this->resource->findConflictingAttributeSets($ids, $ruleSet->getRulesetId());
        if ($conflicts) {
            $errors[] = (string)__(
                'An attribute set can belong to one attribute set rule set only. Already covered: %1.',
                implode(', ', array_keys($conflicts))
            );
        }
        return $errors;
    }
}
