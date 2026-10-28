<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Ui\Component\Listing\Column;

use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Escaper;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

/**
 * Edit, Duplicate and Delete for a rule set. Duplicate and Delete are POST requests with the form key;
 * Delete asks first. Users without the edit permission see Edit (as view) only.
 */
class RuleSetActions extends Column
{
    public const ACL_EDIT = 'BigCartEnergy_CatalogCompleteness::rulesets_edit';

    /**
     * @var UrlInterface
     */
    private $urlBuilder;

    /**
     * @var AuthorizationInterface
     */
    private $authorization;

    /**
     * @var Escaper
     */
    private $escaper;

    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        UrlInterface $urlBuilder,
        AuthorizationInterface $authorization,
        Escaper $escaper,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
        $this->urlBuilder = $urlBuilder;
        $this->authorization = $authorization;
        $this->escaper = $escaper;
    }

    /**
     * @inheritDoc
     */
    public function prepareDataSource(array $dataSource)
    {
        if (!isset($dataSource['data']['items'])) {
            return $dataSource;
        }
        $field = $this->getData('name');
        $canEdit = $this->authorization->isAllowed(self::ACL_EDIT);
        foreach ($dataSource['data']['items'] as &$item) {
            $id = (int)($item['ruleset_id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $actions = [
                'edit' => [
                    'href' => $this->urlBuilder->getUrl('bce_completeness/ruleset/edit', ['id' => $id]),
                    'label' => $canEdit ? __('Edit') : __('View'),
                ],
            ];
            if ($canEdit) {
                $actions['duplicate'] = [
                    'href' => $this->urlBuilder->getUrl('bce_completeness/ruleset/duplicate', ['id' => $id]),
                    'label' => __('Duplicate'),
                    'post' => true,
                ];
                $actions['delete'] = [
                    'href' => $this->urlBuilder->getUrl('bce_completeness/ruleset/delete', ['id' => $id]),
                    'label' => __('Delete'),
                    'post' => true,
                    'confirm' => [
                        'title' => __('Delete rule set'),
                        'message' => __(
                            'Delete "%1" and the scores calculated with it? This cannot be undone.',
                            $this->escaper->escapeHtml((string)($item['name'] ?? ''))
                        ),
                    ],
                ];
            }
            $item[$field] = $actions;
        }
        unset($item);
        return $dataSource;
    }
}
