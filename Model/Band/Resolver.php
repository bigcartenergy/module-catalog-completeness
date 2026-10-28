<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Model\Band;

use BigCartEnergy\CatalogCompleteness\Model\Config;

/**
 * Bands are never stored: they derive from a score and the thresholds setting at read time.
 */
class Resolver
{
    public const COMPLETE = 'complete';
    public const GOOD = 'good';
    public const NEEDS_WORK = 'needs_work';
    public const POOR = 'poor';

    public const CODES = [self::COMPLETE, self::GOOD, self::NEEDS_WORK, self::POOR];

    /**
     * @var Config
     */
    private $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * Band code of a score, null when the product has no score.
     */
    public function resolve(?int $score): ?string
    {
        return self::resolveWith($score, $this->config->getThresholds());
    }

    public static function resolveWith(?int $score, Thresholds $thresholds): ?string
    {
        if ($score === null) {
            return null;
        }
        if ($score >= 100) {
            return self::COMPLETE;
        }
        if ($score >= $thresholds->getGoodFrom()) {
            return self::GOOD;
        }
        if ($score >= $thresholds->getNeedsWorkFrom()) {
            return self::NEEDS_WORK;
        }
        return self::POOR;
    }

    /**
     * Human label of a band code.
     */
    public function getLabel(?string $code): string
    {
        switch ($code) {
            case self::COMPLETE:
                return (string)__('Complete');
            case self::GOOD:
                return (string)__('Good');
            case self::NEEDS_WORK:
                return (string)__('Needs work');
            case self::POOR:
                return (string)__('Poor');
            default:
                return '';
        }
    }

    /**
     * Inclusive score range of a band under the current thresholds: [min, max].
     *
     * @return int[]
     */
    public function getRange(string $code): array
    {
        $t = $this->config->getThresholds();
        switch ($code) {
            case self::COMPLETE:
                return [100, 100];
            case self::GOOD:
                return [$t->getGoodFrom(), 99];
            case self::NEEDS_WORK:
                return [$t->getNeedsWorkFrom(), $t->getGoodFrom() - 1];
            case self::POOR:
                return [0, $t->getNeedsWorkFrom() - 1];
            default:
                return [0, 100];
        }
    }

    /**
     * SQL CASE expression that yields the band code for a score column.
     */
    public function getSqlCase(string $scoreColumn): string
    {
        $t = $this->config->getThresholds();
        return sprintf(
            "CASE WHEN %1\$s IS NULL THEN NULL WHEN %1\$s >= 100 THEN '%2\$s' WHEN %1\$s >= %3\$d THEN '%4\$s'"
            . " WHEN %1\$s >= %5\$d THEN '%6\$s' ELSE '%7\$s' END",
            $scoreColumn,
            self::COMPLETE,
            $t->getGoodFrom(),
            self::GOOD,
            $t->getNeedsWorkFrom(),
            self::NEEDS_WORK,
            self::POOR
        );
    }

    /**
     * The four bands in display order with their labels and current ranges.
     *
     * @return array<string, array{label: string, min: int, max: int}>
     */
    public function getAll(): array
    {
        $all = [];
        foreach (self::CODES as $code) {
            $range = $this->getRange($code);
            $all[$code] = ['label' => $this->getLabel($code), 'min' => $range[0], 'max' => $range[1]];
        }
        return $all;
    }
}
