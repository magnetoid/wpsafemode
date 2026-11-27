<?php

declare(strict_types=1);

if (!defined('WPSM_ENTRY')) {
    http_response_code(404);
    exit;
}

/**
 * Single entry point for src/index.php. Loads the autoloader and refuses to
 * continue on an unsupported PHP version.
 *
 * The version check is written without any 8.0-only syntax so that it still
 * produces a readable message on PHP 5.x/7.x rather than a parse error.
 */

require __DIR__ . '/autoload.php';

use WPSafeMode\Core\Version;

if (version_compare(PHP_VERSION, Version::MIN_PHP, '<')) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "WP Safe Mode requires PHP " . Version::MIN_PHP . " or newer.\n";
    echo "This server is running PHP " . PHP_VERSION . ".\n";
    exit(1);
}

return __DIR__;
