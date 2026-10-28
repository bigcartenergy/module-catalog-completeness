<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Model\Score;

use BigCartEnergy\CatalogCompleteness\Api\Data\ProductScoreInterface;
use BigCartEnergy\CatalogCompleteness\Api\Data\ProductScoreInterfaceFactory;
use BigCartEnergy\CatalogCompleteness\Model\Band\Resolver as BandResolver;
use Magento\Framework\Serialize\Serializer\Json;

/**
 * Stored score rows into read-only API objects with band and rule labels.
 */
class ProductScoreBuilder
{
    /**
     * @var ProductScoreInterfaceFactory
     */
    private $factory;

    /**
     * @var RuleLabels
     */
    private $ruleLabels;

    /**
     * @var BandResolver
     */
    private $bands;

    /**
     * @var Json
     */
    private $json;

    public function __construct(
        ProductScoreInterfaceFactory $factory,
        RuleLabels $ruleLabels,
        BandResolver $bands,
        Json $json
    ) {
        $this->factory = $factory;
        $this->ruleLabels = $ruleLabels;
        $this->bands = $bands;
        $this->json = $json;
    }

    /**
     * @param array<int, array<string, mixed>> $rows score rows joined with the rule set name
     * @return ProductScoreInterface[]
     */
    public function build(array $rows): array
    {
        $rulesetIds = [];
        foreach ($rows as $row) {
            $rulesetIds[] = (int)$row['ruleset_id'];
        }
        $this->ruleLabels->preload($rulesetIds);
        $scores = [];
        foreach ($rows as $row) {
            $score = $row['score'] === null ? null : (int)$row['score'];
            $band = $this->bands->resolve($score);
            /** @var ProductScoreInterface $object */
            $object = $this->factory->create(['data' => [
                ProductScoreInterface::PRODUCT_ID => (int)$row['product_id'],
                ProductScoreInterface::RULESET_ID => (int)$row['ruleset_id'],
                ProductScoreInterface::RULESET_NAME => (string)($row['ruleset_name'] ?? ''),
                ProductScoreInterface::IS_PRIMARY => (bool)$row['is_primary'],
                ProductScoreInterface::SCORE => $score,
                ProductScoreInterface::BAND => $band,
                ProductScoreInterface::BAND_LABEL => $band === null ? null : $this->bands->getLabel($band),
                ProductScoreInterface::PASSED_WEIGHT => (int)$row['passed_weight'],
                ProductScoreInterface::TOTAL_WEIGHT => (int)$row['total_weight'],
                ProductScoreInterface::MISSING => $this->ruleLabels->describeIds(
                    (int)$row['ruleset_id'],
                    $this->decodeMissing($row['missing'] ?? null)
                ),
                ProductScoreInterface::SCORED_AT => $row['scored_at'] ?? null,
                ProductScoreInterface::STATE => $score === null
                    ? ProductScoreInterface::STATE_NO_RULES_ASSIGNED
                    : ProductScoreInterface::STATE_SCORED,
            ]]);
            $scores[] = $object;
        }
        return $scores;
    }

    /**
     * The attribute set rules entry of a product that has no stored score from them: rule set id 0, no score,
     * and the state that says why (no_rules_assigned or not_calculated).
     */
    public function placeholder(int $productId, string $state): ProductScoreInterface
    {
        return $this->factory->create(['data' => [
            ProductScoreInterface::PRODUCT_ID => $productId,
            ProductScoreInterface::RULESET_ID => 0,
            ProductScoreInterface::RULESET_NAME => '',
            ProductScoreInterface::IS_PRIMARY => true,
            ProductScoreInterface::SCORE => null,
            ProductScoreInterface::BAND => null,
            ProductScoreInterface::BAND_LABEL => null,
            ProductScoreInterface::PASSED_WEIGHT => 0,
            ProductScoreInterface::TOTAL_WEIGHT => 0,
            ProductScoreInterface::MISSING => [],
            ProductScoreInterface::SCORED_AT => null,
            ProductScoreInterface::STATE => $state,
        ]]);
    }

    /**
     * @param mixed $raw
     * @return int[]
     */
    public function decodeMissing($raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }
        try {
            $decoded = $this->json->unserialize((string)$raw);
        } catch (\InvalidArgumentException $e) {
            return [];
        }
        if (!is_array($decoded)) {
            return [];
        }
        return array_values(array_map('intval', $decoded));
    }
}
