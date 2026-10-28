<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Model\ResourceModel;

use BigCartEnergy\CatalogCompleteness\Api\Data\RuleInterface;
use BigCartEnergy\CatalogCompleteness\Api\Data\RuleSetInterface;
use BigCartEnergy\CatalogCompleteness\Model\Rule as RuleModel;
use BigCartEnergy\CatalogCompleteness\Model\RuleFactory;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Model\ResourceModel\Db\Context;

/**
 * Persists a rule set with its attribute sets and rules. Rules keep their ids across saves so that stored
 * failures and missing lists stay meaningful until the next recalculation.
 */
class RuleSet extends AbstractDb
{
    public const TABLE = 'bce_completeness_ruleset';
    public const TABLE_ATTRIBUTE_SET = 'bce_completeness_ruleset_attribute_set';

    /**
     * @var RuleFactory
     */
    private $ruleFactory;

    public function __construct(Context $context, RuleFactory $ruleFactory, ?string $connectionName = null)
    {
        parent::__construct($context, $connectionName);
        $this->ruleFactory = $ruleFactory;
    }

    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init(self::TABLE, 'ruleset_id');
    }

    /**
     * @inheritDoc
     */
    protected function _afterLoad(AbstractModel $object)
    {
        parent::_afterLoad($object);
        $id = (int)$object->getId();
        if ($id > 0) {
            $sets = $this->fetchAttributeSetIds([$id]);
            $object->setData(RuleSetInterface::ATTRIBUTE_SET_IDS, $sets[$id] ?? []);
            $rules = $this->fetchRules([$id]);
            $object->setData(RuleSetInterface::RULES, $rules[$id] ?? []);
        }
        return $this;
    }

    /**
     * @inheritDoc
     */
    protected function _afterSave(AbstractModel $object)
    {
        parent::_afterSave($object);
        $id = (int)$object->getId();
        if ($id <= 0) {
            return $this;
        }
        $this->saveAttributeSets($id, $object->getData(RuleSetInterface::ATTRIBUTE_SET_IDS) ?: []);
        $rules = $object->getData(RuleSetInterface::RULES);
        if (is_array($rules)) {
            $this->saveRules($id, $rules);
        }
        return $this;
    }

    /**
     * @param int[] $rulesetIds
     * @return array<int, int[]> ruleset id => attribute set ids
     */
    public function fetchAttributeSetIds(array $rulesetIds): array
    {
        $rulesetIds = array_values(array_unique(array_map('intval', $rulesetIds)));
        if (!$rulesetIds) {
            return [];
        }
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getTable(self::TABLE_ATTRIBUTE_SET), ['ruleset_id', 'attribute_set_id'])
            ->where('ruleset_id IN (?)', $rulesetIds)
            ->order('attribute_set_id ASC');
        $result = [];
        foreach ($connection->fetchAll($select) as $row) {
            $result[(int)$row['ruleset_id']][] = (int)$row['attribute_set_id'];
        }
        return $result;
    }

    /**
     * @param int[] $rulesetIds
     * @return array<int, RuleInterface[]> ruleset id => rules in order
     */
    public function fetchRules(array $rulesetIds): array
    {
        $rulesetIds = array_values(array_unique(array_map('intval', $rulesetIds)));
        if (!$rulesetIds) {
            return [];
        }
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getTable(Rule::TABLE))
            ->where('ruleset_id IN (?)', $rulesetIds)
            ->order(['ruleset_id ASC', 'sort_order ASC', 'rule_id ASC']);
        $result = [];
        foreach ($connection->fetchAll($select) as $row) {
            $rule = $this->ruleFactory->create();
            $row[RuleInterface::APPLIES_TO] = RuleModel::normalizeTypes($row[RuleInterface::APPLIES_TO] ?? '');
            $rule->setData($row);
            $result[(int)$row['ruleset_id']][] = $rule;
        }
        return $result;
    }

    /**
     * Attribute set ids already covered by an enabled or disabled attribute set rule set, other than the given one.
     *
     * @param int[] $attributeSetIds
     * @return array<int, int> attribute set id => ruleset id
     */
    public function findConflictingAttributeSets(array $attributeSetIds, ?int $excludeRulesetId): array
    {
        $attributeSetIds = array_values(array_unique(array_map('intval', $attributeSetIds)));
        if (!$attributeSetIds) {
            return [];
        }
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from(['rs' => $this->getTable(self::TABLE_ATTRIBUTE_SET)], ['attribute_set_id', 'ruleset_id'])
            ->join(['r' => $this->getMainTable()], 'r.ruleset_id = rs.ruleset_id', [])
            ->where('r.kind = ?', RuleSetInterface::KIND_ATTRIBUTE_SET)
            ->where('rs.attribute_set_id IN (?)', $attributeSetIds);
        if ($excludeRulesetId !== null && $excludeRulesetId > 0) {
            $select->where('rs.ruleset_id <> ?', $excludeRulesetId);
        }
        $result = [];
        foreach ($connection->fetchAll($select) as $row) {
            $result[(int)$row['attribute_set_id']] = (int)$row['ruleset_id'];
        }
        return $result;
    }

    /**
     * @param int[] $attributeSetIds
     */
    private function saveAttributeSets(int $rulesetId, array $attributeSetIds): void
    {
        $connection = $this->getConnection();
        $table = $this->getTable(self::TABLE_ATTRIBUTE_SET);
        $connection->delete($table, ['ruleset_id = ?' => $rulesetId]);
        $rows = [];
        foreach (array_unique(array_map('intval', $attributeSetIds)) as $setId) {
            if ($setId > 0) {
                $rows[] = ['ruleset_id' => $rulesetId, 'attribute_set_id' => $setId];
            }
        }
        if ($rows) {
            $connection->insertMultiple($table, $rows);
        }
    }

    /**
     * @param RuleInterface[] $rules
     */
    private function saveRules(int $rulesetId, array $rules): void
    {
        $connection = $this->getConnection();
        $table = $this->getTable(Rule::TABLE);
        $existing = array_map(
            'intval',
            $connection->fetchCol(
                $connection->select()->from($table, 'rule_id')->where('ruleset_id = ?', $rulesetId)
            )
        );
        $keep = [];
        $sortOrder = 0;
        foreach ($rules as $rule) {
            if (!$rule instanceof RuleInterface) {
                continue;
            }
            $data = [
                'ruleset_id' => $rulesetId,
                'sort_order' => $sortOrder++,
                'role' => $rule->getRole(),
                'attribute_code' => $rule->getAttributeCode(),
                'rule_type' => $rule->getRuleType(),
                'parameter' => $rule->getParameter(),
                'weight' => (int)$rule->getWeight(),
                'applies_to' => implode(',', $rule->getAppliesTo()),
            ];
            $ruleId = (int)$rule->getRuleId();
            if ($ruleId > 0 && in_array($ruleId, $existing, true)) {
                $connection->update($table, $data, ['rule_id = ?' => $ruleId]);
            } else {
                $connection->insert($table, $data);
                $ruleId = (int)$connection->lastInsertId($table);
                $rule->setRuleId($ruleId);
            }
            $rule->setRulesetId($rulesetId);
            $keep[] = $ruleId;
        }
        $remove = array_values(array_diff($existing, $keep));
        if ($remove) {
            $connection->delete($table, ['rule_id IN (?)' => $remove]);
        }
    }
}
