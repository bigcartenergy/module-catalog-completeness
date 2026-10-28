<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Model;

use Magento\Framework\Model\AbstractModel;

/**
 * One recalculation run: queued by the dashboard, REST or the editor; worked by the consumer or the CLI.
 */
class Run extends AbstractModel
{
    public const STATE_QUEUED = 'queued';
    public const STATE_RUNNING = 'running';
    public const STATE_DONE = 'done';
    public const STATE_FAILED = 'failed';

    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init(ResourceModel\Run::class);
    }

    public function getRunId(): ?int
    {
        $id = $this->getData('run_id');
        return $id === null || $id === '' ? null : (int)$id;
    }

    public function getRulesetId(): ?int
    {
        $id = $this->getData('ruleset_id');
        return $id === null || $id === '' ? null : (int)$id;
    }

    /**
     * @return int[]|null null for a run over every product
     */
    public function getProductIds(): ?array
    {
        $raw = $this->getData('product_ids');
        if ($raw === null || $raw === '') {
            return null;
        }
        if (is_array($raw)) {
            return array_values(array_map('intval', $raw));
        }
        $decoded = json_decode((string)$raw, true);
        if (!is_array($decoded)) {
            return [];
        }
        return array_values(array_map('intval', $decoded));
    }

    /**
     * @param int[]|null $productIds
     */
    public function setProductIds(?array $productIds): self
    {
        $this->setData('product_ids', $productIds === null ? null : json_encode(array_values(array_map('intval', $productIds))));
        return $this;
    }

    public function getState(): string
    {
        return (string)$this->getData('state');
    }

    public function getTotal(): int
    {
        return (int)$this->getData('total');
    }

    public function getDone(): int
    {
        return (int)$this->getData('done');
    }

    public function getMessage(): ?string
    {
        $message = $this->getData('message');
        return $message === null || $message === '' ? null : (string)$message;
    }

    public function isActive(): bool
    {
        return in_array($this->getState(), [self::STATE_QUEUED, self::STATE_RUNNING], true);
    }

    public function getPercent(): int
    {
        $total = $this->getTotal();
        if ($total <= 0) {
            return 0;
        }
        return (int)min(100, floor(100 * $this->getDone() / $total));
    }
}
