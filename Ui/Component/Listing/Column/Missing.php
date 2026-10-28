<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Ui\Component\Listing\Column;

use BigCartEnergy\CatalogCompleteness\Model\Score\ProductScoreBuilder;
use BigCartEnergy\CatalogCompleteness\Model\Score\RuleLabels;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

/**
 * The Missing column: the first three attribute labels as tags, "+N", and the full list for the popover.
 * Everything is plain text; the cell template binds it as text.
 */
class Missing extends Column
{
    public const TAGS_SHOWN = 3;

    /**
     * @var RuleLabels
     */
    private $ruleLabels;

    /**
     * @var ProductScoreBuilder
     */
    private $builder;

    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        RuleLabels $ruleLabels,
        ProductScoreBuilder $builder,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
        $this->ruleLabels = $ruleLabels;
        $this->builder = $builder;
    }

    /**
     * @inheritDoc
     */
    public function prepareDataSource(array $dataSource)
    {
        if (!isset($dataSource['data']['items'])) {
            return $dataSource;
        }
        $rulesetIds = [];
        foreach ($dataSource['data']['items'] as $item) {
            if (isset($item['ruleset_id'])) {
                $rulesetIds[] = (int)$item['ruleset_id'];
            }
        }
        $this->ruleLabels->preload($rulesetIds);
        $field = $this->getData('name');
        foreach ($dataSource['data']['items'] as &$item) {
            $rulesetId = (int)($item['ruleset_id'] ?? 0);
            $ids = $this->builder->decodeMissing($item[$field] ?? null);
            $tags = [];
            $all = [];
            foreach ($ids as $ruleId) {
                $rule = $this->ruleLabels->getRule($rulesetId, (int)$ruleId);
                if ($rule === null) {
                    continue;
                }
                $tags[] = $this->ruleLabels->attributeLabel($rule);
                $all[] = [
                    'rule' => $this->ruleLabels->describe($rule),
                    'weight' => $this->ruleLabels->weightLabel($rule),
                ];
            }
            $item[$field] = $tags;
            $item[$field . '_tags'] = array_slice($tags, 0, self::TAGS_SHOWN);
            $item[$field . '_more'] = count($tags) > self::TAGS_SHOWN ? '+' . (count($tags) - self::TAGS_SHOWN) : '';
            $item[$field . '_count'] = count($tags);
            $item[$field . '_all'] = $all;
        }
        unset($item);
        return $dataSource;
    }
}
