<?php

declare(strict_types=1);

namespace WPSafeMode\Installer;

if (!defined('WPSM_ENTRY')) {
    http_response_code(404);
    exit;
}

/**
 * Whether a folder is one the tool owns outright (R41, sharing the guards of
 * R3, R28 and R30).
 *
 * Renaming or removing the tool's folder acts on everything in it. If the
 * tool's files sit flat in a docroot or beside other content, either action
 * would take the site with it, so both refuse unless the folder holds the
 * tool alone.
 */
final class InstallLayout
{
    /** Every top-level name the tool ships (src/), plus its data directory. */
    public const SHIPPED = [
        'Api', 'Core', 'Installer', 'Security', 'Storage',
        'autoload.php', 'bootstrap.php', 'index.php', 'public', 'data',
    ];

    /** Any of these means the folder is a WordPress site, not the tool. */
    private const WORDPRESS_MARKERS = ['wp-config.php', 'wp-load.php', 'wp-includes'];

    /**
     * True only for a folder that is not a filesystem root, holds the tool's
     * bootstrap, has no WordPress marker, and holds nothing but the shipped
     * names, an error_log and dotfiles.
     */
    public static function holdsOnlyTheTool(string $appDir): bool
    {
        $app = @realpath($appDir);

        if ($app === false || dirname($app) === $app || !@is_file($app . '/bootstrap.php')) {
            return false;
        }

        foreach (self::WORDPRESS_MARKERS as $marker) {
            if (@file_exists($app . '/' . $marker)) {
                return false;
            }
        }

        $entries = @scandir($app);

        if ($entries === false) {
            return false;
        }

        foreach ($entries as $entry) {
            if (
                $entry === '.' || $entry === '..' || $entry === 'error_log'
                || $entry[0] === '.' || in_array($entry, self::SHIPPED, true)
            ) {
                continue;
            }

            return false;
        }

        return true;
    }
}
