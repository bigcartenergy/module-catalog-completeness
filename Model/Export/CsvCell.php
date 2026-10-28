<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Model\Export;

/**
 * Formula-injection guard for CSV cells: a value that a spreadsheet would read as a formula gets a leading
 * apostrophe, so it opens as text. The check looks past leading whitespace (spaces, line breaks, no-break
 * spaces, a byte order mark), and a tab or carriage return anywhere in that leading run is itself a trigger.
 */
final class CsvCell
{
    /**
     * Optional leading whitespace, then a formula trigger (=, +, -, @) or a tab or carriage return.
     */
    private const TRIGGER_PATTERN = '/^[ \n\x0B\f\x{00A0}\x{FEFF}\t\r]*[\t\r=+\-@]/u';

    /**
     * @param mixed $value
     */
    public static function sanitize($value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_array($value)) {
            $value = implode(' | ', array_map([self::class, 'sanitize'], $value));
        }
        $text = (string)$value;
        if ($text === '') {
            return '';
        }
        // A hit gets the prefix, and so does a check that fails on invalid UTF-8 under the u modifier: fail safe.
        if (preg_match(self::TRIGGER_PATTERN, $text) !== 0) {
            return "'" . $text;
        }
        return $text;
    }
}
