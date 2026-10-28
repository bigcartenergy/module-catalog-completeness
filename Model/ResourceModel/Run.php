<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Run extends AbstractDb
{
    public const TABLE = 'bce_completeness_run';

    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init(self::TABLE, 'run_id');
    }

    /**
     * Update the progress counters of a run without loading the model.
     */
    public function updateProgress(int $runId, int $done, int $total): void
    {
        $this->getConnection()->update(
            $this->getMainTable(),
            ['done' => $done, 'total' => $total],
            ['run_id = ?' => $runId]
        );
    }
}
