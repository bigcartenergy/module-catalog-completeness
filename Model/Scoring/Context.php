<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Model\Scoring;

use BigCartEnergy\CatalogCompleteness\Model\Rule\AttributeDescriptor;

/**
 * Everything a scoring run needs besides the product values: the attribute descriptors of every rule and
 * which attribute belongs to which attribute set. Built once per run, shared by every chunk.
 */
final class Context
{
    /**
     * @var array<string, AttributeDescriptor>
     */
    private $attributes;

    /**
     * @var array<int, array<int, bool>> attribute set id => [attribute id => true]
     */
    private $membership;

    /**
     * @param array<string, AttributeDescriptor> $attributes keyed by attribute code
     * @param array<int, array<int, bool>> $membership
     */
    public function __construct(array $attributes, array $membership)
    {
        $this->attributes = $attributes;
        $this->membership = $membership;
    }

    public function getAttribute(string $code): ?AttributeDescriptor
    {
        return $this->attributes[$code] ?? null;
    }

    /**
     * @return array<string, AttributeDescriptor>
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Codes of the real EAV attributes (pseudo attributes and unknown codes excluded).
     *
     * @return string[]
     */
    public function getEavAttributeCodes(): array
    {
        $codes = [];
        foreach ($this->attributes as $code => $descriptor) {
            if (!$descriptor->isPseudo() && $descriptor->getId() !== null) {
                $codes[] = $code;
            }
        }
        return $codes;
    }

    /**
     * Whether a product in the given attribute set can hold the attribute at all.
     * Unknown attribute codes (an empty mapping, or an attribute deleted since) are never available.
     */
    public function isAvailable(string $code, int $attributeSetId): bool
    {
        $descriptor = $this->attributes[$code] ?? null;
        if ($descriptor === null) {
            return false;
        }
        if ($descriptor->isPseudo() || $descriptor->isStatic() || $code === 'sku') {
            return true;
        }
        $id = $descriptor->getId();
        if ($id === null) {
            return false;
        }
        return isset($this->membership[$attributeSetId][$id]);
    }

    /**
     * Attribute set ids (of those known to the context) that do not hold the attribute.
     *
     * @param int[] $attributeSetIds
     * @return int[]
     */
    public function missingFromSets(string $code, array $attributeSetIds): array
    {
        $missing = [];
        foreach ($attributeSetIds as $setId) {
            if (!$this->isAvailable($code, (int)$setId)) {
                $missing[] = (int)$setId;
            }
        }
        return $missing;
    }
}
