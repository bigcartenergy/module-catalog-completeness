<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Model\Rescore;

/**
 * The schedules the periodic rescore accepts: five fields (minute, hour, day of month, month, day of week),
 * each a comma list of items. An item is the star, a number, a range a-b, or the star with a step (star,
 * slash, a number from 1 up to the field's highest value). Month and day names (jan to dec, sun to sat)
 * count as numbers in their own field. Every number is checked against its field's range. This is a strict
 * subset of what Magento's cron parses, chosen so that every Magento line from 2.3.0 to 2.4.9 reads it the
 * same way: a step is allowed on the star only, because 2.4 counts a step from the start of a range and 2.3
 * does not.
 */
final class CronExpression
{
    public const MAX_LENGTH = 200;

    /**
     * Lowest and highest value of each field, in field order.
     */
    private const RANGES = [[0, 59], [0, 23], [1, 31], [1, 12], [0, 6]];

    private const MONTHS = [
        'jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'may' => 5, 'jun' => 6,
        'jul' => 7, 'aug' => 8, 'sep' => 9, 'oct' => 10, 'nov' => 11, 'dec' => 12,
    ];

    private const DAYS = ['sun' => 0, 'mon' => 1, 'tue' => 2, 'wed' => 3, 'thu' => 4, 'fri' => 5, 'sat' => 6];

    /**
     * Trims the expression and joins its fields with single spaces.
     */
    public static function normalize(string $expression): string
    {
        $fields = preg_split('/\s+/', trim($expression), -1, PREG_SPLIT_NO_EMPTY);
        return is_array($fields) ? implode(' ', $fields) : '';
    }

    public static function isValid(string $expression): bool
    {
        if (strlen($expression) > self::MAX_LENGTH) {
            return false;
        }
        $fields = preg_split('/\s+/', trim($expression), -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($fields) || count($fields) !== 5) {
            return false;
        }
        foreach ($fields as $position => $field) {
            foreach (explode(',', $field) as $item) {
                if (!self::isValidItem($item, $position)) {
                    return false;
                }
            }
        }
        return true;
    }

    private static function isValidItem(string $item, int $position): bool
    {
        list($min, $max) = self::RANGES[$position];
        if ($item === '*') {
            return true;
        }
        if (preg_match('/^\*\/([0-9]{1,2})$/', $item, $step)) {
            return (int)$step[1] >= 1 && (int)$step[1] <= $max;
        }
        if (!preg_match('/^([0-9A-Za-z]{1,3})(?:-([0-9A-Za-z]{1,3}))?$/', $item, $bounds)) {
            return false;
        }
        $from = self::number($bounds[1], $position);
        $to = isset($bounds[2]) ? self::number($bounds[2], $position) : $from;
        return $from !== null && $to !== null && $from >= $min && $to <= $max && $from <= $to;
    }

    private static function number(string $token, int $position): ?int
    {
        if (preg_match('/^[0-9]{1,2}$/', $token)) {
            return (int)$token;
        }
        $name = strtolower($token);
        if ($position === 3 && isset(self::MONTHS[$name])) {
            return self::MONTHS[$name];
        }
        if ($position === 4 && isset(self::DAYS[$name])) {
            return self::DAYS[$name];
        }
        return null;
    }
}
