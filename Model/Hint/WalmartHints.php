<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Model\Hint;

use BigCartEnergy\CatalogCompleteness\Api\Data\RuleInterface;
use BigCartEnergy\CatalogCompleteness\Api\Data\RuleSetInterface;

/**
 * Walmart Marketplace item setup guides and developer documentation, see docs/channels.md.
 */
class WalmartHints extends AbstractHintList
{
    public function getCode(): string
    {
        return RuleSetInterface::CHANNEL_WALMART;
    }

    public function getTitle(): string
    {
        return (string)__('Suggested for Walmart Marketplace');
    }

    public function getSourceNote(): string
    {
        return (string)__('From Walmart\'s item setup guides. Product type attributes and their limits come from the spec of each product type and are not listed.');
    }

    /**
     * @inheritDoc
     */
    protected function definitions(): array
    {
        return [
            ['product_name', 'Product name', 'Required: Brand + defining qualities + item name + pack count.', RuleInterface::TYPE_PRESENT, null, self::required()],
            ['description', 'Description', 'Required: the key selling points in paragraph form.', RuleInterface::TYPE_PRESENT, null, self::required()],
            ['brand', 'Brand', 'Required; "Unbranded" when the item has no brand.', RuleInterface::TYPE_PRESENT, null, self::required()],
            ['product_id', 'Product ID', 'Required: UPC (12), EAN (13), GTIN (14) or ISBN (10 or 13) with the check digit.', RuleInterface::TYPE_PATTERN, self::PRODUCT_ID_PATTERN, self::required()],
            ['image', 'Image', 'Required: a main image, 500 x 500 pixels at least (2000 x 2000 for zoom), JPEG preferred.', RuleInterface::TYPE_MIN_COUNT, '1', self::required()],
            ['shipping_weight', 'Shipping weight', 'Required: the weight of the individual selling unit.', RuleInterface::TYPE_GT_ZERO, null, self::required()],
            ['price', 'Price', 'The offer price, part of the orderable attributes.', RuleInterface::TYPE_GT_ZERO, null, self::important()],
        ];
    }
}
