<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Model\Rule;

use BigCartEnergy\CatalogCompleteness\Api\Data\RuleInterface;
use BigCartEnergy\CatalogCompleteness\Model\Channel\ChannelRegistry;
use BigCartEnergy\CatalogCompleteness\Model\Rule as RuleModel;

/**
 * Server-side validation of a rules payload, used on save and on preview. Enums, integer ranges, parameter
 * caps, attribute codes against the catalog, product types against the type list, patterns compiled.
 */
class PayloadValidator
{
    public const MAX_RULES = 200;
    public const MAX_PARAMETER_LENGTH = 2000;
    public const MAX_MIN_LENGTH = 100000;
    public const MAX_MIN_COUNT = 1000;
    public const MAX_ATTRIBUTE_CODE_LENGTH = 255;

    /**
     * @var CatalogLookupInterface
     */
    private $lookup;

    /**
     * @var ChannelRegistry
     */
    private $channels;

    public function __construct(CatalogLookupInterface $lookup, ChannelRegistry $channels)
    {
        $this->lookup = $lookup;
        $this->channels = $channels;
    }

    /**
     * Validate raw rows (from the form, the preview request or the REST layer) and return normalised rows.
     *
     * @param array<int, array<string, mixed>> $rows
     * @param string $kind attribute_set or channel
     * @param string|null $channelCode the profile's channel; roles must belong to it
     * @param bool $allowEmptyAttribute preview mode: rows without an attribute are reported but not fatal
     */
    public function validateRules(
        array $rows,
        string $kind,
        ?string $channelCode,
        bool $allowEmptyAttribute = false
    ): ValidationResult {
        $result = new ValidationResult();
        if (count($rows) > self::MAX_RULES) {
            $result->addError(null, null, (string)__('A rule set can hold at most %1 rules.', self::MAX_RULES));
            return $result;
        }
        $isChannel = $kind === \BigCartEnergy\CatalogCompleteness\Api\Data\RuleSetInterface::KIND_CHANNEL;
        $allowedRoles = $isChannel ? array_keys($this->channels->getRoles($channelCode)) : [];
        $typeIds = $this->lookup->getProductTypeIds();
        $seen = [];
        $index = 0;
        foreach (array_values($rows) as $row) {
            if (!is_array($row)) {
                $result->addError($index, null, (string)__('Rule %1 is not readable.', $index + 1));
                $index++;
                continue;
            }
            $normalised = $this->normaliseRow($row, $index, $isChannel, $allowedRoles, $typeIds, $allowEmptyAttribute, $result);
            $code = $normalised['attribute_code'];
            if ($code !== '') {
                if (isset($seen[$code])) {
                    $result->addError(
                        $index,
                        'attribute_code',
                        (string)__(
                            '%1 already has a rule (rule %2). Use one rule per attribute.',
                            $normalised['attribute_label'],
                            $seen[$code] + 1
                        )
                    );
                } else {
                    $seen[$code] = $index;
                }
            }
            $result->addRule($normalised);
            $index++;
        }
        return $result;
    }

    /**
     * @param array<string, mixed> $row
     * @param string[] $allowedRoles
     * @param string[] $typeIds
     * @return array<string, mixed>
     */
    private function normaliseRow(
        array $row,
        int $index,
        bool $isChannel,
        array $allowedRoles,
        array $typeIds,
        bool $allowEmptyAttribute,
        ValidationResult $result
    ): array {
        $ruleId = isset($row['rule_id']) && (int)$row['rule_id'] > 0 ? (int)$row['rule_id'] : null;

        $role = null;
        if ($isChannel) {
            $role = trim((string)($row['role'] ?? ''));
            if ($role === '' || !in_array($role, $allowedRoles, true)) {
                $role = RuleInterface::ROLE_CUSTOM;
            }
        }

        $code = trim((string)($row['attribute_code'] ?? ''));
        $descriptor = null;
        $label = $code;
        if ($code === '') {
            if (!$allowEmptyAttribute) {
                $result->addError($index, 'attribute_code', (string)__('Choose the attribute this rule checks.'));
            }
        } elseif (strlen($code) > self::MAX_ATTRIBUTE_CODE_LENGTH || !preg_match('/^[a-z0-9_]+$/i', $code)) {
            $result->addError($index, 'attribute_code', (string)__('The attribute code %1 is not valid.', $code));
            $code = '';
        } else {
            $descriptor = $this->lookup->getDescriptor($code);
            if ($descriptor === null) {
                $result->addError($index, 'attribute_code', (string)__('The attribute %1 does not exist.', $code));
                $code = '';
            } else {
                $label = $descriptor->getLabel();
            }
        }

        $type = trim((string)($row['rule_type'] ?? ''));
        if (!in_array($type, RuleInterface::TYPES, true)) {
            $result->addError($index, 'rule_type', (string)__('Choose a rule type.'));
            $type = '';
        }

        $parameter = $this->normaliseParameter($type, $row['parameter'] ?? null, $descriptor, $index, $result);

        if ($descriptor !== null && $type !== '') {
            $this->checkCompatibility($type, $descriptor, $index, $result);
        }

        $weightRaw = $row['weight'] ?? null;
        $weight = is_numeric($weightRaw) ? (int)$weightRaw : 0;
        if (!in_array($weight, RuleInterface::WEIGHTS, true)) {
            $result->addError(
                $index,
                'weight',
                (string)__('Choose a weight. Without one, this rule adds 0 % to the score.')
            );
            $weight = 0;
        }

        $appliesTo = [];
        foreach (RuleModel::normalizeTypes($row['applies_to'] ?? []) as $typeId) {
            if (!in_array($typeId, $typeIds, true)) {
                $result->addError($index, 'applies_to', (string)__('Unknown product type %1.', $typeId));
                continue;
            }
            $appliesTo[] = $typeId;
        }
        // Every type selected means "all": store the empty list so new product types are covered too.
        if ($typeIds && count($appliesTo) === count($typeIds)) {
            $appliesTo = [];
        }

        return [
            'rule_id' => $ruleId,
            'role' => $role,
            'attribute_code' => $code,
            'attribute_label' => $label,
            'rule_type' => $type,
            'parameter' => $parameter,
            'weight' => $weight,
            'applies_to' => $appliesTo,
        ];
    }

