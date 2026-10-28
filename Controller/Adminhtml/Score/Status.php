<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Controller\Adminhtml\Score;

use BigCartEnergy\CatalogCompleteness\Model\Dashboard\StatusReader;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;

/**
 * Polled by the dashboard and the editor while a recalculation runs.
 */
class Status extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'BigCartEnergy_CatalogCompleteness::dashboard';

    /**
     * @var JsonFactory
     */
    private $jsonFactory;

    /**
     * @var StatusReader
     */
    private $statusReader;

    public function __construct(Context $context, JsonFactory $jsonFactory, StatusReader $statusReader)
    {
        parent::__construct($context);
        $this->jsonFactory = $jsonFactory;
        $this->statusReader = $statusReader;
    }

    /**
     * @inheritDoc
     */
    public function execute()
    {
        $rulesetId = (int)$this->getRequest()->getParam('ruleset_id');
        return $this->jsonFactory->create()->setData($this->statusReader->read($rulesetId > 0 ? $rulesetId : null));
    }
}
