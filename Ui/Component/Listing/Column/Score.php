<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Ui\Component\Listing\Column;

use BigCartEnergy\CatalogCompleteness\Model\Band\Resolver as BandResolver;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

/**
 * A score column: adds the band code and label next to the number so the cell can draw the chip.
 * Used by the product grid (field bce_completeness_score) and the work list (field score).
 */
class Score extends Column
{
    /**
     * @var BandResolver
     */
    private $bands;

    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        BandResolver $bands,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
        $this->bands = $bands;
    }

    /**
     * @inheritDoc
     */
    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
            $field = $this->getData('name');
            foreach ($dataSource['data']['items'] as &$item) {
                $raw = $item[$field] ?? null;
                $score = $raw === null || $raw === '' ? null : (int)$raw;
                $band = $this->bands->resolve($score);
                $item[$field] = $score;
                $item[$field . '_band'] = $band;
                $item[$field . '_band_label'] = $band === null ? '' : $this->bands->getLabel($band);
            }
            unset($item);
        }
        return $dataSource;
    }
}
