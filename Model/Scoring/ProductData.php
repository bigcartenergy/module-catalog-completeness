<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Model\Scoring;

use BigCartEnergy\CatalogCompleteness\Api\Data\RuleInterface;
use BigCartEnergy\CatalogCompleteness\Model\Rule\AttributeDescriptor;

/**
 * The admin-scope values of one product that the rules need, plus the two pseudo attributes.
 */
final class ProductData
{
    /**
     * @var int
     */
    private $id;

    /**
     * @var string
     */
    private $sku;

    /**
     * @var string
     */
    private $typeId;

    /**
     * @var int
     */
    private $attributeSetId;

    /**
     * @var array<string, mixed>
     */
    private $values;

    /**
     * @var int
     */
    private $imageCount;

    /**
     * @var int|null
     */
    private $stockStatus;

    /**
     * @param array<string, mixed> $values attribute code => raw stored value
     */
    public function __construct(
        int $id,
        string $sku,
        string $typeId,
        int $attributeSetId,
        array $values = [],
        int $imageCount = 0,
        ?int $stockStatus = null
    ) {
        $this->id = $id;
        $this->sku = $sku;
        $this->typeId = $typeId;
        $this->attributeSetId = $attributeSetId;
        $this->values = $values;
        $this->imageCount = $imageCount;
        $this->stockStatus = $stockStatus;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getSku(): string
    {
        return $this->sku;
    }

    public function getTypeId(): string
    {
        return $this->typeId;
    }

    public function getAttributeSetId(): int
    {
        return $this->attributeSetId;
    }

    public function getImageCount(): int
    {
        return $this->imageCount;
    }

    public function getStockStatus(): ?int
    {
        return $this->stockStatus;
    }

    /**
     * @param mixed $value
     */
    public function setValue(string $code, $value): void
    {
        $this->values[$code] = $value;
    }

    public function setImageCount(int $count): void
    {
        $this->imageCount = $count;
    }

    public function setStockStatus(?int $status): void
    {
        $this->stockStatus = $status;
    }

    /**
     * The value a rule on this attribute code is evaluated against.
     *
     * @return mixed
     */
    public function getValue(string $code)
    {
        if ($code === RuleInterface::MEDIA_GALLERY) {
            return $this->imageCount;
        }
        if ($code === AttributeDescriptor::STOCK_STATUS_CODE) {
            return $this->stockStatus;
        }
        if ($code === 'sku') {
            return $this->sku;
        }
        return $this->values[$code] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function getValues(): array
    {
        return $this->values;
    }
}
