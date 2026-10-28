<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

use BigCartEnergy\CatalogCompleteness\Api\Data\RuleInterface;
use BigCartEnergy\CatalogCompleteness\Api\Data\RuleSetInterface;
use BigCartEnergy\CatalogCompleteness\Api\RuleSetRepositoryInterface;
use BigCartEnergy\CatalogCompleteness\Model\RuleFactory;
use BigCartEnergy\CatalogCompleteness\Model\RuleSetFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ProductFactory;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\App\Area;
use Magento\TestFramework\Helper\Bootstrap;

/*
 * Three simple products in the default attribute set and one attribute set rule set with four rules:
 * - BCE-FST-001: complete (name, long description, manufacturer, weight)
 * - BCE-FST-002: short description, no weight
 * - BCE-FST-003: everything empty but the name
 */
$objectManager = Bootstrap::getObjectManager();
$objectManager->get(\Magento\Framework\App\State::class)->setAreaCode(Area::AREA_ADMINHTML);

$eavConfig = $objectManager->get(EavConfig::class);
$attributeSetId = (int)$eavConfig->getEntityType(\Magento\Catalog\Model\Product::ENTITY)->getDefaultAttributeSetId();

/** @var ProductRepositoryInterface $productRepository */
$productRepository = $objectManager->get(ProductRepositoryInterface::class);
/** @var ProductFactory $productFactory */
$productFactory = $objectManager->get(ProductFactory::class);

$products = [
    'BCE-FST-001' => [
        'name' => 'Hex bolt M10 x 50, zinc plated, grade 8.8',
        'description' => '<p>' . str_repeat('Zinc plated hex bolt for structural steel work. ', 6) . '</p>',
        'weight' => 0.045,
    ],
    'BCE-FST-002' => [
        'name' => 'Flap disc 125 mm',
        'description' => '<p>Flap disc.</p>',
        'weight' => null,
    ],
    'BCE-FST-003' => [
        'name' => 'Cable ties',
        'description' => '',
        'weight' => null,
    ],
];
foreach ($products as $sku => $data) {
    $product = $productFactory->create();
    $product->setTypeId(Type::TYPE_SIMPLE)
        ->setAttributeSetId($attributeSetId)
        ->setWebsiteIds([1])
        ->setSku($sku)
        ->setName($data['name'])
        ->setDescription($data['description'])
        ->setPrice(10)
        ->setVisibility(Visibility::VISIBILITY_BOTH)
        ->setStatus(Status::STATUS_ENABLED)
        ->setStockData(['use_config_manage_stock' => 1, 'qty' => 10, 'is_in_stock' => 1]);
    if ($data['weight'] !== null) {
        $product->setWeight($data['weight']);
    }
    $productRepository->save($product);
}

/** @var RuleSetFactory $ruleSetFactory */
$ruleSetFactory = $objectManager->get(RuleSetFactory::class);
/** @var RuleFactory $ruleFactory */
$ruleFactory = $objectManager->get(RuleFactory::class);
$ruleSet = $ruleSetFactory->create();
$ruleSet->setName('Fasteners core content');
$ruleSet->setKind(RuleSetInterface::KIND_ATTRIBUTE_SET);
$ruleSet->setIsEnabled(true);
$ruleSet->setAttributeSetIds([$attributeSetId]);
$rules = [];
foreach ([
    ['name', RuleInterface::TYPE_MIN_LENGTH, '20', RuleInterface::WEIGHT_NICE_TO_HAVE],
    ['description', RuleInterface::TYPE_MIN_LENGTH, '100', RuleInterface::WEIGHT_REQUIRED],
    ['weight', RuleInterface::TYPE_GT_ZERO, null, RuleInterface::WEIGHT_IMPORTANT],
    ['media_gallery', RuleInterface::TYPE_MIN_COUNT, '1', RuleInterface::WEIGHT_IMPORTANT],
] as [$code, $type, $parameter, $weight]) {
    $rule = $ruleFactory->create();
    $rule->setAttributeCode($code);
    $rule->setRuleType($type);
    $rule->setParameter($parameter);
    $rule->setWeight($weight);
    $rule->setAppliesTo([]);
    $rules[] = $rule;
}
$ruleSet->setRules($rules);
$objectManager->get(RuleSetRepositoryInterface::class)->save($ruleSet);
