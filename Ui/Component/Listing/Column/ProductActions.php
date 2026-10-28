<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Ui\Component\Listing\Column;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

/**
 * The work list's "Edit" action: the product edit page.
 */
class ProductActions extends Column
{
    /**
     * @var UrlInterface
     */
    private $urlBuilder;

    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        UrlInterface $urlBuilder,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
        $this->urlBuilder = $urlBuilder;
    }

    /**
     * @inheritDoc
     */
    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
            $field = $this->getData('name');
            foreach ($dataSource['data']['items'] as &$item) {
                $productId = (int)($item['product_id'] ?? 0);
                if ($productId > 0) {
                    $item[$field] = [
                        'edit' => [
                            'href' => $this->urlBuilder->getUrl('catalog/product/edit', ['id' => $productId]),
                            'label' => __('Edit'),
                        ],
                    ];
                }
            }
            unset($item);
        }
        return $dataSource;
    }
}
