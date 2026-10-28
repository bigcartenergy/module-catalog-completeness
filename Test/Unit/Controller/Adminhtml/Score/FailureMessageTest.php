<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

namespace BigCartEnergy\CatalogCompleteness\Test\Unit\Controller\Adminhtml\Score;

use BigCartEnergy\CatalogCompleteness\Controller\Adminhtml\Score\Export;
use BigCartEnergy\CatalogCompleteness\Controller\Adminhtml\Score\Recalculate;
use BigCartEnergy\CatalogCompleteness\Model\Config;
use Magento\Backend\App\Action;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Phrase;
use PHPUnit\Framework\TestCase;

/**
 * Export and Recalculate when the work list fails. An unexpected failure is logged with its exception and the
 * admin sees a fixed sentence, never the exception's own text (an SQL error carries the whole statement); a
 * LocalizedException keeps its message, which is meant for the admin.
 */
class FailureMessageTest extends TestCase
{
    private const SQL_ERROR = "SQLSTATE[42S22]: Column not found: 1054 Unknown column 'NULL' in 'where clause', "
        . 'query was: SELECT `main_table`.`entity_id` FROM `catalog_product_entity` AS `main_table`';

    private const EXPORT_FAILED = 'The export failed. The details are in the Magento exception log.';

    private const RECALCULATION_FAILED = 'The recalculation could not be started. The details are in the Magento exception log.';

    /**
     * @var string[]
     */
    private $errors = [];

    public function testAFailedExportIsLoggedAndTheAdminSeesAFixedSentence(): void
    {
        $failure = new \RuntimeException(self::SQL_ERROR);
        $logger = $this->logger();
        $redirect = $this->redirect();
        $this->export($failure, $logger, $redirect)->execute();
        self::assertSame([self::EXPORT_FAILED], $this->errors);
        self::assertSame(
            [['critical', 'Catalog completeness: the export failed.', $failure]],
            $logger->records,
            'the exception goes to the log'
        );
        self::assertSame(1, $redirect->toReferer, 'back to the dashboard');
    }

    public function testAnExportRefusalKeepsItsMessage(): void
    {
        $logger = $this->logger();
        $this->export(new LocalizedException(new Phrase('An item needs to be selected.')), $logger, $this->redirect())
            ->execute();
        self::assertSame(['An item needs to be selected.'], $this->errors);
        self::assertSame([], $logger->records);
    }

    public function testAFailedRecalculationIsLoggedAndAnswersWithAFixedSentence(): void
    {
        $failure = new \RuntimeException(self::SQL_ERROR);
        $logger = $this->logger();
        $json = $this->json();
        $this->recalculate($failure, $logger, $json, true)->execute();
        self::assertSame(400, $json->code);
        self::assertSame(['ok' => false, 'error' => self::RECALCULATION_FAILED], $json->data);
        self::assertSame(
            [['critical', 'Catalog completeness: the recalculation could not be started.', $failure]],
            $logger->records
        );

        // The form post (no isAjax) shows the same sentence as an error message.
        $logger = $this->logger();
        $this->recalculate($failure, $logger, $this->json(), false)->execute();
        self::assertSame([self::RECALCULATION_FAILED], $this->errors);
        self::assertCount(1, $logger->records);
    }

    public function testARecalculationRefusalKeepsItsMessage(): void
    {
        $logger = $this->logger();
        $json = $this->json();
        $this->recalculate(new LocalizedException(new Phrase('Select at least one product.')), $logger, $json, true)
            ->execute();
        self::assertSame(['ok' => false, 'error' => 'Select at least one product.'], $json->data);
        self::assertSame([], $logger->records);
    }

    /**
     * Export selected in the "No rules assigned" view, with a work list that throws the given exception.
     *
     * @param mixed $logger
     * @param mixed $redirect
     */
    private function export(\Throwable $failure, $logger, $redirect): Export
    {
        $controller = $this->controller(Export::class, ['coverage' => 'none', 'selected' => ['12']]);
        $this->set($controller, 'collectionFactory', $this->failingFactory($failure));
        $this->set($controller, 'uncoveredCollectionFactory', $this->failingFactory($failure));
        $this->set($controller, 'logger', $logger);
        $this->set($controller, 'resultRedirectFactory', $this->factoryOf($redirect));
        return $controller;
    }

