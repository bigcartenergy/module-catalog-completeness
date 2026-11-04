<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Test\Unit\Controller\Adminhtml\Ruleset;

use BigCartEnergy\CatalogCompleteness\Api\Data\RuleInterface;
use BigCartEnergy\CatalogCompleteness\Controller\Adminhtml\Ruleset\Save;
use BigCartEnergy\CatalogCompleteness\Test\Unit\Stub\RuleSetStub;
use BigCartEnergy\CatalogCompleteness\Test\Unit\Stub\RuleStub;
use Magento\Backend\App\Action;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Phrase;
use PHPUnit\Framework\TestCase;

/**
 * The editor's rules table orders its rows by position, and Move up/down and drag change only the positions. Save
 * keeps the rules in that order: it saves them, and after a failed save redisplays them, sorted by the posted
 * position, the posted order breaking a tie.
 */
class SaveOrderTest extends TestCase
{
    /**
     * @var RuleSetStub|null
     */
    private $saved;

    /**
     * @var array<string, mixed>|null
     */
    private $persisted;

    /**
     * @var string[]
     */
    private $errors = [];

    public function testAReorderedTableIsSavedInItsScreenOrderAndKeepsItsRuleIds(): void
    {
        // Loaded as Name, Description, Images (positions 1 to 3), then Images moved up twice.
        $this->post([
            ['rule_id' => '1', 'position' => '2', 'attribute_code' => 'name'],
            ['rule_id' => '2', 'position' => '3', 'attribute_code' => 'description'],
            ['rule_id' => '3', 'position' => '1', 'attribute_code' => 'media_gallery'],
        ]);
        self::assertSame(['media_gallery', 'name', 'description'], $this->savedCodes());
        self::assertSame([3, 1, 2], $this->savedIds(), 'a pure reorder keeps every rule id');
    }

    public function testARemovalThenAReorderKeepsTheRightRows(): void
    {
        // Description (position 2) removed, Short description moved above Images; a new row added at the end.
        $this->post([
            ['rule_id' => '1', 'position' => '1', 'attribute_code' => 'name'],
            ['rule_id' => '3', 'position' => '4', 'attribute_code' => 'media_gallery'],
            ['rule_id' => '8', 'position' => '3', 'attribute_code' => 'short_description'],
            ['rule_id' => '', 'position' => '6', 'attribute_code' => 'meta_description'],
        ]);
        self::assertSame(['name', 'short_description', 'media_gallery', 'meta_description'], $this->savedCodes());
        self::assertSame([1, 8, 3, null], $this->savedIds());
    }

    public function testTiesKeepThePostedOrderAndRowsWithoutAPositionGoLast(): void
    {
        $this->post([
            ['rule_id' => '', 'position' => '', 'attribute_code' => 'weight'],
            ['rule_id' => '7', 'position' => '4', 'attribute_code' => 'name'],
            ['rule_id' => '8', 'position' => '2', 'attribute_code' => 'sku'],
            ['rule_id' => '9', 'position' => '2', 'attribute_code' => 'description'],
            ['rule_id' => '', 'attribute_code' => 'color'],
        ]);
        self::assertSame(['sku', 'description', 'name', 'weight', 'color'], $this->savedCodes());
    }

    public function testAFailedSaveRedisplaysTheRowsInTheOrderItsMessagesCount(): void
    {
        $this->post([
            ['rule_id' => '1', 'position' => '2', 'attribute_code' => 'name'],
            ['rule_id' => '2', 'position' => '1', 'attribute_code' => ''],
        ], new LocalizedException(new Phrase('Rule 1: Choose the attribute this rule checks.')));
        self::assertNull($this->saved);
        self::assertSame(['Rule 1: Choose the attribute this rule checks.'], $this->errors);
        self::assertSame(
            ['', 'name'],
            array_map(static function (array $row) {
                return $row['attribute_code'];
            }, $this->persisted['rules']),
            'the row the message names comes first again'
        );
        self::assertSame('Fasteners', $this->persisted['name'], 'the other fields are kept as posted');
    }

    public function testAPostWithoutRulesSavesNone(): void
    {
        $this->post(null);
        self::assertSame([], $this->savedCodes());
    }

