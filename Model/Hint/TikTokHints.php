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
 * TikTok Shop listing policy and quality guidelines (seller-us.tiktok.com Seller University), see docs/channels.md.
 */
class TikTokHints extends AbstractHintList
{
    public function getCode(): string
    {
        return RuleSetInterface::CHANNEL_TIKTOK;
    }

    public function getTitle(): string
    {
        return (string)__('Suggested for TikTok Shop');
    }

    public function getSourceNote(): string
    {
        return (string)__('From the TikTok Shop product listing policy and listing quality guidelines. Category attributes are set per category and are not listed.');
    }

    /**
     * @inheritDoc
     */
    protected function definitions(): array
    {
        return [
            ['title', 'Title', 'Between 25 and 200 characters; 40 to 150 recommended; no promotional wording.', RuleInterface::TYPE_MIN_LENGTH, '25', self::required()],
            ['description', 'Description', 'At least 500 characters recommended; no less than 30 words; 3 to 5 bullet points.', RuleInterface::TYPE_MIN_LENGTH, '500', self::required()],
            ['image', 'Image', 'At least 5 images recommended, up to 9, each at least 600 x 600 pixels; main image on a white background.', RuleInterface::TYPE_MIN_COUNT, '5', self::important()],
            ['brand', 'Brand', 'The correct brand name is mandatory on every product detail page.', RuleInterface::TYPE_PRESENT, null, self::required()],
            ['price', 'Price', 'A list price in line with the Fair Pricing Policy.', RuleInterface::TYPE_GT_ZERO, null, self::required()],
            ['category', 'Product category', 'The most accurate product category; a wrong category violates the listing policy.', RuleInterface::TYPE_PRESENT, null, self::important()],
        ];
    }
}
