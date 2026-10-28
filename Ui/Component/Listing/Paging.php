<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Ui\Component\Listing;

use BigCartEnergy\CatalogCompleteness\Model\Config;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Ui\Component\Paging as UiPaging;

/**
 * Paging whose default page size comes from the settings.
 */
class Paging extends UiPaging
{
    /**
     * @var Config
     */
    private $config;

    public function __construct(ContextInterface $context, Config $config, array $components = [], array $data = [])
    {
        parent::__construct($context, $components, $data);
        $this->config = $config;
    }

    /**
     * @inheritDoc
     */
    public function prepare()
    {
        $config = $this->getData('config');
        if (!is_array($config)) {
            $config = [];
        }
        if (!isset($config['pageSize'])) {
            $config['pageSize'] = $this->config->getWorkListPageSize();
        }
        $options = [];
        foreach (Config::PAGE_SIZES as $size) {
            $options[(string)$size] = ['value' => $size, 'label' => (string)$size];
        }
        $config['options'] = $options;
        $this->setData('config', $config);
        parent::prepare();
    }
}
