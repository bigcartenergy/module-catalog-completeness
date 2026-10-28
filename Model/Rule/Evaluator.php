<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Model\Rule;

use BigCartEnergy\CatalogCompleteness\Api\Data\RuleInterface;

/**
 * Decides whether one stored value passes one rule. Pure: no database, no framework.
 *
 * Value conventions: the raw admin-scope value from the EAV tables (a string, or null when no row exists);
 * a multiselect is the stored comma list; the media gallery pseudo attribute is the count of enabled images;
 * the stock status pseudo attribute is 1 or 0.
 */
class Evaluator
{
    /**
     * @param mixed $value
     * @param bool $available false when the attribute is not in the product's attribute set
     */
    public function evaluate(
        string $ruleType,
        ?string $parameter,
        $value,
        AttributeDescriptor $attribute,
        bool $available
    ): bool {
        if (!$available) {
            return false;
        }
        switch ($ruleType) {
            case RuleInterface::TYPE_PRESENT:
                return $this->isPresent($value, $attribute);
            case RuleInterface::TYPE_MIN_LENGTH:
                return $this->hasMinLength($value, $attribute, (int)$parameter);
            case RuleInterface::TYPE_MIN_COUNT:
                return $this->hasMinCount($value, $attribute, (int)$parameter);
            case RuleInterface::TYPE_GT_ZERO:
                return $this->isGreaterThanZero($value);
            case RuleInterface::TYPE_ALLOWED_VALUES:
                return $this->isAllowed($value, $attribute, (string)$parameter);
            case RuleInterface::TYPE_PATTERN:
                return $this->matchesPattern($value, $attribute, (string)$parameter);
            default:
                return false;
        }
    }

    /**
     * Non-empty values of a multiselect, or a one-element list for any other value.
     *
     * @param mixed $value
     * @return string[]
     */
    public function listValues($value, AttributeDescriptor $attribute): array
    {
        if ($value === null) {
            return [];
        }
        if (is_array($value)) {
            $parts = $value;
        } elseif ($attribute->isMultiselect()) {
            $parts = explode(',', (string)$value);
        } else {
            $parts = [(string)$value];
        }
        $list = [];
        foreach ($parts as $part) {
            $part = trim((string)$part);
            if ($part !== '') {
                $list[] = $part;
            }
        }
        return $list;
    }

    /**
     * The text a merchant would count: rich text is reduced to plain text first.
     *
     * @param mixed $value
     */
    public function textOf($value, AttributeDescriptor $attribute): string
    {
        if ($value === null) {
            return '';
        }
        $text = is_array($value) ? implode(',', $value) : (string)$value;
        return $attribute->isRichText() ? Text::plain($text) : trim($text);
    }

    /**
     * @param mixed $value
     */
    private function isPresent($value, AttributeDescriptor $attribute): bool
    {
        if ($attribute->isGallery()) {
            return (int)$value >= 1;
        }
        if ($value === null) {
            return false;
        }
        if ($attribute->isStockStatus()) {
            return trim((string)$value) !== '';
        }
        if ($attribute->isMultiselect() || is_array($value)) {
            return count($this->listValues($value, $attribute)) > 0;
        }
        // "0" is a value for numeric, boolean and select attributes; only an empty string or whitespace is missing.
        return $this->textOf($value, $attribute) !== '';
    }

    /**
     * @param mixed $value
     */
    private function hasMinLength($value, AttributeDescriptor $attribute, int $minimum): bool
    {
        if ($attribute->isGallery()) {
            return false;
        }
        $text = $this->textOf($value, $attribute);
        if ($text === '') {
            return false;
        }
        return Text::length($text) >= max(1, $minimum);
    }

    /**
     * @param mixed $value
     */
    private function hasMinCount($value, AttributeDescriptor $attribute, int $minimum): bool
    {
        $minimum = max(1, $minimum);
        if ($attribute->isGallery()) {
            return (int)$value >= $minimum;
        }
        if ($attribute->isMultiselect() || is_array($value)) {
            return count($this->listValues($value, $attribute)) >= $minimum;
        }
        return false;
    }

    /**
     * @param mixed $value
     */
    private function isGreaterThanZero($value): bool
    {
        if ($value === null || is_array($value)) {
            return false;
        }
        $text = trim((string)$value);
        if ($text === '' || !is_numeric($text)) {
            return false;
        }
        return (float)$text > 0;
    }

    /**
     * Every stored value must match an allowed entry, by option label or by the raw stored value, case-insensitively.
     *
     * @param mixed $value
     */
    private function isAllowed($value, AttributeDescriptor $attribute, string $parameter): bool
    {
        $allowed = [];
        foreach (explode(',', $parameter) as $entry) {
            $entry = self::fold($entry);
            if ($entry !== '') {
                $allowed[$entry] = true;
            }
        }
        if (!$allowed) {
            return false;
        }
        $values = $attribute->isGallery() ? [] : $this->listValues($value, $attribute);
        if (!$values) {
            return false;
        }
        $options = $attribute->getOptions();
        foreach ($values as $stored) {
            $candidates = [self::fold($stored)];
            if (isset($options[$stored])) {
                $candidates[] = self::fold($options[$stored]);
            }
            $hit = false;
            foreach ($candidates as $candidate) {
                if ($candidate !== '' && isset($allowed[$candidate])) {
                    $hit = true;
                    break;
                }
            }
            if (!$hit) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param mixed $value
     */
    private function matchesPattern($value, AttributeDescriptor $attribute, string $parameter): bool
    {
        if ($attribute->isGallery()) {
            return false;
        }
        $regex = Pattern::compile($parameter);
        if ($regex === null) {
            return false;
        }
        $text = $this->textOf($value, $attribute);
        if ($text === '') {
            return false;
        }
        return Pattern::matches($regex, $text);
    }

    private static function fold(string $text): string
    {
        $text = trim($text);
        $folded = mb_strtolower($text, 'UTF-8');
        return $folded === false ? strtolower($text) : $folded;
    }
}
