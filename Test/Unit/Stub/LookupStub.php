<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Test\Unit\Stub;

use BigCartEnergy\CatalogCompleteness\Api\Data\RuleInterface;
use BigCartEnergy\CatalogCompleteness\Model\Rule\AttributeDescriptor;
use BigCartEnergy\CatalogCompleteness\Model\Rule\CatalogLookupInterface;

/**
 * An invented catalog: a handful of attributes of every input type, six product types, three attribute sets.
 */
class LookupStub implements CatalogLookupInterface
{
    /**
     * @var array<string, AttributeDescriptor>
     */
    private $attributes;

    public function __construct()
    {
        $this->attributes = [
            'name' => new AttributeDescriptor('name', 71, 'Product Name', 'varchar', 'text'),
            'description' => new AttributeDescriptor('description', 72, 'Description', 'text', 'textarea'),
            'short_description' => new AttributeDescriptor('short_description', 73, 'Short Description', 'text', 'textarea'),
            'weight' => new AttributeDescriptor('weight', 74, 'Weight', 'decimal', 'weight'),
            'price' => new AttributeDescriptor('price', 75, 'Price', 'decimal', 'price'),
            'manufacturer' => new AttributeDescriptor('manufacturer', 76, 'Manufacturer', 'int', 'select', [
                '11' => 'Northfield Fasteners',
                '12' => 'Ridgeway Abrasives',
            ]),
            'material' => new AttributeDescriptor('material', 77, 'Material', 'varchar', 'multiselect', [
                '21' => 'Steel',
                '22' => 'Stainless 304',
                '23' => 'Brass',
            ]),
            'item_condition' => new AttributeDescriptor('item_condition', 78, 'Condition', 'varchar', 'text'),
            'gtin' => new AttributeDescriptor('gtin', 79, 'GTIN', 'varchar', 'text'),
            'meta_description' => new AttributeDescriptor('meta_description', 80, 'Meta Description', 'varchar', 'textarea'),
            'country_of_manufacture' => new AttributeDescriptor('country_of_manufacture', 81, 'Country of Manufacture', 'varchar', 'select', [
                'DE' => 'Germany',
                'US' => 'United States',
            ]),
            'is_featured' => new AttributeDescriptor('is_featured', 82, 'Featured', 'int', 'boolean', [
                '1' => 'Yes',
                '0' => 'No',
            ]),
        ];
    }

    public function getDescriptor(string $code): ?AttributeDescriptor
    {
        if ($code === RuleInterface::MEDIA_GALLERY) {
            return AttributeDescriptor::gallery();
        }
        if ($code === AttributeDescriptor::STOCK_STATUS_CODE) {
            return AttributeDescriptor::stockStatus();
        }
        return $this->attributes[$code] ?? null;
    }

    public function getProductTypeIds(): array
    {
        return ['simple', 'configurable', 'bundle', 'grouped', 'virtual', 'downloadable'];
    }

    public function getAttributeSetIds(): array
    {
        return [4, 9, 10];
    }

    /**
     * Every attribute belongs to set 4; set 9 lacks gtin and material; set 10 lacks description.
     *
     * @return array<int, array<int, bool>>
     */
    public function getMembership(): array
    {
        $membership = [4 => [], 9 => [], 10 => []];
        foreach ($this->attributes as $code => $descriptor) {
            $id = $descriptor->getId();
            $membership[4][$id] = true;
            if (!in_array($code, ['gtin', 'material'], true)) {
                $membership[9][$id] = true;
            }
            if ($code !== 'description') {
                $membership[10][$id] = true;
            }
        }
        return $membership;
    }
}
