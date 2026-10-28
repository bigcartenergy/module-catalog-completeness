<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Api\Data;

/**
 * One completeness rule inside a rule set.
 *
 * @api
 */
interface RuleInterface
{
    public const RULE_ID = 'rule_id';
    public const RULESET_ID = 'ruleset_id';
    public const SORT_ORDER = 'sort_order';
    public const ROLE = 'role';
    public const ATTRIBUTE_CODE = 'attribute_code';
    public const RULE_TYPE = 'rule_type';
    public const PARAMETER = 'parameter';
    public const WEIGHT = 'weight';
    public const APPLIES_TO = 'applies_to';

    public const TYPE_PRESENT = 'present';
    public const TYPE_MIN_LENGTH = 'min_length';
    public const TYPE_MIN_COUNT = 'min_count';
    public const TYPE_GT_ZERO = 'gt_zero';
    public const TYPE_ALLOWED_VALUES = 'allowed_values';
    public const TYPE_PATTERN = 'pattern';

    public const TYPES = [
        self::TYPE_PRESENT,
        self::TYPE_MIN_LENGTH,
        self::TYPE_MIN_COUNT,
        self::TYPE_GT_ZERO,
        self::TYPE_ALLOWED_VALUES,
        self::TYPE_PATTERN,
    ];

    public const WEIGHT_REQUIRED = 3;
    public const WEIGHT_IMPORTANT = 2;
    public const WEIGHT_NICE_TO_HAVE = 1;

    public const WEIGHTS = [
        self::WEIGHT_REQUIRED,
        self::WEIGHT_IMPORTANT,
        self::WEIGHT_NICE_TO_HAVE,
    ];

    public const ROLE_CUSTOM = 'custom';

    /**
     * Pseudo attribute code for the media gallery image count.
     */
    public const MEDIA_GALLERY = 'media_gallery';

    /**
     * @return int|null
     */
    public function getRuleId();

    /**
     * @param int|null $ruleId
     * @return $this
     */
    public function setRuleId($ruleId);

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
     * @return int
     */
    public function getSortOrder();

    /**
     * @param int $sortOrder
     * @return $this
     */
    public function setSortOrder($sortOrder);

    /**
     * @return string|null
     */
    public function getRole();

    /**
     * @param string|null $role
     * @return $this
     */
    public function setRole($role);

    /**
     * @return string
     */
    public function getAttributeCode();

    /**
     * @param string $attributeCode
     * @return $this
     */
    public function setAttributeCode($attributeCode);

    /**
     * @return string
     */
    public function getRuleType();

    /**
     * @param string $ruleType
     * @return $this
     */
    public function setRuleType($ruleType);

    /**
     * @return string|null
     */
    public function getParameter();

    /**
     * @param string|null $parameter
     * @return $this
     */
    public function setParameter($parameter);

    /**
     * @return int
     */
    public function getWeight();

    /**
     * @param int $weight
     * @return $this
     */
    public function setWeight($weight);

    /**
     * Product type ids the rule applies to; an empty list means every type.
     *
     * @return string[]
     */
    public function getAppliesTo();

    /**
     * @param string[] $typeIds
     * @return $this
     */
    public function setAppliesTo(array $typeIds);
}
