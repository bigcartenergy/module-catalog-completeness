<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Model;

use BigCartEnergy\CatalogCompleteness\Model\Band\Thresholds;
use BigCartEnergy\CatalogCompleteness\Model\Rescore\CronExpression;
use Magento\Framework\App\Config\ScopeConfigInterface;

/**
 * Typed reader of the module settings. Every setting is read here and nowhere else.
 */
class Config
{
    public const XML_PATH_BRAND_ATTRIBUTE = 'bce_completeness/general/brand_attribute';
    public const XML_PATH_ENABLED_ONLY_DEFAULT = 'bce_completeness/general/enabled_only_default';
    public const XML_PATH_WORKLIST_PAGE_SIZE = 'bce_completeness/general/worklist_page_size';
    public const XML_PATH_CSV_ROW_CAP = 'bce_completeness/general/csv_row_cap';
    public const XML_PATH_GOOD_FROM = 'bce_completeness/thresholds/good_from';
    public const XML_PATH_NEEDS_WORK_FROM = 'bce_completeness/thresholds/needs_work_from';
    public const XML_PATH_RESCORE_ENABLED = 'bce_completeness/rescore/enabled';
    public const XML_PATH_RESCORE_SCHEDULE = 'bce_completeness/rescore/schedule';
    public const XML_PATH_RESCORE_MODE = 'bce_completeness/rescore/mode';
    public const XML_PATH_RESCORE_BATCH_SIZE = 'bce_completeness/rescore/batch_size';

    public const PAGE_SIZES = [20, 50, 100, 200];
    public const DEFAULT_PAGE_SIZE = 20;
    public const DEFAULT_CSV_ROW_CAP = 50000;
    public const DEFAULT_BRAND_ATTRIBUTE = 'manufacturer';
    public const RESCORE_MODE_FULL = 'full';
    public const RESCORE_MODE_CHANGED = 'changed';
    public const DEFAULT_RESCORE_SCHEDULE = '0 */6 * * *';
    public const DEFAULT_RESCORE_BATCH_SIZE = 500;
    public const RESCORE_BATCH_SIZE_MIN = 50;
    public const RESCORE_BATCH_SIZE_MAX = 5000;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var Thresholds|null
     */
    private $thresholds;

    public function __construct(ScopeConfigInterface $scopeConfig)
    {
        $this->scopeConfig = $scopeConfig;
    }

    public function getBrandAttributeCode(): string
    {
        $code = trim((string)$this->scopeConfig->getValue(self::XML_PATH_BRAND_ATTRIBUTE));
        if ($code === '' || !preg_match('/^[a-z][a-z0-9_]{0,254}$/', $code)) {
            return self::DEFAULT_BRAND_ATTRIBUTE;
        }
        return $code;
    }

    public function isEnabledOnlyDefault(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED_ONLY_DEFAULT);
    }

    public function getWorkListPageSize(): int
    {
        $size = (int)$this->scopeConfig->getValue(self::XML_PATH_WORKLIST_PAGE_SIZE);
        return in_array($size, self::PAGE_SIZES, true) ? $size : self::DEFAULT_PAGE_SIZE;
    }

    public function getCsvRowCap(): int
    {
        $cap = (int)$this->scopeConfig->getValue(self::XML_PATH_CSV_ROW_CAP);
        return $cap > 0 ? $cap : self::DEFAULT_CSV_ROW_CAP;
    }

    public function isRescoreEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_RESCORE_ENABLED);
    }

    /**
     * The periodic rescore's schedule as Magento's cron reads it through the job's config path, with its
     * fields joined by single spaces; the default when the stored value is empty or not an accepted expression.
     */
    public function getRescoreSchedule(): string
    {
        $stored = (string)$this->scopeConfig->getValue(self::XML_PATH_RESCORE_SCHEDULE);
        $expression = CronExpression::normalize($stored);
        return CronExpression::isValid($expression) ? $expression : self::DEFAULT_RESCORE_SCHEDULE;
    }

    /**
     * Every product (full), or only the products changed since the last run by updated_at (changed).
     */
    public function getRescoreMode(): string
    {
        $mode = trim((string)$this->scopeConfig->getValue(self::XML_PATH_RESCORE_MODE));
        return $mode === self::RESCORE_MODE_CHANGED ? self::RESCORE_MODE_CHANGED : self::RESCORE_MODE_FULL;
    }

    /**
     * Products per batch, within the accepted range; the default when the stored value is not a whole number.
     */
    public function getRescoreBatchSize(): int
    {
        $raw = trim((string)$this->scopeConfig->getValue(self::XML_PATH_RESCORE_BATCH_SIZE));
        if (!preg_match('/^[0-9]{1,9}$/', $raw)) {
            return self::DEFAULT_RESCORE_BATCH_SIZE;
        }
        return max(self::RESCORE_BATCH_SIZE_MIN, min(self::RESCORE_BATCH_SIZE_MAX, (int)$raw));
    }

    public function getThresholds(): Thresholds
    {
        if ($this->thresholds === null) {
            $this->thresholds = Thresholds::fromSettings(
                (int)$this->scopeConfig->getValue(self::XML_PATH_GOOD_FROM),
                (int)$this->scopeConfig->getValue(self::XML_PATH_NEEDS_WORK_FROM)
            );
        }
        return $this->thresholds;
    }
}
