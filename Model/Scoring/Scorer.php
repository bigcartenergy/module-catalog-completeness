<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Model\Scoring;

use BigCartEnergy\CatalogCompleteness\Api\Data\RuleInterface;
use BigCartEnergy\CatalogCompleteness\Api\Data\RuleSetInterface;
use BigCartEnergy\CatalogCompleteness\Model\Rule\Evaluator;

/**
 * Scores one product against one rule set. Pure: the indexer, the preview and the tests all use it.
 */
class Scorer
{
    /**
     * @var Evaluator
     */
    private $evaluator;

    /**
     * @var Calculator
     */
    private $calculator;

    public function __construct(Evaluator $evaluator, Calculator $calculator)
    {
        $this->evaluator = $evaluator;
        $this->calculator = $calculator;
    }

    public function score(ProductData $product, RuleSetInterface $ruleSet, Context $context): ScoreResult
    {
        return $this->scoreRules($product, $ruleSet->getRules(), $context);
    }

    /**
     * @param RuleInterface[] $rules in rule set order
     */
    public function scoreRules(ProductData $product, array $rules, Context $context): ScoreResult
    {
        $passedWeight = 0;
        $totalWeight = 0;
        $results = [];
        $index = 0;
        foreach ($rules as $rule) {
            $applies = $this->calculator->applies($rule->getAppliesTo(), $product->getTypeId());
            $passed = false;
            if ($applies) {
                $weight = (int)$rule->getWeight();
                $totalWeight += $weight;
                $passed = $this->passes($product, $rule, $context);
                if ($passed) {
                    $passedWeight += $weight;
                }
            }
            $results[] = ['index' => $index, 'rule' => $rule, 'applies' => $applies, 'passed' => $passed];
            $index++;
        }
        return new ScoreResult(
            $this->calculator->score($passedWeight, $totalWeight),
            $passedWeight,
            $totalWeight,
            $results
        );
    }

    private function passes(ProductData $product, RuleInterface $rule, Context $context): bool
    {
        $code = (string)$rule->getAttributeCode();
        if ($code === '') {
            // A rule without an attribute cannot be satisfied: the catalog has no field for it.
            return false;
        }
        $descriptor = $context->getAttribute($code);
        if ($descriptor === null) {
            return false;
        }
        return $this->evaluator->evaluate(
            (string)$rule->getRuleType(),
            $rule->getParameter(),
            $product->getValue($code),
            $descriptor,
            $context->isAvailable($code, $product->getAttributeSetId())
        );
    }
}
