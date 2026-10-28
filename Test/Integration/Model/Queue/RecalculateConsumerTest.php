<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Test\Integration\Model\Queue;

use BigCartEnergy\CatalogCompleteness\Model\Queue\RecalculateConsumer;
use BigCartEnergy\CatalogCompleteness\Model\Queue\RunManager;
use BigCartEnergy\CatalogCompleteness\Model\Run;
use Magento\Framework\MessageQueue\PublisherInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * The consumer works a queued run and leaves the run row done with its counters.
 *
 * @magentoAppArea adminhtml
 * @magentoDbIsolation disabled
 * @magentoAppIsolation enabled
 */
class RecalculateConsumerTest extends TestCase
{
    /**
     * @magentoDataFixture BigCartEnergy_CatalogCompleteness::Test/Integration/_files/rule_set_with_products.php
     */
    public function testConsumerUpdatesTheRunRow(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        // Publishing is a side effect of queue(); the message is consumed by hand here.
        $objectManager->configure([PublisherInterface::class => ['shared' => true]]);
        /** @var RunManager $runManager */
        $runManager = $objectManager->get(RunManager::class);
        $run = $runManager->queue(null);
        self::assertSame(Run::STATE_QUEUED, $run->getState());
        self::assertGreaterThanOrEqual(3, $run->getTotal());

        $objectManager->get(RecalculateConsumer::class)->process(json_encode(['run_id' => (int)$run->getId()]));

        $done = $runManager->load((int)$run->getId());
        self::assertSame(Run::STATE_DONE, $done->getState());
        self::assertSame($done->getTotal(), $done->getDone());
        self::assertNotEmpty($done->getData('finished_at'));
        self::assertNull($runManager->getActiveRun());
    }

    public function testUnknownRunIsSkippedWithoutError(): void
    {
        Bootstrap::getObjectManager()->get(RecalculateConsumer::class)->process(json_encode(['run_id' => 999999]));
        Bootstrap::getObjectManager()->get(RecalculateConsumer::class)->process('not json');
        self::assertTrue(true);
    }
}
