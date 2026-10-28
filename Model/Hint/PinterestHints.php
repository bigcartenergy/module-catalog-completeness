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
 * Pinterest catalogs data source specification (help.pinterest.com), see docs/channels.md.
 */
class PinterestHints extends AbstractHintList
{
    public function getCode(): string
    {
        return RuleSetInterface::CHANNEL_PINTEREST;
    }

    public function getTitle(): string
    {
        return (string)__('Suggested for Pinterest');
    }

    public function getSourceNote(): string
    {
        return (string)__('From the Pinterest data source specification. A missing required field fails the whole feed, so the required rows matter most.');
    }

    /**
     * @inheritDoc
     */
    protected function definitions(): array
    {
        return [
            ['title', 'Title', 'Required. At most 500 characters, matching the landing page.', RuleInterface::TYPE_PATTERN, self::maxLength(500), self::required()],
            ['description', 'Description', 'Required. Plain text, at most 10,000 characters.', RuleInterface::TYPE_PATTERN, self::maxLength(10000), self::required()],
            ['image', 'Image', 'Required: one main image of at least 1000 x 1500 pixels; up to 10 more.', RuleInterface::TYPE_MIN_COUNT, '1', self::required()],
            ['price', 'Price', 'Required, with an ISO currency code; zero is not allowed.', RuleInterface::TYPE_GT_ZERO, null, self::required()],
            ['availability', 'Availability', 'Required: in stock, out of stock or preorder. Magento supplies it from the stock status.', RuleInterface::TYPE_PRESENT, null, self::niceToHave()],
            ['brand', 'Brand', 'Optional. At most 100 characters.', RuleInterface::TYPE_PRESENT, null, self::important()],
            ['gtin', 'GTIN', 'Optional: numeric, following the GTIN guidelines.', RuleInterface::TYPE_PATTERN, self::GTIN_PATTERN, self::niceToHave()],
            ['mpn', 'MPN', 'Optional. At most 70 alphanumeric characters.', RuleInterface::TYPE_PRESENT, null, self::niceToHave()],
            ['google_product_category', 'Google product category', 'Optional: text or numeric code from the Google product taxonomy.', RuleInterface::TYPE_PRESENT, null, self::niceToHave()],
            ['condition', 'Condition', 'Optional: new, used or refurbished.', RuleInterface::TYPE_ALLOWED_VALUES, 'new, used, refurbished', self::niceToHave()],
            ['shipping_weight', 'Shipping weight', 'Optional: a number followed by a unit.', RuleInterface::TYPE_GT_ZERO, null, self::niceToHave()],
        ];
    }
}
