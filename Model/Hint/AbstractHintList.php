<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Model\Hint;

use BigCartEnergy\CatalogCompleteness\Api\Data\RuleInterface;
use BigCartEnergy\CatalogCompleteness\Model\Rule\Pattern;

abstract class AbstractHintList implements HintListInterface
{
    /**
     * GTIN as the channels accept it: GTIN-8, UPC (12), EAN/ISBN-13 (13) or ITF-14 (14), digits only.
     */
    public const GTIN_PATTERN = '^(\d{8}|\d{12}|\d{13}|\d{14})$';

    /**
     * Product ids as marketplaces accept them: ISBN-10 in addition to the GTIN lengths.
     */
    public const PRODUCT_ID_PATTERN = '^(\d{8}|\d{10}|\d{12}|\d{13}|\d{14})$';

    /**
     * @inheritDoc
     */
    public function getHints(): array
    {
        $hints = [];
        foreach ($this->definitions() as $definition) {
            $hints[] = new Hint(
                (string)$definition[0],
                (string)__($definition[1]),
                (string)__($definition[2]),
                (string)$definition[3],
                $definition[4] === null ? null : (string)$definition[4],
                (int)$definition[5]
            );
        }
        return $hints;
    }

    /**
     * Rows of [role, label, note, rule type, parameter, weight].
     *
     * @return array<int, array{0: string, 1: string, 2: string, 3: string, 4: string|int|null, 5: int}>
     */
    abstract protected function definitions(): array;

    protected static function maxLength(int $max): string
    {
        return Pattern::maxLengthBody($max);
    }

    protected static function required(): int
    {
        return RuleInterface::WEIGHT_REQUIRED;
    }

    protected static function important(): int
    {
        return RuleInterface::WEIGHT_IMPORTANT;
    }

    protected static function niceToHave(): int
    {
        return RuleInterface::WEIGHT_NICE_TO_HAVE;
    }
}
