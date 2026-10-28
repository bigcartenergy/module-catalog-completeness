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
 * Google Merchant Center product data specification (support.google.com/merchants/answer/7052112), see docs/channels.md.
 */
class GoogleHints extends AbstractHintList
{
    public function getCode(): string
    {
        return RuleSetInterface::CHANNEL_GOOGLE;
    }

    public function getTitle(): string
    {
        return (string)__('Suggested for Google Merchant Center');
    }

    public function getSourceNote(): string
    {
        return (string)__('From the Merchant Center product data specification. Shipping, tax and apparel attributes are set per country or category and are not listed.');
    }

    /**
     * @inheritDoc
     */
    protected function definitions(): array
    {
        return [
            ['title', 'Title', 'Required. At most 150 characters.', RuleInterface::TYPE_PATTERN, self::maxLength(150), self::required()],
            ['description', 'Description', 'Required. At most 5,000 characters.', RuleInterface::TYPE_PATTERN, self::maxLength(5000), self::required()],
            ['image', 'Image', 'Required: one main image (image_link); up to 10 more with additional_image_link.', RuleInterface::TYPE_MIN_COUNT, '1', self::required()],
            ['availability', 'Availability', 'Required: in_stock, out_of_stock, preorder or backorder. Magento supplies it from the stock status.', RuleInterface::TYPE_PRESENT, null, self::niceToHave()],
            ['price', 'Price', 'Required, with the currency; it cannot be 0 for most products.', RuleInterface::TYPE_GT_ZERO, null, self::required()],
            ['brand', 'Brand', 'Required for new products (except movies, books and music). At most 70 characters.', RuleInterface::TYPE_PRESENT, null, self::required()],
            ['gtin', 'GTIN', 'Strongly recommended when one exists: 8, 12, 13 or 14 digits, no dashes or spaces.', RuleInterface::TYPE_PATTERN, self::GTIN_PATTERN, self::important()],
            ['mpn', 'MPN', 'Required when the product has no manufacturer-assigned GTIN. At most 70 characters.', RuleInterface::TYPE_PRESENT, null, self::niceToHave()],
            ['condition', 'Condition', 'Required for used or refurbished products: new, refurbished or used.', RuleInterface::TYPE_ALLOWED_VALUES, 'new, refurbished, used', self::important()],
            ['google_product_category', 'Google product category', 'Optional but recommended: one category id or full path from the Google taxonomy.', RuleInterface::TYPE_PRESENT, null, self::niceToHave()],
            ['shipping_weight', 'Shipping weight', 'Used when shipping is charged by weight.', RuleInterface::TYPE_GT_ZERO, null, self::niceToHave()],
        ];
    }
}
