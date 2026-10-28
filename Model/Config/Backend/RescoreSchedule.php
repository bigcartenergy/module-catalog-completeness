<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Model\Config\Backend;

use BigCartEnergy\CatalogCompleteness\Model\Config;
use BigCartEnergy\CatalogCompleteness\Model\Rescore\CronExpression;
use Magento\Framework\App\Config\Value;
use Magento\Framework\Exception\LocalizedException;

/**
 * The periodic rescore's schedule, which Magento's cron reads as stored. It saves with its fields joined by
 * single spaces and falls back to the default when left empty. Anything else is refused, so a saved
 * schedule can never stop Magento's cron from planning the module's cron group.
 */
class RescoreSchedule extends Value
{
    /**
     * @inheritDoc
     */
    public function beforeSave()
    {
        $this->setValue(self::toSchedule($this->getValue()));
        return parent::beforeSave();
    }

    /**
     * @param mixed $raw
     * @throws LocalizedException
     */
    public static function toSchedule($raw): string
    {
        $expression = is_scalar($raw) || $raw === null ? CronExpression::normalize((string)$raw) : null;
        if ($expression === '') {
            return Config::DEFAULT_RESCORE_SCHEDULE;
        }
        if ($expression === null || !CronExpression::isValid($expression)) {
            throw new LocalizedException(__('The schedule is not a cron expression the periodic rescore accepts. Use five fields (minute, hour, day of month, month, day of week), each *, a number, a range such as 1-5, a list such as 0,30 or a step such as */15. The default, 0 */6 * * *, runs every six hours.'));
        }
        return $expression;
    }
}
