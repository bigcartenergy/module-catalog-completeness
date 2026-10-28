<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Model\Rule;

/**
 * Turns stored rich text into the plain text a merchant would count: entities decoded, tags removed,
 * non-breaking spaces and runs of whitespace collapsed, ends trimmed.
 */
final class Text
{
    public static function plain(string $html): string
    {
        // Block-level boundaries become spaces so "<p>a</p><p>b</p>" does not read as "ab".
        $text = preg_replace('/<\s*\/?\s*(br|p|div|li|tr|td|th|h[1-6])\b[^>]*>/i', ' ', $html) ?? $html;
        // Tags go first; entities are decoded afterwards so an escaped "&lt;" stays text.
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace(["\xC2\xA0", "\xE2\x80\xAF"], ' ', $text);
        $collapsed = preg_replace('/\s+/u', ' ', $text);
        if ($collapsed === null) {
            // Invalid UTF-8: fall back to a byte-wise collapse rather than failing the rule by accident.
            $collapsed = preg_replace('/\s+/', ' ', $text) ?? $text;
        }
        return trim($collapsed);
    }

    /**
     * Length in characters, tolerant of invalid UTF-8.
     */
    public static function length(string $text): int
    {
        $length = mb_strlen($text, 'UTF-8');
        return $length === false ? strlen($text) : $length;
    }
}
