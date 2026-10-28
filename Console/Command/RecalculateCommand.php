<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Console\Command;

use BigCartEnergy\CatalogCompleteness\Model\Indexer\Reindexer;
use BigCartEnergy\CatalogCompleteness\Model\Indexer\Status as IndexerStatus;
use Magento\Framework\App\Area;
use Magento\Framework\App\State as AppState;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Console\Cli;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * bin/magento bce:completeness:recalculate [--ruleset=<id>] [--product=<id>]
 *
 * Runs synchronously with a progress bar: the fallback when cron or the queue consumer is not set up.
 */
class RecalculateCommand extends Command
{
    public const NAME = 'bce:completeness:recalculate';
    public const OPTION_RULESET = 'ruleset';
    public const OPTION_PRODUCT = 'product';

    /**
     * @var Reindexer
     */
    private $reindexer;

    /**
     * @var IndexerStatus
     */
    private $indexerStatus;

    /**
     * @var AppState
     */
    private $appState;

    public function __construct(Reindexer $reindexer, IndexerStatus $indexerStatus, AppState $appState)
    {
        $this->reindexer = $reindexer;
        $this->indexerStatus = $indexerStatus;
        $this->appState = $appState;
        parent::__construct();
    }

    /**
     * @inheritDoc
     */
    protected function configure()
    {
        $this->setName(self::NAME)
            ->setDescription('Recalculate catalog completeness scores (all products, one rule set, or one product).')
            ->addOption(self::OPTION_RULESET, null, InputOption::VALUE_REQUIRED, 'Rescore one rule set by id')
            ->addOption(self::OPTION_PRODUCT, null, InputOption::VALUE_REQUIRED, 'Rescore one product by id');
        parent::configure();
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->appState->setAreaCode(Area::AREA_ADMINHTML);
        } catch (LocalizedException $e) {
            // The area code was set already; that is fine.
        }
        $rulesetId = $this->intOption($input, self::OPTION_RULESET);
        $productId = $this->intOption($input, self::OPTION_PRODUCT);
        if ($rulesetId === 0 || $productId === 0) {
            $output->writeln('<error>Ids must be positive integers.</error>');
            return Cli::RETURN_FAILURE;
        }

        $bar = new ProgressBar($output);
        $bar->setFormat(' %current%/%max% products [%bar%] %percent:3s%% %elapsed:6s%');
        $started = false;
        $this->reindexer->setProgressCallback(function (int $done, int $total) use ($bar, &$started) {
            if (!$started) {
                $bar->start(max($total, 1));
                $started = true;
            }
            $bar->setProgress(min($done, max($total, 1)));
        });

        try {
            if ($productId !== null) {
                $this->reindexer->reindexIds([$productId], $rulesetId);
            } elseif ($rulesetId !== null) {
                $this->reindexer->reindexAll($rulesetId);
            } else {
                // Through the framework so the indexer becomes valid and the changelog position moves on.
                $this->indexerStatus->reindexAll();
            }
        } finally {
            $this->reindexer->setProgressCallback(null);
        }
        if ($started) {
            $bar->finish();
            $output->writeln('');
        }
        $output->writeln('<info>Catalog completeness scores recalculated.</info>');
        return Cli::RETURN_SUCCESS;
    }

    /**
     * null when absent, 0 when not a positive integer, the id otherwise.
     */
    private function intOption(InputInterface $input, string $name): ?int
    {
        $value = $input->getOption($name);
        if ($value === null || $value === '') {
            return null;
        }
        if (!preg_match('/^\d+$/', (string)$value) || (int)$value <= 0) {
            return 0;
        }
        return (int)$value;
    }
}
