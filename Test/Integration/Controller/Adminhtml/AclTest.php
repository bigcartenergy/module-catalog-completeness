<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Test\Integration\Controller\Adminhtml;

use Magento\Framework\Acl\Builder as AclBuilder;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\TestFramework\TestCase\AbstractBackendController;

/**
 * Every admin controller is denied without its ACL resource, and every state change refuses GET.
 *
 * @magentoAppArea adminhtml
 */
class AclTest extends AbstractBackendController
{
    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function routesProvider(): array
    {
        return [
            'dashboard' => ['bce_completeness/dashboard/index', 'BigCartEnergy_CatalogCompleteness::dashboard', 'GET'],
            'ruleset list' => ['bce_completeness/ruleset/index', 'BigCartEnergy_CatalogCompleteness::rulesets', 'GET'],
            'ruleset new' => ['bce_completeness/ruleset/new', 'BigCartEnergy_CatalogCompleteness::rulesets_edit', 'GET'],
            'ruleset save' => ['bce_completeness/ruleset/save', 'BigCartEnergy_CatalogCompleteness::rulesets_edit', 'POST'],
            'ruleset delete' => ['bce_completeness/ruleset/delete', 'BigCartEnergy_CatalogCompleteness::rulesets_edit', 'POST'],
            'ruleset duplicate' => ['bce_completeness/ruleset/duplicate', 'BigCartEnergy_CatalogCompleteness::rulesets_edit', 'POST'],
            'ruleset affected' => ['bce_completeness/ruleset/affected', 'BigCartEnergy_CatalogCompleteness::rulesets', 'GET'],
            'score preview' => ['bce_completeness/score/preview', 'BigCartEnergy_CatalogCompleteness::rulesets', 'POST'],
            'score search' => ['bce_completeness/score/search', 'BigCartEnergy_CatalogCompleteness::rulesets', 'GET'],
            'score status' => ['bce_completeness/score/status', 'BigCartEnergy_CatalogCompleteness::dashboard', 'GET'],
            'score recalculate' => ['bce_completeness/score/recalculate', 'BigCartEnergy_CatalogCompleteness::recalculate', 'POST'],
            'score export' => ['bce_completeness/score/export', 'BigCartEnergy_CatalogCompleteness::export', 'POST'],
        ];
    }

    public function testDeniedWithoutResource(): void
    {
        foreach (self::routesProvider() as $case => $arguments) {
            $this->checkDeniedWithoutResource(...array_values($arguments));
        }
    }

    /**
     * One case of testDeniedWithoutResource().
     */
    private function checkDeniedWithoutResource(string $route, string $resource, string $method): void
    {
        $this->_objectManager->get(AclBuilder::class)->getAcl()->deny(null, $resource);
        $this->getRequest()->setMethod($method === 'POST' ? HttpRequest::METHOD_POST : HttpRequest::METHOD_GET);
        if ($method === 'POST') {
            $this->getRequest()->setPostValue('form_key', $this->_objectManager->get(\Magento\Framework\Data\Form\FormKey::class)->getFormKey());
        }
        $this->dispatch('backend/' . $route);
        self::assertSame(403, $this->getResponse()->getHttpResponseCode(), $route . ' must be denied');
    }

    public function testStateChangesRefuseGet(): void
    {
        foreach (self::routesProvider() as $case => $arguments) {
            $this->checkStateChangesRefuseGet(...array_values($arguments));
        }
    }

    /**
     * One case of testStateChangesRefuseGet().
     */
    private function checkStateChangesRefuseGet(string $route, string $resource, string $method): void
    {
        if ($method !== 'POST') {
            self::markTestSkipped('read-only route');
        }
        $this->getRequest()->setMethod(HttpRequest::METHOD_GET);
        $this->dispatch('backend/' . $route);
        self::assertSame(404, $this->getResponse()->getHttpResponseCode(), $route . ' is POST only');
    }
}
