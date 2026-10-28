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
 * Meta (Facebook and Instagram Shops) catalog fields (developers.facebook.com), see docs/channels.md.
 */
class MetaHints extends AbstractHintList
{
    public function getCode(): string
    {
        return RuleSetInterface::CHANNEL_META;
    }

    public function getTitle(): string
    {
        return (string)__('Suggested for Meta (Facebook and Instagram Shops)');
    }

    public function getSourceNote(): string
    {
        return (string)__('From the Meta commerce catalog fields reference. Variant and apparel fields apply per product and are not listed.');
    }

    /**
     * @inheritDoc
     */
    protected function definitions(): array
    {
        return [
            ['title', 'Title', 'Required. At most 200 characters; Meta recommends 65 or fewer.', RuleInterface::TYPE_PATTERN, self::maxLength(200), self::required()],
            ['description', 'Description', 'Required. Plain text, at most 9,999 characters, different from the title.', RuleInterface::TYPE_PATTERN, self::maxLength(9999), self::required()],
            ['image', 'Image', 'Required: one image of at least 500 x 500 pixels; up to 50 more.', RuleInterface::TYPE_MIN_COUNT, '1', self::required()],
            ['availability', 'Availability', 'Required: in stock or out of stock. Magento supplies it from the stock status.', RuleInterface::TYPE_PRESENT, null, self::niceToHave()],
            ['condition', 'Condition', 'Required: new, refurbished or used.', RuleInterface::TYPE_ALLOWED_VALUES, 'new, refurbished, used', self::required()],
            ['price', 'Price', 'Required, a number with an ISO currency code.', RuleInterface::TYPE_GT_ZERO, null, self::required()],
            ['google_product_category', 'Google product category', 'Required: a Google product category or a Facebook product category.', RuleInterface::TYPE_PRESENT, null, self::required()],
            ['brand', 'Brand', 'Required; at least one of brand, GTIN or MPN. At most 100 characters.', RuleInterface::TYPE_PRESENT, null, self::required()],
            ['gtin', 'GTIN', 'Recommended: UPC 12, EAN or JAN 13 (JAN also 8), ISBN-13 13 or ITF-14 14 digits.', RuleInterface::TYPE_PATTERN, self::GTIN_PATTERN, self::important()],
            ['mpn', 'MPN', 'Recommended. At most 100 characters.', RuleInterface::TYPE_PRESENT, null, self::niceToHave()],
            ['shipping_weight', 'Shipping weight', 'Optional, with a unit of lb, oz, g or kg.', RuleInterface::TYPE_GT_ZERO, null, self::niceToHave()],
        ];
    }
}
