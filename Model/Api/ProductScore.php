<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Model\Api;

use BigCartEnergy\CatalogCompleteness\Api\Data\ProductScoreInterface;
use Magento\Framework\Api\AbstractSimpleObject;

class ProductScore extends AbstractSimpleObject implements ProductScoreInterface
{
    /**
     * @inheritDoc
     */
    public function getProductId()
    {
        return (int)$this->_get(self::PRODUCT_ID);
    }

    /**
     * @inheritDoc
     */
    public function getRulesetId()
    {
        return (int)$this->_get(self::RULESET_ID);
    }

    /**
     * @inheritDoc
     */
    public function getRulesetName()
    {
        return (string)$this->_get(self::RULESET_NAME);
    }

    /**
     * @inheritDoc
     */
    public function getIsPrimary()
    {
        return (bool)$this->_get(self::IS_PRIMARY);
    }

    /**
     * @inheritDoc
     */
    public function getScore()
    {
        $score = $this->_get(self::SCORE);
        return $score === null ? null : (int)$score;
    }

    /**
     * @inheritDoc
     */
    public function getBand()
    {
        $band = $this->_get(self::BAND);
        return $band === null ? null : (string)$band;
    }

    /**
     * @inheritDoc
     */
    public function getBandLabel()
    {
        $label = $this->_get(self::BAND_LABEL);
        return $label === null ? null : (string)$label;
    }

    /**
     * @inheritDoc
     */
    public function getPassedWeight()
    {
        return (int)$this->_get(self::PASSED_WEIGHT);
    }

    /**
     * @inheritDoc
     */
    public function getTotalWeight()
    {
        return (int)$this->_get(self::TOTAL_WEIGHT);
    }

    /**
     * @inheritDoc
     */
    public function getMissing()
    {
        $missing = $this->_get(self::MISSING);
        return is_array($missing) ? array_values(array_map('strval', $missing)) : [];
    }

    /**
     * @inheritDoc
     */
    public function getScoredAt()
    {
        $scoredAt = $this->_get(self::SCORED_AT);
        return $scoredAt === null ? null : (string)$scoredAt;
    }

    /**
     * @inheritDoc
     */
    public function getState()
    {
        $state = $this->_get(self::STATE);
        if (in_array($state, [self::STATE_SCORED, self::STATE_NO_RULES_ASSIGNED, self::STATE_NOT_CALCULATED], true)) {
            return $state;
        }
        return $this->getScore() === null ? self::STATE_NO_RULES_ASSIGNED : self::STATE_SCORED;
    }
}
