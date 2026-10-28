<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\ViewModel;

use BigCartEnergy\CatalogCompleteness\Api\Data\RuleSetInterface;
use BigCartEnergy\CatalogCompleteness\Model\Band\Resolver as BandResolver;
use BigCartEnergy\CatalogCompleteness\Model\Config;
use BigCartEnergy\CatalogCompleteness\Model\Dashboard\Filter;
use BigCartEnergy\CatalogCompleteness\Model\Dashboard\FilterOptions;
use BigCartEnergy\CatalogCompleteness\Model\Dashboard\Statistics;
use BigCartEnergy\CatalogCompleteness\Model\Dashboard\StatusReader;
use BigCartEnergy\CatalogCompleteness\Model\RuleSetRepository;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Locale\ResolverInterface as LocaleResolver;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * Everything the dashboard templates read. Numbers come from Statistics, states from StatusReader.
 */
class Dashboard implements ArgumentInterface
{
    public const STATE_FIRST_RUN = 'first_run';
    public const STATE_NEVER = 'never';
    public const STATE_ZERO = 'zero';
    public const STATE_CALCULATING = 'calculating';
    public const STATE_READY = 'ready';
    public const STATE_UNCOVERED = 'uncovered';

    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var Statistics
     */
    private $statistics;

    /**
     * @var StatusReader
     */
    private $statusReader;

    /**
     * @var RuleSetRepository
     */
    private $ruleSetRepository;

    /**
     * @var FilterOptions
     */
    private $filterOptions;

    /**
     * @var BandResolver
     */
    private $bands;

    /**
     * @var UrlInterface
     */
    private $urlBuilder;

    /**
     * @var AuthorizationInterface
     */
    private $authorization;

    /**
     * @var Json
     */
    private $json;

    /**
     * @var LocaleResolver
     */
    private $localeResolver;

    /**
     * @var Filter|null
     */
    private $filter;

    /**
     * @var array<string, mixed>|null
     */
    private $summary;

    /**
     * @var array<string, mixed>|null
     */
    private $status;

    /**
     * @var string|null
     */
    private $state;

    /**
     * @var int|null
     */
    private $uncovered;

    /**
     * @var array<string, array<int, array{value: string, label: string}>>
     */
    private $options = [];

    /**
     * @var \NumberFormatter|null
     */
    private $numberFormatter;

    public function __construct(
        RequestInterface $request,
        Config $config,
        Statistics $statistics,
        StatusReader $statusReader,
        RuleSetRepository $ruleSetRepository,
        FilterOptions $filterOptions,
        BandResolver $bands,
        UrlInterface $urlBuilder,
        AuthorizationInterface $authorization,
        Json $json,
        LocaleResolver $localeResolver
    ) {
        $this->request = $request;
        $this->config = $config;
        $this->statistics = $statistics;
        $this->statusReader = $statusReader;
        $this->ruleSetRepository = $ruleSetRepository;
        $this->filterOptions = $filterOptions;
        $this->bands = $bands;
        $this->urlBuilder = $urlBuilder;
        $this->authorization = $authorization;
        $this->json = $json;
        $this->localeResolver = $localeResolver;
    }

    public function getFilter(): Filter
    {
        if ($this->filter === null) {
            $params = [];
            foreach (Filter::PARAMS as $name) {
                $value = $this->request->getParam($name);
                if ($value !== null && $value !== '') {
                    $params[$name] = $value;
                }
            }
            $this->filter = Filter::fromParams($params, $this->config->isEnabledOnlyDefault());
        }
        return $this->filter;
    }

    /**
     * first_run (no rule sets), uncovered (the products with no rules assigned, which need no score), never (no
     * scores yet), zero (filters match nothing) or ready.
     */
    public function getState(): string
    {
        if ($this->state === null) {
            if ($this->statistics->countRuleSets() === 0) {
                $this->state = self::STATE_FIRST_RUN;
            } elseif ($this->getFilter()->isUncoveredOnly()) {
                $this->state = self::STATE_UNCOVERED;
            } elseif ($this->getStatus()['state'] === StatusReader::STATE_NEVER) {
                $this->state = self::STATE_NEVER;
            } elseif ($this->getSummary()['count'] === 0 && !$this->getActiveFilters()) {
                // Nothing in scope and no filter: the first run is in progress, the scope was never scored
                // (another run is active, so the status reader could not say so), or everything is disabled.
                if ($this->getStatus()['state'] === StatusReader::STATE_RECALCULATING && $this->runCoversScope()) {
                    $this->state = self::STATE_CALCULATING;
                } elseif ($this->getStatus()['scored_at'] === null) {
                    $this->state = self::STATE_NEVER;
                } else {
                    $this->state = self::STATE_READY;
                }
            } elseif ($this->getSummary()['count'] === 0) {
                $this->state = self::STATE_ZERO;
            } else {
                $this->state = self::STATE_READY;
            }
        }
        return $this->state;
    }

