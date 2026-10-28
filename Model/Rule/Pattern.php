<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Model\Rule;

/**
 * Merchant patterns are PCRE bodies without delimiters or modifiers. The module picks a delimiter the body
 * does not use and adds the UTF-8 modifier only, so a pattern can never carry modifiers of its own. Bodies
 * that nest repetition (a quantified group whose content is itself quantified, such as (a+)+) are refused,
 * and every match runs under its own backtracking and recursion limits.
 */
final class Pattern
{
    public const MAX_LENGTH = 500;

    /**
     * PCRE limits applied around every match. Plain patterns on product text stay far below them; a
     * pathological pattern hits them and simply fails its rule.
     */
    public const BACKTRACK_LIMIT = 100000;
    public const RECURSION_LIMIT = 10000;

    private const DELIMITERS = ['/', '~', '#', '%', '@', '!', '+', '=', '|'];

    /**
     * Compile a body into a full regex, or null when it cannot be used.
     */
    public static function compile(string $body): ?string
    {
        if ($body === '' || Text::length($body) > self::MAX_LENGTH) {
            return null;
        }
        $delimiter = null;
        foreach (self::DELIMITERS as $candidate) {
            if (strpos($body, $candidate) === false) {
                $delimiter = $candidate;
                break;
            }
        }
        if ($delimiter === null) {
            return null;
        }
        $regex = $delimiter . $body . $delimiter . 'u';
        if (!self::compiles($regex)) {
            return null;
        }
        return $regex;
    }

    /**
     * Whether a body is a usable pattern.
     */
    public static function isValid(string $body): bool
    {
        return self::compile($body) !== null;
    }

    /**
     * Whether a body looks like a whole delimited regex pasted by mistake ("/.../i"): it opens and closes with
     * the same common delimiter, with nothing but modifier letters after the closing one. Such a body compiles,
     * but the anchors inside it can never match, so it is refused with a plain message instead.
     */
    public static function looksDelimited(string $body): bool
    {
        return (bool)preg_match('#^([/~\#%@|]).+\1[a-zA-Z]*$#s', $body);
    }

    /**
     * Whether a body nests repetition: a group that is quantified (+, *, {n,} or {n,m}) and contains an
     * unescaped quantifier itself, the shape behind catastrophic backtracking. A single quantifier on a
     * group, or quantifiers side by side, are fine.
     */
    public static function hasNestedQuantifier(string $body): bool
    {
        $length = strlen($body);
        $depth = 0;
        $quantifiedInside = [];
        $inClass = false;
        for ($i = 0; $i < $length; $i++) {
            $char = $body[$i];
            if ($char === '\\') {
                $i++;
                continue;
            }
            if ($inClass) {
                if ($char === ']') {
                    $inClass = false;
                }
                continue;
            }
            if ($char === '[') {
                $inClass = true;
                continue;
            }
            if ($char === '(') {
                $depth++;
                $quantifiedInside[$depth] = false;
                continue;
            }
            if ($char === ')') {
                $inner = $quantifiedInside[$depth] ?? false;
                unset($quantifiedInside[$depth]);
                $depth = max(0, $depth - 1);
                if ($inner && self::quantifierFollows($body, $i + 1)) {
                    return true;
                }
                if ($inner && $depth > 0) {
                    $quantifiedInside[$depth] = true;
                }
                continue;
            }
            if ($depth > 0 && self::isRepeatingQuantifierAt($body, $i)) {
                $quantifiedInside[$depth] = true;
            }
        }
        return false;
    }

    /**
     * Match a compiled regex against a subject under the module's PCRE limits. A PCRE error of any kind
     * counts as "no match", never as an exception.
     */
    public static function matches(string $regex, string $subject): bool
    {
        $previousBacktrack = ini_get('pcre.backtrack_limit');
        $previousRecursion = ini_get('pcre.recursion_limit');
        ini_set('pcre.backtrack_limit', (string)self::BACKTRACK_LIMIT);
        ini_set('pcre.recursion_limit', (string)self::RECURSION_LIMIT);
        try {
            // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
            $result = @preg_match($regex, $subject);
            if ($result !== 1 || preg_last_error() !== PREG_NO_ERROR) {
                return false;
            }
            return true;
        } catch (\Throwable $e) {
            return false;
        } finally {
            if ($previousBacktrack !== false) {
                ini_set('pcre.backtrack_limit', (string)$previousBacktrack);
            }
            if ($previousRecursion !== false) {
                ini_set('pcre.recursion_limit', (string)$previousRecursion);
            }
        }
    }

    /**
     * The N of a pure maximum-length body such as "^[\s\S]{1,150}$", or null when the body is anything else.
     */
    public static function maxLengthOf(string $body): ?int
    {
        if (preg_match('/^\^(?:\[\\\\s\\\\S\]|\.)\{1,(\d{1,6})\}\$$/', $body, $m)) {
            return (int)$m[1];
        }
        return null;
    }

    /**
     * Body of a maximum-length pattern for N characters, the inverse of maxLengthOf().
     */
    public static function maxLengthBody(int $max): string
    {
        return '^[\s\S]{1,' . max(1, $max) . '}$';
    }

    private static function compiles(string $regex): bool
    {
        try {
            // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
            $result = @preg_match($regex, '');
        } catch (\Throwable $e) {
            return false;
        }
        return $result !== false;
    }

    /**
     * Whether the character at $i starts a repeating quantifier: +, *, or {n,} / {n,m} with an open upper bound
     * or a bound above one. "?" and {1} repeat nothing.
     */
    private static function isRepeatingQuantifierAt(string $body, int $i): bool
    {
        $char = $body[$i];
        if ($char === '+' || $char === '*') {
            return true;
        }
        if ($char === '{' && preg_match('/\G\{(\d+)(?:,(\d*))?\}/', $body, $m, 0, $i)) {
            $min = (int)$m[1];
            if (!isset($m[2])) {
                return $min > 1;
            }
            return $m[2] === '' || (int)$m[2] > 1;
        }
        return false;
    }

    private static function quantifierFollows(string $body, int $i): bool
    {
        return $i < strlen($body) && self::isRepeatingQuantifierAt($body, $i);
    }
}
