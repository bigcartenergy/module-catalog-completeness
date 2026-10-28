<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Model\Channel;

use BigCartEnergy\CatalogCompleteness\Api\Data\RuleInterface;
use BigCartEnergy\CatalogCompleteness\Api\Data\RuleSetInterface;

/**
 * The channels a profile can target and the role vocabulary of each. Static by design: the role column of a
 * rule stores one of these codes (or "custom"), and the editor's Role select follows the chosen channel.
 */
class ChannelRegistry
{
    /**
     * Role code => label, for every role any channel uses.
     */
    private const ROLE_LABELS = [
        'title' => 'Title',
        'description' => 'Description',
        'bullet_points' => 'Bullet points',
        'image' => 'Image',
        'availability' => 'Availability',
        'price' => 'Price',
        'brand' => 'Brand',
        'gtin' => 'GTIN',
        'mpn' => 'MPN',
        'product_id' => 'Product ID',
        'condition' => 'Condition',
        'google_product_category' => 'Google product category',
        'category' => 'Product category',
        'shipping_weight' => 'Shipping weight',
        'product_name' => 'Product name',
        RuleInterface::ROLE_CUSTOM => 'Custom',
    ];

    private const GENERIC_ROLES = [
        'title',
        'description',
        'image',
        'availability',
        'price',
        'brand',
        'gtin',
        'mpn',
        'condition',
        'google_product_category',
        'shipping_weight',
    ];

    /**
     * Channel code => [label, role codes in display order].
     */
    private const CHANNELS = [
        RuleSetInterface::CHANNEL_GOOGLE => [
            'Google Merchant Center',
            self::GENERIC_ROLES,
        ],
        RuleSetInterface::CHANNEL_MICROSOFT => [
            'Microsoft Merchant Center',
            self::GENERIC_ROLES,
        ],
        RuleSetInterface::CHANNEL_META => [
            'Meta (Facebook and Instagram Shops)',
            self::GENERIC_ROLES,
        ],
        RuleSetInterface::CHANNEL_PINTEREST => [
            'Pinterest',
            self::GENERIC_ROLES,
        ],
        RuleSetInterface::CHANNEL_TIKTOK => [
            'TikTok Shop',
            ['title', 'description', 'image', 'brand', 'price', 'category'],
        ],
        RuleSetInterface::CHANNEL_AMAZON => [
            'Amazon',
            ['title', 'bullet_points', 'description', 'image', 'brand', 'product_id', 'price'],
        ],
        RuleSetInterface::CHANNEL_EBAY => [
            'eBay',
            ['title', 'description', 'image', 'brand', 'product_id', 'mpn', 'condition', 'price'],
        ],
        RuleSetInterface::CHANNEL_WALMART => [
            'Walmart Marketplace',
            ['product_name', 'description', 'image', 'brand', 'product_id', 'shipping_weight', 'price'],
        ],
        RuleSetInterface::CHANNEL_CUSTOM => [
            'Custom',
            self::GENERIC_ROLES,
        ],
    ];

    /**
     * @return string[]
     */
    public function getChannelCodes(): array
    {
        return array_keys(self::CHANNELS);
    }

    public function isChannel(?string $code): bool
    {
        return $code !== null && isset(self::CHANNELS[$code]);
    }

    public function getChannelLabel(?string $code): string
    {
        if ($code === null || !isset(self::CHANNELS[$code])) {
            return '';
        }
        return (string)__(self::CHANNELS[$code][0]);
    }

    /**
     * Channel code => label, in display order.
     *
     * @return array<string, string>
     */
    public function getChannelLabels(): array
    {
        $labels = [];
        foreach (self::CHANNELS as $code => $definition) {
            $labels[$code] = (string)__($definition[0]);
        }
        return $labels;
    }

    /**
     * Role code => label for a channel, "Custom" always last. Unknown or null channel: the generic roles.
     *
     * @return array<string, string>
     */
    public function getRoles(?string $channelCode): array
    {
        $codes = isset(self::CHANNELS[$channelCode]) ? self::CHANNELS[$channelCode][1] : self::GENERIC_ROLES;
        $roles = [];
        foreach ($codes as $code) {
            $roles[$code] = (string)__(self::ROLE_LABELS[$code]);
        }
        $roles[RuleInterface::ROLE_CUSTOM] = (string)__(self::ROLE_LABELS[RuleInterface::ROLE_CUSTOM]);
        return $roles;
    }

    /**
     * Every role code any channel may store.
     *
     * @return string[]
     */
    public function getAllRoleCodes(): array
    {
        return array_keys(self::ROLE_LABELS);
    }

    public function getRoleLabel(?string $roleCode): string
    {
        if ($roleCode === null || !isset(self::ROLE_LABELS[$roleCode])) {
            return '';
        }
        return (string)__(self::ROLE_LABELS[$roleCode]);
    }
}
