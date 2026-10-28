<?php
/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 *
 * Static check: every identifier the module exposes carries the bce_ / bce- / BigCartEnergy prefix.
 *
 *   php dev/check-prefixes.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];

$check = function (string $what, string $value, string $pattern) use (&$failures): void {
    if (!preg_match($pattern, $value)) {
        $failures[] = $what . ': ' . $value;
    }
};

$xml = function (string $file): SimpleXMLElement {
    $loaded = simplexml_load_file($file);
    if ($loaded === false) {
        throw new RuntimeException('Unreadable XML: ' . $file);
    }
    return $loaded;
};

// Tables, indexes and constraints.
foreach ($xml($root . '/etc/db_schema.xml')->table as $table) {
    $check('table', (string)$table['name'], '/^bce_completeness_/');
    foreach ($table->constraint as $constraint) {
        $id = (string)$constraint['referenceId'];
        if ($id !== 'PRIMARY') {
            $check('constraint', $id, '/^BCE_CMPL_/');
        }
    }
    foreach ($table->index as $index) {
        $check('index', (string)$index['referenceId'], '/^BCE_CMPL_/');
    }
}

// Admin route.
foreach ($xml($root . '/etc/adminhtml/routes.xml')->router->route as $route) {
    $check('route id', (string)$route['id'], '/^bce_/');
    $check('frontName', (string)$route['frontName'], '/^bce_/');
}

// ACL ids (ours only).
$acl = file_get_contents($root . '/etc/acl.xml');
preg_match_all('/resource id="([^"]+)"/', $acl, $m);
foreach ($m[1] as $id) {
    if (strpos($id, 'Magento_') === 0 || strpos($id, 'BigCartEnergy_Core::') === 0) {
        continue;
    }
    $check('acl', $id, '/^BigCartEnergy_CatalogCompleteness::/');
}

// Menu ids.
foreach ($xml($root . '/etc/adminhtml/menu.xml')->menu->add as $add) {
    $check('menu', (string)$add['id'], '/^BigCartEnergy_CatalogCompleteness::/');
}

// Config section and config paths.
foreach ($xml($root . '/etc/adminhtml/system.xml')->system->section as $section) {
    $check('config section', (string)$section['id'], '/^bce_/');
}
$configXml = file_get_contents($root . '/etc/config.xml');
if (!preg_match('/<default>\s*<bce_completeness>/', $configXml)) {
    $failures[] = 'config.xml defaults are not under bce_completeness';
}

// Indexer and mview ids.
foreach ($xml($root . '/etc/indexer.xml')->indexer as $indexer) {
    $check('indexer id', (string)$indexer['id'], '/^bce_/');
    $check('indexer view', (string)$indexer['view_id'], '/^bce_/');
}
foreach ($xml($root . '/etc/mview.xml')->view as $view) {
    $check('mview id', (string)$view['id'], '/^bce_/');
}

// Queue names.
foreach ($xml($root . '/etc/communication.xml')->topic as $topic) {
    $check('topic', (string)$topic['name'], '/^bce_/');
}
foreach ($xml($root . '/etc/queue_consumer.xml')->consumer as $consumer) {
    $check('consumer', (string)$consumer['name'], '/^bceCompleteness/');
    $check('queue', (string)$consumer['queue'], '/^bce_/');
}

// Cron group, job and the module's own log file.
foreach ($xml($root . '/etc/crontab.xml')->group as $group) {
    $check('cron group', (string)$group['id'], '/^bce_/');
    foreach ($group->job as $job) {
        $check('cron job', (string)$job['name'], '/^bce_completeness_/');
    }
}
foreach ($xml($root . '/etc/cron_groups.xml')->group as $group) {
    $check('cron group settings', (string)$group['id'], '/^bce_/');
}
$diXml = file_get_contents($root . '/etc/di.xml');
preg_match_all('#<argument name="fileName" xsi:type="string">([^<]+)</argument>#', $diXml, $logFiles);
foreach ($logFiles[1] as $logFile) {
    $check('log file', $logFile, '#^/var/log/bce_#');
}

