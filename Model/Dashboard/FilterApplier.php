<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Model\Dashboard;

use BigCartEnergy\CatalogCompleteness\Model\Band\Resolver as BandResolver;
use BigCartEnergy\CatalogCompleteness\Model\Catalog\Lookup;
use BigCartEnergy\CatalogCompleteness\Model\Config;
use BigCartEnergy\CatalogCompleteness\Model\ResourceModel\Failure as FailureResource;
use BigCartEnergy\CatalogCompleteness\Model\Rule\AttributeDescriptor;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Select;

/**
 * Applies a dashboard Filter to a select over the score table (alias "s") joined to the product entity
 * (alias "e"). Shared by the statistics, the work list and the CSV export so every number agrees.
 * Every value is bound; nothing from the request is interpolated.
 */
class FilterApplier
{
    public const SCORE_ALIAS = 's';
    public const ENTITY_ALIAS = 'e';
    private const STATUS_ALIAS = 'bce_status';
    private const BRAND_ALIAS = 'bce_brand';

    /**
     * @var ResourceConnection
     */
    private $resource;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var BandResolver
     */
    private $bands;

    /**
     * @var Lookup
     */
    private $lookup;

    public function __construct(ResourceConnection $resource, Config $config, BandResolver $bands, Lookup $lookup)
    {
        $this->resource = $resource;
        $this->config = $config;
        $this->bands = $bands;
        $this->lookup = $lookup;
    }

    /**
     * Scope and filters, except the tile shortcut "below average" which needs the average first.
     *
     * @param string $s alias of the score table in the select
     * @param string $e alias of the product entity table in the select
     */
    public function apply(Select $select, Filter $filter, string $s = self::SCORE_ALIAS, string $e = self::ENTITY_ALIAS): void
    {
        $this->applyScope($select, $filter, $s);
        $select->where($s . '.score IS NOT NULL');
        if ($filter->isUncoveredOnly()) {
            // A product with no rules assigned has no score to count.
            $select->where('1 = 0');
        }
        $this->applyProductFilters($select, $filter, $e);
        if ($filter->getBand() !== null) {
            $range = $this->bands->getRange($filter->getBand());
            $select->where($s . '.score >= ?', $range[0]);
            $select->where($s . '.score <= ?', $range[1]);
        }
        switch ($filter->getTile()) {
            case Filter::TILE_COMPLETE:
                $select->where($s . '.score = 100');
                break;
            case Filter::TILE_NEEDS_WORK_OR_POOR:
                $select->where($s . '.score < ?', $this->config->getThresholds()->getGoodFrom());
                break;
            case Filter::TILE_FAILING:
                if ($filter->getRuleId() !== null) {
                    $this->applyFailingRule($select, $filter->getRuleId(), $s);
                }
                break;
            default:
                if ($filter->getRuleId() !== null) {
                    $this->applyFailingRule($select, $filter->getRuleId(), $s);
                }
        }
    }

    /**
     * The filters on the products themselves: enabled only, attribute set, product type, category and brand.
     * Shared by the scored list and the list of products with no rules assigned.
     *
     * @param string $e alias of the product entity table in the select
     */
    public function applyProductFilters(Select $select, Filter $filter, string $e = self::ENTITY_ALIAS): void
    {
        if ($filter->isEnabledOnly()) {
            $this->joinStatus($select, $e);
            $select->where(self::STATUS_ALIAS . '.value = ?', Status::STATUS_ENABLED);
        }
        if ($filter->getAttributeSetId() !== null) {
            $select->where($e . '.attribute_set_id = ?', $filter->getAttributeSetId());
        }
        if ($filter->getTypeId() !== null) {
            $select->where($e . '.type_id = ?', $filter->getTypeId());
        }
        if ($filter->getCategoryId() !== null) {
            $connection = $this->resource->getConnection();
            $categoryExists = $connection->select()
                ->from(['bce_ccp' => $this->resource->getTableName('catalog_category_product')], [new \Zend_Db_Expr('1')])
                ->where('bce_ccp.product_id = ' . $e . '.entity_id')
                ->where('bce_ccp.category_id = ?', $filter->getCategoryId());
            $select->where('EXISTS (' . $categoryExists . ')');
        }
        if ($filter->getBrand() !== null) {
            $this->joinBrand($select, $e);
            if ($filter->getBrand() === Filter::BRAND_NONE) {
                $select->where(self::BRAND_ALIAS . '.value IS NULL OR ' . self::BRAND_ALIAS . ".value = ''");
            } else {
                $select->where(self::BRAND_ALIAS . '.value = ?', $filter->getBrand());
            }
        }
    }

