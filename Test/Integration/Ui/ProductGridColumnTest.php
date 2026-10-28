<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Test\Integration\Ui;

use BigCartEnergy\CatalogCompleteness\Model\Indexer\Reindexer;
use Magento\Catalog\Ui\DataProvider\Product\ProductDataProvider;
use Magento\Framework\Api\FilterBuilder;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * D2 and D3: the product grid's data provider selects the score when the column asks for its field, and
 * the range filter narrows the collection.
 *
 * @magentoAppArea adminhtml
 * @magentoDbIsolation disabled
 * @magentoAppIsolation enabled
 */
class ProductGridColumnTest extends TestCase
{
    /**
     * @magentoDataFixture BigCartEnergy_CatalogCompleteness::Test/Integration/_files/rule_set_with_products.php
     */
    public function testColumnIsSelectedAndRangeFilterNarrows(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $objectManager->get(Reindexer::class)->reindexAll(null);

        /** @var ProductDataProvider $provider */
        $provider = $objectManager->create(ProductDataProvider::class, [
            'name' => 'product_listing_data_source',
            'primaryFieldName' => 'entity_id',
            'requestFieldName' => 'id',
        ]);
        $provider->addField('bce_completeness_score');
        $provider->addFilter(
            $objectManager->get(FilterBuilder::class)->setField('sku')->setConditionType('like')->setValue('BCE-FST-%')->create()
        );
        $items = $provider->getData()['items'];
        $bySku = [];
        foreach ($items as $item) {
            $bySku[$item['sku']] = $item;
        }
        self::assertArrayHasKey('bce_completeness_score', $bySku['BCE-FST-001'], 'addField selects the joined score');
        self::assertSame(75, (int)$bySku['BCE-FST-001']['bce_completeness_score']);
        self::assertSame(0, (int)$bySku['BCE-FST-003']['bce_completeness_score']);

        /** @var ProductDataProvider $filtered */
        $filtered = $objectManager->create(ProductDataProvider::class, [
            'name' => 'product_listing_data_source',
            'primaryFieldName' => 'entity_id',
            'requestFieldName' => 'id',
        ]);
        $filtered->addField('bce_completeness_score');
        $filtered->addFilter(
            $objectManager->get(FilterBuilder::class)->setField('sku')->setConditionType('like')->setValue('BCE-FST-%')->create()
        );
        $filtered->addFilter(
            $objectManager->get(FilterBuilder::class)->setField('bce_completeness_score')->setConditionType('gteq')->setValue(50)->create()
        );
        $filtered->addFilter(
            $objectManager->get(FilterBuilder::class)->setField('bce_completeness_score')->setConditionType('lteq')->setValue(100)->create()
        );
        $skus = array_column($filtered->getData()['items'], 'sku');
        self::assertSame(['BCE-FST-001'], $skus, 'the gteq/lteq range keeps the one product in range');
    }
}
