<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Model\Dashboard;

use BigCartEnergy\CatalogCompleteness\Model\Band\Resolver as BandResolver;

/**
 * The dashboard's filter state, carried as query parameters and applied to everything below the filter bar.
 */
final class Filter
{
    public const PARAM_SCOPE = 'scope';
    public const PARAM_ATTRIBUTE_SET = 'attribute_set';
    public const PARAM_CATEGORY = 'category';
    public const PARAM_BRAND = 'brand';
    public const PARAM_TYPE = 'type';
    public const PARAM_BAND = 'band';
    public const PARAM_ENABLED = 'enabled';
    public const PARAM_TILE = 'tile';
    public const PARAM_RULE = 'rule';
    public const PARAM_COVERAGE = 'coverage';

    public const PARAMS = [
        self::PARAM_SCOPE,
        self::PARAM_ATTRIBUTE_SET,
        self::PARAM_CATEGORY,
        self::PARAM_BRAND,
        self::PARAM_TYPE,
        self::PARAM_BAND,
        self::PARAM_ENABLED,
        self::PARAM_TILE,
        self::PARAM_RULE,
        self::PARAM_COVERAGE,
    ];

    /**
     * The coverage value for the products of the scope that no rule covers, so they carry no score.
     */
    public const COVERAGE_NONE = 'none';

    public const TILE_BELOW_AVERAGE = 'below_average';
    public const TILE_ALL = 'all';
    public const TILE_COMPLETE = 'complete';
    public const TILE_NEEDS_WORK_OR_POOR = 'needs_work_or_poor';
    public const TILE_FAILING = 'failing';

    public const TILES = [
        self::TILE_BELOW_AVERAGE,
        self::TILE_ALL,
        self::TILE_COMPLETE,
        self::TILE_NEEDS_WORK_OR_POOR,
        self::TILE_FAILING,
    ];

    /**
     * The brand value "no brand set".
     */
    public const BRAND_NONE = '-';

    private const HEX_PREFIX = 'h_';

    /**
     * @var int|null rule set id, null for "all attribute set rules" (the primary scores)
     */
    private $rulesetId;

    /**
     * @var int|null
     */
    private $attributeSetId;

    /**
     * @var int|null
     */
    private $categoryId;

    /**
     * @var string|null raw brand value (option id or text), "-" for products without a brand
     */
    private $brand;

    /**
     * @var string|null
     */
    private $typeId;

    /**
     * @var string|null
     */
    private $band;

    /**
     * @var bool
     */
    private $enabledOnly;

    /**
     * @var string|null
     */
    private $tile;

    /**
     * @var int|null
     */
    private $ruleId;

    /**
     * @var string|null "none" for the products with no rules assigned, null for the scored products
     */
    private $coverage;

    public function __construct(
        ?int $rulesetId,
        ?int $attributeSetId,
        ?int $categoryId,
        ?string $brand,
        ?string $typeId,
        ?string $band,
        bool $enabledOnly,
        ?string $tile,
        ?int $ruleId,
        ?string $coverage = null
    ) {
        $this->rulesetId = $rulesetId;
        $this->attributeSetId = $attributeSetId;
        $this->categoryId = $categoryId;
        $this->brand = $brand;
        $this->typeId = $typeId;
        $this->band = $band;
        $this->enabledOnly = $enabledOnly;
        $this->tile = $tile;
        $this->ruleId = $ruleId;
        $this->coverage = $coverage === self::COVERAGE_NONE ? self::COVERAGE_NONE : null;
    }

    /**
     * Build from request parameters; anything unreadable is ignored.
     *
     * @param array<string, mixed> $params
     */
    public static function fromParams(array $params, bool $enabledOnlyDefault): self
    {
        $enabled = $params[self::PARAM_ENABLED] ?? null;
        if ($enabled === 'yes' || $enabled === '1' || $enabled === 1 || $enabled === true) {
            $enabledOnly = true;
        } elseif ($enabled === 'no' || $enabled === '0' || $enabled === 0 || $enabled === false) {
            $enabledOnly = false;
        } else {
            $enabledOnly = $enabledOnlyDefault;
        }
        $band = self::stringParam($params, self::PARAM_BAND);
        if ($band !== null && !in_array($band, BandResolver::CODES, true)) {
            $band = null;
        }
        $tile = self::stringParam($params, self::PARAM_TILE);
        if ($tile !== null && !in_array($tile, self::TILES, true)) {
            $tile = null;
        }
        $type = self::stringParam($params, self::PARAM_TYPE);
        if ($type !== null && !preg_match('/^[a-z0-9_]{1,32}$/i', $type)) {
            $type = null;
        }
        $rule = self::intParam($params, self::PARAM_RULE);
        $coverage = self::stringParam($params, self::PARAM_COVERAGE) === self::COVERAGE_NONE
            ? self::COVERAGE_NONE
            : null;
        if ($coverage !== null) {
            // A product with no rules assigned has no score, so the band, the tiles and the failing rule do not apply.
            $band = null;
            $tile = null;
            $rule = null;
        }
        return new self(
            self::intParam($params, self::PARAM_SCOPE),
            self::intParam($params, self::PARAM_ATTRIBUTE_SET),
            self::intParam($params, self::PARAM_CATEGORY),
            self::decodeBrand(self::stringParam($params, self::PARAM_BRAND)),
            $type,
            $band,
            $enabledOnly,
            $tile,
            $rule,
            $coverage
        );
    }

