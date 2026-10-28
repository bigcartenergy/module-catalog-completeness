<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\ViewModel;

use BigCartEnergy\CatalogCompleteness\Api\Data\RuleSetInterface;
use BigCartEnergy\CatalogCompleteness\Model\Catalog\Lookup;
use BigCartEnergy\CatalogCompleteness\Model\Dashboard\Filter;
use BigCartEnergy\CatalogCompleteness\Model\Dashboard\StatusReader;
use BigCartEnergy\CatalogCompleteness\Model\Hint\HintRegistry;
use BigCartEnergy\CatalogCompleteness\Model\RuleSetRepository;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * What the editor page's own blocks (hint line, saved messages, affected box, hints, preview) read.
 */
class Editor implements ArgumentInterface
{
    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @var RuleSetRepository
     */
    private $repository;

    /**
     * @var Lookup
     */
    private $lookup;

    /**
     * @var HintRegistry
     */
    private $hints;

    /**
     * @var StatusReader
     */
    private $statusReader;

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
     * @var RuleSetInterface|null|false
     */
    private $ruleSet = false;

    public function __construct(
        RequestInterface $request,
        RuleSetRepository $repository,
        Lookup $lookup,
        HintRegistry $hints,
        StatusReader $statusReader,
        AuthorizationInterface $authorization,
        UrlInterface $urlBuilder,
        Json $json
    ) {
        $this->request = $request;
        $this->repository = $repository;
        $this->lookup = $lookup;
        $this->hints = $hints;
        $this->statusReader = $statusReader;
        $this->authorization = $authorization;
        $this->urlBuilder = $urlBuilder;
        $this->json = $json;
    }

    public function getRuleSetId(): int
    {
        return (int)$this->request->getParam('id');
    }

    public function getRuleSet(): ?RuleSetInterface
    {
        if ($this->ruleSet === false) {
            $this->ruleSet = null;
            if ($this->getRuleSetId() > 0) {
                try {
                    $this->ruleSet = $this->repository->getById($this->getRuleSetId());
                } catch (NoSuchEntityException $e) {
                    $this->ruleSet = null;
                }
            }
        }
        return $this->ruleSet;
    }

    public function isNew(): bool
    {
        return $this->getRuleSet() === null;
    }

    public function canEdit(): bool
    {
        return $this->authorization->isAllowed('BigCartEnergy_CatalogCompleteness::rulesets_edit');
    }

    public function canRecalculate(): bool
    {
        return $this->authorization->isAllowed('BigCartEnergy_CatalogCompleteness::recalculate');
    }

    /**
     * "recalculating" or "stale" right after a save, otherwise null.
     */
    public function getSavedState(): ?string
    {
        $saved = (string)$this->request->getParam('saved');
        return in_array($saved, ['recalculating', 'stale'], true) ? $saved : null;
    }

    /**
     * Products the rule set covers now: every product for a profile, the sets' products otherwise.
     */
    public function getAffectedCount(): int
    {
        $ruleSet = $this->getRuleSet();
        if ($ruleSet === null) {
            return 0;
        }
        if ($ruleSet->getKind() === RuleSetInterface::KIND_CHANNEL) {
            return $this->lookup->countProducts();
        }
        return $this->lookup->countProductsInSets($ruleSet->getAttributeSetIds());
    }

    /**
     * "1 product" or "N products".
     */
    public function products(int $count): string
    {
        return $count === 1 ? (string)__('1 product') : (string)__('%1 products', number_format($count));
    }

    /**
     * "N products in M attribute sets", with the right number words.
     */
    public function getAffectedLabel(): string
    {
        $sets = $this->getAffectedSetCount();
        return (string)__(
            '%1 in %2',
            $this->products($this->getAffectedCount()),
            $sets === 1 ? __('1 attribute set') : __('%1 attribute sets', number_format($sets))
        );
    }

    public function getAffectedSetCount(): int
    {
        $ruleSet = $this->getRuleSet();
        if ($ruleSet === null) {
            return 0;
        }
        if ($ruleSet->getKind() === RuleSetInterface::KIND_CHANNEL) {
            return count($this->lookup->getAttributeSetIds());
        }
        return count($ruleSet->getAttributeSetIds());
    }

    /**
     * @return array<string, mixed>
     */
    public function getStatus(): array
    {
        return $this->statusReader->read($this->getRuleSetId() > 0 ? $this->getRuleSetId() : null);
    }

    public function getDashboardUrl(): string
    {
        $params = $this->getRuleSet() !== null && $this->getRuleSet()->getKind() === RuleSetInterface::KIND_CHANNEL
            ? [Filter::PARAM_SCOPE => $this->getRuleSetId()]
            : [];
        return $this->urlBuilder->getUrl('bce_completeness/dashboard/index', ['_query' => $params]);
    }

    public function getStatusUrl(): string
    {
        return $this->urlBuilder->getUrl('bce_completeness/score/status');
    }

    public function getRecalculateUrl(): string
    {
        return $this->urlBuilder->getUrl('bce_completeness/score/recalculate');
    }

    public function getPreviewUrl(): string
    {
        return $this->urlBuilder->getUrl('bce_completeness/score/preview');
    }

    public function getSearchUrl(): string
    {
        return $this->urlBuilder->getUrl('bce_completeness/score/search');
    }

    public function getAffectedUrl(): string
    {
        return $this->urlBuilder->getUrl('bce_completeness/ruleset/affected');
    }

    public function getIndexUrl(): string
    {
        return $this->urlBuilder->getUrl('bce_completeness/ruleset/index');
    }

    public function getHintsJson(): string
    {
        return $this->json->serialize($this->hints->toArray());
    }

    /**
     * Configuration shared by the editor's KO components.
     */
    public function getJsConfig(): string
    {
        $ruleSet = $this->getRuleSet();
        return $this->json->serialize([
            'rulesetId' => $this->getRuleSetId() > 0 ? $this->getRuleSetId() : null,
            'rulesetName' => $ruleSet !== null ? $ruleSet->getName() : '',
            'kind' => $ruleSet !== null ? $ruleSet->getKind() : RuleSetInterface::KIND_ATTRIBUTE_SET,
            'channelCode' => $ruleSet !== null ? $ruleSet->getChannelCode() : null,
            'canEdit' => $this->canEdit(),
            'canRecalculate' => $this->canRecalculate(),
            'savedState' => $this->getSavedState(),
            'affected' => $this->getAffectedCount(),
            'affectedSets' => $this->getAffectedSetCount(),
            'statusUrl' => $this->getStatusUrl(),
            'recalculateUrl' => $this->getRecalculateUrl(),
            'previewUrl' => $this->getPreviewUrl(),
            'searchUrl' => $this->getSearchUrl(),
            'affectedUrl' => $this->getAffectedUrl(),
            'dashboardUrl' => $this->getDashboardUrl(),
            'indexUrl' => $this->getIndexUrl(),
            'status' => $this->getStatus(),
            'pollSeconds' => 4,
        ]);
    }
}
