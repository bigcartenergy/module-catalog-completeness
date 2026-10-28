<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Ui\DataProvider\Product;

use BigCartEnergy\CatalogCompleteness\Model\ResourceModel\Score as ScoreResource;
use Magento\Framework\Data\Collection;
use Magento\Ui\DataProvider\AddFieldToCollectionInterface;

/**
 * Joins the primary score onto the product grid collection. The column declares addField, so the data
 * provider calls this for every grid load, filtered or not (investigation D2).
 */
class AddScoreFieldToCollection implements AddFieldToCollectionInterface
{
    public const FIELD = 'bce_completeness_score';

    /**
     * @inheritDoc
     */
    public function addField(Collection $collection, $field, $alias = null)
    {
        self::join($collection);
    }

    /**
     * Add the join once, whoever asks first (the column or the filter).
     *
     * @param Collection $collection
     */
    public static function join(Collection $collection): void
    {
        if (!method_exists($collection, 'joinField') || !method_exists($collection, 'getSelect')) {
            return;
        }
        $alias = self::tableAlias();
        foreach (array_keys($collection->getSelect()->getPart(\Magento\Framework\DB\Select::FROM)) as $existing) {
            if ($existing === $alias) {
                return;
            }
        }
        $collection->joinField(
            self::FIELD,
            ScoreResource::TABLE,
            'score',
            'product_id=entity_id',
            '{{table}}.is_primary=1',
            'left'
        );
    }

    /**
     * The alias the EAV collection gives the joined table.
     */
    public static function tableAlias(): string
    {
        return \Magento\Eav\Model\Entity\Collection\AbstractCollection::ATTRIBUTE_TABLE_ALIAS_PREFIX . self::FIELD;
    }
}