    /**
     * @return array<string, mixed>
     */
    public function getStatus(): array
    {
        if ($this->status === null) {
            $this->status = $this->statusReader->read($this->getFilter()->getRulesetId());
        }
        return $this->status;
    }

    /**
     * @return array<string, mixed>
     */
    public function getSummary(): array
    {
        if ($this->summary === null) {
            $this->summary = $this->statistics->getSummary($this->getFilter());
        }
        return $this->summary;
    }

    /**
     * Bands with count, percentage and bar width (largest band = 78 % of the track).
     *
     * @return array<int, array{code: string, label: string, count: int, pct: string, width: string}>
     */
    public function getDistribution(): array
    {
        $bands = $this->getSummary()['bands'];
        $total = (int)$this->getSummary()['count'];
        $max = max(1, max($bands ?: [0]));
        $rows = [];
        foreach (BandResolver::CODES as $code) {
            $count = (int)($bands[$code] ?? 0);
            $rows[] = [
                'code' => $code,
                'label' => $this->bands->getLabel($code),
                'count' => $count,
                'pct' => $total > 0 ? $this->formatPercent(100 * $count / $total) : '0 %',
                'width' => $count > 0 ? round(78 * $count / $max, 1) . '%' : '0%',
            ];
        }
        return $rows;
    }

