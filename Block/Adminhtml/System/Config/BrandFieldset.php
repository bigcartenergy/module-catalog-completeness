<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Block\Adminhtml\System\Config;

use BigCartEnergy\Core\Model\Modules;
use BigCartEnergy\Core\ViewModel\Brand;
use Magento\Backend\Block\Context;
use Magento\Backend\Model\Auth\Session;
use Magento\Config\Block\System\Config\Form\Fieldset;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\View\Helper\Js;

/**
 * The General group of the settings with the brand line in its comment: the mark, the installed version,
 * "by Big Cart Energy" and the links.
 */
class BrandFieldset extends Fieldset
{
    /**
     * @var Brand
     */
    private $brand;

    /**
     * @var Modules
     */
    private $modules;

    public function __construct(
        Context $context,
        Session $authSession,
        Js $jsHelper,
        Brand $brand,
        Modules $modules,
        array $data = []
    ) {
        parent::__construct($context, $authSession, $jsHelper, $data);
        $this->brand = $brand;
        $this->modules = $modules;
    }

    /**
     * @inheritDoc
     */
    protected function _getHeaderCommentHtml($element)
    {
        $version = '';
        foreach ($this->modules->getInstalled() as $module) {
            if ($module['module'] === 'BigCartEnergy_CatalogCompleteness') {
                $version = (string)$module['version'];
            }
        }
        $html = $this->getLayout()
            ->createBlock(\Magento\Backend\Block\Template::class)
            ->setTemplate('BigCartEnergy_CatalogCompleteness::system/config/brand.phtml')
            ->setData('brand', $this->brand)
            ->setData('version', $version)
            ->toHtml();
        return $html . parent::_getHeaderCommentHtml($element);
    }
}
