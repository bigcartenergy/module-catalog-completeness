<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Test\Unit\Model\Rule;

use BigCartEnergy\CatalogCompleteness\Api\Data\RuleInterface;
use BigCartEnergy\CatalogCompleteness\Api\Data\RuleSetInterface;
use BigCartEnergy\CatalogCompleteness\Model\Channel\ChannelRegistry;
use BigCartEnergy\CatalogCompleteness\Model\Rule\PayloadValidator;
use BigCartEnergy\CatalogCompleteness\Model\Rule\ValidationResult;
use BigCartEnergy\CatalogCompleteness\Test\Unit\Stub\LookupStub;
use PHPUnit\Framework\TestCase;

class PayloadValidatorTest extends TestCase
{
    /**
     * @var PayloadValidator
     */
    private $validator;

    protected function setUp(): void
    {
        $this->validator = new PayloadValidator(new LookupStub(), new ChannelRegistry());
    }

    private function validate(array $rows, string $kind = RuleSetInterface::KIND_ATTRIBUTE_SET, ?string $channel = null, bool $preview = false): ValidationResult
    {
        return $this->validator->validateRules($rows, $kind, $channel, $preview);
    }

    private static function fields(ValidationResult $result): array
    {
        $fields = [];
        foreach ($result->getErrors() as $error) {
            $fields[] = $error['row'] . ':' . $error['field'];
        }
        return $fields;
    }

    public function testAValidPayloadIsNormalised(): void
    {
        $result = $this->validate([
            ['attribute_code' => ' description ', 'rule_type' => 'min_length', 'parameter' => ' 200 ', 'weight' => '3', 'applies_to' => ['simple', 'configurable']],
            ['attribute_code' => 'media_gallery', 'rule_type' => 'min_count', 'parameter' => '3', 'weight' => 2, 'applies_to' => 'simple,configurable,bundle,grouped,virtual,downloadable'],
            ['attribute_code' => 'manufacturer', 'rule_type' => 'present', 'parameter' => 'ignored', 'weight' => 1, 'applies_to' => []],
            ['attribute_code' => 'item_condition', 'rule_type' => 'allowed_values', 'parameter' => ' new ,used,, new', 'weight' => 2],
            ['attribute_code' => 'gtin', 'rule_type' => 'pattern', 'parameter' => '^\d{13}$', 'weight' => 3, 'rule_id' => '17'],
        ]);
        self::assertTrue($result->isValid(), implode(' | ', $result->getMessages()));
        $rules = $result->getRules();
        self::assertSame('description', $rules[0]['attribute_code']);
        self::assertSame('200', $rules[0]['parameter']);
        self::assertSame(['simple', 'configurable'], $rules[0]['applies_to']);
        self::assertSame([], $rules[1]['applies_to'], 'every type selected is stored as all');
        self::assertNull($rules[2]['parameter'], 'present takes no parameter');
        self::assertSame('new, used', $rules[3]['parameter']);
        self::assertSame(17, $rules[4]['rule_id']);
        self::assertNull($rules[0]['role'], 'attribute set rules carry no role');
    }

    public function testDuplicateAttributeIsReportedOnTheLaterRow(): void
    {
        $result = $this->validate([
            ['attribute_code' => 'description', 'rule_type' => 'min_length', 'parameter' => '200', 'weight' => 3],
            ['attribute_code' => 'description', 'rule_type' => 'present', 'weight' => 2],
        ]);
        self::assertSame(['1:attribute_code'], self::fields($result));
        self::assertSame('Description already has a rule (rule 1). Use one rule per attribute.', $result->getMessages()[0]);
    }

    public function testMissingParametersPerType(): void
    {
        $result = $this->validate([
            ['attribute_code' => 'media_gallery', 'rule_type' => 'min_count', 'parameter' => '', 'weight' => 2],
            ['attribute_code' => 'description', 'rule_type' => 'min_length', 'parameter' => 'two hundred', 'weight' => 3],
            ['attribute_code' => 'item_condition', 'rule_type' => 'allowed_values', 'parameter' => ' , ', 'weight' => 1],
            ['attribute_code' => 'gtin', 'rule_type' => 'pattern', 'parameter' => '', 'weight' => 1],
            ['attribute_code' => 'name', 'rule_type' => 'pattern', 'parameter' => '(unclosed', 'weight' => 1],
        ]);
        self::assertSame(
            ['0:parameter', '1:parameter', '2:parameter', '3:parameter', '4:parameter'],
            self::fields($result)
        );
        self::assertSame('Enter the minimum number of images.', $result->getMessages()[0]);
    }