    public function getBreakdownBy(): string
    {
        $by = (string)$this->request->getParam('by');
        return in_array($by, Statistics::GROUPINGS, true) ? $by : Statistics::BY_ATTRIBUTE_SET;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getBreakdown(): array
    {
        $by = $this->getBreakdownBy();
        $rows = $this->statistics->getBreakdown($this->getFilter(), $by);
        foreach ($rows as &$row) {
            $row['band_label'] = $row['band'] === null ? '' : $this->bands->getLabel($row['band']);
            $row['complete_pct_label'] = $this->formatPercent((float)$row['complete_pct']);
            $row['url'] = $this->getGroupUrl($by, (string)$row['key']);
        }
        unset($row);
        return $rows;
    }

    /**
     * @return array<string, string>
     */
    public function getGroupings(): array
    {
        return [
            Statistics::BY_ATTRIBUTE_SET => (string)__('Attribute set'),
            Statistics::BY_CATEGORY => (string)__('Category'),
            Statistics::BY_BRAND => (string)__('Brand'),
            Statistics::BY_TYPE => (string)__('Product type'),
        ];
    }

    public function getGroupingNote(): string
    {
        switch ($this->getBreakdownBy()) {
            case Statistics::BY_CATEGORY:
                return (string)__('Sorted by average score, lowest first. A product in several categories counts in each.');
            case Statistics::BY_BRAND:
                return (string)__('Sorted by average score, lowest first. Brand comes from the %1 attribute (Settings).', $this->config->getBrandAttributeCode());
            default:
                return (string)__('Sorted by average score, lowest first.');
        }
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, total_rules: int}
     */
    public function getMostMissing(): array
    {
        $data = $this->statistics->getMostMissing($this->getFilter(), 10);
        foreach ($data['items'] as &$item) {
            $item['url'] = $this->getUrlWithFilter(
                $this->getFilter()->withoutTile()->with(Filter::PARAM_RULE, $item['rule_id'])->with(Filter::PARAM_TILE, Filter::TILE_FAILING)
            );
        }
        unset($item);
        return $data;
    }

    /**
     * Rule set scope options: all attribute set rules, then each enabled channel profile.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public function getScopeOptions(): array
    {
        $options = [['value' => '', 'label' => (string)__('All attribute set rules')]];
        foreach ($this->ruleSetRepository->getAll() as $ruleSet) {
            if ($ruleSet->getKind() === RuleSetInterface::KIND_CHANNEL && $ruleSet->getIsEnabled()) {
                $options[] = ['value' => (string)$ruleSet->getRulesetId(), 'label' => $ruleSet->getName()];
            }
        }
        return $options;
    }

    public function getScopeLabel(): string
    {
        $value = $this->getFilter()->getRulesetId();
        return FilterOptions::labelOf($this->getScopeOptions(), $value === null ? '' : (string)$value);
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function getOptions(string $which): array
    {
        if (!isset($this->options[$which])) {
            switch ($which) {
                case 'attribute_set':
                    $this->options[$which] = $this->filterOptions->getAttributeSets();
                    break;
                case 'category':
                    $this->options[$which] = $this->filterOptions->getCategories();
                    break;
                case 'brand':
                    $this->options[$which] = $this->filterOptions->getBrands();
                    break;
                case 'type':
                    $this->options[$which] = $this->filterOptions->getProductTypes();
                    break;
                case 'band':
                    $this->options[$which] = array_map(function (string $code): array {
                        return ['value' => $code, 'label' => $this->bands->getLabel($code)];
                    }, BandResolver::CODES);
                    break;
                default:
                    $this->options[$which] = [];
            }
        }
        return $this->options[$which];
    }

    /**
     * The active filters as chips: label, value label and the URL that removes the chip.
     *
     * @return array<int, array{label: string, value: string, url: string}>
     */
    public function getActiveFilters(): array
    {
        $filter = $this->getFilter();
        $chips = [];
        $map = [
            Filter::PARAM_ATTRIBUTE_SET => [(string)__('Attribute set'), 'attribute_set', $filter->getAttributeSetId()],
            Filter::PARAM_CATEGORY => [(string)__('Category'), 'category', $filter->getCategoryId()],
            Filter::PARAM_BRAND => [(string)__('Brand'), 'brand', $filter->getBrand()],
            Filter::PARAM_TYPE => [(string)__('Product type'), 'type', $filter->getTypeId()],
            Filter::PARAM_BAND => [(string)__('Band'), 'band', $filter->getBand()],
        ];
        foreach ($map as $param => [$label, $options, $value]) {
            if ($value === null) {
                continue;
            }
            $chips[] = [
                'label' => $label,
                'value' => ltrim(FilterOptions::labelOf($this->getOptions($options), (string)$value), '- '),
                'url' => $this->getUrlWithFilter($filter->with($param, null)),
            ];
        }
        if ($filter->getTile() !== null) {
            $chips[] = [
                'label' => (string)__('Shortcut'),
                'value' => $this->getTileLabel($filter->getTile()),
                'url' => $this->getUrlWithFilter($filter->withoutTile()),
            ];
        }
        if ($filter->isUncoveredOnly()) {
            $chips[] = [
                'label' => (string)__('Rule coverage'),
                'value' => (string)__('No rules assigned'),
                'url' => $this->getCoverageUrl(false),
            ];
        }
        return $chips;
    }

    public function getTileLabel(?string $tile): string
    {
        switch ($tile) {
            case Filter::TILE_BELOW_AVERAGE:
                return (string)__('Below average');
            case Filter::TILE_ALL:
                return (string)__('All scored products');
            case Filter::TILE_COMPLETE:
                return (string)__('Complete');
            case Filter::TILE_NEEDS_WORK_OR_POOR:
                return (string)__('Needs work or Poor');
            case Filter::TILE_FAILING:
                return (string)__('Failing the rule');
            default:
                return '';
        }
    }

    /**
     * A sentence naming the active filters, for the "no products match" state.
     */
    public function describeActiveFilters(): string
    {
        $parts = [];
        $filter = $this->getFilter();
        if ($filter->getAttributeSetId() !== null) {
            $parts[] = (string)__('in the %1 attribute set', FilterOptions::labelOf($this->getOptions('attribute_set'), (string)$filter->getAttributeSetId()));
        }
        if ($filter->getCategoryId() !== null) {
            $parts[] = (string)__('in the category %1', ltrim(FilterOptions::labelOf($this->getOptions('category'), (string)$filter->getCategoryId()), '- '));
        }
        if ($filter->getBrand() !== null) {
            $parts[] = (string)__('made by %1', FilterOptions::labelOf($this->getOptions('brand'), $filter->getBrand()));
        }
        if ($filter->getTypeId() !== null) {
            $parts[] = (string)__('of type %1', FilterOptions::labelOf($this->getOptions('type'), $filter->getTypeId()));
        }
        if ($filter->getBand() !== null) {
            $parts[] = (string)__('scored %1', $this->bands->getLabel($filter->getBand()));
        }
        if ($filter->getTile() !== null) {
            $parts[] = (string)__('matching the shortcut %1', $this->getTileLabel($filter->getTile()));
        }
        $subject = $filter->isEnabledOnly() ? (string)__('No enabled product is') : (string)__('No product is');
        if (!$parts) {
            return (string)__('%1 scored under this rule set yet.', $subject);
        }
        return $subject . ' ' . implode(', ', $parts) . '. '
            . (string)__('Remove a filter, or clear them all to see the whole catalog again.');
    }

    public function getUrlWithFilter(Filter $filter): string
    {
        $params = $filter->toParams();
        $by = $this->getBreakdownBy();
        if ($by !== Statistics::BY_ATTRIBUTE_SET) {
            $params['by'] = $by;
        }
        return $this->urlBuilder->getUrl('bce_completeness/dashboard/index', ['_query' => $params]);
    }

    public function getTileUrl(string $tile, ?int $ruleId = null): string
    {
        $filter = $this->getFilter();
        if ($filter->getTile() === $tile) {
            return $this->getUrlWithFilter($filter->withoutTile());
        }
        $next = $filter->withoutTile()->with(Filter::PARAM_TILE, $tile);
        if ($tile === Filter::TILE_ALL) {
            $next = $next->with(Filter::PARAM_BAND, null);
        }
        if ($ruleId !== null) {
            $next = $next->with(Filter::PARAM_RULE, $ruleId);
        }
        return $this->getUrlWithFilter($next);
    }

    public function isTilePressed(string $tile): bool
    {
        return $this->getFilter()->getTile() === $tile;
    }

    public function getBreakdownUrl(string $by): string
    {
        $params = $this->getFilter()->toParams();
        if ($by !== Statistics::BY_ATTRIBUTE_SET) {
            $params['by'] = $by;
        }
        return $this->urlBuilder->getUrl('bce_completeness/dashboard/index', ['_query' => $params]);
    }

    public function getGroupUrl(string $by, string $key): string
    {
        $filter = $this->getFilter()->withoutTile();
        switch ($by) {
            case Statistics::BY_CATEGORY:
                $filter = $filter->with(Filter::PARAM_CATEGORY, $key);
                break;
            case Statistics::BY_BRAND:
                $filter = $filter->with(Filter::PARAM_BRAND, $key === '' ? Filter::BRAND_NONE : $key);
                break;
            case Statistics::BY_TYPE:
                $filter = $filter->with(Filter::PARAM_TYPE, $key);
                break;
            default:
                $filter = $filter->with(Filter::PARAM_ATTRIBUTE_SET, $key);
        }
        return $this->getUrlWithFilter($filter) . '#bce-completeness-worklist';
    }

    public function getClearUrl(): string
    {
        // The rule coverage shows among the active filters, so clearing them all returns to the scored products.
        return $this->getUrlWithFilter($this->getFilter()->cleared()->with(Filter::PARAM_COVERAGE, null));
    }

    /**
     * Products of the scope with no rules assigned, under the filters on the products themselves.
     */
    public function countUncovered(): int
    {
        if ($this->uncovered === null) {
            $this->uncovered = $this->statistics->countUncovered($this->getFilter());
        }
        return $this->uncovered;
    }

    /**
     * The dashboard with the products that have no rules assigned (true) or with the scored products (false),
     * keeping the scope and the filters on the products themselves.
     */
    public function getCoverageUrl(bool $uncovered): string
    {
        $filter = $this->getFilter();
        if ($uncovered) {
            $filter = $filter->withoutTile()->with(Filter::PARAM_BAND, null)
                ->with(Filter::PARAM_COVERAGE, Filter::COVERAGE_NONE);
        } else {
            $filter = $filter->with(Filter::PARAM_COVERAGE, null);
        }
        return $this->getUrlWithFilter($filter);
    }

    /**
     * Why a product of this scope has no rules assigned, in one sentence.
     */
    public function describeUncoveredScope(): string
    {
        if ($this->getFilter()->getRulesetId() === null) {
            return (string)__('No enabled attribute set rule set covers their attribute set, or none of its rules applies to their product type, so no score measures them.');
        }
        return (string)__('None of the rules of %1 applies to their product type, so no score from it measures them.', $this->getScopeLabel());
    }

    public function getDashboardUrl(): string
    {
        return $this->urlBuilder->getUrl('bce_completeness/dashboard/index');
    }

    public function getManageUrl(): string
    {
        return $this->urlBuilder->getUrl('bce_completeness/ruleset/index');
    }

    public function getNewRuleSetUrl(): string
    {
        return $this->urlBuilder->getUrl('bce_completeness/ruleset/new');
    }

    public function getRecalculateUrl(): string
    {
        return $this->urlBuilder->getUrl('bce_completeness/score/recalculate');
    }

    public function getExportUrl(): string
    {
        return $this->urlBuilder->getUrl('bce_completeness/score/export');
    }

    public function getStatusUrl(): string
    {
        return $this->urlBuilder->getUrl('bce_completeness/score/status');
    }

    public function getSettingsUrl(): string
    {
        return $this->urlBuilder->getUrl('adminhtml/system_config/edit', ['section' => 'bce_completeness']);
    }

    public function getIndexManagementUrl(): string
    {
        return $this->urlBuilder->getUrl('indexer/indexer/list');
    }

    public function canRecalculate(): bool
    {
        return $this->authorization->isAllowed('BigCartEnergy_CatalogCompleteness::recalculate');
    }

    public function canExport(): bool
    {
        return $this->authorization->isAllowed('BigCartEnergy_CatalogCompleteness::export');
    }

    public function canEditRuleSets(): bool
    {
        return $this->authorization->isAllowed('BigCartEnergy_CatalogCompleteness::rulesets_edit');
    }

    public function countEnabledRuleSets(): int
    {
        return $this->statistics->countEnabledRuleSets();
    }

    public function countCoveredProducts(): int
    {
        return $this->statistics->countCoveredProducts($this->getFilter()->getRulesetId());
    }

    /**
     * Configuration for the status poller and the recalculate button.
     */
    public function getJsConfig(): string
    {
        return $this->json->serialize([
            'statusUrl' => $this->getStatusUrl(),
            'recalculateUrl' => $this->getRecalculateUrl(),
            'rulesetId' => $this->getFilter()->getRulesetId(),
            'status' => $this->getStatus(),
            'pollSeconds' => 4,
            'indexManagementUrl' => $this->getIndexManagementUrl(),
        ]);
    }

    public function getBandLabel(?string $code): string
    {
        return $this->bands->getLabel($code);
    }

    public function getBandOf(?int $score): ?string
    {
        return $this->bands->resolve($score);
    }

    public function getBandRange(string $code): string
    {
        $range = $this->bands->getRange($code);
        if ($code === BandResolver::COMPLETE) {
            return '100';
        }
        if ($code === BandResolver::POOR) {
            return (string)__('below %1', $range[1] + 1);
        }
        return $range[0] . '–' . $range[1];
    }

    /**
     * Whether the active run will produce scores for the dashboard's scope: a full run always, a run of one
     * rule set only for that rule set's scope (or for "all attribute set rules" when it is one of those).
     */
    private function runCoversScope(): bool
    {
        $run = $this->getStatus()['run'];
        if (!is_array($run)) {
            return false;
        }
        $runSet = $run['ruleset_id'] ?? null;
        if ($runSet === null || $runSet === '') {
            return true;
        }
        $scope = $this->getFilter()->getRulesetId();
        if ($scope !== null) {
            return (int)$runSet === (int)$scope;
        }
        try {
            return $this->ruleSetRepository->getById((int)$runSet)->getKind() === RuleSetInterface::KIND_ATTRIBUTE_SET;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * "1 product" or "N products".
     */
    public function products(int $count): string
    {
        return $count === 1 ? (string)__('1 product') : (string)__('%1 products', $this->formatNumber($count));
    }

    /**
     * "Top N of M rules" with the right number words.
     */
    public function topRulesLabel(int $shown, int $total): string
    {
        if ($total === 1) {
            return (string)__('The only rule');
        }
        return (string)__('Top %1 of %2 rules', $shown, $this->formatNumber($total));
    }

    public function formatNumber(int $number): string
    {
        $formatter = $this->getNumberFormatter();
        if ($formatter === null) {
            return number_format($number, 0, '.', ',');
        }
        $formatted = $formatter->format($number);
        return $formatted === false ? (string)$number : $formatted;
    }

    public function formatPercent(float $value): string
    {
        $formatter = $this->getNumberFormatter();
        $text = $formatter === null ? number_format($value, 1, '.', ',') : $formatter->format(round($value, 1));
        return ($text === false ? (string)round($value, 1) : $text) . ' %';
    }

    private function getNumberFormatter(): ?\NumberFormatter
    {
        if ($this->numberFormatter === null && class_exists(\NumberFormatter::class)) {
            try {
                $this->numberFormatter = new \NumberFormatter($this->localeResolver->getLocale(), \NumberFormatter::DECIMAL);
                $this->numberFormatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, 1);
            } catch (\Throwable $e) {
                $this->numberFormatter = null;
            }
        }
        return $this->numberFormatter;
    }
}
