<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Test\Unit\Model\Band;

use BigCartEnergy\CatalogCompleteness\Model\Band\Resolver;
use BigCartEnergy\CatalogCompleteness\Model\Band\Thresholds;
use BigCartEnergy\CatalogCompleteness\Model\Config;
use Magento\Framework\App\Config\ScopeConfigInterface;
use PHPUnit\Framework\TestCase;

class ResolverTest extends TestCase
{
    public function testDefaultThresholds(): void
    {
        foreach (self::defaultBandsProvider() as $case => $arguments) {
            $this->checkDefaultThresholds(...array_values($arguments));
        }
    }

    /**
     * One case of testDefaultThresholds().
     */
    private function checkDefaultThresholds(?int $score, ?string $expected): void
    {
        self::assertSame($expected, Resolver::resolveWith($score, Thresholds::fromSettings(80, 50)));
    }

    public static function defaultBandsProvider(): array
    {
        return [
            'null' => [null, null],
            '100 complete' => [100, Resolver::COMPLETE],
            '99 good' => [99, Resolver::GOOD],
            '80 good' => [80, Resolver::GOOD],
            '79 needs work' => [79, Resolver::NEEDS_WORK],
            '50 needs work' => [50, Resolver::NEEDS_WORK],
            '49 poor' => [49, Resolver::POOR],
            '0 poor' => [0, Resolver::POOR],
        ];
    }

    public function testCustomThresholds(): void
    {
        $thresholds = Thresholds::fromSettings(90, 70);
        self::assertSame(Resolver::NEEDS_WORK, Resolver::resolveWith(85, $thresholds));
        self::assertSame(Resolver::GOOD, Resolver::resolveWith(90, $thresholds));
        self::assertSame(Resolver::POOR, Resolver::resolveWith(69, $thresholds));
    }

    public function testInconsistentSettingsFallBack(): void
    {
        $t = Thresholds::fromSettings(50, 80);
        self::assertSame(50, $t->getGoodFrom());
        self::assertSame(49, $t->getNeedsWorkFrom());

        $t = Thresholds::fromSettings(0, 0);
        self::assertSame(Thresholds::DEFAULT_GOOD_FROM, $t->getGoodFrom());
        self::assertSame(Thresholds::DEFAULT_NEEDS_WORK_FROM, $t->getNeedsWorkFrom());

        $t = Thresholds::fromSettings(150, 20);
        self::assertSame(Thresholds::DEFAULT_GOOD_FROM, $t->getGoodFrom());
        self::assertSame(20, $t->getNeedsWorkFrom());
    }

    public function testResolverReadsTheSettingsOnce(): void
    {
        if (!interface_exists(ScopeConfigInterface::class)) {
            self::markTestSkipped('Magento framework not available (set BCE_MAGENTO_ROOT).');
        }
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnMap([
            [Config::XML_PATH_GOOD_FROM, 'default', null, '85'],
            [Config::XML_PATH_NEEDS_WORK_FROM, 'default', null, '40'],
        ]);
        $resolver = new Resolver(new Config($scopeConfig));
        self::assertSame(Resolver::GOOD, $resolver->resolve(85));
        self::assertSame(Resolver::NEEDS_WORK, $resolver->resolve(84));
        self::assertSame(Resolver::POOR, $resolver->resolve(39));
        self::assertSame([85, 99], $resolver->getRange(Resolver::GOOD));
        self::assertSame([40, 84], $resolver->getRange(Resolver::NEEDS_WORK));
        self::assertSame([0, 39], $resolver->getRange(Resolver::POOR));
        self::assertTrue(strpos($resolver->getSqlCase('s.score'), '>= 85') !== false);
        self::assertTrue(strpos($resolver->getSqlCase('s.score'), "'needs_work'") !== false);
        self::assertSame('Needs work', $resolver->getLabel(Resolver::NEEDS_WORK));
        self::assertSame(['complete', 'good', 'needs_work', 'poor'], array_keys($resolver->getAll()));
    }
}
