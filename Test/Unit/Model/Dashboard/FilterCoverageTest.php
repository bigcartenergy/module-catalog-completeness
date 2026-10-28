<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Test\Unit\Model\Dashboard;

use BigCartEnergy\CatalogCompleteness\Model\Dashboard\Filter;
use PHPUnit\Framework\TestCase;

/**
 * The dashboard's rule coverage parameter: "none" lists the products of the scope with no rules assigned.
 */
class FilterCoverageTest extends TestCase
{
    public function testOnlyNoneSelectsTheProductsWithNoRulesAssigned(): void
    {
        self::assertTrue(Filter::fromParams(['coverage' => 'none'], true)->isUncoveredOnly());
        self::assertSame('none', Filter::fromParams(['coverage' => ' none '], true)->getCoverage());
        foreach (['', 'all', 'NONE', 'scored', '1'] as $value) {
            self::assertFalse(Filter::fromParams(['coverage' => $value], true)->isUncoveredOnly(), $value);
        }
        self::assertFalse(Filter::fromParams(['coverage' => ['none']], true)->isUncoveredOnly(), 'an array');
        self::assertNull(Filter::fromParams([], true)->getCoverage());
    }

    public function testTheScoreFiltersDoNotApplyToProductsWithoutAScore(): void
    {
        $filter = Filter::fromParams(
            ['coverage' => 'none', 'band' => 'poor', 'tile' => 'complete', 'rule' => '7', 'attribute_set' => '4'],
            true
        );
        self::assertNull($filter->getBand());
        self::assertNull($filter->getTile());
        self::assertNull($filter->getRuleId());
        self::assertSame(4, $filter->getAttributeSetId(), 'the filters on the products themselves stay');
    }

    public function testItTravelsWithTheOtherParametersAndSurvivesClearing(): void
    {
        $params = ['scope' => '5', 'coverage' => 'none', 'type' => 'simple', 'enabled' => 'no'];
        $filter = Filter::fromParams($params, true);
        self::assertSame(
            ['scope' => '5', 'type' => 'simple', 'enabled' => 'no', 'coverage' => 'none'],
            $filter->toParams()
        );
        self::assertSame(['scope' => '5', 'enabled' => 'no', 'coverage' => 'none'], $filter->cleared()->toParams());
        self::assertTrue($filter->hasActiveFilters());
        self::assertFalse($filter->with(Filter::PARAM_COVERAGE, null)->isUncoveredOnly());
        self::assertTrue(Filter::fromParams($filter->toParams(), true)->isUncoveredOnly(), 'round trip');
        self::assertTrue(Filter::fromParams(['coverage' => 'none'], true)->hasActiveFilters());
    }

    /**
     * The grid's selection column forwards exactly these names from the update URL to the mass actions.
     */
    public function testTheSelectionColumnForwardsTheSameParameters(): void
    {
        self::assertContains(Filter::PARAM_COVERAGE, Filter::PARAMS);
        $script = file_get_contents(dirname(__DIR__, 4) . '/view/adminhtml/web/js/grid/columns/multiselect.js');
        self::assertSame(1, preg_match('/var PARAMS = \[([^\]]*)\]/', (string)$script, $match));
        $names = array_map(function (string $name): string {
            return trim($name, " '\"");
        }, explode(',', $match[1]));
        self::assertSame(Filter::PARAMS, $names);
    }
}
