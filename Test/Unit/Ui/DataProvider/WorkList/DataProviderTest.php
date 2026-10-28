<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Test\Unit\Ui\DataProvider\WorkList;

use BigCartEnergy\CatalogCompleteness\Model\Band\Resolver;
use BigCartEnergy\CatalogCompleteness\Model\Config;
use BigCartEnergy\CatalogCompleteness\Ui\DataProvider\WorkList\DataProvider;
use Magento\Framework\Api\Filter as ApiFilter;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\ObjectFactory;
use Magento\Framework\Api\Search\SearchCriteriaBuilder;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Element\UiComponent\DataProvider\DataProvider as UiDataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The work list's column filters in its two views. The grid's bookmark keeps an admin's filters across both, so
 * a Score, Last scored or Band filter set in the scored view reaches the "No rules assigned" view too; there no
 * row has a score, so those three step aside, while the filters on the products themselves still apply. In the
 * scored view every one applies, the band as a score range.
 */
class DataProviderTest extends TestCase
{
    /**
     * @var string[] every filter that reached the search criteria, as "field condition value"
     */
    private $applied = [];

    public function testTheScoreFiltersStepAsideInTheNoRulesAssignedView(): void
    {
        $provider = $this->provider(['coverage' => 'none']);
        foreach ($this->scoreFilters() as $filter) {
            $provider->addFilter($filter);
        }
        self::assertSame([], $this->applied, 'no Score, Last scored or Band filter reaches the view');

        $provider->addFilter($this->filter('sku', 'like', '%NORULES%'));
        $provider->addFilter($this->filter('attribute_set_id', 'eq', '9'));
        $provider->addFilter($this->filter('product_id', 'in', ['12', '13']));
        self::assertSame(
            ['sku like %NORULES%', 'attribute_set_id eq 9', 'product_id in 12,13'],
            $this->applied,
            'the filters on the products themselves, the mass action selection included, still apply'
        );
    }

    public function testTheScoreFiltersApplyInTheScoredView(): void
    {
        $provider = $this->provider([]);
        foreach ($this->scoreFilters() as $filter) {
            $provider->addFilter($filter);
        }
        $poor = $this->resolver()->getRange(Resolver::POOR);
        self::assertSame(
            [
                'score gteq 10',
                'score lteq 90',
                'scored_at gteq 2026-01-01 00:00:00',
                'scored_at lteq 2026-12-31 23:59:59',
                'score gteq ' . $poor[0],
                'score lteq ' . $poor[1],
            ],
            $this->applied
        );
    }

    /**
     * The grid's Score (text range), Last scored (date range) and Band filters, as Magento's filter components
     * hand them to the data provider.
     *
     * @return ApiFilter[]
     */
    private function scoreFilters(): array
    {
        return [
            $this->filter('score', 'gteq', '10'),
            $this->filter('score', 'lteq', '90'),
            $this->filter('scored_at', 'gteq', '2026-01-01 00:00:00'),
            $this->filter('scored_at', 'lteq', '2026-12-31 23:59:59'),
            $this->filter('band', 'eq', Resolver::POOR),
        ];
    }

    /**
     * @param mixed $value
     */
    private function filter(string $field, string $condition, $value): ApiFilter
    {
        return new ApiFilter([
            ApiFilter::KEY_FIELD => $field,
            ApiFilter::KEY_CONDITION_TYPE => $condition,
            ApiFilter::KEY_VALUE => $value,
        ]);
    }

    private function resolver(): Resolver
    {
        return new Resolver(new Config($this->scopeConfig()));
    }

    /**
     * The data provider with the request's dashboard parameters and a search criteria builder that records what
     * reaches it. The constructor is bypassed because the uncovered list's factory is generated code; the
     * properties it would set are set directly.
     *
     * @param array<string, string> $params
     */
    private function provider(array $params): DataProvider
    {
        if (!class_exists(UiDataProvider::class) || !interface_exists(RequestInterface::class)) {
            self::markTestSkipped('Magento framework not available (set BCE_MAGENTO_ROOT).');
        }
        $this->applied = [];
        $request = $this->createStub(RequestInterface::class);
        $request->method('getParam')->willReturnCallback(function ($name, $default = null) use ($params) {
            return isset($params[$name]) ? $params[$name] : $default;
        });
        $applied = &$this->applied;
        $criteria = $this->createStub(SearchCriteriaBuilder::class);
        $criteria->method('addFilter')->willReturnCallback(function (ApiFilter $filter) use (&$applied, $criteria) {
            $value = $filter->getValue();
            $applied[] = $filter->getField() . ' ' . $filter->getConditionType() . ' '
                . (is_array($value) ? implode(',', $value) : (string)$value);
            return $criteria;
        });
        // The builder makes the filter itself: some 2.3 releases find the data class with a pattern that newer
        // PCRE versions refuse, and the class is known anyway.
        $filters = new class ($this->createStub(ObjectFactory::class)) extends FilterBuilder {
            public function create()
            {
                $filter = new ApiFilter($this->data);
                $this->data = [];
                return $filter;
            }
        };
        $config = new Config($this->scopeConfig());

        $provider = (new \ReflectionClass(DataProvider::class))->newInstanceWithoutConstructor();
        $this->set(UiDataProvider::class, $provider, 'request', $request);
        $this->set(UiDataProvider::class, $provider, 'searchCriteriaBuilder', $criteria);
        $this->set(UiDataProvider::class, $provider, 'filterBuilder', $filters);
        $this->set(DataProvider::class, $provider, 'config', $config);
        $this->set(DataProvider::class, $provider, 'bands', new Resolver($config));
        return $provider;
    }

    /**
     * The settings at their defaults: no stored value, so the default thresholds and "enabled only" off.
     */
    private function scopeConfig(): ScopeConfigInterface
    {
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturn(null);
        $scopeConfig->method('isSetFlag')->willReturn(false);
        return $scopeConfig;
    }

    /**
     * @param mixed $object
     * @param mixed $value
     */
    private function set(string $class, $object, string $property, $value): void
    {
        $reflection = new \ReflectionProperty($class, $property);
        if (PHP_VERSION_ID < 80100) {
            $reflection->setAccessible(true);
        }
        $reflection->setValue($object, $value);
    }
}
