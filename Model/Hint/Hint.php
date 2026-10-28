<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Model\Hint;

/**
 * One suggestion in the editor's "Suggested" list: a role, a one-line note, and the rule type, parameter and
 * weight to prefill when the merchant clicks Add. The attribute is always left for the merchant to choose.
 */
final class Hint
{
    /**
     * @var string
     */
    private $role;

    /**
     * @var string
     */
    private $label;

    /**
     * @var string
     */
    private $note;

    /**
     * @var string
     */
    private $ruleType;

    /**
     * @var string|null
     */
    private $parameter;

    /**
     * @var int
     */
    private $weight;

    public function __construct(
        string $role,
        string $label,
        string $note,
        string $ruleType,
        ?string $parameter,
        int $weight
    ) {
        $this->role = $role;
        $this->label = $label;
        $this->note = $note;
        $this->ruleType = $ruleType;
        $this->parameter = $parameter;
        $this->weight = $weight;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getNote(): string
    {
        return $this->note;
    }

    public function getRuleType(): string
    {
        return $this->ruleType;
    }

    public function getParameter(): ?string
    {
        return $this->parameter;
    }

    public function getWeight(): int
    {
        return $this->weight;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'role' => $this->role,
            'label' => $this->label,
            'note' => $this->note,
            'rule_type' => $this->ruleType,
            'parameter' => $this->parameter,
            'weight' => $this->weight,
        ];
    }
}
