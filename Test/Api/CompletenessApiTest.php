<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Test\Api;

use Magento\Framework\Webapi\Rest\Request;
use Magento\TestFramework\TestCase\WebapiAbstract;

/**
 * REST: scores are readable and recalculable, and nothing writes a score. Runs in Magento's
 * api-functional test framework on the bench.
 */
class CompletenessApiTest extends WebapiAbstract
{
    /**
     * @magentoApiDataFixture BigCartEnergy_CatalogCompleteness::Test/Integration/_files/rule_set_with_products.php
     */
    public function testRecalculateThenReadScores(): void
    {
        $productId = $this->productId('BCE-FST-001');
        $scores = $this->_webApiCall([
            'rest' => ['resourcePath' => '/V1/bce-completeness/products/' . $productId . '/recalculate', 'httpMethod' => Request::HTTP_METHOD_POST],
        ]);
        self::assertCount(1, $scores);
        self::assertSame(75, (int)$scores[0]['score']);
        self::assertSame('needs_work', $scores[0]['band']);
        self::assertSame(['At least 1 image'], $scores[0]['missing']);
        self::assertSame('scored', $scores[0]['state']);

        $read = $this->_webApiCall([
            'rest' => ['resourcePath' => '/V1/bce-completeness/products/' . $productId . '/scores', 'httpMethod' => Request::HTTP_METHOD_GET],
        ]);
        self::assertSame($scores[0]['score'], $read[0]['score']);

        $ruleSets = $this->_webApiCall([
            'rest' => ['resourcePath' => '/V1/bce-completeness/rulesets', 'httpMethod' => Request::HTTP_METHOD_GET],
        ]);
        self::assertSame('Fasteners core content', $ruleSets[0]['name']);
        self::assertCount(4, $ruleSets[0]['rules']);
    }

    /**
     * @magentoApiDataFixture BigCartEnergy_CatalogCompleteness::Test/Integration/_files/rule_set_with_products.php
     */
    public function testNoRouteWritesAScore(): void
    {
        $productId = $this->productId('BCE-FST-001');
        foreach ([Request::HTTP_METHOD_PUT, Request::HTTP_METHOD_POST, Request::HTTP_METHOD_DELETE] as $method) {
            try {
                $this->_webApiCall(
                    ['rest' => ['resourcePath' => '/V1/bce-completeness/products/' . $productId . '/scores', 'httpMethod' => $method]],
                    ['score' => 100, 'missing' => []]
                );
                self::fail($method . ' on the scores route must not exist');
            } catch (\Exception $e) {
                self::assertTrue(strpos((string)$e->getCode() . ' ' . $e->getMessage(), '404') !== false, $method . ' on the scores route must answer 404');
            }
        }
        $read = $this->_webApiCall([
            'rest' => ['resourcePath' => '/V1/bce-completeness/products/' . $productId . '/scores', 'httpMethod' => Request::HTTP_METHOD_GET],
        ]);
        self::assertNotSame(100, (int)($read[0]['score'] ?? 0));
    }

    private function productId(string $sku): int
    {
        $product = $this->_webApiCall([
            'rest' => ['resourcePath' => '/V1/products/' . rawurlencode($sku), 'httpMethod' => Request::HTTP_METHOD_GET],
        ]);
        return (int)$product['id'];
    }
}
