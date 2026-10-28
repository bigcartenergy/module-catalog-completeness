<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Model\Hint;

use BigCartEnergy\CatalogCompleteness\Api\Data\RuleSetInterface;

/**
 * Every hint list by code: "core" for attribute set rules, one per channel. A Custom profile has none.
 */
class HintRegistry
{
    /**
     * @var array<string, HintListInterface>
     */
    private $lists = [];

    /**
     * @param HintListInterface[] $lists
     */
    public function __construct(array $lists = [])
    {
        foreach ($lists as $list) {
            if ($list instanceof HintListInterface) {
                $this->lists[$list->getCode()] = $list;
            }
        }
    }

    /**
     * The list that matches a rule set's kind and channel, or null when there is none.
     */
    public function getFor(string $kind, ?string $channelCode): ?HintListInterface
    {
        if ($kind === RuleSetInterface::KIND_CHANNEL) {
            if ($channelCode === null || $channelCode === RuleSetInterface::CHANNEL_CUSTOM) {
                return null;
            }
            return $this->lists[$channelCode] ?? null;
        }
        return $this->lists[CoreContentHints::CODE] ?? null;
    }

    /**
     * @return array<string, HintListInterface>
     */
    public function getAll(): array
    {
        return $this->lists;
    }

    /**
     * Every list as plain arrays for the editor, keyed by code.
     *
     * @return array<string, array{code: string, title: string, note: string, hints: array<int, array<string, mixed>>}>
     */
    public function toArray(): array
    {
        $out = [];
        foreach ($this->lists as $code => $list) {
            $hints = [];
            foreach ($list->getHints() as $hint) {
                $hints[] = $hint->toArray();
            }
            $out[$code] = [
                'code' => $code,
                'title' => $list->getTitle(),
                'note' => $list->getSourceNote(),
                'hints' => $hints,
            ];
        }
        return $out;
    }
}
