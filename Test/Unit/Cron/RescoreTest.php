<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Test\Unit\Cron;

use BigCartEnergy\CatalogCompleteness\Api\Data\RuleSetInterface;
use BigCartEnergy\CatalogCompleteness\Cron\Rescore;
use BigCartEnergy\CatalogCompleteness\Model\Config;
use BigCartEnergy\CatalogCompleteness\Model\Indexer\Plan;
use BigCartEnergy\CatalogCompleteness\Model\Indexer\Reindexer;
use BigCartEnergy\CatalogCompleteness\Model\Rescore\Batches;
use BigCartEnergy\CatalogCompleteness\Model\Rescore\ProductIdQuery;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\FlagManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class RescoreTest extends TestCase
{
    private const NOW = '2026-10-27 12:00:00';

    /**
     * @var array<int, array{0: string, 1: string, 2: array<string, mixed>}> level, message, context
     */
    private $log = [];

    /**
     * @var array<int, int[]> the batches handed to the reindexer
     */
    private $scored = [];

    /**
     * @var array<int, array{0: string, 1: mixed}> saveFlag calls
     */
    private $saved = [];

    /**
     * @var array<int, string|null> the since value of every id query
     */
    private $since = [];

    /**
     * Magento's cron reads the schedule from the setting the admin edits; the defaults are the ones the
     * settings reader falls back to.
     */
    public function testTheJobRunsOnTheScheduleTheSettingsHold(): void
    {
        $root = dirname(__DIR__, 3);
        $crontab = simplexml_load_file($root . '/etc/crontab.xml');
        $jobs = $crontab->xpath('/config/group[@id="' . Rescore::GROUP . '"]/job[@name="' . Rescore::JOB . '"]');
        self::assertCount(1, $jobs);
        self::assertSame(Config::XML_PATH_RESCORE_SCHEDULE, (string)$jobs[0]->config_path);
        self::assertSame(Rescore::class, (string)$jobs[0]['instance']);
        self::assertSame('execute', (string)$jobs[0]['method']);
        self::assertCount(0, $jobs[0]->schedule, 'no fixed schedule beside the setting');

        $defaults = simplexml_load_file($root . '/etc/config.xml')->default->bce_completeness->rescore;
        self::assertSame('1', (string)$defaults->enabled);
        self::assertSame(Config::DEFAULT_RESCORE_SCHEDULE, (string)$defaults->schedule);
        self::assertSame(Config::RESCORE_MODE_FULL, (string)$defaults->mode);
        self::assertSame((string)Config::DEFAULT_RESCORE_BATCH_SIZE, (string)$defaults->batch_size);

        $groups = simplexml_load_file($root . '/etc/cron_groups.xml')
            ->xpath('/config/group[@id="' . Rescore::GROUP . '"]');
        self::assertCount(1, $groups);
        self::assertSame('1', (string)$groups[0]->use_separate_process);

        $fields = simplexml_load_file($root . '/etc/adminhtml/system.xml')
            ->xpath('/config/system/section[@id="bce_completeness"]/group[@id="rescore"]/field');
        $ids = [];
        foreach ($fields as $field) {
            $ids[] = (string)$field['id'];
        }
        self::assertSame(['enabled', 'schedule', 'mode', 'batch_size'], $ids);
        foreach ([Config::XML_PATH_RESCORE_ENABLED, Config::XML_PATH_RESCORE_SCHEDULE, Config::XML_PATH_RESCORE_MODE,
            Config::XML_PATH_RESCORE_BATCH_SIZE] as $index => $path) {
            self::assertSame('bce_completeness/rescore/' . $ids[$index], $path);
        }
    }

    public function testDoesNothingWhenSwitchedOff(): void
    {
        $this->job(['enabled' => '0'], range(1, 120))->execute();
        self::assertSame([], $this->scored);
        self::assertSame([], $this->log);
        self::assertSame([], $this->saved);
        self::assertSame([], $this->since);
    }

    public function testSkipsWhenNoEnabledRuleSetHasRules(): void
    {
        $this->job(['enabled' => '1'], range(1, 120), null, true, false)->execute();
        self::assertSame([], $this->scored);
        self::assertSame([], $this->saved);
        self::assertCount(1, $this->log);
        self::assertSame('info', $this->log[0][0]);
        self::assertStringContainsString('skipped', $this->log[0][1]);
    }

    public function testAFullPassScoresEveryProductInBatches(): void
    {
        $this->job(['enabled' => '1', 'mode' => 'full', 'batch_size' => '50'], range(1, 120))->execute();
        self::assertSame([range(1, 50), range(51, 100), range(101, 120)], $this->scored);
        self::assertSame([null, null, null], $this->since);
        self::assertSame([[Rescore::FLAG_CODE, ['started_at' => self::NOW]]], $this->saved);
        self::assertCount(1, $this->log);
        list($level, $message, $context) = $this->log[0];
        self::assertSame('info', $level);
        self::assertStringContainsString('(every product): 120 products in 3 batches of up to 50', $message);
        self::assertSame('full', $context['mode']);
        self::assertNull($context['since']);
        self::assertSame(120, $context['products']);
        self::assertSame(3, $context['batches']);
        self::assertSame(50, $context['batch_size']);
        self::assertIsFloat($context['seconds']);
    }

    public function testChangedModeReadsFromShortlyBeforeTheLastRunStarted(): void
    {
        $catalog = [
            4 => '2026-10-27 05:40:00',
            7 => '2026-10-27 05:45:00',
            9 => '2026-10-27 07:30:00',
            12 => '2026-10-26 23:00:00',
            15 => '2026-10-27 11:59:59',
        ];
        $flag = ['started_at' => '2026-10-27 06:00:00'];
        $this->job(['enabled' => '1', 'mode' => 'changed', 'batch_size' => '500'], $catalog, $flag)->execute();
        self::assertSame([[7, 9, 15]], $this->scored);
        self::assertSame(['2026-10-27 05:45:00'], $this->since);
        self::assertSame([[Rescore::FLAG_CODE, ['started_at' => self::NOW]]], $this->saved);
        self::assertStringContainsString(
            '(products changed since 2026-10-27 05:45:00 UTC): 3 products in 1 batch of up to 500',
            $this->log[0][1]
        );
        self::assertSame('changed', $this->log[0][2]['mode']);
        self::assertSame('2026-10-27 05:45:00', $this->log[0][2]['since']);
    }

    public function testOneProductReadsInTheSingular(): void
    {
        $catalog = [4 => '2026-10-27 05:40:00', 7 => '2026-10-27 07:00:00'];
        $flag = ['started_at' => '2026-10-27 06:00:00'];
        $this->job(['enabled' => '1', 'mode' => 'changed', 'batch_size' => '500'], $catalog, $flag)->execute();
        self::assertSame([[7]], $this->scored);
        self::assertStringContainsString('): 1 product in 1 batch of up to 500, ', $this->log[0][1]);
    }

    public function testChangedModeWithoutAnEarlierRunIsAFullPass(): void
    {
        $this->job(['enabled' => '1', 'mode' => 'changed', 'batch_size' => '50'], range(1, 60))->execute();
        self::assertSame([range(1, 50), range(51, 60)], $this->scored);
        self::assertSame([null, null], $this->since);
        self::assertStringContainsString('no earlier run is on record', $this->log[0][1]);
        self::assertSame('full', $this->log[0][2]['mode']);
        self::assertCount(1, $this->saved);
    }

    public function testChangedModeWithoutAUsableUpdatedAtIsAFullPass(): void
    {
        $flag = ['started_at' => '2026-10-27 06:00:00'];
        $settings = ['enabled' => '1', 'mode' => 'changed', 'batch_size' => '50'];
        $this->job($settings, range(1, 60), $flag, false)->execute();
        self::assertSame([range(1, 50), range(51, 60)], $this->scored);
        self::assertSame([null, null], $this->since);
        self::assertStringContainsString('no usable updated_at', $this->log[0][1]);
    }

    public function testAnUnreadableMarkIsAFullPass(): void
    {
        foreach ([['started_at' => 'yesterday'], ['started_at' => 5], 'not an array', []] as $flag) {
            $this->job(['enabled' => '1', 'mode' => 'changed', 'batch_size' => '50'], range(1, 10), $flag)->execute();
            self::assertSame([null], $this->since, var_export($flag, true));
        }
    }

    public function testAFailedBatchIsLoggedAndRethrownAndTheMarkStays(): void
    {
        $settings = ['enabled' => '1', 'mode' => 'full', 'batch_size' => '50'];
        $job = $this->job($settings, range(1, 120), null, true, true, 1);
        try {
            $job->execute();
            self::fail('the failure was swallowed');
        } catch (\RuntimeException $e) {
            self::assertSame('database went away', $e->getMessage());
        }
        self::assertSame([range(1, 50)], $this->scored);
        self::assertSame([], $this->saved);
        self::assertCount(1, $this->log);
        self::assertSame('error', $this->log[0][0]);
        self::assertStringContainsString('failed after 50 products', $this->log[0][1]);
        self::assertStringContainsString('database went away', $this->log[0][1]);
    }

    /**
     * The job on stubs: settings under bce_completeness/rescore, an in-memory catalog (a list of ids, or id =>
     * updated_at), the flag the last run left, and a reindexer that records the batches it is given.
     *
     * @param array<string, string> $settings field => stored value
     * @param array<int, int|string> $catalog
     * @param mixed $flag
     */
    private function job(
        array $settings,
        array $catalog,
        $flag = null,
        bool $hasUpdatedAt = true,
        bool $withRules = true,
        ?int $failOnBatch = null
    ): Rescore {
        if (!interface_exists(LoggerInterface::class) || !class_exists(FlagManager::class)
            || !interface_exists(ScopeConfigInterface::class)) {
            self::markTestSkipped('Needs the Magento framework and psr/log: run inside a Magento installation.');
        }
        $this->log = [];
        $this->scored = [];
        $this->saved = [];
        $this->since = [];
        if (array_keys($catalog) === range(0, count($catalog) - 1) && !is_string(reset($catalog))) {
            $catalog = array_fill_keys($catalog, '2026-01-01 00:00:00');
        }

        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnCallback(function ($path) use ($settings) {
            $field = substr((string)$path, strlen('bce_completeness/rescore/'));
            return isset($settings[$field]) ? $settings[$field] : null;
        });
        $scopeConfig->method('isSetFlag')->willReturnCallback(function ($path) use ($settings) {
            $field = substr((string)$path, strlen('bce_completeness/rescore/'));
            return !empty($settings[$field]);
        });

        $plan = new Plan();
        if ($withRules) {
            $plan->ruleSets = [$this->createStub(RuleSetInterface::class)];
            $plan->ruleSetIds = [1];
        }
        $scored = &$this->scored;
        $reindexer = $this->createStub(Reindexer::class);
        $reindexer->method('plan')->willReturn($plan);
        $reindexer->method('reindexBatch')->willReturnCallback(
            function (array $ids, Plan $given) use ($plan, &$scored, $failOnBatch): void {
                self::assertSame($plan, $given, 'one plan for the whole run');
                if ($failOnBatch !== null && count($scored) === $failOnBatch) {
                    throw new \RuntimeException('database went away');
                }
                $scored[] = $ids;
            }
        );

        $since = &$this->since;
        $query = $this->createStub(ProductIdQuery::class);
        $query->method('nextIds')->willReturnCallback(
            function (int $afterId, int $limit, ?string $changedSince = null) use ($catalog, &$since): array {
                $since[] = $changedSince;
                $ids = [];
                foreach ($catalog as $id => $updatedAt) {
                    if ($id > $afterId && ($changedSince === null || $updatedAt >= $changedSince)) {
                        $ids[] = $id;
                    }
                }
                sort($ids);
                return array_slice($ids, 0, $limit);
            }
        );
        $query->method('hasUpdatedAt')->willReturn($hasUpdatedAt);
        $query->method('now')->willReturn(self::NOW);

        $saved = &$this->saved;
        $flags = $this->createStub(FlagManager::class);
        $flags->method('getFlagData')->willReturn($flag);
        $flags->method('saveFlag')->willReturnCallback(function ($code, $value) use (&$saved) {
            $saved[] = [$code, $value];
            return true;
        });

        $log = &$this->log;
        $logger = $this->createStub(LoggerInterface::class);
        foreach (['info', 'error'] as $level) {
            $logger->method($level)->willReturnCallback(function ($message, array $context = []) use (&$log, $level) {
                $log[] = [$level, (string)$message, $context];
            });
        }
        return new Rescore(new Config($scopeConfig), $reindexer, new Batches($query), $query, $flags, $logger);
    }
}
