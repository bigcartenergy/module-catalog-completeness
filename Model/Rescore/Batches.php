<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Model\Rescore;

/**
 * Walks the catalog by product id range: each batch is the next ids above the last batch's highest id, at
 * most one batch size of them, so one run holds a single batch of products in memory however large the
 * catalog is.
 */
class Batches
{
    /**
     * @var ProductIdQuery
     */
    private $query;

    public function __construct(ProductIdQuery $query)
    {
        $this->query = $query;
    }

    /**
     * Hands every product id, or with $since every id whose updated_at is at or after it, to $handle in
     * ascending batches of at most $batchSize ids.
     *
     * @param callable $handle function (int[] $ids): void
     * @return int the number of ids handed over
     */
    public function walk(int $batchSize, ?string $since, callable $handle): int
    {
        $batchSize = max(1, $batchSize);
        $afterId = 0;
        $total = 0;
        while (true) {
            $ids = $this->query->nextIds($afterId, $batchSize, $since);
            // An id range that does not move past the last one would repeat forever: stop instead.
            if (!$ids || (int)max($ids) <= $afterId) {
                break;
            }
            $handle($ids);
            $total += count($ids);
            if (count($ids) < $batchSize) {
                break;
            }
            $afterId = (int)max($ids);
        }
        return $total;
    }
}
