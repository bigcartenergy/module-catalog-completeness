<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Model\Catalog;

use BigCartEnergy\CatalogCompleteness\Api\Data\RuleInterface;
use BigCartEnergy\CatalogCompleteness\Model\Rule\AttributeDescriptor;
use BigCartEnergy\CatalogCompleteness\Model\Rule\CatalogLookupInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Type as ProductType;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;

/**
 * The catalog behind the lookup interface: EAV attribute metadata, attribute set membership, product types.
 * Everything is cached for the life of the object, so one indexer run reads each attribute once.
 */
class Lookup implements CatalogLookupInterface
{
    /**
     * @var EavConfig
     */
    private $eavConfig;

    /**
     * @var ProductType
     */
    private $productType;

    /**
     * @var ResourceConnection
     */
    private $resource;

    /**
     * @var array<string, AttributeDescriptor|null>
     */
    private $descriptors = [];

    /**
     * @var array<int, string>|null
     */
    private $attributeSetNames;

    /**
     * @var int|null
     */
    private $entityTypeId;

    public function __construct(EavConfig $eavConfig, ProductType $productType, ResourceConnection $resource)
    {
        $this->eavConfig = $eavConfig;
        $this->productType = $productType;
        $this->resource = $resource;
    }

    /**
     * @inheritDoc
     */
    public function getDescriptor(string $code): ?AttributeDescriptor
    {
        if ($code === RuleInterface::MEDIA_GALLERY) {
            return AttributeDescriptor::gallery((string)__('Images'));
        }
        if ($code === AttributeDescriptor::STOCK_STATUS_CODE) {
            return AttributeDescriptor::stockStatus((string)__('Stock status'));
        }
        if (array_key_exists($code, $this->descriptors)) {
            return $this->descriptors[$code];
        }
        $descriptor = null;
        try {
            $attribute = $this->eavConfig->getAttribute(Product::ENTITY, $code);
            if ($attribute && $attribute->getId()) {
                $descriptor = new AttributeDescriptor(
                    $code,
                    (int)$attribute->getId(),
                    $this->labelOf($attribute, $code),
                    (string)$attribute->getBackendType(),
                    (string)$attribute->getFrontendInput(),
                    $this->optionsOf($attribute)
                );
            }
        } catch (LocalizedException $e) {
            $descriptor = null;
        }
        $this->descriptors[$code] = $descriptor;
        return $descriptor;
    }

    /**
     * @inheritDoc
     */
    public function getProductTypeIds(): array
    {
        return array_map('strval', array_keys($this->productType->getOptionArray()));
    }

    /**
     * Product type id => label.
     *
     * @return array<string, string>
     */
    public function getProductTypeLabels(): array
    {
        $labels = [];
        foreach ($this->productType->getOptionArray() as $typeId => $label) {
            $labels[(string)$typeId] = (string)$label;
        }
        return $labels;
    }

    /**
     * @inheritDoc
     */
    public function getAttributeSetIds(): array
    {
        return array_keys($this->getAttributeSetNames());
    }

    /**
     * Product attribute set id => name.
     *
     * @return array<int, string>
     */
    public function getAttributeSetNames(): array
    {
        if ($this->attributeSetNames === null) {
            $connection = $this->resource->getConnection();
            $select = $connection->select()
                ->from($this->resource->getTableName('eav_attribute_set'), ['attribute_set_id', 'attribute_set_name'])
                ->where('entity_type_id = ?', $this->getEntityTypeId())
                ->order('sort_order ASC')
                ->order('attribute_set_name ASC');
            $this->attributeSetNames = [];
            foreach ($connection->fetchAll($select) as $row) {
                $this->attributeSetNames[(int)$row['attribute_set_id']] = (string)$row['attribute_set_name'];
            }
        }
        return $this->attributeSetNames;
    }

