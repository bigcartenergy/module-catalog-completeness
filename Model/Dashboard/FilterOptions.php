<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Model\Dashboard;

use BigCartEnergy\CatalogCompleteness\Model\Catalog\Lookup;
use BigCartEnergy\CatalogCompleteness\Model\Config;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Framework\App\ResourceConnection;

/**
 * The options of the dashboard's filter selects: attribute sets, categories, brands and product types.
 */
class FilterOptions
{
    public const MAX_BRANDS = 300;

    /**
     * @var Lookup
     */
    private $lookup;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var ResourceConnection
     */
    private $resource;

    /**
     * @var CategoryCollectionFactory
     */
    private $categoryCollectionFactory;

    public function __construct(
        Lookup $lookup,
        Config $config,
        ResourceConnection $resource,
        CategoryCollectionFactory $categoryCollectionFactory
    ) {
        $this->lookup = $lookup;
        $this->config = $config;
        $this->resource = $resource;
        $this->categoryCollectionFactory = $categoryCollectionFactory;
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function getAttributeSets(): array
    {
        $options = [];
        foreach ($this->lookup->getAttributeSetNames() as $id => $name) {
            $options[] = ['value' => (string)$id, 'label' => $name];
        }
        return $options;
    }

    /**
     * Categories below the root, indented by depth.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public function getCategories(): array
    {
        $collection = $this->categoryCollectionFactory->create();
        $collection->addAttributeToSelect('name')
            ->addFieldToFilter('level', ['gteq' => 2])
            ->setOrder('path', 'ASC');
        $options = [];
        foreach ($collection->getItems() as $category) {
            $indent = str_repeat('- ', max(0, (int)$category->getLevel() - 2));
            $options[] = ['value' => (string)$category->getId(), 'label' => $indent . (string)$category->getName()];
        }
        return $options;
    }

    /**
     * Brand values in use, labelled; "No brand set" first.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public function getBrands(): array
    {
        $options = [['value' => Filter::BRAND_NONE, 'label' => (string)__('No brand set')]];
        $descriptor = $this->lookup->getDescriptor($this->config->getBrandAttributeCode());
        if ($descriptor === null || $descriptor->getId() === null) {
            return $options;
        }
        $labels = $descriptor->getOptions();
        $backend = $descriptor->getBackendType();
        if (!in_array($backend, ['varchar', 'int', 'text', 'decimal', 'datetime'], true)) {
            return $options;
        }
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from($this->resource->getTableName('catalog_product_entity_' . $backend), ['value'])
            ->where('attribute_id = ?', (int)$descriptor->getId())
            ->where('store_id = 0')
            ->where('value IS NOT NULL')
            ->where("value <> ''")
            ->group('value')
            ->order('value ASC')
            ->limit(self::MAX_BRANDS);
        $values = [];
        foreach ($connection->fetchCol($select) as $value) {
            $value = (string)$value;
            foreach ($descriptor->isMultiselect() ? explode(',', $value) : [$value] as $single) {
                $single = trim($single);
                if ($single !== '' && !isset($values[$single])) {
                    $values[$single] = $labels[$single] ?? $single;
                }
            }
        }
        asort($values, SORT_NATURAL | SORT_FLAG_CASE);
        foreach ($values as $value => $label) {
            $options[] = ['value' => (string)$value, 'label' => (string)$label];
        }
        return $options;
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function getProductTypes(): array
    {
        $options = [];
        foreach ($this->lookup->getProductTypeLabels() as $typeId => $label) {
            $options[] = ['value' => $typeId, 'label' => $label];
        }
        return $options;
    }

    /**
     * Label of a filter value for the active-filter chips.
     *
     * @param array<int, array{value: string, label: string}> $options
     */
    public static function labelOf(array $options, ?string $value): string
    {
        if ($value === null) {
            return '';
        }
        foreach ($options as $option) {
            if ($option['value'] === $value) {
                return $option['label'];
            }
        }
        return $value;
    }
}
