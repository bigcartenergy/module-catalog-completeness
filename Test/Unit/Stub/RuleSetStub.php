<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Test\Unit\Stub;

use BigCartEnergy\CatalogCompleteness\Api\Data\RuleSetInterface;

class RuleSetStub implements RuleSetInterface
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
            'ruleset_id' => null,
            'name' => 'Test rule set',
            'kind' => self::KIND_ATTRIBUTE_SET,
            'channel_code' => null,
            'description' => null,
            'is_enabled' => true,
            'attribute_set_ids' => [],
            'rules' => [],
            'created_at' => null,
            'updated_at' => null,
        ], $data);
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

    public function getName()
    {
        return (string)$this->data['name'];
    }

    public function setName($name)
    {
        $this->data['name'] = $name;
        return $this;
    }

    public function getKind()
    {
        return (string)$this->data['kind'];
    }

    public function setKind($kind)
    {
        $this->data['kind'] = $kind;
        return $this;
    }

    public function getChannelCode()
    {
        return $this->data['channel_code'];
    }

    public function setChannelCode($channelCode)
    {
        $this->data['channel_code'] = $channelCode;
        return $this;
    }

    public function getDescription()
    {
        return $this->data['description'];
    }

    public function setDescription($description)
    {
        $this->data['description'] = $description;
        return $this;
    }

    public function getIsEnabled()
    {
        return (bool)$this->data['is_enabled'];
    }

    public function setIsEnabled($isEnabled)
    {
        $this->data['is_enabled'] = (bool)$isEnabled;
        return $this;
    }

    public function getAttributeSetIds()
    {
        return $this->data['attribute_set_ids'];
    }

    public function setAttributeSetIds(array $attributeSetIds)
    {
        $this->data['attribute_set_ids'] = $attributeSetIds;
        return $this;
    }

    public function getRules()
    {
        return $this->data['rules'];
    }

    public function setRules(array $rules)
    {
        $this->data['rules'] = array_values($rules);
        return $this;
    }

    public function getCreatedAt()
    {
        return $this->data['created_at'];
    }

    public function getUpdatedAt()
    {
        return $this->data['updated_at'];
    }
}
