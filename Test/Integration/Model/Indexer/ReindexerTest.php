<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Test\Integration\Model\Indexer;

use BigCartEnergy\CatalogCompleteness\Api\RuleSetRepositoryInterface;
use BigCartEnergy\CatalogCompleteness\Model\Indexer\Reindexer;
use BigCartEnergy\CatalogCompleteness\Model\Indexer\ScoreIndexer;
use BigCartEnergy\CatalogCompleteness\Model\Indexer\Status as IndexerStatus;
use BigCartEnergy\CatalogCompleteness\Model\ResourceModel\RuleSet\CollectionFactory as RuleSetCollectionFactory;
use BigCartEnergy\CatalogCompleteness\Model\ResourceModel\Score as ScoreResource;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Registry;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * Runs on the bench inside Magento's integration test framework.
 *
 * @magentoAppArea adminhtml
 * @magentoDbIsolation disabled
 * @magentoAppIsolation enabled
 */
class ReindexerTest extends TestCase
{
    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    private $objectManager;

    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();
    }

    /**
     * Full reindex on a fixture catalog: a score row per product, the expected scores.
     *
     * @magentoDataFixture BigCartEnergy_CatalogCompleteness::Test/Integration/_files/rule_set_with_products.php
     */
    public function testFullReindexScoresEveryProduct(): void
    {
        $this->objectManager->get(Reindexer::class)->reindexAll(null);
        $scores = $this->scoresBySku();
        // weights: name 1, description 3, weight 2, images 2 = 8
        self::assertSame(75, $scores['BCE-FST-001'], 'name, description and weight pass; no image: 6 of 8');
        self::assertSame(0, $scores['BCE-FST-002'], 'short description, no weight, no image, short name');
        self::assertSame(0, $scores['BCE-FST-003']);
    }

    /**
     * R2 / D1: a changelog batch with a deleted product id completes and the surviving rows stay.
     *
     * @magentoDataFixture BigCartEnergy_CatalogCompleteness::Test/Integration/_files/rule_set_with_products.php
     */
    public function testExecuteListWithDeletedProductCompletes(): void
    {
        $reindexer = $this->objectManager->get(Reindexer::class);
        $reindexer->reindexAll(null);
        $ids = $this->productIdsBySku();
        $deletedId = $ids['BCE-FST-003'];

        $registry = $this->objectManager->get(Registry::class);
        $registry->unregister('isSecureArea');
        $registry->register('isSecureArea', true);
        $this->objectManager->get(ProductRepositoryInterface::class)->deleteById('BCE-FST-003');
        $registry->unregister('isSecureArea');

        /** @var ScoreIndexer $action */
        $action = $this->objectManager->create(ScoreIndexer::class);
        $action->execute([$deletedId, $ids['BCE-FST-001'], 999999999]);

        $scores = $this->scoresBySku();
        self::assertArrayNotHasKey('BCE-FST-003', $scores);
        self::assertSame(75, $scores['BCE-FST-001']);
        self::assertSame(
            0,
            (int)$this->objectManager->get(ResourceConnection::class)->getConnection()->fetchOne(
                'SELECT COUNT(*) FROM ' . $this->objectManager->get(ScoreResource::class)->getTable() . ' WHERE product_id = ?',
                [$deletedId]
            )
        );
    }

    /**
     * Saving a rule set invalidates the indexer (D4).
     *
     * @magentoDataFixture BigCartEnergy_CatalogCompleteness::Test/Integration/_files/rule_set_with_products.php
     */
    public function testSavingARuleSetInvalidatesTheIndexer(): void
    {
        $status = $this->objectManager->get(IndexerStatus::class);
        $status->reindexAll();
        self::assertTrue($status->isValid());

        $ruleSet = $this->objectManager->get(RuleSetCollectionFactory::class)->create()->getFirstItem();
        $ruleSet->setDescription('touched');
        $this->objectManager->get(RuleSetRepositoryInterface::class)->save($ruleSet);

        self::assertTrue($status->isInvalid());
    }

    /**
     * @return array<string, int>
     */
    private function scoresBySku(): array
    {
        $connection = $this->objectManager->get(ResourceConnection::class)->getConnection();
        $select = $connection->select()
            ->from(['s' => $this->objectManager->get(ScoreResource::class)->getTable()], ['score'])
            ->join(['e' => $connection->getTableName('catalog_product_entity')], 'e.entity_id = s.product_id', ['sku'])
            ->where('e.sku LIKE ?', 'BCE-FST-%');
        $scores = [];
        foreach ($connection->fetchAll($select) as $row) {
            $scores[$row['sku']] = $row['score'] === null ? null : (int)$row['score'];
        }
        return $scores;
    }

    /**
     * @return array<string, int>
     */
    private function productIdsBySku(): array
    {
        $connection = $this->objectManager->get(ResourceConnection::class)->getConnection();
        $select = $connection->select()
            ->from($connection->getTableName('catalog_product_entity'), ['sku', 'entity_id'])
            ->where('sku LIKE ?', 'BCE-FST-%');
        $ids = [];
        foreach ($connection->fetchAll($select) as $row) {
            $ids[$row['sku']] = (int)$row['entity_id'];
        }
        return $ids;
    }
}
