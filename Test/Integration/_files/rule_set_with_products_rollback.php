<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

use BigCartEnergy\CatalogCompleteness\Model\ResourceModel\RuleSet\CollectionFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Registry;
use Magento\TestFramework\Helper\Bootstrap;

$objectManager = Bootstrap::getObjectManager();
$registry = $objectManager->get(Registry::class);
$registry->unregister('isSecureArea');
$registry->register('isSecureArea', true);

$productRepository = $objectManager->get(ProductRepositoryInterface::class);
foreach (['BCE-FST-001', 'BCE-FST-002', 'BCE-FST-003'] as $sku) {
    try {
        $productRepository->deleteById($sku);
    } catch (NoSuchEntityException $e) {
        // Already gone.
    }
}
foreach ($objectManager->get(CollectionFactory::class)->create()->getItems() as $ruleSet) {
    $ruleSet->delete();
}

$registry->unregister('isSecureArea');
$registry->register('isSecureArea', false);
