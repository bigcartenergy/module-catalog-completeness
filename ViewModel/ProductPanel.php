<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\ViewModel;

use BigCartEnergy\CatalogCompleteness\Api\Data\RuleSetInterface;
use BigCartEnergy\CatalogCompleteness\Model\Catalog\ProductSearch;
use BigCartEnergy\CatalogCompleteness\Model\RuleSetRepository;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * The preview panel on the product edit page: the product, the rule sets that apply to it, the URLs.
 */
class ProductPanel implements ArgumentInterface
{
    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @var ProductSearch
     */
    private $productSearch;

    /**
     * @var RuleSetRepository
     */
    private $repository;

    /**
     * @var AuthorizationInterface
     */
    private $authorization;

    /**
     * @var UrlInterface
     */
    private $urlBuilder;

    /**
     * @var Json
     */
    private $json;

    /**
     * @var array<string, mixed>|null|false
     */
    private $product = false;

    public function __construct(
        RequestInterface $request,
        ProductSearch $productSearch,
        RuleSetRepository $repository,
        AuthorizationInterface $authorization,
        UrlInterface $urlBuilder,
        Json $json
    ) {
        $this->request = $request;
        $this->productSearch = $productSearch;
        $this->repository = $repository;
        $this->authorization = $authorization;
        $this->urlBuilder = $urlBuilder;
        $this->json = $json;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getProduct(): ?array
    {
        if ($this->product === false) {
            $this->product = $this->productSearch->find((int)$this->request->getParam('id'));
        }
        return $this->product;
    }

    /**
     * Rule sets that apply to this product: its attribute set's rule set first, then the enabled profiles.
     *
     * @return array<int, array{id: int, name: string, primary: bool}>
     */
    public function getRuleSets(): array
    {
        $product = $this->getProduct();
        if ($product === null) {
            return [];
        }
        $primary = [];
        $profiles = [];
        foreach ($this->repository->getAll() as $ruleSet) {
            if (!$ruleSet->getIsEnabled()) {
                continue;
            }
            if ($ruleSet->getKind() === RuleSetInterface::KIND_CHANNEL) {
                $profiles[] = ['id' => (int)$ruleSet->getRulesetId(), 'name' => $ruleSet->getName(), 'primary' => false];
            } elseif (in_array((int)$product['attribute_set_id'], $ruleSet->getAttributeSetIds(), true)) {
                $primary[] = ['id' => (int)$ruleSet->getRulesetId(), 'name' => $ruleSet->getName(), 'primary' => true];
            }
        }
        return array_merge($primary, $profiles);
    }

    public function isVisible(): bool
    {
        return $this->getProduct() !== null
            && $this->authorization->isAllowed('BigCartEnergy_CatalogCompleteness::dashboard');
    }

    public function canRecalculate(): bool
    {
        return $this->authorization->isAllowed('BigCartEnergy_CatalogCompleteness::recalculate');
    }

    public function getJsConfig(): string
    {
        $product = $this->getProduct();
        return $this->json->serialize([
            'context' => 'product',
            'productId' => $product !== null ? (int)$product['id'] : null,
            'ruleSets' => $this->getRuleSets(),
            'previewUrl' => $this->urlBuilder->getUrl('bce_completeness/score/preview'),
            'recalculateUrl' => $this->urlBuilder->getUrl('bce_completeness/score/recalculate'),
            'canRecalculate' => $this->canRecalculate(),
            'dashboardUrl' => $this->urlBuilder->getUrl('bce_completeness/dashboard/index'),
        ]);
    }
}
