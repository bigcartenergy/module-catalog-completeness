<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Model;

use BigCartEnergy\CatalogCompleteness\Api\Data\RuleInterface;
use BigCartEnergy\CatalogCompleteness\Api\Data\RuleSetInterface;
use Magento\Framework\Model\AbstractModel;

class RuleSet extends AbstractModel implements RuleSetInterface
{
    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init(ResourceModel\RuleSet::class);
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
    public function getName()
    {
        return (string)$this->getData(self::NAME);
    }

    /**
     * @inheritDoc
     */
    public function setName($name)
    {
        return $this->setData(self::NAME, trim((string)$name));
    }

    /**
     * @inheritDoc
     */
    public function getKind()
    {
        return (string)($this->getData(self::KIND) ?: self::KIND_ATTRIBUTE_SET);
    }

    /**
     * @inheritDoc
     */
    public function setKind($kind)
    {
        return $this->setData(self::KIND, (string)$kind);
    }

    /**
     * @inheritDoc
     */
    public function getChannelCode()
    {
        $code = $this->getData(self::CHANNEL_CODE);
        return $code === null || $code === '' ? null : (string)$code;
    }

    /**
     * @inheritDoc
     */
    public function setChannelCode($channelCode)
    {
        return $this->setData(self::CHANNEL_CODE, $channelCode === null || $channelCode === '' ? null : (string)$channelCode);
    }

    /**
     * @inheritDoc
     */
    public function getDescription()
    {
        $description = $this->getData(self::DESCRIPTION);
        return $description === null ? null : (string)$description;
    }

    /**
     * @inheritDoc
     */
    public function setDescription($description)
    {
        return $this->setData(self::DESCRIPTION, $description === null ? null : (string)$description);
    }

    /**
     * @inheritDoc
     */
    public function getIsEnabled()
    {
        return (bool)$this->getData(self::IS_ENABLED);
    }

    /**
     * @inheritDoc
     */
    public function setIsEnabled($isEnabled)
    {
        return $this->setData(self::IS_ENABLED, $isEnabled ? 1 : 0);
    }

    /**
     * @inheritDoc
     */
    public function getAttributeSetIds()
    {
        $ids = $this->getData(self::ATTRIBUTE_SET_IDS);
        if (!is_array($ids)) {
            $ids = $ids === null || $ids === '' ? [] : explode(',', (string)$ids);
        }
        $clean = [];
        foreach ($ids as $id) {
            $id = (int)$id;
            if ($id > 0 && !in_array($id, $clean, true)) {
                $clean[] = $id;
            }
        }
        return $clean;
    }

    /**
     * @inheritDoc
     */
    public function setAttributeSetIds(array $attributeSetIds)
    {
        return $this->setData(self::ATTRIBUTE_SET_IDS, $attributeSetIds);
    }

    /**
     * @inheritDoc
     */
    public function getRules()
    {
        $rules = $this->getData(self::RULES);
        if (!is_array($rules)) {
            return [];
        }
        $clean = [];
        foreach ($rules as $rule) {
            if ($rule instanceof RuleInterface) {
                $clean[] = $rule;
            }
        }
        return $clean;
    }

    /**
     * @inheritDoc
     */
    public function setRules(array $rules)
    {
        return $this->setData(self::RULES, array_values($rules));
    }

    /**
     * @inheritDoc
     */
    public function getCreatedAt()
    {
        $value = $this->getData(self::CREATED_AT);
        return $value === null ? null : (string)$value;
    }

    /**
     * @inheritDoc
     */
    public function getUpdatedAt()
    {
        $value = $this->getData(self::UPDATED_AT);
        return $value === null ? null : (string)$value;
    }

    public function isChannel(): bool
    {
        return $this->getKind() === self::KIND_CHANNEL;
    }
}
