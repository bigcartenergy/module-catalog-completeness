<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Test\Unit\Model\Rule;

use BigCartEnergy\CatalogCompleteness\Api\Data\RuleInterface;
use BigCartEnergy\CatalogCompleteness\Model\Rule\Describer;
use PHPUnit\Framework\TestCase;

class DescriberTest extends TestCase
{
    public function testDescribe(): void
    {
        foreach (self::describeProvider() as $case => $arguments) {
            $this->checkDescribe(...array_values($arguments));
        }
    }

    /**
     * One case of testDescribe().
     */
    private function checkDescribe(string $type, ?string $parameter, string $label, bool $gallery, string $expected): void
    {
        self::assertSame($expected, (new Describer())->describe($type, $parameter, $label, $gallery));
    }

    public static function describeProvider(): array
    {
        return [
            'present' => [RuleInterface::TYPE_PRESENT, null, 'Manufacturer', false, 'Manufacturer is filled in'],
            'min length' => [RuleInterface::TYPE_MIN_LENGTH, '200', 'Description', false, 'Description at least 200 characters'],
            'min count images' => [RuleInterface::TYPE_MIN_COUNT, '3', 'Images', true, 'At least 3 images'],
            'min count multiselect' => [RuleInterface::TYPE_MIN_COUNT, '2', 'Material', false, 'Material has at least 2 values'],
            'gt zero' => [RuleInterface::TYPE_GT_ZERO, null, 'Weight', false, 'Weight greater than 0'],
            'allowed values' => [RuleInterface::TYPE_ALLOWED_VALUES, 'new, refurbished, used', 'Condition', false, 'Condition is one of 3 allowed values'],
            'pattern' => [RuleInterface::TYPE_PATTERN, '^EN \d{3}$', 'Safety standard', false, 'Safety standard matches the pattern ^EN \d{3}$'],
            'max length pattern' => [RuleInterface::TYPE_PATTERN, '^[\s\S]{1,150}$', 'Name', false, 'Name is filled in and at most 150 characters'],
            'one image' => [RuleInterface::TYPE_MIN_COUNT, '1', 'Images', true, 'At least 1 image'],
            'one value' => [RuleInterface::TYPE_MIN_COUNT, '1', 'Material', false, 'Material has at least 1 value'],
            'one character' => [RuleInterface::TYPE_MIN_LENGTH, '1', 'Name', false, 'Name at least 1 character'],
            'one allowed value' => [RuleInterface::TYPE_ALLOWED_VALUES, 'new', 'Condition', false, 'Condition is the allowed value'],
            'max length one' => [RuleInterface::TYPE_PATTERN, '^[\s\S]{1,1}$', 'Flag', false, 'Flag is filled in and at most 1 character'],
            'zero images stays plural' => [RuleInterface::TYPE_MIN_COUNT, '0', 'Images', true, 'At least 0 images'],
        ];
    }

    public function testLabels(): void
    {
        $describer = new Describer();
        self::assertSame('Required', $describer->weightLabel(3));
        self::assertSame('Important', $describer->weightLabel(2));
        self::assertSame('Nice to have', $describer->weightLabel(1));
        self::assertSame('', $describer->weightLabel(0));
        self::assertSame('Minimum length', $describer->typeLabel(RuleInterface::TYPE_MIN_LENGTH));
    }
}
