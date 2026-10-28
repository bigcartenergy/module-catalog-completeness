<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Test\Unit\Model\Rescore;

use BigCartEnergy\CatalogCompleteness\Model\Rescore\Batches;
use BigCartEnergy\CatalogCompleteness\Model\Rescore\ProductIdQuery;
use PHPUnit\Framework\TestCase;

class BatchesTest extends TestCase
{
    /**
     * Product ids with gaps, each with its updated_at.
     */
    private const CATALOG = [
        1 => '2026-01-01 00:00:00',
        2 => '2026-01-02 00:00:00',
        3 => '2026-03-01 08:00:00',
        5 => '2026-01-01 00:00:00',
        8 => '2026-03-01 09:00:00',
        13 => '2026-01-01 00:00:00',
        21 => '2026-03-02 00:00:00',
        34 => '2026-01-01 00:00:00',
        55 => '2026-01-01 00:00:00',
        89 => '2026-03-05 00:00:00',
        144 => '2026-01-01 00:00:00',
    ];

    /**
     * @var array<int, array{0: int, 1: int, 2: string|null}> every nextIds call: after id, limit, since
     */
    private $calls = [];

    public function testEveryIdOnceInAscendingIdRangesOfAtMostOneBatch(): void
    {
        list($batches, $total) = $this->walk(self::CATALOG, 3, null);
        self::assertSame([[1, 2, 3], [5, 8, 13], [21, 34, 55], [89, 144]], $batches);
        self::assertSame(11, $total);
        self::assertSame([[0, 3, null], [3, 3, null], [13, 3, null], [55, 3, null]], $this->calls);
    }

    public function testAFullLastBatchEndsWithOneEmptyQuery(): void
    {
        $catalog = array_slice(self::CATALOG, 0, 6, true);
        list($batches, $total) = $this->walk($catalog, 3, null);
        self::assertSame([[1, 2, 3], [5, 8, 13]], $batches);
        self::assertSame(6, $total);
        self::assertCount(3, $this->calls);
        self::assertSame(13, $this->calls[2][0]);
    }

    public function testAnEmptyCatalogHandsNothingOver(): void
    {
        list($batches, $total) = $this->walk([], 500, null);
        self::assertSame([], $batches);
        self::assertSame(0, $total);
        self::assertCount(1, $this->calls);
    }

    public function testChangedSinceHandsOverOnlyTheChangedIds(): void
    {
        list($batches, $total) = $this->walk(self::CATALOG, 2, '2026-03-01 00:00:00');
        self::assertSame([[3, 8], [21, 89]], $batches);
        self::assertSame(4, $total);
        foreach ($this->calls as $call) {
            self::assertSame('2026-03-01 00:00:00', $call[2]);
            self::assertSame(2, $call[1]);
        }
    }

    public function testABatchLargerThanTheCatalogIsOneBatch(): void
    {
        list($batches, $total) = $this->walk(self::CATALOG, 500, null);
        self::assertSame([array_keys(self::CATALOG)], $batches);
        self::assertSame(11, $total);
        self::assertCount(1, $this->calls);
    }

    public function testALargeCatalogNeverHoldsMoreThanOneBatch(): void
    {
        $catalog = array_fill_keys(range(1, 10007), '2026-01-01 00:00:00');
        list($batches, $total) = $this->walk($catalog, 500, null);
        self::assertSame(10007, $total);
        self::assertCount(21, $batches);
        self::assertCount(21, $this->calls);
        $previousHighest = 0;
        foreach ($batches as $index => $batch) {
            self::assertLessThanOrEqual(500, count($batch));
            self::assertGreaterThan($previousHighest, $batch[0], 'batch ' . $index . ' starts above the last one');
            $highest = $batch[count($batch) - 1];
            self::assertSame(range($batch[0], $highest), $batch, 'batch ' . $index . ' is one id range');
            $previousHighest = $highest;
        }
        self::assertCount(7, $batches[20]);
    }

    public function testAnIdRangeThatDoesNotMoveOnStopsTheWalk(): void
    {
        $query = $this->createStub(ProductIdQuery::class);
        $query->method('nextIds')->willReturn([5, 6, 7]);
        $handed = [];
        $total = (new Batches($query))->walk(3, null, function (array $ids) use (&$handed): void {
            $handed[] = $ids;
        });
        self::assertSame([[5, 6, 7]], $handed);
        self::assertSame(3, $total);
    }

    public function testABatchSizeBelowOneIsTreatedAsOne(): void
    {
        list($batches) = $this->walk([4 => '2026-01-01 00:00:00', 9 => '2026-01-01 00:00:00'], 0, null);
        self::assertSame([[4], [9]], $batches);
        foreach ($this->calls as $call) {
            self::assertSame(1, $call[1]);
        }
    }

    /**
     * Walks an in-memory catalog the way the database query does.
     *
     * @param array<int, string> $catalog product id => updated_at
     * @return array{0: array<int, int[]>, 1: int} the batches handed over and the total
     */
    private function walk(array $catalog, int $batchSize, ?string $since): array
    {
        $this->calls = [];
        $calls = &$this->calls;
        $query = $this->createStub(ProductIdQuery::class);
        $query->method('nextIds')->willReturnCallback(
            function (int $afterId, int $limit, ?string $changedSince = null) use ($catalog, &$calls): array {
                $calls[] = [$afterId, $limit, $changedSince];
                $ids = [];
                foreach ($catalog as $id => $updatedAt) {
                    if ($id > $afterId && ($changedSince === null || $updatedAt >= $changedSince)) {
                        $ids[] = $id;
                    }
                }
                sort($ids);
                return array_slice($ids, 0, $limit);
            }
        );
        $batches = [];
        $total = (new Batches($query))->walk($batchSize, $since, function (array $ids) use (&$batches): void {
            $batches[] = $ids;
        });
        return [$batches, $total];
    }
}
