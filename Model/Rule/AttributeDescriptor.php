<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Model\Rule;

use BigCartEnergy\CatalogCompleteness\Api\Data\RuleInterface;

/**
 * What the evaluator needs to know about an attribute: its storage type, input type and options.
 * Plain value object so the evaluator can be unit tested without the EAV layer.
 */
final class AttributeDescriptor
{
    public const INPUT_GALLERY = 'gallery';
    public const INPUT_STOCK_STATUS = 'stock_status';
    public const STOCK_STATUS_CODE = 'quantity_and_stock_status';

    /**
     * @var string
     */
    private $code;

    /**
     * @var int|null
     */
    private $id;

    /**
     * @var string
     */
    private $label;

    /**
     * @var string
     */
    private $backendType;

    /**
     * @var string
     */
    private $frontendInput;

    /**
     * @var array<string, string> option value => option label
     */
    private $options;

    /**
     * @param array<string, string> $options
     */
    public function __construct(
        string $code,
        ?int $id,
        string $label,
        string $backendType,
        string $frontendInput,
        array $options = []
    ) {
        $this->code = $code;
        $this->id = $id;
        $this->label = $label;
        $this->backendType = $backendType;
        $this->frontendInput = $frontendInput;
        $this->options = [];
        foreach ($options as $value => $optionLabel) {
            $this->options[(string)$value] = (string)$optionLabel;
        }
    }

    public static function gallery(string $label = 'Images'): self
    {
        return new self(RuleInterface::MEDIA_GALLERY, null, $label, self::INPUT_GALLERY, self::INPUT_GALLERY);
    }

    public static function stockStatus(string $label = 'Stock status'): self
    {
        return new self(self::STOCK_STATUS_CODE, null, $label, 'int', self::INPUT_STOCK_STATUS);
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getBackendType(): string
    {
        return $this->backendType;
    }

    public function getFrontendInput(): string
    {
        return $this->frontendInput;
    }

    /**
     * @return array<string, string>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    public function isGallery(): bool
    {
        return $this->frontendInput === self::INPUT_GALLERY;
    }

    public function isStockStatus(): bool
    {
        return $this->frontendInput === self::INPUT_STOCK_STATUS;
    }

    /**
     * Pseudo attributes live outside the EAV value tables and belong to every attribute set.
     */
    public function isPseudo(): bool
    {
        return $this->isGallery() || $this->isStockStatus();
    }

    public function isRichText(): bool
    {
        return in_array($this->frontendInput, ['textarea', 'texteditor'], true);
    }

    public function isMultiselect(): bool
    {
        return $this->frontendInput === 'multiselect';
    }

    public function isSelect(): bool
    {
        return in_array($this->frontendInput, ['select', 'boolean'], true);
    }

    public function isNumeric(): bool
    {
        return in_array($this->backendType, ['decimal', 'int'], true);
    }

    public function isStatic(): bool
    {
        return $this->backendType === 'static';
    }
}
