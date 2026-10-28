<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Test\Unit\Model\Config\Backend;

use BigCartEnergy\CatalogCompleteness\Model\Config\Backend\Threshold;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;

class ThresholdTest extends TestCase
{
    public function testAcceptsWholeNumbersFromOneToNinetyNine(): void
    {
        self::assertSame(1, Threshold::toThreshold('1', Threshold::FIELD_GOOD));
        self::assertSame(80, Threshold::toThreshold(' 80 ', Threshold::FIELD_GOOD));
        self::assertSame(99, Threshold::toThreshold(99, Threshold::FIELD_NEEDS_WORK));
    }

    public function testRejectsAnythingElse(): void
    {
        foreach (self::rejectedProvider() as $case => $arguments) {
            $this->checkRejectsAnythingElse((string)$case, ...array_values($arguments));
        }
    }

    /**
     * One case of testRejectsAnythingElse(): the value must be refused with a message.
     *
     * @param mixed $raw
     */
    private function checkRejectsAnythingElse(string $case, $raw): void
    {
        try {
            Threshold::toThreshold($raw, Threshold::FIELD_GOOD);
        } catch (LocalizedException $e) {
            self::assertNotSame('', (string)$e->getMessage(), $case);
            return;
        }
        self::fail($case . ' was accepted');
    }

    public static function rejectedProvider(): array
    {
        return [
            'empty' => [''],
            'zero' => ['0'],
            'hundred' => ['100'],
            'negative' => ['-5'],
            'decimal' => ['80.5'],
            'text' => ['eighty'],
            'formula' => ['=80'],
        ];
    }
}
