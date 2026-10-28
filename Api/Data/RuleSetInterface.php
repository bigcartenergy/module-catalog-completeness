<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Api\Data;

/**
 * A rule set: attribute set rules or a channel profile.
 *
 * @api
 */
interface RuleSetInterface
{
    public const RULESET_ID = 'ruleset_id';
    public const NAME = 'name';
    public const KIND = 'kind';
    public const CHANNEL_CODE = 'channel_code';
    public const DESCRIPTION = 'description';
    public const IS_ENABLED = 'is_enabled';
    public const ATTRIBUTE_SET_IDS = 'attribute_set_ids';
    public const RULES = 'rules';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';

    public const KIND_ATTRIBUTE_SET = 'attribute_set';
    public const KIND_CHANNEL = 'channel';

    public const KINDS = [
        self::KIND_ATTRIBUTE_SET,
        self::KIND_CHANNEL,
    ];

    public const CHANNEL_GOOGLE = 'google';
    public const CHANNEL_MICROSOFT = 'microsoft';
    public const CHANNEL_META = 'meta';
    public const CHANNEL_PINTEREST = 'pinterest';
    public const CHANNEL_TIKTOK = 'tiktok';
    public const CHANNEL_AMAZON = 'amazon';
    public const CHANNEL_EBAY = 'ebay';
    public const CHANNEL_WALMART = 'walmart';
    public const CHANNEL_CUSTOM = 'custom';

    public const CHANNEL_CODES = [
        self::CHANNEL_GOOGLE,
        self::CHANNEL_MICROSOFT,
        self::CHANNEL_META,
        self::CHANNEL_PINTEREST,
        self::CHANNEL_TIKTOK,
        self::CHANNEL_AMAZON,
        self::CHANNEL_EBAY,
        self::CHANNEL_WALMART,
        self::CHANNEL_CUSTOM,
    ];

    /**
     * @return int|null
     */
    public function getRulesetId();

    /**
     * @param int|null $rulesetId
     * @return $this
     */
    public function setRulesetId($rulesetId);

    /**
     * @return string
     */
    public function getName();

    /**
     * @param string $name
     * @return $this
     */
    public function setName($name);

    /**
     * @return string
     */
    public function getKind();

    /**
     * @param string $kind
     * @return $this
     */
    public function setKind($kind);

    /**
     * @return string|null
     */
    public function getChannelCode();

    /**
     * @param string|null $channelCode
     * @return $this
     */
    public function setChannelCode($channelCode);

    /**
     * @return string|null
     */
    public function getDescription();

    /**
     * @param string|null $description
     * @return $this
     */
    public function setDescription($description);

    /**
     * @return bool
     */
    public function getIsEnabled();

    /**
     * @param bool $isEnabled
     * @return $this
     */
    public function setIsEnabled($isEnabled);

    /**
     * Attribute set ids covered by an attribute set rule set; empty for channel profiles.
     *
     * @return int[]
     */
    public function getAttributeSetIds();

    /**
     * @param int[] $attributeSetIds
     * @return $this
     */
    public function setAttributeSetIds(array $attributeSetIds);

    /**
     * Rules in rule set order.
     *
     * @return \BigCartEnergy\CatalogCompleteness\Api\Data\RuleInterface[]
     */
    public function getRules();

    /**
     * @param \BigCartEnergy\CatalogCompleteness\Api\Data\RuleInterface[] $rules
     * @return $this
     */
    public function setRules(array $rules);

    /**
     * @return string|null
     */
    public function getCreatedAt();

    /**
     * @return string|null
     */
    public function getUpdatedAt();
}
