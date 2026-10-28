<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Controller\Adminhtml\Ruleset;

use BigCartEnergy\CatalogCompleteness\Api\Data\RuleSetInterface;
use BigCartEnergy\CatalogCompleteness\Model\Catalog\Lookup;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;

/**
 * "N products in these attribute sets" for the editor, live as the sets change.
 */
class Affected extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'BigCartEnergy_CatalogCompleteness::rulesets';

    /**
     * @var JsonFactory
     */
    private $jsonFactory;

    /**
     * @var Lookup
     */
    private $lookup;

    public function __construct(Context $context, JsonFactory $jsonFactory, Lookup $lookup)
    {
        parent::__construct($context);
        $this->jsonFactory = $jsonFactory;
        $this->lookup = $lookup;
    }

    /**
     * @inheritDoc
     */
    public function execute()
    {
        $kind = (string)$this->getRequest()->getParam('kind');
        $sets = $this->getRequest()->getParam('attribute_set_ids');
        if (is_string($sets)) {
            $sets = $sets === '' ? [] : explode(',', $sets);
        }
        $setIds = [];
        foreach (is_array($sets) ? $sets : [] as $setId) {
            if (is_scalar($setId) && (int)$setId > 0) {
                $setIds[] = (int)$setId;
            }
        }
        if ($kind === RuleSetInterface::KIND_CHANNEL) {
            $count = $this->lookup->countProducts();
            $setCount = count($this->lookup->getAttributeSetIds());
        } else {
            $count = $this->lookup->countProductsInSets($setIds);
            $setCount = count(array_unique($setIds));
        }
        return $this->jsonFactory->create()->setData(['products' => $count, 'sets' => $setCount]);
    }
}
