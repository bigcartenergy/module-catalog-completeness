<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Test\Unit\Model\Scoring;

use BigCartEnergy\CatalogCompleteness\Api\Data\RuleInterface;
use BigCartEnergy\CatalogCompleteness\Model\Rule\Evaluator;
use BigCartEnergy\CatalogCompleteness\Model\Scoring\Calculator;
use BigCartEnergy\CatalogCompleteness\Model\Scoring\Context;
use BigCartEnergy\CatalogCompleteness\Model\Scoring\ProductData;
use BigCartEnergy\CatalogCompleteness\Model\Scoring\Scorer;
use BigCartEnergy\CatalogCompleteness\Test\Unit\Stub\LookupStub;
use BigCartEnergy\CatalogCompleteness\Test\Unit\Stub\RuleSetStub;
use BigCartEnergy\CatalogCompleteness\Test\Unit\Stub\RuleStub;
use PHPUnit\Framework\TestCase;

/**
 * Scoring a whole rule set: applicability by product type, missing attributes, the missing list order.
 */
class ScorerTest extends TestCase
{
    /**
     * @var Scorer
     */
    private $scorer;

    /**
     * @var Context
     */
    private $context;

    protected function setUp(): void
    {
        $lookup = new LookupStub();
        $this->scorer = new Scorer(new Evaluator(), new Calculator());
        $attributes = [];
        foreach (['name', 'description', 'media_gallery', 'weight', 'manufacturer', 'gtin', 'material', 'quantity_and_stock_status'] as $code) {
            $attributes[$code] = $lookup->getDescriptor($code);
        }
        $this->context = new Context($attributes, $lookup->getMembership());
    }

    private function ruleSet(): RuleSetStub
    {
        return new RuleSetStub(['ruleset_id' => 5, 'rules' => [
            RuleStub::make('name', RuleInterface::TYPE_MIN_LENGTH, 30, 1, [], 101),
            RuleStub::make('description', RuleInterface::TYPE_MIN_LENGTH, 200, 3, [], 102),
            RuleStub::make('media_gallery', RuleInterface::TYPE_MIN_COUNT, 3, 2, [], 103),
            RuleStub::make('weight', RuleInterface::TYPE_GT_ZERO, null, 2, ['simple', 'configurable', 'bundle', 'grouped'], 104),
            RuleStub::make('manufacturer', RuleInterface::TYPE_PRESENT, null, 3, [], 105),
            RuleStub::make('gtin', RuleInterface::TYPE_PATTERN, '^(\d{8}|\d{12,14})$', 3, [], 106),
        ]]);
    }

    public function testACompleteProductScoresOneHundred(): void
    {
        $product = new ProductData(10, 'FST-HXB-M10-50', 'simple', 4, [
            'name' => 'Hex bolt M10 x 50, zinc plated, grade 8.8',
            'description' => '<p>' . str_repeat('Zinc plated hex bolt for structural use. ', 6) . '</p>',
            'weight' => '0.0450',
            'manufacturer' => '11',
            'gtin' => '4006381333931',
        ], 3);
        $result = $this->scorer->score($product, $this->ruleSet(), $this->context);
        self::assertSame(100, $result->getScore());
        self::assertSame(14, $result->getTotalWeight());
        self::assertSame(14, $result->getPassedWeight());
        self::assertSame([], $result->getMissingRuleIds());
        self::assertSame(6, $result->countPassed());
    }

    public function testMissingRulesComeInRuleSetOrderAndWeightsAddUp(): void
    {
        $product = new ProductData(11, 'ABR-FD-125-Z60', 'simple', 4, [
            'name' => 'Flap disc',
            'description' => '',
            'weight' => '0',
            'manufacturer' => '',
            'gtin' => '12',
        ], 1);
        $result = $this->scorer->score($product, $this->ruleSet(), $this->context);
        self::assertSame(0, $result->getScore());
        self::assertSame([101, 102, 103, 104, 105, 106], $result->getMissingRuleIds());
    }

    public function testRulesThatDoNotApplyLeaveNumeratorAndDenominator(): void
    {
        $product = new ProductData(12, 'SVC-TRQ-TRAIN', 'virtual', 4, [
            'name' => 'On-site torque tool training, per hour, any site',
            'description' => str_repeat('Training session for torque tools. ', 8),
            'weight' => null,
            'manufacturer' => '12',
            'gtin' => '40063813',
        ], 3);
        $result = $this->scorer->score($product, $this->ruleSet(), $this->context);
        self::assertSame(12, $result->getTotalWeight(), 'weight rule (2) does not apply to virtual products');
        self::assertSame(100, $result->getScore());
        $results = $result->getRuleResults();
        self::assertFalse($results[3]['applies']);
    }

    public function testAnAttributeMissingFromTheSetFailsItsRule(): void
    {
        // Set 9 lacks gtin: a product in it cannot hold a GTIN, so the rule fails (design call 2).
        $product = new ProductData(13, 'PPE-EAR-SNR32-200', 'simple', 9, [
            'name' => 'Foam ear plugs SNR 32, box of 200 pairs',
            'description' => str_repeat('Disposable foam ear plugs. ', 10),
            'weight' => '0.2000',
            'manufacturer' => '11',
            'gtin' => '4006381333931',
        ], 3);
        $result = $this->scorer->score($product, $this->ruleSet(), $this->context);
        self::assertSame([106], $result->getMissingRuleIds());
        self::assertSame(79, $result->getScore());
    }

    public function testRuleWithoutAttributeAlwaysFails(): void
    {
        $ruleSet = new RuleSetStub(['rules' => [
            RuleStub::make('', RuleInterface::TYPE_PRESENT, null, 3, [], 201),
            RuleStub::make('name', RuleInterface::TYPE_PRESENT, null, 1, [], 202),
        ]]);
        $product = new ProductData(14, 'HYD-JIC-08-06', 'simple', 4, ['name' => 'JIC adapter']);
        $result = $this->scorer->score($product, $ruleSet, $this->context);
        self::assertSame([201], $result->getMissingRuleIds());
        self::assertSame(25, $result->getScore());
    }

    public function testNoApplicableRuleGivesNoScore(): void
    {
        $ruleSet = new RuleSetStub(['rules' => [
            RuleStub::make('weight', RuleInterface::TYPE_GT_ZERO, null, 2, ['simple'], 301),
        ]]);
        $product = new ProductData(15, 'DOC-CAL-CERT', 'downloadable', 4, []);
        $result = $this->scorer->score($product, $ruleSet, $this->context);
        self::assertNull($result->getScore());
        self::assertSame(0, $result->getTotalWeight());
    }

    public function testUnsavedPreviewRulesHaveNoIdsButStillCount(): void
    {
        $ruleSet = new RuleSetStub(['rules' => [
            RuleStub::make('name', RuleInterface::TYPE_MIN_LENGTH, 100, 3),
        ]]);
        $product = new ProductData(16, 'ELC-CT-300-UV', 'simple', 4, ['name' => 'Cable ties']);
        $result = $this->scorer->score($product, $ruleSet, $this->context);
        self::assertSame(0, $result->getScore());
        self::assertSame([], $result->getMissingRuleIds());
        self::assertCount(1, $result->getMissingRules());
    }
}
