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
 * eBay listing help (picture policy, creating a listing, product identifiers, item conditions), see docs/channels.md.
 * Item specifics are per category and are not hinted.
 */
class EbayHints extends AbstractHintList
{
    public function getCode(): string
    {
        return RuleSetInterface::CHANNEL_EBAY;
    }

    public function getTitle(): string
    {
        return (string)__('Suggested for eBay');
    }

    public function getSourceNote(): string
    {
        return (string)__('From eBay\'s listing and picture policies. Required item specifics differ by category and are not listed.');
    }

    /**
     * @inheritDoc
     */
    protected function definitions(): array
    {
        return [
            ['title', 'Title', 'At most 80 characters.', RuleInterface::TYPE_PATTERN, self::maxLength(80), self::required()],
            ['image', 'Image', 'At least one photo of 500 pixels or more on the longest side; up to 24.', RuleInterface::TYPE_MIN_COUNT, '1', self::required()],
            ['condition', 'Condition', 'A condition such as New, Used or Refurbished; the exact options depend on the category.', RuleInterface::TYPE_ALLOWED_VALUES, 'new, used, refurbished', self::important()],
            ['product_id', 'Product ID', 'Product identifiers (UPC, EAN, ISBN, GTIN) are required in most categories.', RuleInterface::TYPE_PATTERN, self::PRODUCT_ID_PATTERN, self::important()],
            ['brand', 'Brand', 'The brand name, an item specific most categories ask for.', RuleInterface::TYPE_PRESENT, null, self::important()],
            ['mpn', 'MPN', 'The manufacturer part number, highly recommended when the product has one.', RuleInterface::TYPE_PRESENT, null, self::niceToHave()],
            ['description', 'Description', 'A clear, accurate description with the key selling points.', RuleInterface::TYPE_PRESENT, null, self::important()],
            ['price', 'Price', 'The fixed price or the starting bid.', RuleInterface::TYPE_GT_ZERO, null, self::required()],
        ];
    }
}
