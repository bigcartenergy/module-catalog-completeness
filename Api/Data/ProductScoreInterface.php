<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Api\Data;

/**
 * A product's stored score against one rule set. Read-only: nothing writes a score from the caller.
 *
 * @api
 */
interface ProductScoreInterface
{
    public const PRODUCT_ID = 'product_id';
    public const RULESET_ID = 'ruleset_id';
    public const RULESET_NAME = 'ruleset_name';
    public const IS_PRIMARY = 'is_primary';
    public const SCORE = 'score';
    public const BAND = 'band';
    public const BAND_LABEL = 'band_label';
    public const PASSED_WEIGHT = 'passed_weight';
    public const TOTAL_WEIGHT = 'total_weight';
    public const MISSING = 'missing';
    public const SCORED_AT = 'scored_at';
    public const STATE = 'state';

    /**
     * A stored score from at least one rule that applies to the product.
     */
    public const STATE_SCORED = 'scored';

    /**
     * No rule applies to the product: no attribute set rule set covers its attribute set or its product type,
     * or none of the rule set's rules applies to its product type. It carries no score.
     */
    public const STATE_NO_RULES_ASSIGNED = 'no_rules_assigned';

    /**
     * Rules cover the product, but no score has been calculated for it yet.
     */
    public const STATE_NOT_CALCULATED = 'not_calculated';

    /**
     * @return int
     */
    public function getProductId();

    /**
     * @return int
     */
    public function getRulesetId();

    /**
     * @return string
     */
    public function getRulesetName();

    /**
     * @return bool
     */
    public function getIsPrimary();

    /**
     * @return int|null
     */
    public function getScore();

    /**
     * Band code: complete, good, needs_work or poor; null when there is no score.
     *
     * @return string|null
     */
    public function getBand();

    /**
     * @return string|null
     */
    public function getBandLabel();

    /**
     * @return int
     */
    public function getPassedWeight();

    /**
     * @return int
     */
    public function getTotalWeight();

    /**
     * Labels of the rules the product fails, in rule set order.
     *
     * @return string[]
     */
    public function getMissing();

    /**
     * @return string|null
     */
    public function getScoredAt();

    /**
     * Why the score is what it is: scored, no_rules_assigned (no rule applies, so the score is null) or
     * not_calculated (rules apply, no score yet).
     *
     * @return string
     */
    public function getState();
}
