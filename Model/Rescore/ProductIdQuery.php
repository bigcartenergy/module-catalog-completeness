<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Model\Rescore;

use Magento\Framework\App\ResourceConnection;

/**
 * The database side of the periodic rescore: the next range of product ids, whether updated_at can drive the
 * changed-since mode, and the database clock that updated_at is written with.
 */
class ProductIdQuery
{
    /**
     * @var ResourceConnection
     */
    private $resource;

    public function __construct(ResourceConnection $resource)
    {
        $this->resource = $resource;
    }

    /**
     * Up to $limit product ids above $afterId, ascending. With $since (UTC, Y-m-d H:i:s), only the products
     * whose updated_at is at or after it.
     *
     * @return int[]
     */
    public function nextIds(int $afterId, int $limit, ?string $since = null): array
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from($this->resource->getTableName('catalog_product_entity'), ['entity_id'])
            ->where('entity_id > ?', $afterId)
            ->order('entity_id ASC')
            ->limit($limit);
        if ($since !== null) {
            $select->where('updated_at >= ?', $since);
        }
        return array_map('intval', $connection->fetchCol($select));
    }

    /**
     * Whether the product table has a timestamp or datetime updated_at column.
     */
    public function hasUpdatedAt(): bool
    {
        $columns = $this->resource->getConnection()->describeTable(
            $this->resource->getTableName('catalog_product_entity')
        );
        $type = isset($columns['updated_at']['DATA_TYPE']) ? (string)$columns['updated_at']['DATA_TYPE'] : '';
        return in_array(strtolower($type), ['timestamp', 'datetime'], true);
    }

    /**
     * The database's current time in UTC (Y-m-d H:i:s): the clock a timestamp updated_at is set by, so a
     * run's start and the products' updated_at values compare without any clock skew to the web server.
     */
    public function now(): string
    {
        return (string)$this->resource->getConnection()->fetchOne('SELECT UTC_TIMESTAMP()');
    }
}
