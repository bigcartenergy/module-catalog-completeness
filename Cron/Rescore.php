<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Cron;

use BigCartEnergy\CatalogCompleteness\Model\Config;
use BigCartEnergy\CatalogCompleteness\Model\Indexer\Reindexer;
use BigCartEnergy\CatalogCompleteness\Model\Rescore\Batches;
use BigCartEnergy\CatalogCompleteness\Model\Rescore\ProductIdQuery;
use Magento\Framework\FlagManager;
use Psr\Log\LoggerInterface;

/**
 * The periodic rescore: the cron job bce_completeness_rescore in the cron group bce_completeness, on the
 * schedule in the settings. It rescores the catalog so that changes no save event announces (an ERP sync or
 * a script writing straight to the database) still reach the scores: every product, or in the changed-since
 * mode the products whose updated_at moved since shortly before the last completed run. It walks product id
 * ranges of the configured batch size and logs each run's count and duration to var/log/bce_completeness.log.
 * The dashboard's Recalculate, the command and the indexer are separate and unchanged.
 */
class Rescore
{
    public const GROUP = 'bce_completeness';
    public const JOB = 'bce_completeness_rescore';
    public const FLAG_CODE = 'bce_completeness_rescore';

    /**
     * How far before the last run's start the changed-since window opens: a sync transaction still open when
     * that run started can carry an updated_at just before the start and commit after it.
     */
    public const OVERLAP_SECONDS = 900;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var Reindexer
     */
    private $reindexer;

    /**
     * @var Batches
     */
    private $batches;

    /**
     * @var ProductIdQuery
     */
    private $query;

    /**
     * @var FlagManager
     */
    private $flagManager;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        Config $config,
        Reindexer $reindexer,
        Batches $batches,
        ProductIdQuery $query,
        FlagManager $flagManager,
        LoggerInterface $logger
    ) {
        $this->config = $config;
        $this->reindexer = $reindexer;
        $this->batches = $batches;
        $this->query = $query;
        $this->flagManager = $flagManager;
        $this->logger = $logger;
    }

    public function execute(): void
    {
        if (!$this->config->isRescoreEnabled()) {
            return;
        }
        $started = microtime(true);
        $plan = $this->reindexer->plan(null);
        if (!$plan->ruleSets) {
            $this->logger->info('Periodic rescore skipped: no enabled rule set has rules.');
            return;
        }
        $runStart = $this->query->now();
        $batchSize = $this->config->getRescoreBatchSize();
        list($since, $scope) = $this->window();
        $done = 0;
        $batches = 0;
        try {
            $this->batches->walk($batchSize, $since, function (array $ids) use ($plan, &$done, &$batches): void {
                $this->reindexer->reindexBatch($ids, $plan);
                $done += count($ids);
                $batches++;
            });
        } catch (\Throwable $e) {
            $this->logger->error(sprintf(
                'Periodic rescore (%s) failed after %d %s in %s s: %s',
                $scope,
                $done,
                $done === 1 ? 'product' : 'products',
                $this->seconds($started),
                $e->getMessage()
            ));
            throw $e;
        }
        // The run's start, not its end: whatever changed while it ran is taken in by the next run.
        $this->flagManager->saveFlag(self::FLAG_CODE, ['started_at' => $runStart]);
        $this->logger->info(
            sprintf(
                'Periodic rescore (%s): %d %s in %d %s of up to %d, %s s.',
                $scope,
                $done,
                $done === 1 ? 'product' : 'products',
                $batches,
                $batches === 1 ? 'batch' : 'batches',
                $batchSize,
                $this->seconds($started)
            ),
            [
                'mode' => $since === null ? Config::RESCORE_MODE_FULL : Config::RESCORE_MODE_CHANGED,
                'since' => $since,
                'products' => $done,
                'batches' => $batches,
                'batch_size' => $batchSize,
                'seconds' => (float)$this->seconds($started),
            ]
        );
    }

    /**
     * The changed-since window and the run's scope in words.
     *
     * @return array{0: string|null, 1: string} the window's start (UTC), or null for every product, and the
     *     scope for the log
     */
    private function window(): array
    {
        if ($this->config->getRescoreMode() !== Config::RESCORE_MODE_CHANGED) {
            return [null, 'every product'];
        }
        if (!$this->query->hasUpdatedAt()) {
            return [null, 'every product: changed-since mode, but the product table has no usable updated_at'];
        }
        $last = $this->lastStart();
        if ($last === null) {
            return [null, 'every product: changed-since mode, but no earlier run is on record'];
        }
        $since = gmdate('Y-m-d H:i:s', $last - self::OVERLAP_SECONDS);
        return [$since, 'products changed since ' . $since . ' UTC'];
    }

    /**
     * When the last completed run started, as a Unix time, or null when none is on record.
     */
    private function lastStart(): ?int
    {
        $data = $this->flagManager->getFlagData(self::FLAG_CODE);
        $value = is_array($data) && isset($data['started_at']) && is_string($data['started_at'])
            ? $data['started_at'] : '';
        if (!preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}$/', $value)) {
            return null;
        }
        $time = strtotime($value . ' UTC');
        return $time === false ? null : $time;
    }

    private function seconds(float $started): string
    {
        return number_format(microtime(true) - $started, 1, '.', '');
    }
}
