<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Ui\Component\Listing\Column;

use BigCartEnergy\CatalogCompleteness\Api\Data\RuleSetInterface;
use BigCartEnergy\CatalogCompleteness\Model\Catalog\Lookup;
use BigCartEnergy\CatalogCompleteness\Model\Channel\ChannelRegistry;
use BigCartEnergy\CatalogCompleteness\Model\ResourceModel\RuleSet as RuleSetResource;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

/**
 * "Attribute sets or channel" and "Products covered" of the rule set listing, in one pass.
 */
class RuleSetScope extends Column
{
    /**
     * @var RuleSetResource
     */
    private $ruleSetResource;

    /**
     * @var Lookup
     */
    private $lookup;

    /**
     * @var ChannelRegistry
     */
    private $channels;

    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        RuleSetResource $ruleSetResource,
        Lookup $lookup,
        ChannelRegistry $channels,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
        $this->ruleSetResource = $ruleSetResource;
        $this->lookup = $lookup;
        $this->channels = $channels;
    }

    /**
     * @inheritDoc
     */
    public function prepareDataSource(array $dataSource)
    {
        if (!isset($dataSource['data']['items'])) {
            return $dataSource;
        }
        $ids = [];
        foreach ($dataSource['data']['items'] as $item) {
            $ids[] = (int)($item['ruleset_id'] ?? 0);
        }
        $setsByRuleset = $this->ruleSetResource->fetchAttributeSetIds($ids);
        $setNames = $this->lookup->getAttributeSetNames();
        $countsPerSet = $this->lookup->countProductsPerSet();
        $allProducts = $this->lookup->countProducts();
        $field = $this->getData('name');
        foreach ($dataSource['data']['items'] as &$item) {
            $rulesetId = (int)($item['ruleset_id'] ?? 0);
            if (($item['kind'] ?? '') === RuleSetInterface::KIND_CHANNEL) {
                $item[$field] = $this->channels->getChannelLabel($item['channel_code'] ?? null);
                $item['products_covered'] = $allProducts;
                continue;
            }
            $names = [];
            $covered = 0;
            foreach ($setsByRuleset[$rulesetId] ?? [] as $setId) {
                $names[] = $setNames[$setId] ?? (string)$setId;
                $covered += $countsPerSet[$setId] ?? 0;
            }
            $item[$field] = $names ? implode(', ', $names) : (string)__('No attribute sets');
            $item['products_covered'] = $covered;
        }
        unset($item);
        return $dataSource;
    }
}
