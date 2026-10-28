<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Test\Unit\Model\Api;

use BigCartEnergy\CatalogCompleteness\Api\Data\ProductScoreInterface;
use BigCartEnergy\CatalogCompleteness\Model\Api\ProductScore;
use PHPUnit\Framework\TestCase;

/**
 * A score the REST API returns always says why it is what it is.
 */
class ProductScoreTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(\Magento\Framework\Api\AbstractSimpleObject::class)) {
            self::markTestSkipped('Magento framework not available (set BCE_MAGENTO_ROOT).');
        }
    }

    public function testANullScoreSaysNoRulesAssigned(): void
    {
        $score = new ProductScore([ProductScoreInterface::SCORE => null]);
        self::assertNull($score->getScore());
        self::assertSame(ProductScoreInterface::STATE_NO_RULES_ASSIGNED, $score->getState());
        $scored = new ProductScore([ProductScoreInterface::SCORE => 80]);
        self::assertSame(ProductScoreInterface::STATE_SCORED, $scored->getState());
    }

    public function testTheStateItWasGivenWins(): void
    {
        $notCalculated = new ProductScore([
            ProductScoreInterface::SCORE => null,
            ProductScoreInterface::STATE => ProductScoreInterface::STATE_NOT_CALCULATED,
        ]);
        self::assertSame('not_calculated', $notCalculated->getState());
        $unknown = new ProductScore([ProductScoreInterface::SCORE => 5, ProductScoreInterface::STATE => 'whatever']);
        self::assertSame('scored', $unknown->getState(), 'an unknown state falls back to what the score says');
    }
}