    /**
     * Which attribute sets hold which of the given attributes.
     *
     * @param int[] $attributeIds
     * @return array<int, array<int, bool>> attribute set id => [attribute id => true]
     */
    public function getMembership(array $attributeIds): array
    {
        $attributeIds = array_values(array_unique(array_map('intval', $attributeIds)));
        if (!$attributeIds) {
            return [];
        }
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from($this->resource->getTableName('eav_entity_attribute'), ['attribute_set_id', 'attribute_id'])
            ->where('entity_type_id = ?', $this->getEntityTypeId())
            ->where('attribute_id IN (?)', $attributeIds);
        $membership = [];
        foreach ($connection->fetchAll($select) as $row) {
            $membership[(int)$row['attribute_set_id']][(int)$row['attribute_id']] = true;
        }
        return $membership;
    }

    /**
     * Number of products in the given attribute sets.
     *
     * @param int[] $attributeSetIds
     */
    public function countProductsInSets(array $attributeSetIds): int
    {
        $attributeSetIds = array_values(array_unique(array_map('intval', $attributeSetIds)));
        if (!$attributeSetIds) {
            return 0;
        }
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from($this->resource->getTableName('catalog_product_entity'), ['n' => new \Zend_Db_Expr('COUNT(*)')])
            ->where('attribute_set_id IN (?)', $attributeSetIds);
        return (int)$connection->fetchOne($select);
    }

    /**
     * Number of products in the catalog.
     */
    public function countProducts(): int
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from($this->resource->getTableName('catalog_product_entity'), ['n' => new \Zend_Db_Expr('COUNT(*)')]);
        return (int)$connection->fetchOne($select);
    }

    /**
     * Products per attribute set, for the editor's set picker and the warnings.
     *
     * @return array<int, int> attribute set id => product count
     */
    public function countProductsPerSet(): array
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from(
                $this->resource->getTableName('catalog_product_entity'),
                ['attribute_set_id', 'n' => new \Zend_Db_Expr('COUNT(*)')]
            )
            ->group('attribute_set_id');
        $counts = [];
        foreach ($connection->fetchAll($select) as $row) {
            $counts[(int)$row['attribute_set_id']] = (int)$row['n'];
        }
        return $counts;
    }

    public function getEntityTypeId(): int
    {
        if ($this->entityTypeId === null) {
            $this->entityTypeId = (int)$this->eavConfig->getEntityType(Product::ENTITY)->getId();
        }
        return $this->entityTypeId;
    }

    /**
     * @param \Magento\Eav\Model\Entity\Attribute\AbstractAttribute $attribute
     */
    private function labelOf($attribute, string $code): string
    {
        $label = (string)$attribute->getDefaultFrontendLabel();
        if ($label === '') {
            $label = (string)$attribute->getFrontendLabel();
        }
        return $label !== '' ? $label : $code;
    }

    /**
     * Option value => admin label, for attributes with a source model. Nested option groups are flattened.
     *
     * @param \Magento\Eav\Model\Entity\Attribute\AbstractAttribute $attribute
     * @return array<string, string>
     */
    private function optionsOf($attribute): array
    {
        $options = [];
        try {
            if (!$attribute->usesSource()) {
                return $options;
            }
            $source = $attribute->getSource();
            if (!$source) {
                return $options;
            }
            $this->collectOptions($source->getAllOptions(), $options);
        } catch (\Throwable $e) {
            // A broken custom source model must not stop scoring; the attribute simply has no option labels.
            return $options;
        }
        return $options;
    }

    /**
     * @param mixed $raw
     * @param array<string, string> $options
     */
    private function collectOptions($raw, array &$options): void
    {
        if (!is_array($raw)) {
            return;
        }
        foreach ($raw as $option) {
            if (!is_array($option) || !array_key_exists('value', $option)) {
                continue;
            }
            if (is_array($option['value'])) {
                $this->collectOptions($option['value'], $options);
                continue;
            }
            $value = (string)$option['value'];
            if ($value === '') {
                continue;
            }
            $options[$value] = (string)($option['label'] ?? $value);
        }
    }
}
