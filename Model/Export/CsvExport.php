<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Model\Export;

use BigCartEnergy\CatalogCompleteness\Model\Band\Resolver as BandResolver;
use BigCartEnergy\CatalogCompleteness\Model\Catalog\Lookup;
use BigCartEnergy\CatalogCompleteness\Model\Config;
use BigCartEnergy\CatalogCompleteness\Model\Dashboard\Filter;
use BigCartEnergy\CatalogCompleteness\Model\ResourceModel\WorkList\Collection;
use BigCartEnergy\CatalogCompleteness\Model\Score\ProductScoreBuilder;
use BigCartEnergy\CatalogCompleteness\Model\Score\RuleLabels;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;

/**
 * Writes the work list to a CSV file under var/export, capped by the setting, every cell guarded against
 * formula injection. Returns the file descriptor the admin file factory streams and removes.
 */
class CsvExport
{
    public const PAGE_SIZE = 500;

    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var BandResolver
     */
    private $bands;

    /**
     * @var RuleLabels
     */
    private $ruleLabels;

    /**
     * @var ProductScoreBuilder
     */
    private $builder;

    /**
     * @var Lookup
     */
    private $lookup;

    public function __construct(
        Filesystem $filesystem,
        Config $config,
        BandResolver $bands,
        RuleLabels $ruleLabels,
        ProductScoreBuilder $builder,
        Lookup $lookup
    ) {
        $this->filesystem = $filesystem;
        $this->config = $config;
        $this->bands = $bands;
        $this->ruleLabels = $ruleLabels;
        $this->builder = $builder;
        $this->lookup = $lookup;
    }

    /**
     * @return array{type: string, value: string, rm: bool}
     */
    public function export(Collection $collection): array
    {
        $directory = $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
        $directory->create('export');
        $file = 'export/bce_completeness_' . gmdate('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.csv';
        $stream = $directory->openFile($file, 'w+');
        $stream->lock();
        $stream->writeCsv([
            (string)__('ID'),
            (string)__('SKU'),
            (string)__('Name'),
            (string)__('Attribute set'),
            (string)__('Type'),
            (string)__('Score'),
            (string)__('Band'),
            (string)__('Missing'),
            (string)__('Scored at'),
        ]);
        $cap = $this->config->getCsvRowCap();
        $types = $this->lookup->getProductTypeLabels();
        $collection->setOrder('score', 'ASC');
        $collection->setPageSize(self::PAGE_SIZE);
        $written = 0;
        $page = 1;
        $lastPage = null;
        while ($written < $cap) {
            $collection->setCurPage($page);
            $collection->clear();
            $items = $collection->getItems();
            if ($lastPage === null) {
                $lastPage = (int)$collection->getLastPageNumber();
            }
            if (!$items) {
                break;
            }
            $rulesetIds = [];
            foreach ($items as $item) {
                $rulesetIds[] = (int)$item->getData('ruleset_id');
            }
            $this->ruleLabels->preload($rulesetIds);
            foreach ($items as $item) {
                if ($written >= $cap) {
                    break;
                }
                $score = $item->getData('score');
                $score = $score === null || $score === '' ? null : (int)$score;
                $band = $this->bands->resolve($score);
                $bandLabel = $band === null ? '' : $this->bands->getLabel($band);
                if ($item->getData('coverage') === Filter::COVERAGE_NONE) {
                    $bandLabel = (string)__('No rules assigned');
                }
                $missing = $this->ruleLabels->describeIds(
                    (int)$item->getData('ruleset_id'),
                    $this->builder->decodeMissing($item->getData('missing'))
                );
                $stream->writeCsv([
                    CsvCell::sanitize($item->getData('product_id')),
                    CsvCell::sanitize($item->getData('sku')),
                    CsvCell::sanitize($item->getData('name')),
                    CsvCell::sanitize($item->getData('attribute_set_name')),
                    CsvCell::sanitize($types[(string)$item->getData('type_id')] ?? $item->getData('type_id')),
                    CsvCell::sanitize($score === null ? '' : (string)$score),
                    CsvCell::sanitize($bandLabel),
                    CsvCell::sanitize($missing),
                    CsvCell::sanitize($item->getData('scored_at')),
                ]);
                $written++;
            }
            if ($page >= $lastPage) {
                break;
            }
            $page++;
        }
        $stream->unlock();
        $stream->close();
        return ['type' => 'filename', 'value' => $file, 'rm' => true];
    }
}
