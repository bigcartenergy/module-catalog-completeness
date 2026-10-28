<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Model\Hint;

/**
 * A static list of hints for one kind of rule set: the core content fields for attribute set rules, or the
 * fields a channel commonly requires or recommends for every listing. Nothing is added without a click.
 */
interface HintListInterface
{
    /**
     * "core" for attribute set rules, or a channel code.
     */
    public function getCode(): string;

    /**
     * The heading of the Suggested list.
     */
    public function getTitle(): string;

    /**
     * One line under the heading naming the source of the list.
     */
    public function getSourceNote(): string;

    /**
     * @return Hint[]
     */
    public function getHints(): array;
}
