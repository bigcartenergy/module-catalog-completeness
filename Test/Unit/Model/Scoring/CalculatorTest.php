<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Test\Unit\Model\Scoring;

use BigCartEnergy\CatalogCompleteness\Model\Scoring\Calculator;
use PHPUnit\Framework\TestCase;

class CalculatorTest extends TestCase
{
    public function testScore(): void
    {
        foreach (self::scoreProvider() as $case => $arguments) {
            $this->checkScore(...array_values($arguments));
        }
    }

    /**
     * One case of testScore().
     */
    private function checkScore(int $passed, int $total, ?int $expected): void
    {
        self::assertSame($expected, (new Calculator())->score($passed, $total));
    }

    public static function scoreProvider(): array
    {
        return [
            'no applicable rule' => [0, 0, null],
            'everything passes' => [6, 6, 100],
            'nothing passes' => [0, 6, 0],
            'one third' => [1, 3, 33],
            'two thirds' => [2, 3, 67],
            'rounding guard: 299 of 300 is 99, never 100' => [299, 300, 99],
            'rounding guard: 999 of 1000' => [999, 1000, 99],
            'half' => [3, 6, 50],
            'weights 3 2 1: required fails' => [3, 6, 50],
            'weights 3 2 1: nice to have fails' => [5, 6, 83],
        ];
    }

    public function testApplies(): void
    {
        $calculator = new Calculator();
        self::assertTrue($calculator->applies([], 'simple'));
        self::assertTrue($calculator->applies(['simple', 'virtual'], 'virtual'));
        self::assertFalse($calculator->applies(['simple', 'virtual'], 'configurable'));
    }

    public function testShare(): void
    {
        $calculator = new Calculator();
        self::assertSame(0.0, $calculator->share(3, 0));
        self::assertSame(0.5, $calculator->share(3, 6));
        self::assertSame(15, $calculator->sharePercent(3, 20));
        self::assertSame(0, $calculator->sharePercent(0, 20));
    }
}
