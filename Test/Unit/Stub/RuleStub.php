<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Test\Unit\Stub;

use BigCartEnergy\CatalogCompleteness\Api\Data\RuleInterface;

/**
 * A plain rule for tests, free of the framework model layer.
 */
class RuleStub implements RuleInterface
{
    /**
     * @var array<string, mixed>
     */
    private $data;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data = [])
    {
        $this->data = array_merge([
            'rule_id' => null,
            'ruleset_id' => null,
            'sort_order' => 0,
            'role' => null,
            'attribute_code' => '',
            'rule_type' => self::TYPE_PRESENT,
            'parameter' => null,
            'weight' => self::WEIGHT_REQUIRED,
            'applies_to' => [],
        ], $data);
    }

    public static function make(string $attributeCode, string $type, $parameter = null, int $weight = 3, array $appliesTo = [], ?int $id = null): self
    {
        return new self([
            'rule_id' => $id,
            'attribute_code' => $attributeCode,
            'rule_type' => $type,
            'parameter' => $parameter === null ? null : (string)$parameter,
            'weight' => $weight,
            'applies_to' => $appliesTo,
        ]);
    }

    public function getRuleId()
    {
        return $this->data['rule_id'];
    }

    public function setRuleId($ruleId)
    {
        $this->data['rule_id'] = $ruleId;
        return $this;
    }

    public function getRulesetId()
    {
        return $this->data['ruleset_id'];
    }

    public function setRulesetId($rulesetId)
    {
        $this->data['ruleset_id'] = $rulesetId;
        return $this;
    }

    public function getSortOrder()
    {
        return (int)$this->data['sort_order'];
    }

    public function setSortOrder($sortOrder)
    {
        $this->data['sort_order'] = (int)$sortOrder;
        return $this;
    }

    public function getRole()
    {
        return $this->data['role'];
    }

    public function setRole($role)
    {
        $this->data['role'] = $role;
        return $this;
    }

    public function getAttributeCode()
    {
        return (string)$this->data['attribute_code'];
    }

    public function setAttributeCode($attributeCode)
    {
        $this->data['attribute_code'] = (string)$attributeCode;
        return $this;
    }

    public function getRuleType()
    {
        return (string)$this->data['rule_type'];
    }

    public function setRuleType($ruleType)
    {
        $this->data['rule_type'] = (string)$ruleType;
        return $this;
    }

    public function getParameter()
    {
        return $this->data['parameter'];
    }

    public function setParameter($parameter)
    {
        $this->data['parameter'] = $parameter;
        return $this;
    }

    public function getWeight()
    {
        return (int)$this->data['weight'];
    }

    public function setWeight($weight)
    {
        $this->data['weight'] = (int)$weight;
        return $this;
    }

    public function getAppliesTo()
    {
        return $this->data['applies_to'];
    }

    public function setAppliesTo(array $typeIds)
    {
        $this->data['applies_to'] = $typeIds;
        return $this;
    }
}
