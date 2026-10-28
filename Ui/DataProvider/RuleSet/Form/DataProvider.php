<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Ui\DataProvider\RuleSet\Form;

use BigCartEnergy\CatalogCompleteness\Api\Data\RuleInterface;
use BigCartEnergy\CatalogCompleteness\Api\Data\RuleSetInterface;
use BigCartEnergy\CatalogCompleteness\Controller\Adminhtml\Ruleset\Save;
use BigCartEnergy\CatalogCompleteness\Model\Catalog\Lookup;
use BigCartEnergy\CatalogCompleteness\Model\Channel\ChannelRegistry;
use BigCartEnergy\CatalogCompleteness\Model\Hint\HintRegistry;
use BigCartEnergy\CatalogCompleteness\Model\ResourceModel\RuleSet\CollectionFactory;
use BigCartEnergy\CatalogCompleteness\Model\Rule as RuleModel;
use BigCartEnergy\CatalogCompleteness\Model\RuleSet;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory as AttributeCollectionFactory;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\UrlInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;

/**
 * Data and configuration of the rule set form: the rule set, the attribute picker options with their
 * attribute set membership, the roles per channel, the hint lists, the product types and the permissions.
 */
class DataProvider extends AbstractDataProvider
{
    public const ACL_EDIT = 'BigCartEnergy_CatalogCompleteness::rulesets_edit';
    public const ACL_RECALCULATE = 'BigCartEnergy_CatalogCompleteness::recalculate';

    /**
     * @var DataPersistorInterface
     */
    private $dataPersistor;

    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @var Lookup
     */
    private $lookup;

    /**
     * @var AttributeCollectionFactory
     */
    private $attributeCollectionFactory;

    /**
     * @var ChannelRegistry
     */
    private $channels;

    /**
     * @var HintRegistry
     */
    private $hints;

    /**
     * @var AuthorizationInterface
     */
    private $authorization;

    /**
     * @var UrlInterface
     */
    private $urlBuilder;

