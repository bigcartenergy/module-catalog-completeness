<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Test\Unit\Model\Hint;

use BigCartEnergy\CatalogCompleteness\Api\Data\RuleInterface;
use BigCartEnergy\CatalogCompleteness\Api\Data\RuleSetInterface;
use BigCartEnergy\CatalogCompleteness\Model\Channel\ChannelRegistry;
use BigCartEnergy\CatalogCompleteness\Model\Hint\AmazonHints;
use BigCartEnergy\CatalogCompleteness\Model\Hint\CoreContentHints;
use BigCartEnergy\CatalogCompleteness\Model\Hint\EbayHints;
use BigCartEnergy\CatalogCompleteness\Model\Hint\GoogleHints;
use BigCartEnergy\CatalogCompleteness\Model\Hint\HintListInterface;
use BigCartEnergy\CatalogCompleteness\Model\Hint\HintRegistry;
use BigCartEnergy\CatalogCompleteness\Model\Hint\MetaHints;
use BigCartEnergy\CatalogCompleteness\Model\Hint\MicrosoftHints;
use BigCartEnergy\CatalogCompleteness\Model\Hint\PinterestHints;
use BigCartEnergy\CatalogCompleteness\Model\Hint\TikTokHints;
use BigCartEnergy\CatalogCompleteness\Model\Hint\WalmartHints;
use BigCartEnergy\CatalogCompleteness\Model\Rule\Pattern;
use PHPUnit\Framework\TestCase;

/**
 * Every hint list loads, its roles belong to its channel, and every suggested parameter is valid.
 */
class HintListsTest extends TestCase
{
    /**
     * @return HintListInterface[]
     */
    private static function lists(): array
    {
        return [
            new CoreContentHints(),
            new GoogleHints(),
            new MicrosoftHints(),
            new MetaHints(),
            new PinterestHints(),
            new TikTokHints(),
            new AmazonHints(),
            new EbayHints(),
            new WalmartHints(),
        ];
    }

    public static function listProvider(): array
    {
        $cases = [];
        foreach (self::lists() as $list) {
            $cases[$list->getCode()] = [$list];
        }
        return $cases;
    }

    public function testListLoadsWithValidParameters(): void
    {
        foreach (self::listProvider() as $case => $arguments) {
            $this->checkListLoadsWithValidParameters(...array_values($arguments));
        }
    }

    /**
     * One case of testListLoadsWithValidParameters().
     */
    private function checkListLoadsWithValidParameters(HintListInterface $list): void
    {
        $hints = $list->getHints();
        self::assertNotEmpty($hints, $list->getCode() . ' has hints');
        self::assertNotSame('', $list->getTitle());
        self::assertNotSame('', $list->getSourceNote());
        $registry = new ChannelRegistry();
        $allowedRoles = $list->getCode() === CoreContentHints::CODE
            ? [RuleInterface::ROLE_CUSTOM]
            : array_keys($registry->getRoles($list->getCode()));
        $seenLabels = [];
        foreach ($hints as $hint) {
            $where = $list->getCode() . ' / ' . $hint->getLabel();
            self::assertContains($hint->getRole(), $allowedRoles, $where . ': role belongs to the channel');
            self::assertNotSame('', $hint->getLabel(), $where);
            self::assertNotSame('', $hint->getNote(), $where . ': one-line note');
            self::assertContains($hint->getRuleType(), RuleInterface::TYPES, $where);
            self::assertContains($hint->getWeight(), RuleInterface::WEIGHTS, $where);
            self::assertFalse(isset($seenLabels[$hint->getLabel()]), $where . ': label is unique in its list');
            $seenLabels[$hint->getLabel()] = true;
            $parameter = $hint->getParameter();
            switch ($hint->getRuleType()) {
                case RuleInterface::TYPE_MIN_LENGTH:
                case RuleInterface::TYPE_MIN_COUNT:
                    self::assertNotNull($parameter, $where);
                    self::assertSame(1, preg_match('/^\d+$/', (string)$parameter), $where . ': number parses');
                    self::assertGreaterThan(0, (int)$parameter, $where);
                    break;
                case RuleInterface::TYPE_ALLOWED_VALUES:
                    self::assertNotNull($parameter, $where);
                    $entries = array_filter(array_map('trim', explode(',', (string)$parameter)));
                    self::assertNotEmpty($entries, $where . ': allowed values non-empty');
                    break;
                case RuleInterface::TYPE_PATTERN:
                    self::assertNotNull($parameter, $where);
                    self::assertTrue(Pattern::isValid((string)$parameter), $where . ': pattern compiles');
                    break;
                default:
                    self::assertNull($parameter, $where . ': present and gt_zero take no parameter');
            }
        }
    }

    public function testRegistryResolvesKindAndChannel(): void
    {
        $registry = new HintRegistry(self::lists());
        self::assertSame(CoreContentHints::CODE, $registry->getFor(RuleSetInterface::KIND_ATTRIBUTE_SET, null)->getCode());
        self::assertSame('google', $registry->getFor(RuleSetInterface::KIND_CHANNEL, 'google')->getCode());
        self::assertNull($registry->getFor(RuleSetInterface::KIND_CHANNEL, 'custom'), 'custom profiles get no hints');
        self::assertNull($registry->getFor(RuleSetInterface::KIND_CHANNEL, null));
        foreach (RuleSetInterface::CHANNEL_CODES as $code) {
            if ($code === RuleSetInterface::CHANNEL_CUSTOM) {
                continue;
            }
            self::assertNotNull($registry->getFor(RuleSetInterface::KIND_CHANNEL, $code), $code . ' has a hint list');
        }
        $asArray = $registry->toArray();
        self::assertCount(9, $asArray);
        self::assertSame('gtin', $asArray['google']['hints'][6]['role']);
        self::assertArrayHasKey('note', $asArray['google']['hints'][0]);
    }

    public function testGtinPatternsAcceptTheDocumentedLengths(): void
    {
        $gtin = Pattern::compile(GoogleHints::GTIN_PATTERN);
        $productId = Pattern::compile(WalmartHints::PRODUCT_ID_PATTERN);
        self::assertNotNull($gtin);
        self::assertNotNull($productId);
        foreach (['12345678', '123456789012', '1234567890123', '12345678901234'] as $ok) {
            self::assertTrue(Pattern::matches($gtin, $ok), $ok);
            self::assertTrue(Pattern::matches($productId, $ok), $ok);
        }
        self::assertFalse(Pattern::matches($gtin, '1234567890'), 'ISBN-10 is not a GTIN');
        self::assertTrue(Pattern::matches($productId, '1234567890'), 'ISBN-10 is a marketplace product id');
        self::assertFalse(Pattern::matches($gtin, '1234-5678'));
    }
}