    /**
     * The filter as query parameters; the brand is encoded so it is safe in a URL path.
     *
     * @return array<string, string>
     */
    public function toParams(): array
    {
        $params = [];
        if ($this->rulesetId !== null) {
            $params[self::PARAM_SCOPE] = (string)$this->rulesetId;
        }
        if ($this->attributeSetId !== null) {
            $params[self::PARAM_ATTRIBUTE_SET] = (string)$this->attributeSetId;
        }
        if ($this->categoryId !== null) {
            $params[self::PARAM_CATEGORY] = (string)$this->categoryId;
        }
        if ($this->brand !== null) {
            $params[self::PARAM_BRAND] = self::encodeBrand($this->brand);
        }
        if ($this->typeId !== null) {
            $params[self::PARAM_TYPE] = $this->typeId;
        }
        if ($this->band !== null) {
            $params[self::PARAM_BAND] = $this->band;
        }
        $params[self::PARAM_ENABLED] = $this->enabledOnly ? 'yes' : 'no';
        if ($this->tile !== null) {
            $params[self::PARAM_TILE] = $this->tile;
        }
        if ($this->ruleId !== null) {
            $params[self::PARAM_RULE] = (string)$this->ruleId;
        }
        if ($this->coverage !== null) {
            $params[self::PARAM_COVERAGE] = $this->coverage;
        }
        return $params;
    }

    /**
     * A copy with one parameter changed (null removes it).
     *
     * @param mixed $value
     */
    public function with(string $param, $value): self
    {
        $params = $this->toParams();
        if ($value === null) {
            unset($params[$param]);
        } else {
            $params[$param] = $param === self::PARAM_BRAND ? self::encodeBrand((string)$value) : (string)$value;
        }
        return self::fromParams($params, $this->enabledOnly);
    }

    /**
     * A copy without the tile shortcut and the failing-rule filter.
     */
    public function withoutTile(): self
    {
        return $this->with(self::PARAM_TILE, null)->with(self::PARAM_RULE, null);
    }

    /**
     * A copy with only the scope, the enabled switch and the coverage kept.
     */
    public function cleared(): self
    {
        return new self(
            $this->rulesetId,
            null,
            null,
            null,
            null,
            null,
            $this->enabledOnly,
            null,
            null,
            $this->coverage
        );
    }

    public function getRulesetId(): ?int
    {
        return $this->rulesetId;
    }

    public function getAttributeSetId(): ?int
    {
        return $this->attributeSetId;
    }

    public function getCategoryId(): ?int
    {
        return $this->categoryId;
    }

    public function getBrand(): ?string
    {
        return $this->brand;
    }

    public function getTypeId(): ?string
    {
        return $this->typeId;
    }

    public function getBand(): ?string
    {
        return $this->band;
    }

    public function isEnabledOnly(): bool
    {
        return $this->enabledOnly;
    }

    public function getTile(): ?string
    {
        return $this->tile;
    }

    public function getRuleId(): ?int
    {
        return $this->ruleId;
    }

    public function getCoverage(): ?string
    {
        return $this->coverage;
    }

    /**
     * Whether the filter asks for the products of the scope with no rules assigned instead of the scored ones.
     */
    public function isUncoveredOnly(): bool
    {
        return $this->coverage === self::COVERAGE_NONE;
    }

    /**
     * Whether any filter besides the scope and the enabled switch is active.
     */
    public function hasActiveFilters(): bool
    {
        return $this->attributeSetId !== null
            || $this->categoryId !== null
            || $this->brand !== null
            || $this->typeId !== null
            || $this->band !== null
            || $this->tile !== null
            || $this->ruleId !== null
            || $this->coverage !== null;
    }

    public static function encodeBrand(string $brand): string
    {
        if ($brand === '' || preg_match('/^[A-Za-z0-9_.-]+$/', $brand)) {
            return $brand;
        }
        return self::HEX_PREFIX . bin2hex($brand);
    }

    public static function decodeBrand(?string $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        if (strncmp($raw, self::HEX_PREFIX, strlen(self::HEX_PREFIX)) === 0) {
            $hex = substr($raw, strlen(self::HEX_PREFIX));
            if ($hex === '' || !ctype_xdigit($hex) || strlen($hex) % 2 !== 0) {
                return null;
            }
            $decoded = hex2bin($hex);
            return $decoded === false || $decoded === '' ? null : $decoded;
        }
        return $raw;
    }

    /**
     * @param array<string, mixed> $params
     */
    private static function intParam(array $params, string $name): ?int
    {
        if (!isset($params[$name]) || is_array($params[$name])) {
            return null;
        }
        $value = trim((string)$params[$name]);
        if ($value === '' || !preg_match('/^\d{1,10}$/', $value) || (int)$value <= 0) {
            return null;
        }
        return (int)$value;
    }

    /**
     * @param array<string, mixed> $params
     */
    private static function stringParam(array $params, string $name): ?string
    {
        if (!isset($params[$name]) || is_array($params[$name])) {
            return null;
        }
        $value = trim((string)$params[$name]);
        if ($value === '' || strlen($value) > 512) {
            return null;
        }
        return $value;
    }
}
