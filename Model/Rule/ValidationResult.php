<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Model\Rule;

/**
 * Normalised rule rows plus the errors found, each tied to a row and a field so the editor can focus it.
 */
final class ValidationResult
{
    /**
     * @var array<int, array<string, mixed>>
     */
    private $rules = [];

    /**
     * @var array<int, array{row: int|null, field: string|null, message: string}>
     */
    private $errors = [];

    /**
     * @param array<string, mixed> $rule
     */
    public function addRule(array $rule): void
    {
        $this->rules[] = $rule;
    }

    public function addError(?int $row, ?string $field, string $message): void
    {
        $this->errors[] = ['row' => $row, 'field' => $field, 'message' => $message];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getRules(): array
    {
        return $this->rules;
    }

    /**
     * @return array<int, array{row: int|null, field: string|null, message: string}>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    public function isValid(): bool
    {
        return !$this->errors;
    }

    /**
     * Row indexes that carry at least one error.
     *
     * @return int[]
     */
    public function getInvalidRows(): array
    {
        $rows = [];
        foreach ($this->errors as $error) {
            if ($error['row'] !== null && !in_array($error['row'], $rows, true)) {
                $rows[] = $error['row'];
            }
        }
        return $rows;
    }

    /**
     * @return string[]
     */
    public function getMessages(): array
    {
        $messages = [];
        foreach ($this->errors as $error) {
            $messages[] = $error['message'];
        }
        return $messages;
    }
}
