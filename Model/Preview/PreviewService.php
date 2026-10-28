<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Model\Preview;

use BigCartEnergy\CatalogCompleteness\Api\Data\RuleInterface;
use BigCartEnergy\CatalogCompleteness\Api\Data\RuleSetInterface;
use BigCartEnergy\CatalogCompleteness\Model\Band\Resolver as BandResolver;
use BigCartEnergy\CatalogCompleteness\Model\Catalog\ProductSearch;
use BigCartEnergy\CatalogCompleteness\Model\Channel\ChannelRegistry;
use BigCartEnergy\CatalogCompleteness\Model\Dashboard\StatusReader;
use BigCartEnergy\CatalogCompleteness\Model\ResourceModel\Score as ScoreResource;
use BigCartEnergy\CatalogCompleteness\Model\Rule\AttributeDescriptor;
use BigCartEnergy\CatalogCompleteness\Model\Rule\Evaluator;
use BigCartEnergy\CatalogCompleteness\Model\Rule\PayloadValidator;
use BigCartEnergy\CatalogCompleteness\Model\Rule\Text;
use BigCartEnergy\CatalogCompleteness\Model\RuleFactory;
use BigCartEnergy\CatalogCompleteness\Model\RuleSetRepository;
use BigCartEnergy\CatalogCompleteness\Model\Score\RuleLabels;
use BigCartEnergy\CatalogCompleteness\Model\Scoring\Calculator;
use BigCartEnergy\CatalogCompleteness\Model\Scoring\ContextBuilder;
use BigCartEnergy\CatalogCompleteness\Model\Scoring\ProductDataLoader;
use BigCartEnergy\CatalogCompleteness\Model\Scoring\Scorer;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Scores one product against rules without saving anything: the editor's unsaved rules, or a stored rule
 * set for the product edit page. Returns plain arrays for the JSON response.
 */
class PreviewService
{
    /**
     * @var PayloadValidator
     */
    private $validator;

    /**
     * @var RuleFactory
     */
    private $ruleFactory;

    /**
     * @var RuleSetRepository
     */
    private $ruleSetRepository;

    /**
     * @var ContextBuilder
     */
    private $contextBuilder;

    /**
     * @var ProductDataLoader
     */
    private $loader;

    /**
     * @var Scorer
     */
    private $scorer;

    /**
     * @var Calculator
     */
    private $calculator;

    /**
     * @var Evaluator
     */
    private $evaluator;

    /**
     * @var RuleLabels
     */
    private $ruleLabels;

    /**
     * @var ChannelRegistry
     */
    private $channels;

    /**
     * @var BandResolver
     */
    private $bands;

    /**
     * @var ProductSearch
     */
    private $productSearch;

    /**
     * @var ScoreResource
     */
    private $scoreResource;

    /**
     * @var StatusReader
     */
    private $statusReader;

    public function __construct(
        PayloadValidator $validator,
        RuleFactory $ruleFactory,
        RuleSetRepository $ruleSetRepository,
        ContextBuilder $contextBuilder,
        ProductDataLoader $loader,
        Scorer $scorer,
        Calculator $calculator,
        Evaluator $evaluator,
        RuleLabels $ruleLabels,
        ChannelRegistry $channels,
        BandResolver $bands,
        ProductSearch $productSearch,
        ScoreResource $scoreResource,
        StatusReader $statusReader
    ) {
        $this->validator = $validator;
        $this->ruleFactory = $ruleFactory;
        $this->ruleSetRepository = $ruleSetRepository;
        $this->contextBuilder = $contextBuilder;
        $this->loader = $loader;
        $this->scorer = $scorer;
        $this->calculator = $calculator;
        $this->evaluator = $evaluator;
        $this->ruleLabels = $ruleLabels;
        $this->channels = $channels;
        $this->bands = $bands;
        $this->productSearch = $productSearch;
        $this->scoreResource = $scoreResource;
        $this->statusReader = $statusReader;
    }

    /**
     * Preview with the rules as they are on the editor page (unsaved included).
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array<string, mixed>
     * @throws NoSuchEntityException
     */
    public function previewRows(int $productId, array $rows, string $kind, ?string $channelCode): array
    {
        $result = $this->validator->validateRules($rows, $kind, $channelCode, true);
        $rules = [];
        $skipped = 0;
        $invalidRows = $result->getInvalidRows();
        foreach ($result->getRules() as $index => $normalised) {
            if ($normalised['attribute_code'] === '' || in_array($index, $invalidRows, true)) {
                $skipped++;
                continue;
            }
            $rule = $this->ruleFactory->create();
            $rule->setRuleId($normalised['rule_id']);
            $rule->setRole($normalised['role']);
            $rule->setAttributeCode($normalised['attribute_code']);
            $rule->setRuleType($normalised['rule_type']);
            $rule->setParameter($normalised['parameter']);
            $rule->setWeight($normalised['weight'] > 0 ? $normalised['weight'] : RuleInterface::WEIGHT_NICE_TO_HAVE);
            $rule->setAppliesTo($normalised['applies_to']);
            $rules[] = $rule;
        }
        $out = $this->score($productId, $rules, null);
        $out['skipped'] = $skipped;
        return $out;
    }

