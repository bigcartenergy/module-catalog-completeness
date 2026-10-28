<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Test\Unit\Model;

use BigCartEnergy\CatalogCompleteness\Model\Config;
use Magento\Framework\App\Config\ScopeConfigInterface;
use PHPUnit\Framework\TestCase;

/**
 * Reading the periodic rescore's settings: the switch, the schedule Magento's cron runs on, the mode and the
 * batch size, with their defaults and limits.
 */
class ConfigRescoreTest extends TestCase
{
    public function testTheScheduleIsReadWithItsFieldsJoinedBySingleSpaces(): void
    {
        $cases = [
            '0 */6 * * *' => '0 */6 * * *',
            '  */15   * * * * ' => '*/15 * * * *',
            "0\t2 * * mon-fri" => '0 2 * * mon-fri',
            '30 1,13 * * *' => '30 1,13 * * *',
        ];
        foreach ($cases as $stored => $expected) {
            $config = $this->config([Config::XML_PATH_RESCORE_SCHEDULE => (string)$stored]);
            self::assertSame($expected, $config->getRescoreSchedule());
        }
    }

    public function testAnEmptyOrUnacceptedScheduleReadsAsTheDefault(): void
    {
        foreach (['', '   ', null, 'every six hours', '*/0 * * * *', '0 */6 * *', '1-5/2 * * * *'] as $stored) {
            self::assertSame(
                '0 */6 * * *',
                $this->config([Config::XML_PATH_RESCORE_SCHEDULE => $stored])->getRescoreSchedule(),
                var_export($stored, true)
            );
        }
        self::assertSame('0 */6 * * *', Config::DEFAULT_RESCORE_SCHEDULE);
    }

    public function testTheSwitch(): void
    {
        self::assertTrue($this->config([Config::XML_PATH_RESCORE_ENABLED => '1'])->isRescoreEnabled());
        self::assertFalse($this->config([Config::XML_PATH_RESCORE_ENABLED => '0'])->isRescoreEnabled());
        self::assertFalse($this->config([])->isRescoreEnabled());
    }

    public function testTheModeIsFullUnlessChangedIsChosen(): void
    {
        $cases = [
            'changed' => 'changed',
            ' changed ' => 'changed',
            'full' => 'full',
            '' => 'full',
            'CHANGED' => 'full',
            'all' => 'full',
        ];
        foreach ($cases as $stored => $expected) {
            $config = $this->config([Config::XML_PATH_RESCORE_MODE => (string)$stored]);
            self::assertSame($expected, $config->getRescoreMode(), 'stored ' . var_export($stored, true));
        }
        self::assertSame('full', $this->config([])->getRescoreMode());
    }

    public function testTheBatchSizeStaysWithinItsRange(): void
    {
        $cases = [
            '500' => 500,
            '50' => 50,
            '49' => 50,
            '0' => 50,
            '5000' => 5000,
            '5001' => 5000,
            '1000000' => 5000,
            ' 750 ' => 750,
            '' => 500,
            'abc' => 500,
            '-5' => 500,
            '1e3' => 500,
            '12.5' => 500,
        ];
        foreach ($cases as $stored => $expected) {
            self::assertSame(
                $expected,
                $this->config([Config::XML_PATH_RESCORE_BATCH_SIZE => (string)$stored])->getRescoreBatchSize(),
                'stored ' . var_export($stored, true)
            );
        }
        self::assertSame(500, $this->config([])->getRescoreBatchSize());
    }

    /**
     * @param array<string, string|null> $values path => stored value
     */
    private function config(array $values): Config
    {
        if (!interface_exists(ScopeConfigInterface::class)) {
            self::markTestSkipped('Magento framework not available (set BCE_MAGENTO_ROOT).');
        }
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnCallback(function ($path) use ($values) {
            return array_key_exists($path, $values) ? $values[$path] : null;
        });
        $scopeConfig->method('isSetFlag')->willReturnCallback(function ($path) use ($values) {
            return !empty($values[$path]);
        });
        return new Config($scopeConfig);
    }
}