// REST routes and extension attributes.
foreach ($xml($root . '/etc/webapi.xml')->route as $route) {
    $check('REST route', (string)$route['url'], '#^/V1/bce-completeness/#');
}
foreach ($xml($root . '/etc/extension_attributes.xml')->extension_attributes->attribute as $attribute) {
    $check('extension attribute', (string)$attribute['code'], '/^bce_completeness_/');
}

// UI components, layout handles and block names.
foreach (glob($root . '/view/adminhtml/ui_component/*.xml') as $file) {
    $name = basename($file, '.xml');
    if ($name !== 'product_listing') {
        $check('ui component', $name, '/^bce_completeness_/');
    }
}
foreach (glob($root . '/view/adminhtml/layout/*.xml') as $file) {
    $name = basename($file, '.xml');
    if (strpos($name, 'catalog_product_') !== 0) {
        $check('layout handle', $name, '/^bce_completeness_/');
    }
    $content = file_get_contents($file);
    preg_match_all('/<(?:block|container)[^>]*\sname="([^"]+)"/', $content, $names);
    foreach ($names[1] as $blockName) {
        $check('block name in ' . basename($file), $blockName, '/^bce_completeness\./');
    }
}

// Plugins and observers.
foreach (glob($root . '/etc/{di,adminhtml/di,webapi_rest/di}.xml', GLOB_BRACE) as $file) {
    preg_match_all('/<plugin name="([^"]+)"/', file_get_contents($file), $plugins);
    foreach ($plugins[1] as $plugin) {
        $check('plugin in ' . basename(dirname($file)) . '/' . basename($file), $plugin, '/^bce_completeness_/');
    }
}
preg_match_all('/<observer name="([^"]+)"/', file_get_contents($root . '/etc/events.xml'), $observers);
foreach ($observers[1] as $observer) {
    $check('observer', $observer, '/^bce_completeness_/');
}

// CLI command name.
$command = file_get_contents($root . '/Console/Command/RecalculateCommand.php');
preg_match("/const NAME = '([^']+)'/", $command, $commandName);
$check('CLI command', $commandName[1] ?? '', '/^bce:completeness:/');

// CSS classes defined in the module's LESS.
$less = file_get_contents($root . '/view/adminhtml/web/css/source/_module.less');
preg_match_all('/^\s*\.([a-zA-Z][a-zA-Z0-9_-]*)/m', $less, $classes);
foreach (array_unique($classes[1]) as $class) {
    if (in_array($class, ['admin__control-select', 'admin__control-text', 'admin__field-error', 'admin__dynamic-rows', 'col-draggable', 'draggable-handle', 'message', 'action-primary', 'action-secondary', 'fieldset-wrapper'], true)) {
        continue;
    }
    $check('css class', $class, '/^(bce-completeness-|bce-brand-)/');
}
preg_match_all('/@([a-z][a-z0-9_-]*):/', $less, $variables);
foreach (array_unique($variables[1]) as $variable) {
    $check('less variable', $variable, '/^bce-cmpl__/');
}

// JavaScript module paths and PHP namespace.
foreach (glob($root . '/view/adminhtml/web/js/**/*.js') as $file) {
    $check('js path', str_replace($root . '/', '', $file), '#^view/adminhtml/web/js/#');
}
$phpFiles = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($phpFiles as $file) {
    if ($file->getExtension() !== 'php' || strpos($file->getPathname(), DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR) !== false) {
        continue;
    }
    $content = file_get_contents($file->getPathname());
    if (preg_match('/^namespace\s+([^;]+);/m', $content, $ns)) {
        $check('namespace in ' . str_replace($root . DIRECTORY_SEPARATOR, '', $file->getPathname()), $ns[1], '/^BigCartEnergy\\\\CatalogCompleteness(\\\\|$)/');
    }
}

if ($failures) {
    echo "Un-prefixed identifiers:\n";
    foreach ($failures as $failure) {
        echo '  - ' . $failure . "\n";
    }
    exit(1);
}
echo "Prefix check passed: every exposed identifier carries bce_, bce-, BCE_CMPL_ or BigCartEnergy.\n";
