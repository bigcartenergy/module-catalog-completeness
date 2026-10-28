<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Test\Unit\Model\Rule;

use BigCartEnergy\CatalogCompleteness\Model\Rule\Pattern;
use PHPUnit\Framework\TestCase;

class PatternTest extends TestCase
{
    public function testCompilesAPlainBodyWithTheSlashDelimiter(): void
    {
        self::assertSame('/^\d{8}$/u', Pattern::compile('^\d{8}$'));
    }

    public function testPicksAnotherDelimiterWhenTheBodyUsesSlash(): void
    {
        self::assertSame('~^EN \d{3}/\d{4}$~u', Pattern::compile('^EN \d{3}/\d{4}$'));
    }

    public function testRejectsEmptyAndOverlongBodies(): void
    {
        self::assertNull(Pattern::compile(''));
        self::assertNull(Pattern::compile(str_repeat('a', Pattern::MAX_LENGTH + 1)));
        self::assertNotNull(Pattern::compile(str_repeat('a', Pattern::MAX_LENGTH)));
    }

    public function testRejectsABodyThatDoesNotCompile(): void
    {
        self::assertNull(Pattern::compile('(unclosed'));
        self::assertFalse(Pattern::isValid('[a-'));
    }

    public function testNeverAcceptsMerchantModifiers(): void
    {
        // The body is wrapped verbatim: a trailing "/i" becomes part of the body, not a modifier.
        $regex = Pattern::compile('abc/i');
        self::assertNotNull($regex);
        self::assertSame('~abc/i~u', $regex);
        self::assertFalse(Pattern::matches($regex, 'ABC'));
    }

    public function testMatchesReturnsFalseOnPcreError(): void
    {
        self::assertFalse(Pattern::matches('/not a regex', 'x'));
    }

    public function testMaxLengthFormIsRecognised(): void
    {
        self::assertSame(150, Pattern::maxLengthOf('^[\s\S]{1,150}$'));
        self::assertSame(80, Pattern::maxLengthOf('^.{1,80}$'));
        self::assertNull(Pattern::maxLengthOf('^\d{1,150}$'));
        self::assertSame('^[\s\S]{1,200}$', Pattern::maxLengthBody(200));
        self::assertSame(200, Pattern::maxLengthOf(Pattern::maxLengthBody(200)));
    }

    public function testFlagsAWholeDelimitedRegexPastedAsABody(): void
    {
        self::assertTrue(Pattern::looksDelimited('/^[A-Z]{3}$/i'));
        self::assertTrue(Pattern::looksDelimited('~^\d+$~'));
        self::assertTrue(Pattern::looksDelimited('#abc#u'));
        self::assertFalse(Pattern::looksDelimited('^[A-Z]{3}$'));
        self::assertFalse(Pattern::looksDelimited('^(\d{8}|\d{12}|\d{13}|\d{14})$'));
        self::assertFalse(Pattern::looksDelimited('\/catalog\/'));
        self::assertFalse(Pattern::looksDelimited('/'));
        self::assertFalse(Pattern::looksDelimited('a/b'));
    }


    public function testFlagsNestedRepetition(): void
    {
        self::assertTrue(Pattern::hasNestedQuantifier('(a+)+'));
        self::assertTrue(Pattern::hasNestedQuantifier('^(a*)*$'));
        self::assertTrue(Pattern::hasNestedQuantifier('(x+x+)+y'));
        self::assertTrue(Pattern::hasNestedQuantifier('(\d{2,})+'));
        self::assertTrue(Pattern::hasNestedQuantifier('((ab)+c)*'));
        self::assertTrue(Pattern::hasNestedQuantifier('(a|b+){3,}'));
        self::assertFalse(Pattern::hasNestedQuantifier('^(\d{8}|\d{12}|\d{13}|\d{14})$'));
        self::assertFalse(Pattern::hasNestedQuantifier('^[A-Z]{2,3}-\d+$'));
        self::assertFalse(Pattern::hasNestedQuantifier('(ab)+'));
        self::assertFalse(Pattern::hasNestedQuantifier('(a+)?'));
        self::assertFalse(Pattern::hasNestedQuantifier('(a+){1}'));
        self::assertFalse(Pattern::hasNestedQuantifier('[+*]+'));
        self::assertFalse(Pattern::hasNestedQuantifier('\(a+\)+'));
        self::assertFalse(Pattern::hasNestedQuantifier('^[\s\S]{1,150}$'));
    }

    public function testMatchRunsUnderItsOwnLimitsAndRestoresThem(): void
    {
        $before = ini_get('pcre.backtrack_limit');
        $regex = Pattern::compile('^(a|aa)+$');
        self::assertNotNull($regex);
        self::assertTrue(Pattern::matches($regex, 'aaaa'));
        self::assertFalse(Pattern::matches($regex, str_repeat('a', 40) . 'b'), 'a pathological run fails the rule instead of throwing');
        self::assertSame($before, ini_get('pcre.backtrack_limit'), 'the limit is restored after the match');
    }
}
