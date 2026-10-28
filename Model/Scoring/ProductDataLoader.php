<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Model\Scoring;

use BigCartEnergy\CatalogCompleteness\Api\Data\RuleInterface;
use BigCartEnergy\CatalogCompleteness\Model\Rule\AttributeDescriptor;
use Magento\CatalogInventory\Model\Stock;
use Magento\Framework\App\ResourceConnection;

/**
 * Loads, for a chunk of product ids, exactly the admin-scope values the rules need: one query per backend
 * table, one grouped query for the gallery count, one for the stock status. Ids that no longer exist are
 * simply absent from the result, which is how deleted products are detected without a single exception.
 */
class ProductDataLoader
{
    private const BACKEND_TABLES = [
        'varchar' => 'catalog_product_entity_varchar',
        'text' => 'catalog_product_entity_text',
        'int' => 'catalog_product_entity_int',
        'decimal' => 'catalog_product_entity_decimal',
        'datetime' => 'catalog_product_entity_datetime',
    ];

    /**
     * @var ResourceConnection
     */
    private $resource;

    public function __construct(ResourceConnection $resource)
    {
        $this->resource = $resource;
    }

    /**
     * @param int[] $productIds
     * @return array<int, ProductData> product id => data, existing products only
     */
    public function load(array $productIds, Context $context): array
    {
        $productIds = array_values(array_unique(array_map('intval', $productIds)));
        if (!$productIds) {
            return [];
        }
        $connection = $this->resource->getConnection();
        $entityTable = $this->resource->getTableName('catalog_product_entity');
        $select = $connection->select()
            ->from($entityTable, ['entity_id', 'sku', 'type_id', 'attribute_set_id'])
            ->where('entity_id IN (?)', $productIds);
        $products = [];
        foreach ($connection->fetchAll($select) as $row) {
            $id = (int)$row['entity_id'];
            $products[$id] = new ProductData(
                $id,
                (string)$row['sku'],
                (string)$row['type_id'],
                (int)$row['attribute_set_id']
            );
        }
        if (!$products) {
            return [];
        }
        $existingIds = array_keys($products);

        $this->loadEavValues($products, $existingIds, $context);

        $attributes = $context->getAttributes();
        if (isset($attributes[RuleInterface::MEDIA_GALLERY])) {
            $this->loadImageCounts($products, $existingIds);
        }
        if (isset($attributes[AttributeDescriptor::STOCK_STATUS_CODE])) {
            $this->loadStockStatus($products, $existingIds);
        }
        return $products;
    }

    /**
     * @param array<int, ProductData> $products
     * @param int[] $productIds
     */
    private function loadEavValues(array $products, array $productIds, Context $context): void
    {
        $byBackend = [];
        $codesById = [];
        foreach ($context->getAttributes() as $code => $descriptor) {
            if ($descriptor->isPseudo() || $descriptor->getId() === null) {
                continue;
            }
            $backend = $descriptor->getBackendType();
            if (!isset(self::BACKEND_TABLES[$backend])) {
                continue;
            }
            $byBackend[$backend][] = $descriptor->getId();
            $codesById[$descriptor->getId()] = $code;
        }
        $connection = $this->resource->getConnection();
        foreach ($byBackend as $backend => $attributeIds) {
            $select = $connection->select()
                ->from($this->resource->getTableName(self::BACKEND_TABLES[$backend]), ['entity_id', 'attribute_id', 'value'])
                ->where('store_id = ?', 0)
                ->where('entity_id IN (?)', $productIds)
                ->where('attribute_id IN (?)', $attributeIds);
            foreach ($connection->fetchAll($select) as $row) {
                $id = (int)$row['entity_id'];
                $code = $codesById[(int)$row['attribute_id']] ?? null;
                if ($code !== null && isset($products[$id])) {
                    $products[$id]->setValue($code, $row['value']);
                }
            }
        }
    }

    /**
     * Enabled images per product: the global flag on the gallery row and the admin-scope flag on its value row.
     *
     * @param array<int, ProductData> $products
     * @param int[] $productIds
     */
    private function loadImageCounts(array $products, array $productIds): void
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from(
                ['v2e' => $this->resource->getTableName('catalog_product_entity_media_gallery_value_to_entity')],
                ['entity_id', 'n' => new \Zend_Db_Expr('COUNT(*)')]
            )
            ->join(
                ['g' => $this->resource->getTableName('catalog_product_entity_media_gallery')],
                'g.value_id = v2e.value_id AND g.media_type = \'image\' AND g.disabled = 0',
                []
            )
            ->joinLeft(
                ['gv' => $this->resource->getTableName('catalog_product_entity_media_gallery_value')],
                'gv.value_id = v2e.value_id AND gv.entity_id = v2e.entity_id AND gv.store_id = 0',
                []
            )
            ->where('v2e.entity_id IN (?)', $productIds)
            ->where('gv.disabled IS NULL OR gv.disabled = 0')
            ->group('v2e.entity_id');
        foreach ($connection->fetchAll($select) as $row) {
            $id = (int)$row['entity_id'];
            if (isset($products[$id])) {
                $products[$id]->setImageCount((int)$row['n']);
            }
        }
    }

    /**
     * @param array<int, ProductData> $products
     * @param int[] $productIds
     */
    private function loadStockStatus(array $products, array $productIds): void
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from($this->resource->getTableName('cataloginventory_stock_item'), ['product_id', 'is_in_stock'])
            ->where('stock_id = ?', Stock::DEFAULT_STOCK_ID)
            ->where('product_id IN (?)', $productIds);
        foreach ($connection->fetchAll($select) as $row) {
            $id = (int)$row['product_id'];
            if (isset($products[$id])) {
                $products[$id]->setStockStatus((int)$row['is_in_stock']);
            }
        }
    }
}
