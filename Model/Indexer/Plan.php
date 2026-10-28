<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Model\Indexer;

use BigCartEnergy\CatalogCompleteness\Api\Data\RuleSetInterface;
use BigCartEnergy\CatalogCompleteness\Model\Scoring\Context;

/**
 * What one run scores: the enabled rule sets in scope, which one is primary for each attribute set, the
 * channel profiles, and the attribute context shared by every chunk.
 */
final class Plan
{
    /**
     * @var RuleSetInterface[]
     */
    public $ruleSets = [];

    /**
     * @var int[]
     */
    public $ruleSetIds = [];

    /**
     * @var array<int, RuleSetInterface> attribute set id => its rule set
     */
    public $primaryBySet = [];

    /**
     * @var RuleSetInterface[]
     */
    public $channels = [];

    /**
     * @var int[]|null attribute set ids that bound a single attribute set rule set run; null means every product
     */
    public $attributeSetScope;

    /**
     * @var Context
     */
    public $context;
}
