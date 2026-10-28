<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Plugin;

use BigCartEnergy\CatalogCompleteness\Api\Data\ProductScoreInterface;
use BigCartEnergy\CatalogCompleteness\Model\Dashboard\Coverage;
use BigCartEnergy\CatalogCompleteness\Model\ResourceModel\Score as ScoreResource;
use BigCartEnergy\CatalogCompleteness\Model\Score\ProductScoreBuilder;
use BigCartEnergy\CatalogCompleteness\Model\Score\RuleLabels;
use Magento\Catalog\Api\Data\ProductExtensionFactory;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\Data\ProductSearchResultsInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;

/**
 * Read-only extension attributes on products loaded through the repository, in the admin and REST areas only:
 * bce_completeness_score (the primary score), bce_completeness_missing (labels of the failed rules) and
 * bce_completeness_state (scored, no_rules_assigned or not_calculated, so a null score always says why).
 * Nothing sent on a product save is ever read back.
 */
class ProductRepositoryExtensionAttributes
{
    /**
     * @var ScoreResource
     */
    private $scoreResource;

    /**
     * @var ProductScoreBuilder
     */
    private $builder;

    /**
     * @var RuleLabels
     */
    private $ruleLabels;

    /**
     * @var ProductExtensionFactory
     */
    private $extensionFactory;

    /**
     * @var Coverage
     */
    private $coverage;

    public function __construct(
        ScoreResource $scoreResource,
        ProductScoreBuilder $builder,
        RuleLabels $ruleLabels,
        ProductExtensionFactory $extensionFactory,
        Coverage $coverage
    ) {
        $this->scoreResource = $scoreResource;
        $this->builder = $builder;
        $this->ruleLabels = $ruleLabels;
        $this->extensionFactory = $extensionFactory;
        $this->coverage = $coverage;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterGet(ProductRepositoryInterface $subject, ProductInterface $product): ProductInterface
    {
        $this->attach([$product]);
        return $product;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterGetById(ProductRepositoryInterface $subject, ProductInterface $product): ProductInterface
    {
        $this->attach([$product]);
        return $product;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterGetList(
        ProductRepositoryInterface $subject,
        ProductSearchResultsInterface $results
    ): ProductSearchResultsInterface {
        $this->attach($results->getItems());
        return $results;
    }

    /**
     * @param ProductInterface[] $products
     */
    private function attach(array $products): void
    {
        $ids = [];
        foreach ($products as $product) {
            if ($product instanceof ProductInterface && $product->getId()) {
                $ids[] = (int)$product->getId();
            }
        }
        if (!$ids) {
            return;
        }
        $rows = $this->scoreResource->fetchPrimary($ids);
        $rulesetIds = [];
        foreach ($rows as $row) {
            $rulesetIds[] = (int)$row['ruleset_id'];
        }
        $this->ruleLabels->preload($rulesetIds);
        foreach ($products as $product) {
            if (!$product instanceof ProductInterface || !$product->getId()) {
                continue;
            }
            $row = $rows[(int)$product->getId()] ?? null;
            $extension = $product->getExtensionAttributes();
            if ($extension === null) {
                $extension = $this->extensionFactory->create();
            }
            if ($row === null) {
                $extension->setBceCompletenessScore(null);
                $extension->setBceCompletenessMissing([]);
                $extension->setBceCompletenessState(
                    $this->coverage->isUncovered((int)$product->getAttributeSetId(), (string)$product->getTypeId())
                        ? ProductScoreInterface::STATE_NO_RULES_ASSIGNED
                        : ProductScoreInterface::STATE_NOT_CALCULATED
                );
            } else {
                $extension->setBceCompletenessState(
                    $row['score'] === null
                        ? ProductScoreInterface::STATE_NO_RULES_ASSIGNED
                        : ProductScoreInterface::STATE_SCORED
                );
                $extension->setBceCompletenessScore($row['score'] === null ? null : (int)$row['score']);
                $extension->setBceCompletenessMissing(
                    $this->ruleLabels->describeIds(
                        (int)$row['ruleset_id'],
                        $this->builder->decodeMissing($row['missing'] ?? null)
                    )
                );
            }
            $product->setExtensionAttributes($extension);
        }
    }
}
