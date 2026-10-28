<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Controller\Adminhtml\Score;

use BigCartEnergy\CatalogCompleteness\Model\Catalog\ProductSearch;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;

/**
 * Product search for the preview panel: by SKU or name.
 */
class Search extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'BigCartEnergy_CatalogCompleteness::rulesets_edit';

    /**
     * @var JsonFactory
     */
    private $jsonFactory;

    /**
     * @var ProductSearch
     */
    private $search;

    public function __construct(Context $context, JsonFactory $jsonFactory, ProductSearch $search)
    {
        parent::__construct($context);
        $this->jsonFactory = $jsonFactory;
        $this->search = $search;
    }

    /**
     * @inheritDoc
     */
    public function execute()
    {
        $keyword = $this->getRequest()->getParam('q');
        $items = is_string($keyword) ? $this->search->search($keyword) : [];
        return $this->jsonFactory->create()->setData(['items' => $items]);
    }
}
