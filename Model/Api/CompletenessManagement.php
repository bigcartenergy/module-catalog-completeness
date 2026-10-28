<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Model\Api;

use BigCartEnergy\CatalogCompleteness\Api\CompletenessManagementInterface;
use BigCartEnergy\CatalogCompleteness\Api\Data\ProductScoreInterface;
use BigCartEnergy\CatalogCompleteness\Model\Dashboard\Coverage;
use BigCartEnergy\CatalogCompleteness\Model\Indexer\Status as IndexerStatus;
use BigCartEnergy\CatalogCompleteness\Model\ResourceModel\Score as ScoreResource;
use BigCartEnergy\CatalogCompleteness\Model\RuleSetRepository;
use BigCartEnergy\CatalogCompleteness\Model\Score\ProductScoreBuilder;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\NoSuchEntityException;

class CompletenessManagement implements CompletenessManagementInterface
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
     * @var RuleSetRepository
     */
    private $ruleSetRepository;

    /**
     * @var IndexerStatus
     */
    private $indexerStatus;

    /**
     * @var ResourceConnection
     */
    private $resource;

    /**
     * @var Coverage
     */
    private $coverage;

    public function __construct(
        ScoreResource $scoreResource,
        ProductScoreBuilder $builder,
        RuleSetRepository $ruleSetRepository,
        IndexerStatus $indexerStatus,
        ResourceConnection $resource,
        Coverage $coverage
    ) {
        $this->scoreResource = $scoreResource;
        $this->builder = $builder;
        $this->ruleSetRepository = $ruleSetRepository;
        $this->indexerStatus = $indexerStatus;
        $this->resource = $resource;
        $this->coverage = $coverage;
    }

    /**
     * @inheritDoc
     */
    public function getProductScores($productId)
    {
        $product = $this->requireProduct($productId);
        return $this->withAttributeSetRulesEntry(
            $product,
            $this->builder->build($this->scoreResource->fetchForProduct((int)$product['entity_id']))
        );
    }

    /**
     * @inheritDoc
     */
    public function recalculateProduct($productId)
    {
        $product = $this->requireProduct($productId);
        $this->indexerStatus->reindexRow((int)$product['entity_id']);
        return $this->withAttributeSetRulesEntry(
            $product,
            $this->builder->build($this->scoreResource->fetchForProduct((int)$product['entity_id']))
        );
    }

    /**
     * @inheritDoc
     */
    public function getRuleSets()
    {
        return $this->ruleSetRepository->getAll();
    }

    /**
     * The stored scores, and when none of them comes from the attribute set rules, one entry first that says
     * why: no rules assigned, or rules that apply with no score calculated yet.
     *
     * @param array<string, mixed> $product the product entity's id, attribute set and type
     * @param ProductScoreInterface[] $scores
     * @return ProductScoreInterface[]
     */
    private function withAttributeSetRulesEntry(array $product, array $scores): array
    {
        foreach ($scores as $score) {
            if ($score->getIsPrimary()) {
                return $scores;
            }
        }
        $state = $this->coverage->isUncovered((int)$product['attribute_set_id'], (string)$product['type_id'])
            ? ProductScoreInterface::STATE_NO_RULES_ASSIGNED
            : ProductScoreInterface::STATE_NOT_CALCULATED;
        array_unshift($scores, $this->builder->placeholder((int)$product['entity_id'], $state));
        return $scores;
    }

    /**
     * @param mixed $productId
     * @return array<string, mixed> the product entity's id, attribute set and type
     * @throws NoSuchEntityException
     */
    private function requireProduct($productId): array
    {
        $productId = (int)$productId;
        $row = $productId > 0 ? $this->productRow($productId) : null;
        if ($row === null) {
            throw new NoSuchEntityException(__('The product with id %1 does not exist.', $productId));
        }
        return $row;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function productRow(int $productId): ?array
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from(
                $this->resource->getTableName('catalog_product_entity'),
                ['entity_id', 'attribute_set_id', 'type_id']
            )
            ->where('entity_id = ?', $productId);
        $row = $connection->fetchRow($select);
        return is_array($row) && $row ? $row : null;
    }
}
