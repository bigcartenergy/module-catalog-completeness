<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Model\Catalog;

use Magento\Framework\App\ResourceConnection;

/**
 * Product lookup for the preview panel: by SKU or name, admin scope, a handful of results.
 */
class ProductSearch
{
    public const LIMIT = 10;

    /**
     * @var ResourceConnection
     */
    private $resource;

    /**
     * @var Lookup
     */
    private $lookup;

    public function __construct(ResourceConnection $resource, Lookup $lookup)
    {
        $this->resource = $resource;
        $this->lookup = $lookup;
    }

    /**
     * @return array<int, array{id: int, sku: string, name: string, type_id: string, type_label: string, attribute_set_id: int, attribute_set_name: string}>
     */
    public function search(string $keyword, int $limit = self::LIMIT): array
    {
        $keyword = trim($keyword);
        if ($keyword === '' || mb_strlen($keyword) > 255) {
            return [];
        }
        $connection = $this->resource->getConnection();
        $name = $this->lookup->getDescriptor('name');
        $nameId = $name !== null && $name->getId() !== null ? (int)$name->getId() : 0;
        $like = '%' . addcslashes($keyword, '%_\\') . '%';
        $select = $connection->select()
            ->from(['e' => $this->resource->getTableName('catalog_product_entity')], ['entity_id', 'sku', 'type_id', 'attribute_set_id'])
            ->joinLeft(
                ['n' => $this->resource->getTableName('catalog_product_entity_varchar')],
                sprintf('n.entity_id = e.entity_id AND n.attribute_id = %d AND n.store_id = 0', $nameId),
                ['name' => 'n.value']
            )
            ->where('e.sku LIKE ? OR n.value LIKE ?', $like)
            ->order(new \Zend_Db_Expr('CASE WHEN e.sku = ' . $connection->quote($keyword) . ' THEN 0 ELSE 1 END'))
            ->order('e.sku ASC')
            ->limit(max(1, min($limit, 50)));
        $types = $this->lookup->getProductTypeLabels();
        $sets = $this->lookup->getAttributeSetNames();
        $rows = [];
        foreach ($connection->fetchAll($select) as $row) {
            $rows[] = [
                'id' => (int)$row['entity_id'],
                'sku' => (string)$row['sku'],
                'name' => (string)($row['name'] ?? ''),
                'type_id' => (string)$row['type_id'],
                'type_label' => (string)($types[(string)$row['type_id']] ?? $row['type_id']),
                'attribute_set_id' => (int)$row['attribute_set_id'],
                'attribute_set_name' => (string)($sets[(int)$row['attribute_set_id']] ?? ''),
            ];
        }
        return $rows;
    }

    /**
     * One product's identity for the panel header, or null when it does not exist.
     *
     * @return array<string, mixed>|null
     */
    public function find(int $productId): ?array
    {
        if ($productId <= 0) {
            return null;
        }
        $connection = $this->resource->getConnection();
        $name = $this->lookup->getDescriptor('name');
        $nameId = $name !== null && $name->getId() !== null ? (int)$name->getId() : 0;
        $select = $connection->select()
            ->from(['e' => $this->resource->getTableName('catalog_product_entity')], ['entity_id', 'sku', 'type_id', 'attribute_set_id'])
            ->joinLeft(
                ['n' => $this->resource->getTableName('catalog_product_entity_varchar')],
                sprintf('n.entity_id = e.entity_id AND n.attribute_id = %d AND n.store_id = 0', $nameId),
                ['name' => 'n.value']
            )
            ->where('e.entity_id = ?', $productId);
        $row = $connection->fetchRow($select);
        if (!$row) {
            return null;
        }
        $types = $this->lookup->getProductTypeLabels();
        $sets = $this->lookup->getAttributeSetNames();
        return [
            'id' => (int)$row['entity_id'],
            'sku' => (string)$row['sku'],
            'name' => (string)($row['name'] ?? ''),
            'type_id' => (string)$row['type_id'],
            'type_label' => (string)($types[(string)$row['type_id']] ?? $row['type_id']),
            'attribute_set_id' => (int)$row['attribute_set_id'],
            'attribute_set_name' => (string)($sets[(int)$row['attribute_set_id']] ?? ''),
        ];
    }
}
