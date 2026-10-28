<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Test\Unit\Model\Export;

use BigCartEnergy\CatalogCompleteness\Model\Export\CsvCell;
use PHPUnit\Framework\TestCase;

class CsvCellTest extends TestCase
{
    public function testSanitize(): void
    {
        foreach (self::cellProvider() as $case => $arguments) {
            $this->checkSanitize(...array_values($arguments));
        }
    }

    /**
     * One case of testSanitize().
     *
     * @param mixed $value
     */
    private function checkSanitize($value, string $expected): void
    {
        self::assertSame($expected, CsvCell::sanitize($value));
    }

    public static function cellProvider(): array
    {
        return [
            'formula' => ['=SUM(A1:A9)', "'=SUM(A1:A9)"],
            'plus' => ['+1 234', "'+1 234"],
            'minus' => ['-5', "'-5"],
            'at' => ['@import', "'@import"],
            'tab' => ["\tx", "'\tx"],
            'carriage return' => ["\rx", "'\rx"],
            'plain text' => ['Hex bolt M10', 'Hex bolt M10'],
            'number' => [42, '42'],
            'empty' => ['', ''],
            'null' => [null, ''],
            'list' => [['=a', 'b'], "'=a | b"],
            'leading space before a formula' => [' =a', "' =a"],
            'several spaces before a plus' => ['   +1', "'   +1"],
            'leading newline before an at' => ["\n@x", "'\n@x"],
            'no-break space before an equals sign' => ["\u{00A0}=1", "'\u{00A0}=1"],
            'byte order mark before a minus' => ["\u{FEFF}-1", "'\u{FEFF}-1"],
            'space then tab at the start' => [" \tx", "' \tx"],
            'leading space without a trigger' => [' plain', ' plain'],
            'trigger later in the text is fine' => ['M8 = 8 mm', 'M8 = 8 mm'],
            'leading quote then a formula is kept as is' => ['"=a"', '"=a"'],
            'invalid UTF-8 is prefixed, never passed through unchecked' => ["Ã=x", "'Ã=x"],
            'invalid UTF-8 without a trigger is still prefixed (fail safe)' => ["Ã x", "'Ã x"],
        ];
    }
}
