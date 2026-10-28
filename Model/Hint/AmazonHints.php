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
 * Amazon product title requirements (Seller Central announcement) and the Product Type Definitions API, see docs/channels.md.
 * Item specifics are per product type and are not hinted.
 */
class AmazonHints extends AbstractHintList
{
    public function getCode(): string
    {
        return RuleSetInterface::CHANNEL_AMAZON;
    }

    public function getTitle(): string
    {
        return (string)__('Suggested for Amazon');
    }

    public function getSourceNote(): string
    {
        return (string)__('From Amazon\'s title requirements and listing attributes. Requirements differ by product type; check the product type definition for the rest.');
    }

    /**
     * @inheritDoc
     */
    protected function definitions(): array
    {
        return [
            ['title', 'Title', 'At most 200 characters including spaces; no word more than twice; no !, $, ?, _, {, }, ^, ¬ or ¦.', RuleInterface::TYPE_PATTERN, self::maxLength(200), self::required()],
            ['brand', 'Brand', 'A brand name is required on new listings (or a brand exemption).', RuleInterface::TYPE_PRESENT, null, self::required()],
            ['product_id', 'Product ID', 'A UPC (12), EAN (13), GTIN (14) or ISBN (10 or 13) is required unless the product is exempt.', RuleInterface::TYPE_PATTERN, self::PRODUCT_ID_PATTERN, self::required()],
            ['image', 'Image', 'A main image on a pure white background is required; more images show the product in use.', RuleInterface::TYPE_MIN_COUNT, '1', self::required()],
            ['bullet_points', 'Bullet points', 'Up to five bullet points highlighting key details; map the attribute you keep them in.', RuleInterface::TYPE_PRESENT, null, self::important()],
            ['description', 'Description', 'A product description beyond the bullet points.', RuleInterface::TYPE_PRESENT, null, self::important()],
            ['price', 'Price', 'An offer price for the listing.', RuleInterface::TYPE_GT_ZERO, null, self::required()],
        ];
    }
}
