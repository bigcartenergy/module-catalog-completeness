<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Model\Scoring;

use BigCartEnergy\CatalogCompleteness\Api\Data\RuleInterface;
use BigCartEnergy\CatalogCompleteness\Api\Data\RuleSetInterface;
use BigCartEnergy\CatalogCompleteness\Model\Catalog\Lookup;

/**
 * Builds the scoring context for a run: the union of the attributes the rule sets need, read once.
 */
class ContextBuilder
{
    /**
     * @var Lookup
     */
    private $lookup;

    public function __construct(Lookup $lookup)
    {
        $this->lookup = $lookup;
    }

    /**
     * @param RuleSetInterface[] $ruleSets
     */
    public function build(array $ruleSets): Context
    {
        $rules = [];
        foreach ($ruleSets as $ruleSet) {
            foreach ($ruleSet->getRules() as $rule) {
                $rules[] = $rule;
            }
        }
        return $this->buildForRules($rules);
    }

    /**
     * @param RuleInterface[] $rules
     */
    public function buildForRules(array $rules): Context
    {
        $attributes = [];
        $ids = [];
        foreach ($rules as $rule) {
            $code = (string)$rule->getAttributeCode();
            if ($code === '' || isset($attributes[$code])) {
                continue;
            }
            $descriptor = $this->lookup->getDescriptor($code);
            if ($descriptor === null) {
                continue;
            }
            $attributes[$code] = $descriptor;
            if ($descriptor->getId() !== null) {
                $ids[] = $descriptor->getId();
            }
        }
        return new Context($attributes, $this->lookup->getMembership($ids));
    }
}
