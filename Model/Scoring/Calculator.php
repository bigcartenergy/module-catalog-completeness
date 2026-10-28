<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Model\Scoring;

/**
 * The score arithmetic. Weights are the counts 3, 2 and 1; a product scores 100 only when every applicable
 * rule passes, and rounding can never produce 100 otherwise.
 */
class Calculator
{
    /**
     * Whether a rule with these product types applies to a product of the given type.
     *
     * @param string[] $appliesTo empty means every type
     */
    public function applies(array $appliesTo, string $typeId): bool
    {
        if (!$appliesTo) {
            return true;
        }
        return in_array($typeId, $appliesTo, true);
    }

    /**
     * Score from the passed and total weights; null when no rule applies.
     */
    public function score(int $passedWeight, int $totalWeight): ?int
    {
        if ($totalWeight <= 0) {
            return null;
        }
        if ($passedWeight >= $totalWeight) {
            return 100;
        }
        if ($passedWeight <= 0) {
            return 0;
        }
        $score = (int)round(100 * $passedWeight / $totalWeight);
        return max(0, min(99, $score));
    }

    /**
     * The share of the score one rule carries, as a fraction between 0 and 1.
     */
    public function share(int $weight, int $totalWeight): float
    {
        if ($totalWeight <= 0 || $weight <= 0) {
            return 0.0;
        }
        return $weight / $totalWeight;
    }

    /**
     * The share as a whole percentage for display.
     */
    public function sharePercent(int $weight, int $totalWeight): int
    {
        return (int)round($this->share($weight, $totalWeight) * 100);
    }
}