    public function testNoWeightAndUnknownTypeAndUnknownAttribute(): void
    {
        $result = $this->validate([
            ['attribute_code' => 'name', 'rule_type' => 'present', 'weight' => ''],
            ['attribute_code' => 'name', 'rule_type' => 'sparkles', 'weight' => 3],
            ['attribute_code' => 'colour_of_the_sky', 'rule_type' => 'present', 'weight' => 3],
            ['attribute_code' => 'bad code!', 'rule_type' => 'present', 'weight' => 3],
            ['attribute_code' => '', 'rule_type' => 'present', 'weight' => 3],
        ]);
        $fields = self::fields($result);
        self::assertContains('0:weight', $fields);
        self::assertContains('1:rule_type', $fields);
        self::assertContains('2:attribute_code', $fields);
        self::assertContains('3:attribute_code', $fields);
        self::assertContains('4:attribute_code', $fields);
        self::assertStringStartsWith('Choose a weight.', $result->getMessages()[0]);
    }

    public function testPreviewModeToleratesAnEmptyAttribute(): void
    {
        $result = $this->validate(
            [['attribute_code' => '', 'rule_type' => 'present', 'weight' => 3]],
            RuleSetInterface::KIND_CHANNEL,
            'google',
            true
        );
        self::assertTrue($result->isValid());
        self::assertSame('', $result->getRules()[0]['attribute_code']);
    }

    public function testTypeCompatibility(): void
    {
        $result = $this->validate([
            ['attribute_code' => 'media_gallery', 'rule_type' => 'min_length', 'parameter' => '3', 'weight' => 2],
            ['attribute_code' => 'name', 'rule_type' => 'min_count', 'parameter' => '2', 'weight' => 2],
            ['attribute_code' => 'material', 'rule_type' => 'min_count', 'parameter' => '2', 'weight' => 2],
        ]);
        self::assertSame(['0:rule_type', '1:rule_type'], self::fields($result));
    }

    public function testUnknownProductTypeIsAnError(): void
    {
        $result = $this->validate([
            ['attribute_code' => 'name', 'rule_type' => 'present', 'weight' => 3, 'applies_to' => ['simple', 'hologram']],
        ]);
        self::assertSame(['0:applies_to'], self::fields($result));
    }

    public function testChannelRolesFollowTheChannel(): void
    {
        $result = $this->validate([
            ['role' => 'gtin', 'attribute_code' => 'gtin', 'rule_type' => 'pattern', 'parameter' => '^\d{13}$', 'weight' => 3],
            ['role' => 'bullet_points', 'attribute_code' => 'name', 'rule_type' => 'present', 'weight' => 3],
            ['role' => '', 'attribute_code' => 'description', 'rule_type' => 'present', 'weight' => 3],
        ], RuleSetInterface::KIND_CHANNEL, 'google');
        self::assertTrue($result->isValid());
        $rules = $result->getRules();
        self::assertSame('gtin', $rules[0]['role']);
        self::assertSame(RuleInterface::ROLE_CUSTOM, $rules[1]['role'], 'a role of another channel falls back to custom');
        self::assertSame(RuleInterface::ROLE_CUSTOM, $rules[2]['role']);
    }

    public function testTooManyRulesIsRejectedAsAWhole(): void
    {
        $rows = array_fill(0, PayloadValidator::MAX_RULES + 1, ['attribute_code' => 'name', 'rule_type' => 'present', 'weight' => 3]);
        $result = $this->validate($rows);
        self::assertFalse($result->isValid());
        self::assertSame([], $result->getRules());
    }

    public function testParameterLengthCap(): void
    {
        $result = $this->validate([
            ['attribute_code' => 'item_condition', 'rule_type' => 'allowed_values', 'parameter' => str_repeat('a,', 1500), 'weight' => 3],
        ]);
        self::assertSame(['0:parameter'], self::fields($result));
    }

    public function testNestedRepetitionInAPatternIsRefused(): void
    {
        $result = $this->validate([
            ['attribute_code' => 'name', 'rule_type' => 'pattern', 'parameter' => '^(a+)+$', 'weight' => 3],
        ]);
        self::assertSame(['0:parameter'], self::fields($result));
        self::assertTrue(strpos($result->getMessages()[0], 'nested repetition') !== false, $result->getMessages()[0]);
    }
}
