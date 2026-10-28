<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Block\Adminhtml\RuleSet\Edit;

use Magento\Backend\Block\Widget\Context;
use Magento\Framework\AuthorizationInterface;

/**
 * Shared bits of the editor's action buttons.
 */
class GenericButton
{
    public const ACL_EDIT = 'BigCartEnergy_CatalogCompleteness::rulesets_edit';
    public const FORM_TARGET = 'bce_completeness_ruleset_form.bce_completeness_ruleset_form';

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var AuthorizationInterface
     */
    protected $authorization;

    public function __construct(Context $context, AuthorizationInterface $authorization)
    {
        $this->context = $context;
        $this->authorization = $authorization;
    }

    public function getRuleSetId(): int
    {
        return (int)$this->context->getRequest()->getParam('id');
    }

    public function canEdit(): bool
    {
        return $this->authorization->isAllowed(self::ACL_EDIT);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function getUrl(string $route = '', array $params = []): string
    {
        return $this->context->getUrlBuilder()->getUrl($route, $params);
    }

    /**
     * A button that runs one of our JavaScript handlers with the form key available.
     *
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    protected function jsButton(string $handler, array $config): array
    {
        return [
            'mage-init' => [
                'BigCartEnergy_CatalogCompleteness/js/form/buttons' => ['handler' => $handler] + $config,
            ],
        ];
    }
}
