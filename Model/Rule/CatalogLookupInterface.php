<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Model\Rule;

/**
 * What the validator and the scorer need from the catalog, behind an interface so both can be unit tested.
 */
interface CatalogLookupInterface
{
    /**
     * Descriptor of a product attribute code, including the two pseudo attributes; null for an unknown code.
     */
    public function getDescriptor(string $code): ?AttributeDescriptor;

    /**
     * Product type ids known to the catalog (simple, configurable, ...).
     *
     * @return string[]
     */
    public function getProductTypeIds(): array;

    /**
     * Product attribute set ids known to the catalog.
     *
     * @return int[]
     */
    public function getAttributeSetIds(): array;
}