    /**
     * Save rule set 5 with the given rows, through a repository that keeps what it is given or refuses it.
     *
     * @param array<int, array<string, string>>|null $rules
     */
    private function post(?array $rules, ?LocalizedException $refusal = null): void
    {
        if (!class_exists(Action::class) || !interface_exists(ManagerInterface::class)) {
            self::markTestSkipped('Magento framework not available (set BCE_MAGENTO_ROOT).');
        }
        $params = ['ruleset_id' => '5', 'name' => 'Fasteners', 'kind' => 'attribute_set', 'is_enabled' => '1'];
        if ($rules !== null) {
            $params['rules'] = $rules;
        }
        $this->saved = null;
        $this->persisted = null;
        $this->errors = [];
        $request = $this->createStub(RequestInterface::class);
        $request->method('getParams')->willReturn($params);
        $request->method('getParam')->willReturnCallback(function ($name, $default = null) use ($params) {
            return isset($params[$name]) ? $params[$name] : $default;
        });
        $errors = &$this->errors;
        $messages = $this->createStub(ManagerInterface::class);
        $messages->method('addErrorMessage')->willReturnCallback(function ($message) use (&$errors, $messages) {
            $errors[] = (string)$message;
            return $messages;
        });
        $controller = (new \ReflectionClass(Save::class))->newInstanceWithoutConstructor();
        $this->set($controller, '_request', $request);
        $this->set($controller, 'messageManager', $messages);
        $this->set($controller, 'repository', $this->repository($refusal));
        $this->set($controller, 'ruleFactory', $this->ruleFactory());
        $this->set($controller, 'dataPersistor', $this->dataPersistor());
        $this->set($controller, 'resultRedirectFactory', $this->redirectFactory());
        $controller->execute();
    }

    /**
     * @return string[]
     */
    private function savedCodes(): array
    {
        return array_map(static function (RuleInterface $rule) {
            return $rule->getAttributeCode();
        }, $this->saved ? $this->saved->getRules() : []);
    }

    /**
     * @return array<int, int|null>
     */
    private function savedIds(): array
    {
        return array_map(static function (RuleInterface $rule) {
            return $rule->getRuleId();
        }, $this->saved ? $this->saved->getRules() : []);
    }

    /**
     * @return mixed a repository that loads rule set 5 and keeps the rule set it saves, or refuses it
     */
    private function repository(?LocalizedException $refusal)
    {
        $test = $this;
        return new class ($test, $refusal) {
            /**
             * @var SaveOrderTest
             */
            private $test;

            /**
             * @var LocalizedException|null
             */
            private $refusal;

            public function __construct(SaveOrderTest $test, ?LocalizedException $refusal)
            {
                $this->test = $test;
                $this->refusal = $refusal;
            }

            public function getById($id): RuleSetStub
            {
                return new RuleSetStub(['ruleset_id' => (int)$id]);
            }

            public function save(RuleSetStub $ruleSet): RuleSetStub
            {
                if ($this->refusal !== null) {
                    throw $this->refusal;
                }
                $this->test->keepSaved($ruleSet);
                return $ruleSet;
            }
        };
    }

    public function keepSaved(RuleSetStub $ruleSet): void
    {
        $this->saved = $ruleSet;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function keepPersisted(array $data): void
    {
        $this->persisted = $data;
    }

    /**
     * @return mixed
     */
    private function ruleFactory()
    {
        return new class {
            public function create(): RuleStub
            {
                return new RuleStub();
            }
        };
    }

    /**
     * @return mixed a data persistor that hands what it keeps to the test
     */
    private function dataPersistor()
    {
        $test = $this;
        return new class ($test) {
            /**
             * @var SaveOrderTest
             */
            private $test;

            public function __construct(SaveOrderTest $test)
            {
                $this->test = $test;
            }

            /**
             * @param string $key
             * @param mixed $value
             */
            public function set($key, $value): void
            {
                $this->test->keepPersisted($value);
            }

            /**
             * @param string $key
             */
            public function clear($key): void
            {
            }
        };
    }

    /**
     * @return mixed a factory of a redirect result that accepts any path
     */
    private function redirectFactory()
    {
        return new class {
            /**
             * @return mixed
             */
            public function create()
            {
                return new class {
                    /**
                     * @param string $path
                     * @param array<string, mixed> $params
                     * @return $this
                     */
                    public function setPath($path, array $params = [])
                    {
                        return $this;
                    }
                };
            }
        };
    }

    /**
     * @param mixed $object
     * @param mixed $value
     */
    private function set($object, string $property, $value): void
    {
        $reflection = new \ReflectionProperty(get_class($object), $property);
        if (PHP_VERSION_ID < 80100) {
            $reflection->setAccessible(true);
        }
        $reflection->setValue($object, $value);
    }
}
