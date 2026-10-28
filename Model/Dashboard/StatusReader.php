<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Model\Dashboard;

use BigCartEnergy\CatalogCompleteness\Model\Indexer\Status as IndexerStatus;
use BigCartEnergy\CatalogCompleteness\Model\Queue\RunManager;
use BigCartEnergy\CatalogCompleteness\Model\ResourceModel\Score as ScoreResource;
use BigCartEnergy\CatalogCompleteness\Model\Run;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;

/**
 * The state the status line shows, for the page render and the polling endpoint alike: the indexer's
 * validity and mode, when scores were last written, and the active or failed run.
 */
class StatusReader
{
    public const STATE_OK = 'ok';
    public const STATE_RECALCULATING = 'recalculating';
    public const STATE_STALE = 'stale';
    public const STATE_ERROR = 'error';
    public const STATE_NEVER = 'never';

    /**
     * @var IndexerStatus
     */
    private $indexerStatus;

    /**
     * @var RunManager
     */
    private $runManager;

    /**
     * @var ScoreResource
     */
    private $scoreResource;

    /**
     * @var TimezoneInterface
     */
    private $timezone;

    /**
     * @var DateTime
     */
    private $dateTime;

    public function __construct(
        IndexerStatus $indexerStatus,
        RunManager $runManager,
        ScoreResource $scoreResource,
        TimezoneInterface $timezone,
        DateTime $dateTime
    ) {
        $this->indexerStatus = $indexerStatus;
        $this->runManager = $runManager;
        $this->scoreResource = $scoreResource;
        $this->timezone = $timezone;
        $this->dateTime = $dateTime;
    }

    /**
     * @return array<string, mixed>
     */
    public function read(?int $rulesetId = null): array
    {
        $scoredAt = $this->scoreResource->fetchLatestScoredAt($rulesetId);
        $active = $this->runManager->getActiveRun();
        $failed = $active === null ? $this->runManager->getUnresolvedFailure() : null;
        $scheduled = $this->indexerStatus->isScheduled();
        $invalid = $this->indexerStatus->isInvalid();
        $never = $scoredAt === null;

        if ($active !== null) {
            $state = self::STATE_RECALCULATING;
        } elseif ($failed !== null) {
            $state = self::STATE_ERROR;
        } elseif ($never) {
            $state = self::STATE_NEVER;
        } elseif ($invalid) {
            $state = self::STATE_STALE;
        } else {
            $state = self::STATE_OK;
        }

        return [
            'state' => $state,
            'scored_at' => $scoredAt,
            'scored_at_formatted' => $scoredAt === null ? '' : $this->format($scoredAt),
            'mode' => $scheduled ? 'schedule' : 'save',
            'mode_label' => $scheduled ? (string)__('updated by schedule') : (string)__('updated on save'),
            'indexer' => [
                'valid' => $this->indexerStatus->isValid(),
                'invalid' => $invalid,
                'working' => $this->indexerStatus->isWorking(),
                'scheduled' => $scheduled,
                'latest_updated' => $this->indexerStatus->getLatestUpdated(),
            ],
            'run' => $active === null ? null : $this->runToArray($active),
            'failed' => $failed === null ? null : $this->runToArray($failed),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function runToArray(Run $run): array
    {
        $now = $this->dateTime->gmtTimestamp();
        return [
            'run_id' => (int)$run->getId(),
            'ruleset_id' => $run->getRulesetId(),
            'state' => $run->getState(),
            'done' => $run->getDone(),
            'total' => $run->getTotal(),
            'percent' => $run->getPercent(),
            'message' => $run->getMessage(),
            'waiting' => $this->runManager->isWaiting($run, $now),
            'created_at' => $this->format((string)$run->getData('created_at')),
            'started_at' => $run->getData('started_at') ? $this->format((string)$run->getData('started_at')) : '',
            'finished_at' => $run->getData('finished_at') ? $this->format((string)$run->getData('finished_at')) : '',
        ];
    }

    /**
     * A UTC database timestamp in the admin's locale and timezone.
     */
    public function format(string $utc): string
    {
        if ($utc === '') {
            return '';
        }
        try {
            return $this->timezone->formatDateTime(
                $utc,
                \IntlDateFormatter::MEDIUM,
                \IntlDateFormatter::SHORT,
                null,
                null
            );
        } catch (\Throwable $e) {
            return $utc;
        }
    }
}
