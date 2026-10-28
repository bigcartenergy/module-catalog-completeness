<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Model\Config\Backend;

use Magento\Framework\App\Config\Value;
use Magento\Framework\Exception\LocalizedException;

/**
 * The two band thresholds: whole numbers from 1 to 99, and Good must start above Needs work. Both fields
 * save in one request, so each compares against the other's posted value.
 */
class Threshold extends Value
{
    public const FIELD_GOOD = 'good_from';
    public const FIELD_NEEDS_WORK = 'needs_work_from';

    /**
     * @inheritDoc
     */
    public function beforeSave()
    {
        $own = self::toThreshold($this->getValue(), (string)$this->getField());
        $this->setValue((string)$own);
        $otherField = $this->getField() === self::FIELD_GOOD ? self::FIELD_NEEDS_WORK : self::FIELD_GOOD;
        $otherRaw = $this->getFieldsetDataValue($otherField);
        if ($otherRaw === null || $otherRaw === '' || !empty($this->getFieldsetDataValue($otherField . '_inherit'))) {
            // The other field is not posted or uses the system value: compare with its effective value.
            $otherRaw = $this->_config->getValue(
                'bce_completeness/thresholds/' . $otherField,
                $this->getScope() ?: 'default',
                $this->getScopeId()
            );
        }
        if ($otherRaw === null || $otherRaw === '') {
            return parent::beforeSave();
        }
        $other = self::toThreshold($otherRaw, $otherField);
        $good = $this->getField() === self::FIELD_GOOD ? $own : $other;
        $needsWork = $this->getField() === self::FIELD_GOOD ? $other : $own;
        if ($good <= $needsWork) {
            throw new LocalizedException(
                __('Good from must be higher than Needs work from (you entered %1 and %2).', $good, $needsWork)
            );
        }
        return parent::beforeSave();
    }

    /**
     * @param mixed $raw
     * @throws LocalizedException
     */
    public static function toThreshold($raw, string $field): int
    {
        $text = trim((string)$raw);
        $label = $field === self::FIELD_GOOD ? __('Good from') : __('Needs work from');
        if ($text === '' || !preg_match('/^\d{1,3}$/', $text) || (int)$text < 1 || (int)$text > 99) {
            throw new LocalizedException(__('%1 must be a whole number from 1 to 99.', $label));
        }
        return (int)$text;
    }
}