    /**
     * Recalculate selected in the "No rules assigned" view, with a work list that throws the given exception.
     *
     * @param mixed $logger
     * @param mixed $json
     */
    private function recalculate(\Throwable $failure, $logger, $json, bool $isAjax): Recalculate
    {
        $params = ['coverage' => 'none', 'selected' => ['12']];
        if ($isAjax) {
            $params['isAjax'] = 'true';
        }
        $controller = $this->controller(Recalculate::class, $params);
        $this->set($controller, 'collectionFactory', $this->failingFactory($failure));
        $this->set($controller, 'uncoveredCollectionFactory', $this->failingFactory($failure));
        $this->set($controller, 'logger', $logger);
        $this->set($controller, 'jsonFactory', $this->factoryOf($json));
        $this->set($controller, 'resultRedirectFactory', $this->factoryOf($this->redirect()));
        return $controller;
    }

    /**
     * The controller without its constructor (its collection factories are generated code), with the request,
     * the settings and a message manager that records the error messages.
     *
     * @param array<string, mixed> $params
     * @return mixed
     */
    private function controller(string $class, array $params)
    {
        if (!class_exists(Action::class) || !interface_exists(ManagerInterface::class)) {
            self::markTestSkipped('Magento framework not available (set BCE_MAGENTO_ROOT).');
        }
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
        $controller = (new \ReflectionClass($class))->newInstanceWithoutConstructor();
        $this->set($controller, '_request', $request);
        $this->set($controller, 'messageManager', $messages);
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturn(null);
        $scopeConfig->method('isSetFlag')->willReturn(false);
        $this->set($controller, 'config', new Config($scopeConfig));
        return $controller;
    }

    /**
     * @return mixed a factory whose collection fails to build
     */
    private function failingFactory(\Throwable $failure)
    {
        return new class ($failure) {
            /**
             * @var \Throwable
             */
            private $failure;

            public function __construct(\Throwable $failure)
            {
                $this->failure = $failure;
            }

            public function create()
            {
                throw $this->failure;
            }
        };
    }

    /**
     * @param mixed $product
     * @return mixed a factory that hands out the given result
     */
    private function factoryOf($product)
    {
        return new class ($product) {
            /**
             * @var mixed
             */
            private $product;

            /**
             * @param mixed $product
             */
            public function __construct($product)
            {
                $this->product = $product;
            }

            /**
             * @return mixed
             */
            public function create()
            {
                return $this->product;
            }
        };
    }

    /**
     * @return mixed a logger that records what it is given
     */
    private function logger()
    {
        return new class {
            /**
             * @var array<int, array<int, mixed>>
             */
            public $records = [];

            /**
             * @param mixed $message
             * @param array<string, mixed> $context
             */
            public function critical($message, array $context = []): void
            {
                $this->records[] = ['critical', (string)$message, isset($context['exception']) ? $context['exception'] : null];
            }

            /**
             * @param mixed $message
             * @param array<string, mixed> $context
             */
            public function error($message, array $context = []): void
            {
                $this->records[] = ['error', (string)$message, isset($context['exception']) ? $context['exception'] : null];
            }
        };
    }

    /**
     * @return mixed a redirect result that counts the redirects back
     */
    private function redirect()
    {
        return new class {
            /**
             * @var int
             */
            public $toReferer = 0;

            /**
             * @return $this
             */
            public function setRefererOrBaseUrl()
            {
                $this->toReferer++;
                return $this;
            }
        };
    }

    /**
     * @return mixed a JSON result that keeps its code and data
     */
    private function json()
    {
        return new class {
            /**
             * @var int|null
             */
            public $code;

            /**
             * @var mixed
             */
            public $data;

            /**
             * @param int $code
             * @return $this
             */
            public function setHttpResponseCode($code)
            {
                $this->code = $code;
                return $this;
            }

            /**
             * @param mixed $data
             * @return $this
             */
            public function setData($data)
            {
                $this->data = $data;
                return $this;
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
