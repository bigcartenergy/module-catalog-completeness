<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Model;

use BigCartEnergy\CatalogCompleteness\Api\Data\RuleInterface;
use Magento\Framework\Model\AbstractModel;

class Rule extends AbstractModel implements RuleInterface
{
    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init(ResourceModel\Rule::class);
    }

    /**
     * @inheritDoc
     */
    public function getRuleId()
    {
        $id = $this->getData(self::RULE_ID);
        return $id === null || $id === '' ? null : (int)$id;
    }

    /**
     * @inheritDoc
     */
    public function setRuleId($ruleId)
    {
        return $this->setData(self::RULE_ID, $ruleId === null ? null : (int)$ruleId);
    }

    /**
     * @inheritDoc
     */
    public function getRulesetId()
    {
        $id = $this->getData(self::RULESET_ID);
        return $id === null || $id === '' ? null : (int)$id;
    }

    /**
     * @inheritDoc
     */
    public function setRulesetId($rulesetId)
    {
        return $this->setData(self::RULESET_ID, $rulesetId === null ? null : (int)$rulesetId);
    }

    /**
     * @inheritDoc
     */
    public function getSortOrder()
    {
        return (int)$this->getData(self::SORT_ORDER);
    }

    /**
     * @inheritDoc
     */
    public function setSortOrder($sortOrder)
    {
        return $this->setData(self::SORT_ORDER, (int)$sortOrder);
    }

    /**
     * @inheritDoc
     */
    public function getRole()
    {
        $role = $this->getData(self::ROLE);
        return $role === null || $role === '' ? null : (string)$role;
    }

    /**
     * @inheritDoc
     */
    public function setRole($role)
    {
        return $this->setData(self::ROLE, $role === null || $role === '' ? null : (string)$role);
    }

    /**
     * @inheritDoc
     */
    public function getAttributeCode()
    {
        return (string)$this->getData(self::ATTRIBUTE_CODE);
    }

    /**
     * @inheritDoc
     */
    public function setAttributeCode($attributeCode)
    {
        return $this->setData(self::ATTRIBUTE_CODE, trim((string)$attributeCode));
    }

    /**
     * @inheritDoc
     */
    public function getRuleType()
    {
        return (string)$this->getData(self::RULE_TYPE);
    }

    /**
     * @inheritDoc
     */
    public function setRuleType($ruleType)
    {
        return $this->setData(self::RULE_TYPE, (string)$ruleType);
    }

    /**
     * @inheritDoc
     */
    public function getParameter()
    {
        $parameter = $this->getData(self::PARAMETER);
        return $parameter === null ? null : (string)$parameter;
    }

    /**
     * @inheritDoc
     */
    public function setParameter($parameter)
    {
        return $this->setData(self::PARAMETER, $parameter === null ? null : (string)$parameter);
    }

    /**
     * @inheritDoc
     */
    public function getWeight()
    {
        return (int)$this->getData(self::WEIGHT);
    }

    /**
     * @inheritDoc
     */
    public function setWeight($weight)
    {
        return $this->setData(self::WEIGHT, (int)$weight);
    }

    /**
     * @inheritDoc
     */
    public function getAppliesTo()
    {
        return self::normalizeTypes($this->getData(self::APPLIES_TO));
    }

    /**
     * @inheritDoc
     */
    public function setAppliesTo(array $typeIds)
    {
        return $this->setData(self::APPLIES_TO, self::normalizeTypes($typeIds));
    }

    /**
     * Comma list or array of type ids into a clean list.
     *
     * @param mixed $raw
     * @return string[]
     */
    public static function normalizeTypes($raw): array
    {
        if ($raw === null || $raw === '' || $raw === []) {
            return [];
        }
        $parts = is_array($raw) ? $raw : explode(',', (string)$raw);
        $types = [];
        foreach ($parts as $part) {
            $part = trim((string)$part);
            if ($part !== '' && !in_array($part, $types, true)) {
                $types[] = $part;
            }
        }
        return $types;
    }
}
