<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Model\Hint;

use BigCartEnergy\CatalogCompleteness\Api\Data\RuleInterface;

/**
 * Common core content fields for attribute set rules. The numbers are suggestions a merchant changes freely.
 */
class CoreContentHints extends AbstractHintList
{
    public const CODE = 'core';

    public function getCode(): string
    {
        return self::CODE;
    }

    public function getTitle(): string
    {
        return (string)__('Suggested core content');
    }

    public function getSourceNote(): string
    {
        return (string)__('Fields most catalogs need on every product. Add the ones that matter to yours and point each at your attribute.');
    }

    /**
     * @inheritDoc
     */
    protected function definitions(): array
    {
        return [
            ['custom', 'Name', 'A product name long enough to say what the item is, for example at least 30 characters.', RuleInterface::TYPE_MIN_LENGTH, '30', self::niceToHave()],
            ['custom', 'Description', 'A description with real content, for example at least 200 characters after markup is removed.', RuleInterface::TYPE_MIN_LENGTH, '200', self::required()],
            ['custom', 'Short description', 'A short description for listings and feeds.', RuleInterface::TYPE_PRESENT, null, self::important()],
            ['custom', 'Images', 'Several enabled gallery images, for example at least 3.', RuleInterface::TYPE_MIN_COUNT, '3', self::important()],
            ['custom', 'Manufacturer', 'The manufacturer or brand attribute filled in.', RuleInterface::TYPE_PRESENT, null, self::required()],
            ['custom', 'Weight', 'A weight above 0 for products that ship.', RuleInterface::TYPE_GT_ZERO, null, self::important()],
            ['custom', 'Meta description', 'A meta description for search results, for example at least 70 characters.', RuleInterface::TYPE_MIN_LENGTH, '70', self::niceToHave()],
            ['custom', 'Country of manufacture', 'The country of manufacture filled in.', RuleInterface::TYPE_PRESENT, null, self::niceToHave()],
        ];
    }
}
