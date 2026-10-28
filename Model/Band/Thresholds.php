<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Model\Band;

/**
 * The two configurable band thresholds, sanitised so that Poor < Needs work < Good < Complete always holds.
 */
final class Thresholds
{
    public const DEFAULT_GOOD_FROM = 80;
    public const DEFAULT_NEEDS_WORK_FROM = 50;

    /**
     * @var int
     */
    private $goodFrom;

    /**
     * @var int
     */
    private $needsWorkFrom;

    public function __construct(int $goodFrom, int $needsWorkFrom)
    {
        $this->goodFrom = $goodFrom;
        $this->needsWorkFrom = $needsWorkFrom;
    }

    /**
     * Build from raw settings; anything inconsistent falls back to the defaults.
     */
    public static function fromSettings(int $goodFrom, int $needsWorkFrom): self
    {
        if ($goodFrom < 2 || $goodFrom > 99) {
            $goodFrom = self::DEFAULT_GOOD_FROM;
        }
        if ($needsWorkFrom < 1 || $needsWorkFrom >= $goodFrom) {
            $needsWorkFrom = min(self::DEFAULT_NEEDS_WORK_FROM, $goodFrom - 1);
        }
        return new self($goodFrom, $needsWorkFrom);
    }

    public function getGoodFrom(): int
    {
        return $this->goodFrom;
    }

    public function getNeedsWorkFrom(): int
    {
        return $this->needsWorkFrom;
    }
}
