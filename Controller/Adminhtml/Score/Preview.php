<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Controller\Adminhtml\Score;

use BigCartEnergy\CatalogCompleteness\Api\Data\RuleSetInterface;
use BigCartEnergy\CatalogCompleteness\Model\Preview\PreviewService;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Scores one product against the page's rules (unsaved included) or a stored rule set. Writes nothing.
 */
class Preview extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'BigCartEnergy_CatalogCompleteness::rulesets_edit';
    public const ACL_PRODUCTS = 'Magento_Catalog::products';

    /**
     * @var JsonFactory
     */
    private $jsonFactory;

    /**
     * @var PreviewService
     */
    private $previewService;

    public function __construct(Context $context, JsonFactory $jsonFactory, PreviewService $previewService)
    {
        parent::__construct($context);
        $this->jsonFactory = $jsonFactory;
        $this->previewService = $previewService;
    }

    /**
     * Rules sent from the editor need the edit permission; a stored rule set on the product page needs the
     * catalog's product permission (the page the panel sits on) or the edit permission.
     *
     * @return bool
     */
    protected function _isAllowed()
    {
        if (is_array($this->getRequest()->getParam('rules'))) {
            return $this->_authorization->isAllowed(self::ADMIN_RESOURCE);
        }
        return $this->_authorization->isAllowed(self::ACL_PRODUCTS)
            || $this->_authorization->isAllowed(self::ADMIN_RESOURCE);
    }

    /**
     * @inheritDoc
     */
    public function execute()
    {
        $json = $this->jsonFactory->create();
        $request = $this->getRequest();
        $productId = (int)$request->getParam('product_id');
        if ($productId <= 0) {
            return $json->setHttpResponseCode(400)->setData(['error' => (string)__('Choose a product to preview.')]);
        }
        try {
            $rulesetId = (int)$request->getParam('ruleset_id');
            $rules = $request->getParam('rules');
            if (is_array($rules)) {
                $kind = (string)$request->getParam('kind');
                $kind = in_array($kind, RuleSetInterface::KINDS, true) ? $kind : RuleSetInterface::KIND_ATTRIBUTE_SET;
                $channel = $request->getParam('channel_code');
                $data = $this->previewService->previewRows(
                    $productId,
                    $rules,
                    $kind,
                    is_string($channel) && $channel !== '' ? $channel : null
                );
            } elseif ($rulesetId > 0) {
                $data = $this->previewService->previewRuleSet($productId, $rulesetId);
            } else {
                return $json->setHttpResponseCode(400)->setData(['error' => (string)__('Nothing to preview: send rules or a rule set.')]);
            }
        } catch (NoSuchEntityException $e) {
            return $json->setHttpResponseCode(404)->setData(['error' => (string)$e->getMessage()]);
        }
        return $json->setData($data);
    }
}
