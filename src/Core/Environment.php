<?php

declare(strict_types=1);

namespace WPSafeMode\Core;

if (!defined('WPSM_ENTRY')) {
    http_response_code(404);
    exit;
}

final class Environment
{
    /** Directories to search for WordPress, relative to the install directory. */
    private const WP_CANDIDATES = ['..', '.', '../..', '../wordpress'];

    public static function probe(?string $baseDir = null): EnvironmentReport
    {
        $baseDir = $baseDir ?? dirname(__DIR__);
        $free = @disk_free_space($baseDir);

        return new EnvironmentReport(
            phpVersion:    PHP_VERSION,
            phpSatisfied:  version_compare(PHP_VERSION, Version::MIN_PHP, '>='),
            hasPdoSqlite:  extension_loaded('pdo_sqlite'),
            hasMysqli:     extension_loaded('mysqli'),
            hasOpenssl:    extension_loaded('openssl'),
            hasSodium:     extension_loaded('sodium'),
            hasExec:       self::execUsable(),
            freeDiskBytes: is_float($free) ? (int) $free : 0,
            wpRoot:        self::findWordPressRoot($baseDir),
        );
    }

    /**
     * exec() can exist but be listed in disable_functions, which is the
     * common shared-hosting configuration. Both must be checked.
     */
    private static function execUsable(): bool
    {
        if (!function_exists('exec')) {
            return false;
        }

        $disabled = array_map(
            'trim',
            explode(',', (string) ini_get('disable_functions'))
        );

        return !in_array('exec', $disabled, true);
    }

    /**
     * A directory is the WordPress root when it holds both wp-config.php and
     * wp-includes/version.php. Requiring both avoids matching a stray
     * wp-config.php backup.
     */
    private static function findWordPressRoot(string $baseDir): ?string
    {
        foreach (self::WP_CANDIDATES as $candidate) {
            $path = realpath($baseDir . '/' . $candidate);

            if ($path === false) {
                continue;
            }

            if (is_file($path . '/wp-config.php') && is_file($path . '/wp-includes/version.php')) {
                return $path;
            }
        }

        return null;
    }
}