    /**
     * @param mixed $raw
     */
    private function normaliseParameter(
        string $type,
        $raw,
        ?AttributeDescriptor $descriptor,
        int $index,
        ValidationResult $result
    ): ?string {
        $value = is_array($raw) ? '' : trim((string)$raw);
        if (Text::length($value) > self::MAX_PARAMETER_LENGTH) {
            $result->addError(
                $index,
                'parameter',
                (string)__('The parameter is too long (at most %1 characters).', self::MAX_PARAMETER_LENGTH)
            );
            return null;
        }
        switch ($type) {
            case RuleInterface::TYPE_MIN_LENGTH:
                if ($value === '' || !preg_match('/^\d+$/', $value) || (int)$value < 1 || (int)$value > self::MAX_MIN_LENGTH) {
                    $result->addError($index, 'parameter', (string)__('Enter the minimum number of characters.'));
                    return null;
                }
                return (string)(int)$value;
            case RuleInterface::TYPE_MIN_COUNT:
                if ($value === '' || !preg_match('/^\d+$/', $value) || (int)$value < 1 || (int)$value > self::MAX_MIN_COUNT) {
                    $isGallery = $descriptor !== null && $descriptor->isGallery();
                    $result->addError(
                        $index,
                        'parameter',
                        $isGallery
                            ? (string)__('Enter the minimum number of images.')
                            : (string)__('Enter the minimum number of values.')
                    );
                    return null;
                }
                return (string)(int)$value;
            case RuleInterface::TYPE_ALLOWED_VALUES:
                $entries = [];
                foreach (explode(',', $value) as $entry) {
                    $entry = trim($entry);
                    if ($entry !== '' && !in_array($entry, $entries, true)) {
                        $entries[] = $entry;
                    }
                }
                if (!$entries) {
                    $result->addError($index, 'parameter', (string)__('Enter the allowed values, separated by commas.'));
                    return null;
                }
                return implode(', ', $entries);
            case RuleInterface::TYPE_PATTERN:
                if ($value === '') {
                    $result->addError($index, 'parameter', (string)__('Enter a pattern.'));
                    return null;
                }
                if (Text::length($value) > Pattern::MAX_LENGTH) {
                    $result->addError(
                        $index,
                        'parameter',
                        (string)__('The pattern is too long (at most %1 characters).', Pattern::MAX_LENGTH)
                    );
                    return null;
                }
                if (Pattern::looksDelimited($value)) {
                    $result->addError(
                        $index,
                        'parameter',
                        (string)__('Enter the pattern body only, without delimiters or modifiers (for example ^[A-Z]{3}$ rather than /^[A-Z]{3}$/i).')
                    );
                    return null;
                }
                if (Pattern::hasNestedQuantifier($value)) {
                    $result->addError(
                        $index,
                        'parameter',
                        (string)__('This pattern repeats a group that already repeats (for example (a+)+), which can take far too long to run. Rewrite it without nested repetition.')
                    );
                    return null;
                }
                if (!Pattern::isValid($value)) {
                    $result->addError($index, 'parameter', (string)__('This pattern does not compile. Enter the pattern body only, without delimiters or modifiers.'));
                    return null;
                }
                return $value;
            default:
                // present and gt_zero take no parameter; anything sent is dropped.
                return null;
        }
    }

    private function checkCompatibility(
        string $type,
        AttributeDescriptor $descriptor,
        int $index,
        ValidationResult $result
    ): void {
        if ($descriptor->isGallery()) {
            if (!in_array($type, [RuleInterface::TYPE_PRESENT, RuleInterface::TYPE_MIN_COUNT], true)) {
                $result->addError($index, 'rule_type', (string)__('Images take Present or Minimum count.'));
            }
            return;
        }
        if ($type === RuleInterface::TYPE_MIN_COUNT && !$descriptor->isMultiselect()) {
            $result->addError(
                $index,
                'rule_type',
                (string)__('Minimum count works for images and multiselect attributes only.')
            );
        }
    }
}
