<?php

declare(strict_types=1);

// What src/index.php defines before anything else: without it every file
// under src/ answers 404 and exits.
define('WPSM_ENTRY', true);

require __DIR__ . '/../src/autoload.php';

spl_autoload_register(static function (string $class): void {
    $prefix = 'WPSafeMode\\Tests\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $path = __DIR__ . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($path)) {
        require $path;
    }
});