    /**
     * Preview with a stored rule set, for the product edit page.
     *
     * @return array<string, mixed>
     * @throws NoSuchEntityException
     */
    public function previewRuleSet(int $productId, int $rulesetId): array
    {
        $ruleSet = $this->ruleSetRepository->getById($rulesetId);
        $out = $this->score($productId, $ruleSet->getRules(), $ruleSet);
        $out['skipped'] = 0;
        return $out;
    }

    /**
     * @param RuleInterface[] $rules
     * @return array<string, mixed>
     * @throws NoSuchEntityException
     */
    private function score(int $productId, array $rules, ?RuleSetInterface $ruleSet): array
    {
        $identity = $this->productSearch->find($productId);
        if ($identity === null) {
            throw new NoSuchEntityException(__('The product with id %1 does not exist.', $productId));
        }
        $context = $this->contextBuilder->buildForRules($rules);
        $products = $this->loader->load([$productId], $context);
        $product = $products[$productId] ?? null;
        if ($product === null) {
            throw new NoSuchEntityException(__('The product with id %1 does not exist.', $productId));
        }
        $result = $this->scorer->scoreRules($product, $rules, $context);
        $score = $result->getScore();
        $band = $this->bands->resolve($score);
        $failing = [];
        $passing = [];
        foreach ($result->getRuleResults() as $entry) {
            if (!$entry['applies']) {
                continue;
            }
            /** @var RuleInterface $rule */
            $rule = $entry['rule'];
            $code = (string)$rule->getAttributeCode();
            $descriptor = $context->getAttribute($code);
            $row = [
                'index' => $entry['index'],
                'rule_id' => $rule->getRuleId(),
                'role' => $rule->getRole(),
                'role_label' => $rule->getRole() !== null ? $this->channels->getRoleLabel($rule->getRole()) : '',
                'attribute_code' => $code,
                'attribute_label' => $this->ruleLabels->attributeLabel($rule),
                'description' => $this->ruleLabels->describe($rule),
                'weight' => (int)$rule->getWeight(),
                'weight_label' => $this->ruleLabels->weightLabel($rule),
                'share' => $this->calculator->sharePercent((int)$rule->getWeight(), $result->getTotalWeight()),
                'available' => $context->isAvailable($code, $product->getAttributeSetId()),
            ];
            if ($entry['passed']) {
                $passing[] = $row;
            } else {
                $row['found'] = $this->describeFound($product->getValue($code), $descriptor, $row['available']);
                $failing[] = $row;
            }
        }
        $stored = null;
        if ($ruleSet !== null) {
            foreach ($this->scoreResource->fetchForProduct($productId) as $storedRow) {
                if ((int)$storedRow['ruleset_id'] === (int)$ruleSet->getRulesetId()) {
                    $stored = $storedRow;
                    break;
                }
            }
        }
        return [
            'product' => $identity,
            'ruleset_id' => $ruleSet !== null ? (int)$ruleSet->getRulesetId() : null,
            'ruleset_name' => $ruleSet !== null ? $ruleSet->getName() : null,
            'score' => $score,
            'band' => $band,
            'band_label' => $band === null ? '' : $this->bands->getLabel($band),
            'passed_weight' => $result->getPassedWeight(),
            'total_weight' => $result->getTotalWeight(),
            'missing_count' => count($failing),
            'passed_count' => count($passing),
            'failing' => $failing,
            'passing' => $passing,
            'stored_score' => $stored !== null && $stored['score'] !== null ? (int)$stored['score'] : null,
            'scored_at' => $stored !== null ? (string)$stored['scored_at'] : null,
            'scored_at_formatted' => $stored !== null ? $this->statusReader->format((string)$stored['scored_at']) : '',
        ];
    }

    /**
     * The value found, in words a merchant reads next to the failing rule.
     *
     * @param mixed $value
     */
    private function describeFound($value, ?AttributeDescriptor $descriptor, bool $available): string
    {
        if ($descriptor === null) {
            return (string)__('No attribute mapped');
        }
        if (!$available) {
            return (string)__('Not in this attribute set');
        }
        if ($descriptor->isGallery()) {
            $count = (int)$value;
            if ($count === 0) {
                return (string)__('No images');
            }
            return $count === 1 ? (string)__('Has 1 image') : (string)__('Has %1 images', $count);
        }
        if ($descriptor->isStockStatus()) {
            if ($value === null) {
                return (string)__('No stock item');
            }
            return (int)$value === 1 ? (string)__('In stock') : (string)__('Out of stock');
        }
        $text = $this->evaluator->textOf($value, $descriptor);
        if ($text === '') {
            return (string)__('Empty');
        }
        if ($descriptor->isRichText()) {
            return (string)__('%1 characters', Text::length($text));
        }
        $options = $descriptor->getOptions();
        if ($options) {
            $labels = [];
            foreach ($this->evaluator->listValues($value, $descriptor) as $stored) {
                $labels[] = $options[$stored] ?? $stored;
            }
            $text = implode(', ', $labels);
        }
        if (Text::length($text) > 60) {
            $text = mb_substr($text, 0, 57) . '...';
        }
        return $text;
    }
}
