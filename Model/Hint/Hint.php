<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Model\Hint;

/**
 * One suggestion in the editor's "Suggested" list: a role, a one-line note, and the rule type, parameter and
 * weight to prefill when the merchant clicks Add, with the attribute where the hint names one (the core content
 * hints do). A channel hint names a role instead, and the merchant chooses the attribute that holds it.
 */
class Hint
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

    /**
     * @var string|null
     */
    private $attributeCode;

    public function __construct(
        string $role,
        string $label,
        string $note,
        string $ruleType,
        ?string $parameter,
        int $weight,
        ?string $attributeCode = null
    ) {
        $this->role = $role;
        $this->label = $label;
        $this->note = $note;
        $this->ruleType = $ruleType;
        $this->parameter = $parameter;
        $this->weight = $weight;
        $this->attributeCode = $attributeCode;
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
     * The attribute the new row starts with, or null when the merchant chooses it.
     */
    public function getAttributeCode(): ?string
    {
        return $this->attributeCode;
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
            'attribute_code' => $this->attributeCode,
        ];
    }
}