    /**
     * The "below average" tile: products under the average of the filtered set, computed by the caller.
     */
    public function applyBelowAverage(Select $select, int $average, string $s = self::SCORE_ALIAS): void
    {
        $select->where($s . '.score < ?', $average);
    }

    public function applyScope(Select $select, Filter $filter, string $s = self::SCORE_ALIAS): void
    {
        if ($filter->getRulesetId() !== null) {
            $select->where($s . '.ruleset_id = ?', $filter->getRulesetId());
        } else {
            $select->where($s . '.is_primary = 1');
        }
    }

    /**
     * Join the product status (admin scope) once.
     */
    public function joinStatus(Select $select, string $e = self::ENTITY_ALIAS): void
    {
        if ($this->hasFrom($select, self::STATUS_ALIAS)) {
            return;
        }
        $status = $this->lookup->getDescriptor('status');
        $statusId = $status !== null ? (int)$status->getId() : 0;
        $select->joinLeft(
            [self::STATUS_ALIAS => $this->resource->getTableName('catalog_product_entity_int')],
            sprintf(
                '%1$s.entity_id = %2$s.entity_id AND %1$s.attribute_id = %3$d AND %1$s.store_id = 0',
                self::STATUS_ALIAS,
                $e,
                $statusId
            ),
            []
        );
    }

    /**
     * Join the brand attribute value (admin scope) once; the attribute comes from the settings.
     */
    public function joinBrand(Select $select, string $e = self::ENTITY_ALIAS): ?AttributeDescriptor
    {
        $descriptor = $this->lookup->getDescriptor($this->config->getBrandAttributeCode());
        if ($descriptor === null || $descriptor->getId() === null) {
            if (!$this->hasFrom($select, self::BRAND_ALIAS)) {
                // Unknown brand attribute: a join that never matches keeps the SQL valid and the brand empty.
                $select->joinLeft(
                    [self::BRAND_ALIAS => $this->resource->getTableName('catalog_product_entity_varchar')],
                    self::BRAND_ALIAS . '.entity_id = ' . $e . '.entity_id AND 1 = 0',
                    []
                );
            }
            return null;
        }
        if ($this->hasFrom($select, self::BRAND_ALIAS)) {
            return $descriptor;
        }
        $table = 'catalog_product_entity_' . $descriptor->getBackendType();
        if (!in_array($descriptor->getBackendType(), ['varchar', 'int', 'text', 'decimal', 'datetime'], true)) {
            $table = 'catalog_product_entity_varchar';
        }
        $select->joinLeft(
            [self::BRAND_ALIAS => $this->resource->getTableName($table)],
            sprintf(
                '%1$s.entity_id = %2$s.entity_id AND %1$s.attribute_id = %3$d AND %1$s.store_id = 0',
                self::BRAND_ALIAS,
                $e,
                (int)$descriptor->getId()
            ),
            []
        );
        return $descriptor;
    }

    public function getBrandAlias(): string
    {
        return self::BRAND_ALIAS;
    }

    public function getStatusAlias(): string
    {
        return self::STATUS_ALIAS;
    }

    private function applyFailingRule(Select $select, int $ruleId, string $s): void
    {
        $connection = $this->resource->getConnection();
        $exists = $connection->select()
            ->from(['bce_f' => $this->resource->getTableName(FailureResource::TABLE)], [new \Zend_Db_Expr('1')])
            ->where('bce_f.product_id = ' . $s . '.product_id')
            ->where('bce_f.ruleset_id = ' . $s . '.ruleset_id')
            ->where('bce_f.rule_id = ?', $ruleId);
        $select->where('EXISTS (' . $exists . ')');
    }

    private function hasFrom(Select $select, string $alias): bool
    {
        $from = $select->getPart(Select::FROM);
        return is_array($from) && isset($from[$alias]);
    }
}
