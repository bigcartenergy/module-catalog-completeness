<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Test\Unit\Model\Config\Backend;

use BigCartEnergy\CatalogCompleteness\Model\Config;
use BigCartEnergy\CatalogCompleteness\Model\Config\Backend\RescoreSchedule;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;

class RescoreScheduleTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(LocalizedException::class)) {
            self::markTestSkipped('Magento framework not available (set BCE_MAGENTO_ROOT).');
        }
    }

    public function testSavesTheFieldsJoinedBySingleSpaces(): void
    {
        self::assertSame('0 */6 * * *', RescoreSchedule::toSchedule(' 0   */6 * * * '));
        self::assertSame('*/15 * * * *', RescoreSchedule::toSchedule('*/15 * * * *'));
        self::assertSame('0 3 * * sun', RescoreSchedule::toSchedule("0\t3 * * sun"));
    }

    public function testAnEmptyScheduleSavesAsTheDefault(): void
    {
        foreach (['', '   ', null] as $raw) {
            self::assertSame(Config::DEFAULT_RESCORE_SCHEDULE, RescoreSchedule::toSchedule($raw));
        }
    }

    public function testRefusesWhatMagentosCronCouldNotRunAlike(): void
    {
        $cases = [
            'four fields' => '0 */6 * *',
            'zero step' => '*/0 * * * *',
            'step on a range' => '1-23/2 * * * *',
            'minute 60' => '60 * * * *',
            'macro' => '@hourly',
            'array' => ['0 */6 * * *'],
        ];
        foreach ($cases as $case => $raw) {
            try {
                RescoreSchedule::toSchedule($raw);
            } catch (LocalizedException $e) {
                self::assertStringContainsString('five fields', (string)$e->getMessage(), $case);
                continue;
            }
            self::fail($case . ' was accepted');
        }
    }
}
