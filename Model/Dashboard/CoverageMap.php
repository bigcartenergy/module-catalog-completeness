<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Model\Dashboard;

use BigCartEnergy\CatalogCompleteness\Api\Data\RuleInterface;

/**
 * Which products of one dashboard scope have no rules assigned, as plain data: a product is covered when a rule
 * the scope uses applies to its product type, and its attribute set is one the scope reaches. Coverage builds it
 * from the rule sets; it answers for one product and turns into one SQL condition over the product entity.
 */
final class CoverageMap
{
    /**
     * @var array<int, string[]|null>|null attribute set id => product types its rule set covers (null: every
     *     type), for the attribute set rules scope; null for a scope of one rule set
     */
    private $bySet;

    /**
     * @var int[]|null for a scope of one rule set: the attribute sets it reaches, null for every product
     */
    private $universe;

    /**
     * @var string[]|null for a scope of one rule set: the product types its rules cover, null for every type
     */
    private $types;

    /**
     * @param array<int, string[]|null>|null $bySet
     * @param int[]|null $universe
     * @param string[]|null $types
     */
    private function __construct(?array $bySet, ?array $universe, ?array $types)
    {
        $this->bySet = $bySet;
        $this->universe = $universe;
        $this->types = $types;
    }

    /**
     * The attribute set rules scope: every product counts, and each attribute set is covered for the product
     * types of its rule set (null: every type). An attribute set that is not listed is not covered.
     *
     * @param array<int, string[]|null> $typesBySet
     */
    public static function perAttributeSet(array $typesBySet): self
    {
        $bySet = [];
        foreach ($typesBySet as $setId => $types) {
            if ($types === null || $types) {
                $bySet[(int)$setId] = $types === null ? null : array_values(array_map('strval', $types));
            }
        }
        return new self($bySet, null, null);
    }

    /**
     * The scope of one rule set: the products it reaches (a channel profile: every product, null; an attribute
     * set rule set: its attribute sets) are covered for the product types of its rules (null: every type; an
     * empty list: none, as for a rule set that is disabled or has no rules).
     *
     * @param int[]|null $attributeSetIds
     * @param string[]|null $types
     */
    public static function single(?array $attributeSetIds, ?array $types): self
    {
        return new self(
            null,
            $attributeSetIds === null ? null : array_values(array_unique(array_map('intval', $attributeSetIds))),
            $types === null ? null : array_values(array_unique(array_map('strval', $types)))
        );
    }

    /**
     * The product types a rule set's rules apply to: null when one of them applies to every type, an empty list
     * when there are no rules.
     *
     * @param RuleInterface[] $rules
     * @return string[]|null
     */
    public static function typesOf(array $rules): ?array
    {
        $types = [];
        foreach ($rules as $rule) {
            $appliesTo = $rule->getAppliesTo();
            if (!$appliesTo) {
                return null;
            }
            foreach ($appliesTo as $type) {
                if (!in_array((string)$type, $types, true)) {
                    $types[] = (string)$type;
                }
            }
        }
        return $types;
    }

    /**
     * Whether a product of this attribute set and type is one the scope reaches and no rule covers.
     */
    public function isUncovered(int $attributeSetId, string $typeId): bool
    {
        if ($this->bySet !== null) {
            if (!array_key_exists($attributeSetId, $this->bySet)) {
                return true;
            }
            $types = $this->bySet[$attributeSetId];
            return $types !== null && !in_array($typeId, $types, true);
        }
        if ($this->universe !== null && !in_array($attributeSetId, $this->universe, true)) {
            return false;
        }
        return $this->types !== null && !in_array($typeId, $this->types, true);
    }

    /**
     * One SQL condition over the product entity alias $e that keeps the uncovered products of the scope, or ''
     * when every product is uncovered. Values reach the SQL only through $quoteInto (the adapter's quoteInto).
     *
     * @param callable $quoteInto function (string $text, mixed $value): string
     */
    public function condition(string $e, callable $quoteInto): string
    {
        if ($this->bySet !== null) {
            $everyType = [];
            $parts = [];
            foreach ($this->bySet as $setId => $types) {
                if ($types === null) {
                    $everyType[] = (int)$setId;
                    continue;
                }
                $parts[] = '(' . $quoteInto($e . '.attribute_set_id = ?', (int)$setId)
                    . ' AND ' . $quoteInto($e . '.type_id IN (?)', $types) . ')';
            }
            if ($everyType) {
                array_unshift($parts, $quoteInto($e . '.attribute_set_id IN (?)', $everyType));
            }
            return $parts ? 'NOT (' . implode(' OR ', $parts) . ')' : '';
        }
        $conditions = [];
        if ($this->universe !== null) {
            if (!$this->universe) {
                return '1 = 0';
            }
            $conditions[] = $quoteInto($e . '.attribute_set_id IN (?)', $this->universe);
        }
        if ($this->types === null) {
            return '1 = 0';
        }
        if ($this->types) {
            $conditions[] = $quoteInto($e . '.type_id NOT IN (?)', $this->types);
        }
        return implode(' AND ', $conditions);
    }
}
