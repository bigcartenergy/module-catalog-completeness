<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Rule extends AbstractDb
{
    public const TABLE = 'bce_completeness_rule';

    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init(self::TABLE, 'rule_id');
    }

    /**
     * Rule rows of the given rule sets, in rule set order.
     *
     * @param int[] $rulesetIds
     * @return array<int, array<string, mixed>>
     */
    public function fetchRows(array $rulesetIds): array
    {
        $rulesetIds = array_values(array_unique(array_map('intval', $rulesetIds)));
        if (!$rulesetIds) {
            return [];
        }
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable())
            ->where('ruleset_id IN (?)', $rulesetIds)
            ->order(['ruleset_id ASC', 'sort_order ASC', 'rule_id ASC']);
        return $connection->fetchAll($select);
    }
}
