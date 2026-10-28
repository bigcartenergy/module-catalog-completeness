<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
declare(strict_types=1);

/*
 * Standalone bootstrap: autoloads this module and, when BCE_MAGENTO_ROOT points at a Magento checkout,
 * the framework and core module classes the tests touch. Inside a Magento installation the unit test
 * framework provides its own bootstrap and this file is not used.
 */
$bceModuleRoot = dirname(__DIR__, 2);
$bceMagentoRoot = getenv('BCE_MAGENTO_ROOT') ?: null;

spl_autoload_register(function (string $class) use ($bceModuleRoot, $bceMagentoRoot): void {
    $modulePrefix = 'BigCartEnergy\\CatalogCompleteness\\';
    if (strncmp($class, $modulePrefix, strlen($modulePrefix)) === 0) {
        $file = $bceModuleRoot . '/' . str_replace('\\', '/', substr($class, strlen($modulePrefix))) . '.php';
        if (is_file($file)) {
            require $file;
        }
        return;
    }
    if ($bceMagentoRoot === null) {
        return;
    }
    $frameworkPrefix = 'Magento\\Framework\\';
    if (strncmp($class, $frameworkPrefix, strlen($frameworkPrefix)) === 0) {
        $file = $bceMagentoRoot . '/lib/internal/Magento/Framework/'
            . str_replace('\\', '/', substr($class, strlen($frameworkPrefix))) . '.php';
        if (is_file($file)) {
            require $file;
        }
        return;
    }
    if (preg_match('#^Magento\\\\([A-Za-z0-9]+)\\\\(.+)$#', $class, $matches)) {
        $file = $bceMagentoRoot . '/app/code/Magento/' . $matches[1] . '/'
            . str_replace('\\', '/', $matches[2]) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

if ($bceMagentoRoot !== null && is_file($bceMagentoRoot . '/lib/internal/Magento/Framework/Phrase/__.php')) {
    require_once $bceMagentoRoot . '/lib/internal/Magento/Framework/Phrase/__.php';
}

if (!function_exists('__')) {
    /**
     * Minimal stand-in for Magento's translation function: placeholder replacement only.
     *
     * @param mixed ...$arguments
     */
    function __(...$arguments): string
    {
        $text = (string)array_shift($arguments);
        if ($arguments && is_array($arguments[0])) {
            $arguments = $arguments[0];
        }
        foreach (array_values($arguments) as $position => $argument) {
            $text = str_replace('%' . ($position + 1), (string)$argument, $text);
        }
        return $text;
    }
}
