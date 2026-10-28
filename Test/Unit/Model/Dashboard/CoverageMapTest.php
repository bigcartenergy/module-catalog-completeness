<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Test\Unit\Model\Dashboard;

use BigCartEnergy\CatalogCompleteness\Api\Data\RuleInterface;
use BigCartEnergy\CatalogCompleteness\Model\Dashboard\CoverageMap;
use BigCartEnergy\CatalogCompleteness\Test\Unit\Stub\RuleStub;
use PHPUnit\Framework\TestCase;

class CoverageMapTest extends TestCase
{
    public function testTheTypesOfARuleSetAreTheUnionOfItsRulesTypes(): void
    {
        self::assertSame([], CoverageMap::typesOf([]));
        self::assertNull(CoverageMap::typesOf([$this->rule([]), $this->rule(['simple'])]));
        self::assertSame(['simple', 'virtual', 'bundle'], CoverageMap::typesOf([
            $this->rule(['simple', 'virtual']),
            $this->rule(['virtual', 'bundle']),
        ]));
    }

    public function testTheAttributeSetRulesScope(): void
    {
        // Set 4: a rule for every type. Set 9: rules for simple and virtual only. Set 12: no rules (not covered).
        $map = CoverageMap::perAttributeSet([4 => null, 9 => ['simple', 'virtual'], 12 => []]);
        self::assertFalse($map->isUncovered(4, 'simple'));
        self::assertFalse($map->isUncovered(4, 'configurable'));
        self::assertFalse($map->isUncovered(9, 'virtual'));
        self::assertTrue($map->isUncovered(9, 'configurable'), 'a type no rule of the set applies to');
        self::assertTrue($map->isUncovered(12, 'simple'), 'a rule set without rules covers nothing');
        self::assertTrue($map->isUncovered(31, 'simple'), 'an attribute set no rule set covers');
        self::assertSame(
            "NOT (e.attribute_set_id IN ('4') OR (e.attribute_set_id = '9' AND e.type_id IN ('simple', 'virtual')))",
            $map->condition('e', [$this, 'quoteInto'])
        );
    }

    public function testWithoutAnyAttributeSetRuleSetEveryProductIsUncovered(): void
    {
        $map = CoverageMap::perAttributeSet([]);
        self::assertTrue($map->isUncovered(4, 'simple'));
        self::assertSame('', $map->condition('e', [$this, 'quoteInto']));
    }

    public function testAChannelProfileReachesEveryProduct(): void
    {
        $all = CoverageMap::single(null, null);
        self::assertFalse($all->isUncovered(4, 'grouped'));
        self::assertSame('1 = 0', $all->condition('e', [$this, 'quoteInto']));

        $some = CoverageMap::single(null, ['simple', 'configurable']);
        self::assertFalse($some->isUncovered(31, 'configurable'));
        self::assertTrue($some->isUncovered(31, 'bundle'));
        self::assertSame("e.type_id NOT IN ('simple', 'configurable')", $some->condition('e', [$this, 'quoteInto']));

        $none = CoverageMap::single(null, []);
        self::assertTrue($none->isUncovered(4, 'simple'), 'a disabled profile or one without rules covers nothing');
        self::assertSame('', $none->condition('e', [$this, 'quoteInto']));
    }

    public function testAnAttributeSetRuleSetReachesItsAttributeSetsOnly(): void
    {
        $map = CoverageMap::single([4, 9], ['simple']);
        self::assertTrue($map->isUncovered(9, 'virtual'));
        self::assertFalse($map->isUncovered(9, 'simple'));
        self::assertFalse($map->isUncovered(31, 'virtual'), 'outside its attribute sets is not its gap');
        self::assertSame(
            "x.attribute_set_id IN ('4', '9') AND x.type_id NOT IN ('simple')",
            $map->condition('x', [$this, 'quoteInto'])
        );
        $quote = [$this, 'quoteInto'];
        self::assertSame('1 = 0', CoverageMap::single([4], null)->condition('e', $quote));
        self::assertSame("e.attribute_set_id IN ('4')", CoverageMap::single([4], [])->condition('e', $quote));
        self::assertSame('1 = 0', CoverageMap::single([], [])->condition('e', $quote), 'an unknown rule set');
    }

    /**
     * The adapter's quoteInto in miniature: every value quoted, a list joined with commas.
     *
     * @param mixed $value
     */
    public function quoteInto(string $text, $value): string
    {
        $values = is_array($value) ? $value : [$value];
        $quoted = array_map(function ($item): string {
            return "'" . addslashes((string)$item) . "'";
        }, $values);
        return str_replace('?', implode(', ', $quoted), $text);
    }

    /**
     * @param string[] $appliesTo
     */
    private function rule(array $appliesTo): RuleInterface
    {
        return RuleStub::make('name', RuleInterface::TYPE_PRESENT, null, 3, $appliesTo);
    }
}
