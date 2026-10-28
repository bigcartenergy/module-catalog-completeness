<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Setup;

use Magento\Framework\Mview\ViewInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UninstallInterface;
use Psr\Log\LoggerInterface;

/**
 * bin/magento module:uninstall BigCartEnergy_CatalogCompleteness --remove-data
 *
 * First unsubscribes the indexer's materialized view, which rewrites the catalog tables' shared triggers
 * without this module's statements and drops its changelog table; a dropped changelog with its trigger
 * statements left behind would break every product save until the next setup:upgrade. When that step fails,
 * the changelog table and the view state are kept (and the failure logged) so the triggers stay valid. Then
 * drops the module's six tables and removes its indexer, view, queue, configuration, cron schedule and flag
 * rows; the queue message status rows go explicitly, because setup runs with foreign key checks off. The
 * periodic rescore's log file, var/log/bce_completeness.log, is left in place. Disabling the module,
 * or removing the package with composer and running setup:upgrade, leaves the data in place on purpose;
 * this is the path that removes it.
 */
class Uninstall implements UninstallInterface
{
    public const INDEXER_ID = 'bce_completeness_score';
    public const QUEUE_NAME = 'bce_completeness.recalculate';
    public const CHANGELOG_TABLE = 'bce_completeness_score_cl';
    public const CRON_JOB = 'bce_completeness_rescore';
    public const FLAG_CODE = 'bce_completeness_rescore';

    private const TABLES = [
        'bce_completeness_failure',
        'bce_completeness_score',
        'bce_completeness_run',
        'bce_completeness_rule',
        'bce_completeness_ruleset_attribute_set',
        'bce_completeness_ruleset',
    ];

    /**
     * @var ViewInterface
     */
    private $view;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(ViewInterface $view, LoggerInterface $logger)
    {
        $this->view = $view;
        $this->logger = $logger;
    }

    /**
     * @inheritDoc
     */
    public function uninstall(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $viewReleased = $this->unsubscribeView();
        $setup->startSetup();
        $connection = $setup->getConnection();
        $tables = self::TABLES;
        if ($viewReleased) {
            $tables[] = self::CHANGELOG_TABLE;
        }
        foreach ($tables as $table) {
            $name = $setup->getTable($table);
            if ($connection->isTableExists($name)) {
                $connection->dropTable($name);
            }
        }
        $this->removeQueueRows($setup);
        $rows = [
            'indexer_state' => ['indexer_id = ?' => self::INDEXER_ID],
            'core_config_data' => ['path LIKE ?' => 'bce_completeness/%'],
            'cron_schedule' => ['job_code = ?' => self::CRON_JOB],
            'flag' => ['flag_code = ?' => self::FLAG_CODE],
        ];
        if ($viewReleased) {
            $rows['mview_state'] = ['view_id = ?' => self::INDEXER_ID];
        }
        foreach ($rows as $table => $where) {
            $name = $setup->getTable($table);
            if ($connection->isTableExists($name)) {
                $connection->delete($name, $where);
            }
        }
        $setup->endSetup();
    }

    /**
     * Remove this view's statements from the shared triggers and drop its changelog, when the view is
     * subscribed (Update by Schedule). Nothing to do in Update on Save, where no trigger exists.
     *
     * @return bool whether the changelog table and the view state may go
     */
    private function unsubscribeView(): bool
    {
        try {
            $view = $this->view->load(self::INDEXER_ID);
            if ($view->isEnabled()) {
                $view->unsubscribe();
            }
            return true;
        } catch (\Throwable $e) {
            $this->logger->warning(
                'Catalog completeness: the materialized view could not be unsubscribed during uninstall, so the '
                . 'changelog table ' . self::CHANGELOG_TABLE . ' and its view state were kept (the catalog tables\' '
                . 'triggers still name it). Run bin/magento setup:upgrade to clean the triggers, then drop the table. '
                . 'Reason: ' . $e->getMessage()
            );
            return false;
        }
    }

    /**
     * The topic's message status rows, its messages and its queue row, in that order: setup runs with foreign
     * key checks off, so the cascade from the messages would not reach the status rows.
     */
    private function removeQueueRows(SchemaSetupInterface $setup): void
    {
        $connection = $setup->getConnection();
        $messageTable = $setup->getTable('queue_message');
        $statusTable = $setup->getTable('queue_message_status');
        $queueTable = $setup->getTable('queue');
        if ($connection->isTableExists($messageTable) && $connection->isTableExists($statusTable)) {
            $messageIds = $connection->select()
                ->from($messageTable, ['id'])
                ->where('topic_name = ?', self::QUEUE_NAME);
            $connection->delete($statusTable, ['message_id IN (?)' => $messageIds]);
        }
        if ($connection->isTableExists($queueTable) && $connection->isTableExists($statusTable)) {
            $queueIds = $connection->select()
                ->from($queueTable, ['id'])
                ->where('name = ?', self::QUEUE_NAME);
            $connection->delete($statusTable, ['queue_id IN (?)' => $queueIds]);
        }
        if ($connection->isTableExists($messageTable)) {
            $connection->delete($messageTable, ['topic_name = ?' => self::QUEUE_NAME]);
        }
        if ($connection->isTableExists($queueTable)) {
            $connection->delete($queueTable, ['name = ?' => self::QUEUE_NAME]);
        }
    }
}
