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
 * Microsoft Merchant Center, from the Content API products resource (learn.microsoft.com), see docs/channels.md.
 */
class MicrosoftHints extends AbstractHintList
{
    public function getCode(): string
    {
        return RuleSetInterface::CHANNEL_MICROSOFT;
    }

    public function getTitle(): string
    {
        return (string)__('Suggested for Microsoft Merchant Center');
    }

    public function getSourceNote(): string
    {
        return (string)__('From the Microsoft Merchant Center product fields. Shipping is required for Germany only and is not listed.');
    }

    /**
     * @inheritDoc
     */
    protected function definitions(): array
    {
        return [
            ['title', 'Title', 'Required. At most 150 characters, no promotional text.', RuleInterface::TYPE_PATTERN, self::maxLength(150), self::required()],
            ['description', 'Description', 'At most 10,000 characters, no promotional text; goes through editorial review.', RuleInterface::TYPE_PATTERN, self::maxLength(10000), self::important()],
            ['image', 'Image', 'Required: one image (bmp, gif, exif, jpg, png or tiff, up to 3.9 MB).', RuleInterface::TYPE_MIN_COUNT, '1', self::required()],
            ['availability', 'Availability', 'Required: in stock, out of stock or preorder. Magento supplies it from the stock status.', RuleInterface::TYPE_PRESENT, null, self::niceToHave()],
            ['price', 'Price', 'Required, between 0.01 and 10,000,000.00, matching the product page.', RuleInterface::TYPE_GT_ZERO, null, self::required()],
            ['brand', 'Brand', 'Required. Keep it to 70 characters so it displays well.', RuleInterface::TYPE_PRESENT, null, self::required()],
            ['gtin', 'GTIN', 'Required when the manufacturer assigns one: EAN, ISBN, JAN or UPC digits.', RuleInterface::TYPE_PATTERN, self::GTIN_PATTERN, self::important()],
            ['mpn', 'MPN', 'Required when the manufacturer assigns one. At most 70 characters.', RuleInterface::TYPE_PRESENT, null, self::important()],
            ['condition', 'Condition', 'Required: new, refurbished or used.', RuleInterface::TYPE_ALLOWED_VALUES, 'new, refurbished, used', self::required()],
            ['google_product_category', 'Google product category', 'Optional: a category string or id, at most 255 characters.', RuleInterface::TYPE_PRESENT, null, self::niceToHave()],
            ['shipping_weight', 'Shipping weight', 'Optional outside Germany; used for shipping cost.', RuleInterface::TYPE_GT_ZERO, null, self::niceToHave()],
        ];
    }
}
