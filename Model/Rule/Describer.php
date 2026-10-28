<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Model\Rule;

use BigCartEnergy\CatalogCompleteness\Api\Data\RuleInterface;

/**
 * The "rule in words" shown everywhere a rule is listed. Labels are plain text; callers escape them.
 */
class Describer
{
    public function describe(string $ruleType, ?string $parameter, string $attributeLabel, bool $isGallery): string
    {
        $parameter = (string)$parameter;
        switch ($ruleType) {
            case RuleInterface::TYPE_PRESENT:
                return (string)__('%1 is filled in', $attributeLabel);
            case RuleInterface::TYPE_MIN_LENGTH:
                $count = (int)$parameter;
                return $count === 1
                    ? (string)__('%1 at least %2 character', $attributeLabel, $count)
                    : (string)__('%1 at least %2 characters', $attributeLabel, $count);
            case RuleInterface::TYPE_MIN_COUNT:
                $count = (int)$parameter;
                if ($isGallery) {
                    return $count === 1
                        ? (string)__('At least %1 image', $count)
                        : (string)__('At least %1 images', $count);
                }
                return $count === 1
                    ? (string)__('%1 has at least %2 value', $attributeLabel, $count)
                    : (string)__('%1 has at least %2 values', $attributeLabel, $count);
            case RuleInterface::TYPE_GT_ZERO:
                return (string)__('%1 greater than 0', $attributeLabel);
            case RuleInterface::TYPE_ALLOWED_VALUES:
                $count = $this->countList($parameter);
                return $count === 1
                    ? (string)__('%1 is the allowed value', $attributeLabel)
                    : (string)__('%1 is one of %2 allowed values', $attributeLabel, $count);
            case RuleInterface::TYPE_PATTERN:
                $max = Pattern::maxLengthOf($parameter);
                if ($max === 1) {
                    return (string)__('%1 is filled in and at most %2 character', $attributeLabel, $max);
                }
                if ($max !== null) {
                    return (string)__('%1 is filled in and at most %2 characters', $attributeLabel, $max);
                }
                return (string)__('%1 matches the pattern %2', $attributeLabel, $parameter);
            default:
                return $attributeLabel;
        }
    }

    /**
     * Short label of a rule type for selects and chips.
     */
    public function typeLabel(string $ruleType): string
    {
        switch ($ruleType) {
            case RuleInterface::TYPE_PRESENT:
                return (string)__('Present');
            case RuleInterface::TYPE_MIN_LENGTH:
                return (string)__('Minimum length');
            case RuleInterface::TYPE_MIN_COUNT:
                return (string)__('Minimum count');
            case RuleInterface::TYPE_GT_ZERO:
                return (string)__('Greater than 0');
            case RuleInterface::TYPE_ALLOWED_VALUES:
                return (string)__('One of the allowed values');
            case RuleInterface::TYPE_PATTERN:
                return (string)__('Matches a pattern');
            default:
                return $ruleType;
        }
    }

    public function weightLabel(int $weight): string
    {
        switch ($weight) {
            case RuleInterface::WEIGHT_REQUIRED:
                return (string)__('Required');
            case RuleInterface::WEIGHT_IMPORTANT:
                return (string)__('Important');
            case RuleInterface::WEIGHT_NICE_TO_HAVE:
                return (string)__('Nice to have');
            default:
                return '';
        }
    }

    private function countList(string $parameter): int
    {
        $count = 0;
        foreach (explode(',', $parameter) as $entry) {
            if (trim($entry) !== '') {
                $count++;
            }
        }
        return $count;
    }
}
