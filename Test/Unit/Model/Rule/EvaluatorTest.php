<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Test\Unit\Model\Rule;

use BigCartEnergy\CatalogCompleteness\Api\Data\RuleInterface;
use BigCartEnergy\CatalogCompleteness\Model\Rule\AttributeDescriptor;
use BigCartEnergy\CatalogCompleteness\Model\Rule\Evaluator;
use PHPUnit\Framework\TestCase;

/**
 * Every rule type and every edge of the scoring rules: whitespace, zero, option values, markup, patterns.
 */
class EvaluatorTest extends TestCase
{
    /**
     * @var Evaluator
     */
    private $evaluator;

    protected function setUp(): void
    {
        $this->evaluator = new Evaluator();
    }

    private static function text(string $code = 'name'): AttributeDescriptor
    {
        return new AttributeDescriptor($code, 1, 'Name', 'varchar', 'text');
    }

    private static function richText(): AttributeDescriptor
    {
        return new AttributeDescriptor('description', 2, 'Description', 'text', 'textarea');
    }

    private static function decimal(): AttributeDescriptor
    {
        return new AttributeDescriptor('weight', 3, 'Weight', 'decimal', 'weight');
    }

    private static function select(): AttributeDescriptor
    {
        return new AttributeDescriptor('manufacturer', 4, 'Manufacturer', 'int', 'select', ['11' => 'Northfield', '12' => 'Ridgeway']);
    }

    private static function boolean(): AttributeDescriptor
    {
        return new AttributeDescriptor('is_featured', 5, 'Featured', 'int', 'boolean', ['1' => 'Yes', '0' => 'No']);
    }

    private static function multiselect(): AttributeDescriptor
    {
        return new AttributeDescriptor('material', 6, 'Material', 'varchar', 'multiselect', ['21' => 'Steel', '22' => 'Brass']);
    }

    public function testPresent(): void
    {
        foreach (self::presentProvider() as $case => $arguments) {
            $this->checkPresent(...array_values($arguments));
        }
    }

    /**
     * One case of testPresent().
     *
     * @param mixed $value
     */
    private function checkPresent($value, AttributeDescriptor $attribute, bool $expected): void
    {
        self::assertSame($expected, $this->evaluator->evaluate(RuleInterface::TYPE_PRESENT, null, $value, $attribute, true));
    }

    public static function presentProvider(): array
    {
        return [
            'text value' => ['Hex bolt M10', self::text(), true],
            'null' => [null, self::text(), false],
            'empty string' => ['', self::text(), false],
            'whitespace only' => ["  \t\n ", self::text(), false],
            'zero as text' => ['0', self::text(), true],
            'decimal zero is a value' => ['0.0000', self::decimal(), true],
            'boolean No is a value' => ['0', self::boolean(), true],
            'select empty option' => ['', self::select(), false],
            'select with option' => ['12', self::select(), true],
            'multiselect none' => ['', self::multiselect(), false],
            'multiselect comma only' => [',', self::multiselect(), false],
            'multiselect one' => ['21', self::multiselect(), true],
            'multiselect two' => ['21,22', self::multiselect(), true],
            'rich text nbsp paragraph' => ['<p>&nbsp;</p>', self::richText(), false],
            'rich text tags only' => ['<div><br></div>', self::richText(), false],
            'rich text real' => ['<p>Zinc plated.</p>', self::richText(), true],
            'gallery zero' => [0, AttributeDescriptor::gallery(), false],
            'gallery one' => [1, AttributeDescriptor::gallery(), true],
            'stock status out of stock still present' => [0, AttributeDescriptor::stockStatus(), true],
            'stock status null' => [null, AttributeDescriptor::stockStatus(), false],
        ];
    }

    public function testAttributeMissingFromSetFailsEveryType(): void
    {
        foreach (RuleInterface::TYPES as $type) {
            self::assertFalse(
                $this->evaluator->evaluate($type, '1', 'value', self::text(), false),
                $type . ' must fail when the attribute is not in the set'
            );
        }
    }

    public function testMinLength(): void
    {
        foreach (self::minLengthProvider() as $case => $arguments) {
            $this->checkMinLength(...array_values($arguments));
        }
    }

    /**
     * One case of testMinLength().
     *
     * @param mixed $value
     */
    private function checkMinLength($value, AttributeDescriptor $attribute, string $parameter, bool $expected): void
    {
        self::assertSame($expected, $this->evaluator->evaluate(RuleInterface::TYPE_MIN_LENGTH, $parameter, $value, $attribute, true));
    }

    public static function minLengthProvider(): array
    {
        return [
            'exact boundary' => ['abcde', self::text(), '5', true],
            'one short' => ['abcd', self::text(), '5', false],
            'trimmed before counting' => ['  abcd  ', self::text(), '5', false],
            'multibyte counts characters' => ['Größe', self::text(), '5', true],
            'markup does not count' => ['<p><strong>Short</strong></p><ul><li>a</li></ul>', self::richText(), '8', false],
            'markup boundaries become spaces' => ['<p>Short</p><p>a</p>', self::richText(), '7', true],
            'markup stripped text counts' => ['<p>Hex bolt M10 x 50, zinc plated, grade 8.8</p>', self::richText(), '30', true],
            'entities decoded' => ['&amp;&lt;&gt;', self::richText(), '3', true],
            'null fails' => [null, self::text(), '1', false],
            'gallery cannot have a length' => [3, AttributeDescriptor::gallery(), '1', false],
        ];
    }

