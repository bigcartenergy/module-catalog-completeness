<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Test\Unit\Model\Rescore;

use BigCartEnergy\CatalogCompleteness\Model\Config;
use BigCartEnergy\CatalogCompleteness\Model\Rescore\CronExpression;
use PHPUnit\Framework\TestCase;

class CronExpressionTest extends TestCase
{
    /**
     * Lowest and highest value of each field, as Magento's cron passes them to its matcher.
     */
    private const RANGES = [[0, 59], [0, 23], [1, 31], [1, 12], [0, 6]];

    public function testAcceptsTheSchedulesEveryLineReadsAlike(): void
    {
        foreach (self::accepted() as $expression) {
            self::assertTrue(CronExpression::isValid($expression), 'accepted: ' . $expression);
        }
    }

    public function testRefusesEverythingElse(): void
    {
        foreach (self::refused() as $case => $expression) {
            self::assertFalse(CronExpression::isValid($expression), 'refused: ' . $case);
        }
    }

    public function testNormalizeJoinsTheFieldsWithSingleSpaces(): void
    {
        self::assertSame('0 */6 * * *', CronExpression::normalize("  0   */6\t* *\n *  "));
        self::assertSame('', CronExpression::normalize(" \t "));
        self::assertSame('*/15 * * * *', CronExpression::normalize('*/15 * * * *'));
    }

    public function testTheDefaultIsAccepted(): void
    {
        self::assertTrue(CronExpression::isValid(Config::DEFAULT_RESCORE_SCHEDULE));
    }

    /**
     * Magento's own matcher, on whichever Magento source the run points at: nothing the module accepts makes
     * it throw for any value of any field, and the default runs at minute 0 of hours 0, 6, 12 and 18.
     */
    public function testMagentosCronReadsEveryAcceptedScheduleWithoutAnError(): void
    {
        $schedule = $this->magentoSchedule();
        foreach (self::accepted() as $expression) {
            $fields = preg_split('/\s+/', trim($expression), -1, PREG_SPLIT_NO_EMPTY);
            foreach ($fields as $position => $field) {
                list($min, $max) = self::RANGES[$position];
                for ($value = $min; $value <= $max; $value++) {
                    try {
                        $schedule->matchCronExpression($field, $value);
                    } catch (\Throwable $e) {
                        self::fail('Magento could not read ' . $field . ' of ' . $expression . ': ' . $e->getMessage());
                    }
                }
            }
        }
        $fields = explode(' ', Config::DEFAULT_RESCORE_SCHEDULE);
        self::assertSame([0], $this->acceptedValues($schedule, $fields[0], 0));
        self::assertSame([0, 6, 12, 18], $this->acceptedValues($schedule, $fields[1], 1));
        self::assertSame(range(1, 31), $this->acceptedValues($schedule, $fields[2], 2));
        self::assertSame(range(1, 12), $this->acceptedValues($schedule, $fields[3], 3));
        self::assertSame(range(0, 6), $this->acceptedValues($schedule, $fields[4], 4));
    }

    /**
     * A zero step is what the validator keeps away from Magento: its matcher cannot read it.
     */
    public function testMagentosCronFailsOnAZeroStep(): void
    {
        $schedule = $this->magentoSchedule();
        $failed = false;
        try {
            $schedule->matchCronExpression('*/0', 5);
        } catch (\Throwable $e) {
            $failed = true;
        }
        self::assertTrue($failed);
        self::assertFalse(CronExpression::isValid('*/0 * * * *'));
    }

    /**
     * @return string[]
     */
    private static function accepted(): array
    {
        return [
            '0 */6 * * *',
            '*/15 * * * *',
            '0 2 * * *',
            '30 1,13 * * *',
            '0 0-5 * * 1-5',
            '0 3 * * sun',
            '0 3 1 jan *',
            '0 3 * * MON-FRI',
            '0 0 1 * *',
            '5 4 * * 0',
            '59 23 31 12 6',
            '0,15,30,45 */2 1-15 */3 *',
            '  0  */6 *  * * ',
            "0\t*/6 * * *",
            '00 06 * * *',
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function refused(): array
    {
        return [
            'empty' => '',
            'four fields' => '* * * *',
            'six fields' => '* * * * * *',
            'minute 60' => '60 * * * *',
            'hour 24' => '* 24 * * *',
            'day 0' => '* * 0 * *',
            'day 32' => '* * 32 * *',
            'month 13' => '* * * 13 *',
            'weekday 7' => '* * * * 7',
            'zero step' => '*/0 * * * *',
            'step above the field' => '*/60 * * * *',
            'step on a range' => '1-5/2 * * * *',
            'step on a number' => '5/10 * * * *',
            'reversed range' => '5-1 * * * *',
            'day name as a month' => '* * * mon *',
            'month name as a weekday' => '* * * * jan',
            'question mark' => '? * * * *',
            'last day' => '0 0 L * *',
            'nth weekday' => '0 0 * * 1#2',
            'macro' => '@daily',
            'letters' => 'a b c d e',
            'negative' => '-1 * * * *',
            'open range' => '1- * * * *',
            'empty list item' => ',1 * * * *',
            'double comma' => '1,,2 * * * *',
            'extra field' => '0 */6 * * * extra',
            'too long' => str_repeat('1,', 150) . '1 * * * *',
            'three-digit number' => '005 * * * *',
            'long name' => '0 3 * * monday',
        ];
    }

    /**
     * @return \Magento\Cron\Model\Schedule
     */
    private function magentoSchedule()
    {
        if (!class_exists(\Magento\Cron\Model\Schedule::class)) {
            self::markTestSkipped('Magento cron module not available (set BCE_MAGENTO_ROOT).');
        }
        return (new \ReflectionClass(\Magento\Cron\Model\Schedule::class))->newInstanceWithoutConstructor();
    }

    /**
     * @param \Magento\Cron\Model\Schedule $schedule
     * @return int[] the values of a field's range the matcher accepts
     */
    private function acceptedValues($schedule, string $field, int $position): array
    {
        list($min, $max) = self::RANGES[$position];
        $values = [];
        for ($value = $min; $value <= $max; $value++) {
            if ($schedule->matchCronExpression($field, $value)) {
                $values[] = $value;
            }
        }
        return $values;
    }
}
