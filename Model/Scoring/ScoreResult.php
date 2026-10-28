<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Model\Scoring;

use BigCartEnergy\CatalogCompleteness\Api\Data\RuleInterface;

/**
 * The outcome of scoring one product against one rule set.
 */
final class ScoreResult
{
    /**
     * @var int|null
     */
    private $score;

    /**
     * @var int
     */
    private $passedWeight;

    /**
     * @var int
     */
    private $totalWeight;

    /**
     * @var array<int, array{index: int, rule: RuleInterface, applies: bool, passed: bool}>
     */
    private $ruleResults;

    /**
     * @param array<int, array{index: int, rule: RuleInterface, applies: bool, passed: bool}> $ruleResults
     */
    public function __construct(?int $score, int $passedWeight, int $totalWeight, array $ruleResults)
    {
        $this->score = $score;
        $this->passedWeight = $passedWeight;
        $this->totalWeight = $totalWeight;
        $this->ruleResults = $ruleResults;
    }

    public function getScore(): ?int
    {
        return $this->score;
    }

    public function getPassedWeight(): int
    {
        return $this->passedWeight;
    }

    public function getTotalWeight(): int
    {
        return $this->totalWeight;
    }

    /**
     * One entry per rule of the set, in rule set order.
     *
     * @return array<int, array{index: int, rule: RuleInterface, applies: bool, passed: bool}>
     */
    public function getRuleResults(): array
    {
        return $this->ruleResults;
    }

    /**
     * Applicable rules that failed, in rule set order.
     *
     * @return RuleInterface[]
     */
    public function getMissingRules(): array
    {
        $missing = [];
        foreach ($this->ruleResults as $result) {
            if ($result['applies'] && !$result['passed']) {
                $missing[] = $result['rule'];
            }
        }
        return $missing;
    }

    /**
     * Ids of the missing rules; rules without an id (unsaved preview rows) are skipped.
     *
     * @return int[]
     */
    public function getMissingRuleIds(): array
    {
        $ids = [];
        foreach ($this->getMissingRules() as $rule) {
            $id = $rule->getRuleId();
            if ($id !== null && (int)$id > 0) {
                $ids[] = (int)$id;
            }
        }
        return $ids;
    }

    /**
     * Applicable rules that passed, in rule set order.
     *
     * @return RuleInterface[]
     */
    public function getPassedRules(): array
    {
        $passed = [];
        foreach ($this->ruleResults as $result) {
            if ($result['applies'] && $result['passed']) {
                $passed[] = $result['rule'];
            }
        }
        return $passed;
    }

    public function countMissing(): int
    {
        return count($this->getMissingRules());
    }

    public function countPassed(): int
    {
        return count($this->getPassedRules());
    }
}