    public function testMinCount(): void
    {
        foreach (self::minCountProvider() as $case => $arguments) {
            $this->checkMinCount(...array_values($arguments));
        }
    }

    /**
     * One case of testMinCount().
     *
     * @param mixed $value
     */
    private function checkMinCount($value, AttributeDescriptor $attribute, string $parameter, bool $expected): void
    {
        self::assertSame($expected, $this->evaluator->evaluate(RuleInterface::TYPE_MIN_COUNT, $parameter, $value, $attribute, true));
    }

    public static function minCountProvider(): array
    {
        return [
            'gallery enough' => [3, AttributeDescriptor::gallery(), '3', true],
            'gallery short' => [2, AttributeDescriptor::gallery(), '3', false],
            'gallery null' => [null, AttributeDescriptor::gallery(), '1', false],
            'multiselect enough' => ['21,22', self::multiselect(), '2', true],
            'multiselect short' => ['21', self::multiselect(), '2', false],
            'multiselect trailing comma ignored' => ['21,', self::multiselect(), '2', false],
            'plain text is not countable' => ['a,b,c', self::text(), '2', false],
        ];
    }

    public function testGreaterThanZero(): void
    {
        foreach (self::gtZeroProvider() as $case => $arguments) {
            $this->checkGreaterThanZero(...array_values($arguments));
        }
    }

    /**
     * One case of testGreaterThanZero().
     *
     * @param mixed $value
     */
    private function checkGreaterThanZero($value, bool $expected): void
    {
        self::assertSame($expected, $this->evaluator->evaluate(RuleInterface::TYPE_GT_ZERO, null, $value, self::decimal(), true));
    }

    public static function gtZeroProvider(): array
    {
        return [
            'positive' => ['12.5000', true],
            'small positive' => ['0.0001', true],
            'zero' => ['0.0000', false],
            'negative' => ['-1', false],
            'text' => ['heavy', false],
            'empty' => ['', false],
            'null' => [null, false],
        ];
    }

    public function testAllowedValues(): void
    {
        foreach (self::allowedValuesProvider() as $case => $arguments) {
            $this->checkAllowedValues(...array_values($arguments));
        }
    }

    /**
     * One case of testAllowedValues().
     *
     * @param mixed $value
     */
    private function checkAllowedValues($value, AttributeDescriptor $attribute, string $parameter, bool $expected): void
    {
        self::assertSame($expected, $this->evaluator->evaluate(RuleInterface::TYPE_ALLOWED_VALUES, $parameter, $value, $attribute, true));
    }

    public static function allowedValuesProvider(): array
    {
        return [
            'text match case-insensitive' => ['New', self::text('item_condition'), 'new, refurbished, used', true],
            'text no match' => ['Open box', self::text('item_condition'), 'new, refurbished, used', false],
            'option label match' => ['11', self::select(), 'northfield, ridgeway', true],
            'option id match' => ['12', self::select(), '12', true],
            'option not allowed' => ['12', self::select(), 'northfield', false],
            'multiselect all allowed' => ['21,22', self::multiselect(), 'steel, brass', true],
            'multiselect one not allowed' => ['21,22', self::multiselect(), 'steel', false],
            'empty value' => ['', self::select(), 'northfield', false],
            'empty list' => ['11', self::select(), ' , ', false],
            'whitespace in list entries' => ['new', self::text('item_condition'), '  New  ,used', true],
        ];
    }

    public function testPattern(): void
    {
        foreach (self::patternProvider() as $case => $arguments) {
            $this->checkPattern(...array_values($arguments));
        }
    }

    /**
     * One case of testPattern().
     *
     * @param mixed $value
     */
    private function checkPattern($value, AttributeDescriptor $attribute, string $parameter, bool $expected): void
    {
        self::assertSame($expected, $this->evaluator->evaluate(RuleInterface::TYPE_PATTERN, $parameter, $value, $attribute, true));
    }

    public static function patternProvider(): array
    {
        return [
            'gtin 13 digits' => ['4006381333931', self::text('gtin'), '^(\d{8}|\d{12}|\d{13}|\d{14})$', true],
            'gtin with dash' => ['4006-381333931', self::text('gtin'), '^(\d{8}|\d{12}|\d{13}|\d{14})$', false],
            'gtin trimmed' => [' 40063813 ', self::text('gtin'), '^(\d{8}|\d{12}|\d{13}|\d{14})$', true],
            'slash inside the body' => ['EN 388/2016', self::text('safety_standard'), '^EN \d{3}/\d{4}$', true],
            'max length form on rich text ignores markup' => ['<p>' . str_repeat('a', 150) . '</p>', self::richText(), '^[\s\S]{1,150}$', true],
            'max length form exceeded' => [str_repeat('a', 151), self::text(), '^[\s\S]{1,150}$', false],
            'invalid pattern never throws' => ['abc', self::text(), '(unclosed', false],
            'empty value' => ['', self::text(), '.*', false],
            'null value' => [null, self::text(), '.*', false],
            'unicode' => ['Größe', self::text(), '^\p{L}+$', true],
        ];
    }

    public function testUnknownRuleTypeFails(): void
    {
        self::assertFalse($this->evaluator->evaluate('something_else', null, 'value', self::text(), true));
    }
}