    /**
     * @var array<int|string, array<string, mixed>>|null
     */
    private $loadedData;

    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collectionFactory,
        DataPersistorInterface $dataPersistor,
        RequestInterface $request,
        Lookup $lookup,
        AttributeCollectionFactory $attributeCollectionFactory,
        ChannelRegistry $channels,
        HintRegistry $hints,
        AuthorizationInterface $authorization,
        UrlInterface $urlBuilder,
        array $meta = [],
        array $data = []
    ) {
        $this->collection = $collectionFactory->create();
        $this->dataPersistor = $dataPersistor;
        $this->request = $request;
        $this->lookup = $lookup;
        $this->attributeCollectionFactory = $attributeCollectionFactory;
        $this->channels = $channels;
        $this->hints = $hints;
        $this->authorization = $authorization;
        $this->urlBuilder = $urlBuilder;
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }

    /**
     * @inheritDoc
     */
    public function getData()
    {
        if ($this->loadedData !== null) {
            return $this->loadedData;
        }
        $this->loadedData = [];
        $id = (int)$this->request->getParam('id');
        if ($id > 0) {
            /** @var RuleSet|null $ruleSet */
            $ruleSet = $this->collection->addFieldToFilter('ruleset_id', $id)->getFirstItem();
            if ($ruleSet && $ruleSet->getId()) {
                $this->loadedData[$id] = $this->ruleSetToArray($ruleSet);
            }
        }
        $persisted = $this->dataPersistor->get(Save::PERSIST_KEY);
        if (is_array($persisted) && $persisted) {
            $key = isset($persisted['ruleset_id']) && (int)$persisted['ruleset_id'] > 0 ? (int)$persisted['ruleset_id'] : '';
            $this->loadedData[$key] = $this->normalisePersisted($persisted);
            $this->dataPersistor->clear(Save::PERSIST_KEY);
        }
        return $this->loadedData;
    }

    /**
     * @inheritDoc
     */
    public function getMeta()
    {
        $meta = parent::getMeta();
        $canEdit = $this->authorization->isAllowed(self::ACL_EDIT);
        $meta['bce_config'] = [
            'arguments' => [
                'data' => [
                    'config' => [
                        'componentType' => 'container',
                        '__disableTmpl' => true,
                        'ignoreTmpls' => [
                            'bceAttributes' => true,
                            'bceAttributeSetNames' => true,
                            'bceAttributeSetCounts' => true,
                            'bceProductTypes' => true,
                            'bceRoles' => true,
                            'bceHints' => true,
                        ],
                        'bceAttributes' => $this->attributeOptions(),
                        'bceAttributeSetNames' => $this->attributeSetNames(),
                        'bceAttributeSetCounts' => $this->lookup->countProductsPerSet(),
                        'bceProductTypes' => $this->lookup->getProductTypeLabels(),
                        'bceRoles' => $this->rolesPerChannel(),
                        'bceHints' => $this->hints->toArray(),
                        'bceCanEdit' => $canEdit,
                        'bceReadOnly' => !$canEdit,
                        'bceCanRecalculate' => $this->authorization->isAllowed(self::ACL_RECALCULATE),
                        'bceAffectedUrl' => $this->urlBuilder->getUrl('bce_completeness/ruleset/affected'),
                        'bcePreviewUrl' => $this->urlBuilder->getUrl('bce_completeness/score/preview'),
                        'bceSearchUrl' => $this->urlBuilder->getUrl('bce_completeness/score/search'),
                        'bceDashboardUrl' => $this->urlBuilder->getUrl('bce_completeness/dashboard/index'),
                    ],
                ],
            ],
        ];
        return $meta;
    }

    public function canEdit(): bool
    {
        return $this->authorization->isAllowed(self::ACL_EDIT);
    }

    /**
     * @return array<string, mixed>
     */
    private function ruleSetToArray(RuleSet $ruleSet): array
    {
        $rules = [];
        $position = 0;
        foreach ($ruleSet->getRules() as $rule) {
            $rules[] = $this->ruleToRow($rule, $position++);
        }
        return [
            'ruleset_id' => (int)$ruleSet->getId(),
            'name' => $ruleSet->getName(),
            'kind' => $ruleSet->getKind(),
            'channel_code' => $ruleSet->getChannelCode() ?? '',
            'description' => $ruleSet->getDescription() ?? '',
            'is_enabled' => $ruleSet->getIsEnabled() ? '1' : '0',
            'attribute_set_ids' => array_map('strval', $ruleSet->getAttributeSetIds()),
            'rules' => $rules,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function ruleToRow(RuleInterface $rule, int $position): array
    {
        return [
            'record_id' => $position,
            'position' => $position,
            'rule_id' => $rule->getRuleId() ?? '',
            'role' => $rule->getRole() ?? '',
            'attribute_code' => $rule->getAttributeCode(),
            'rule_type' => $rule->getRuleType(),
            'parameter' => $rule->getParameter() ?? '',
            'weight' => $rule->getWeight() > 0 ? (string)$rule->getWeight() : '',
            'applies_to' => $rule->getAppliesTo() ?: $this->lookup->getProductTypeIds(),
        ];
    }

    /**
     * Form data kept after a failed save, back into the shape the form expects.
     *
     * @param array<string, mixed> $persisted
     * @return array<string, mixed>
     */
    private function normalisePersisted(array $persisted): array
    {
        $rules = [];
        $position = 0;
        foreach (is_array($persisted['rules'] ?? null) ? $persisted['rules'] : [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $rules[] = [
                'record_id' => $position,
                'position' => $position++,
                'rule_id' => isset($row['rule_id']) && (int)$row['rule_id'] > 0 ? (int)$row['rule_id'] : '',
                'role' => (string)($row['role'] ?? ''),
                'attribute_code' => (string)($row['attribute_code'] ?? ''),
                'rule_type' => (string)($row['rule_type'] ?? ''),
                'parameter' => (string)($row['parameter'] ?? ''),
                'weight' => (string)($row['weight'] ?? ''),
                'applies_to' => RuleModel::normalizeTypes($row['applies_to'] ?? []) ?: $this->lookup->getProductTypeIds(),
            ];
        }
        $sets = $persisted['attribute_set_ids'] ?? [];
        if (is_string($sets)) {
            $sets = $sets === '' ? [] : explode(',', $sets);
        }
        return [
            'ruleset_id' => isset($persisted['ruleset_id']) && (int)$persisted['ruleset_id'] > 0 ? (int)$persisted['ruleset_id'] : '',
            'name' => (string)($persisted['name'] ?? ''),
            'kind' => (string)($persisted['kind'] ?? RuleSetInterface::KIND_ATTRIBUTE_SET),
            'channel_code' => (string)($persisted['channel_code'] ?? ''),
            'description' => (string)($persisted['description'] ?? ''),
            'is_enabled' => (string)($persisted['is_enabled'] ?? '1') === '1' ? '1' : '0',
            'attribute_set_ids' => array_values(array_map('strval', is_array($sets) ? $sets : [])),
            'rules' => $rules,
        ];
    }

    /**
     * Every product attribute a rule can check, with label, code, input type and the sets it belongs to,
     * plus the two pseudo attributes. The picker filters by label or code client-side.
     *
     * @return array<int, array<string, mixed>>
     */
    private function attributeOptions(): array
    {
        $collection = $this->attributeCollectionFactory->create();
        $collection->addVisibleFilter();
        $collection->setOrder('frontend_label', 'ASC');
        $ids = [];
        $rows = [];
        foreach ($collection->getItems() as $attribute) {
            $code = (string)$attribute->getAttributeCode();
            if ($code === '' || in_array($code, ['media_gallery', 'gallery', 'tier_price', 'quantity_and_stock_status'], true)) {
                continue;
            }
            $label = (string)$attribute->getDefaultFrontendLabel();
            $rows[$code] = [
                'code' => $code,
                'label' => $label !== '' ? $label : $code,
                'input' => (string)$attribute->getFrontendInput(),
                'backend' => (string)$attribute->getBackendType(),
                'sets' => [],
            ];
            $ids[(int)$attribute->getId()] = $code;
        }
        foreach ($this->lookup->getMembership(array_keys($ids)) as $setId => $attributeIds) {
            foreach (array_keys($attributeIds) as $attributeId) {
                $code = $ids[$attributeId] ?? null;
                if ($code !== null) {
                    $rows[$code]['sets'][] = (int)$setId;
                }
            }
        }
        $allSets = $this->lookup->getAttributeSetIds();
        $options = [
            [
                'code' => RuleInterface::MEDIA_GALLERY,
                'label' => (string)__('Images'),
                'input' => 'gallery',
                'backend' => 'gallery',
                'sets' => $allSets,
            ],
            [
                'code' => 'quantity_and_stock_status',
                'label' => (string)__('Stock status'),
                'input' => 'stock_status',
                'backend' => 'int',
                'sets' => $allSets,
            ],
        ];
        foreach ($rows as $row) {
            $options[] = $row;
        }
        return $options;
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function attributeSetNames(): array
    {
        $sets = [];
        foreach ($this->lookup->getAttributeSetNames() as $id => $name) {
            $sets[] = ['id' => (int)$id, 'name' => $name];
        }
        return $sets;
    }

    /**
     * @return array<string, array<int, array{value: string, label: string}>>
     */
    private function rolesPerChannel(): array
    {
        $roles = [];
        foreach ($this->channels->getChannelCodes() as $code) {
            $list = [];
            foreach ($this->channels->getRoles($code) as $roleCode => $label) {
                $list[] = ['value' => $roleCode, 'label' => $label];
            }
            $roles[$code] = $list;
        }
        return $roles;
    }
}
