<?php

declare(strict_types=1);

namespace WPSafeMode\Installer;

use WPSafeMode\Storage\Contracts\StorageException;

/**
 * Removes the data directory and then the application itself (spec 5.8).
 *
 * Order matters: secrets first. If the process dies halfway, what survives is
 * an application with nothing in it rather than an unreachable application
 * whose key vault is still on disk.
 *
 * A receipt is left beside the removed directory so the operator can see the
 * cleanup happened rather than wondering whether the tool half-deleted itself.
 */
final class Uninstaller
{
    public const RECEIPT = 'WPSAFEMODE-REMOVED.txt';

    /** Every top-level name the tool ships (src/), plus its data directory. */
    public const SHIPPED = [
        'Api', 'Core', 'Installer', 'Security', 'Storage',
        'autoload.php', 'bootstrap.php', 'index.php', 'public', 'data',
    ];

    private const INCOMPLETE = 'The uninstall did not finish: some files could not be removed.';

    /**
     * Throws, deleting nothing, unless the layout is one the tool can safely
     * remove itself from. The message is fixed and carries no path.
     */
    public static function preflight(string $appDir, string $dataDir): void
    {
        // Load the failure type now: once the files are gone it cannot be autoloaded.
        class_exists(StorageException::class);

        $app = @realpath($appDir);
        $data = @realpath($dataDir);

        if (
            $app === false || $data === false
            || strpos($data, $app . '/') !== 0
            || dirname($app) === $app
            || !@is_file($app . '/bootstrap.php')
            || @file_exists($app . '/wp-config.php')
            || @file_exists($app . '/wp-load.php')
            || @file_exists($app . '/wp-includes')
            || !self::holdsOnlyTheTool($app)
        ) {
            throw new StorageException('Refusing to uninstall: this folder layout is not one the tool can safely remove itself from.');
        }
    }

    /** Anything beside the tool's own files means this is not a folder the tool owns. */
    private static function holdsOnlyTheTool(string $app): bool
    {
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

    /** @return string the path of the receipt */
    public static function run(string $appDir, string $dataDir): string
    {
        self::preflight($appDir, $dataDir);

        $app = (string) @realpath($appDir);
        $data = (string) @realpath($dataDir);
        $receipt = dirname($app) . '/' . self::RECEIPT;

        self::removeRecursively($data);
        @clearstatcache();

        if (@file_exists($data)) {
            throw new StorageException(self::INCOMPLETE);
        }

        self::removeRecursively($app);
        @clearstatcache();

        if (@file_exists($app)) {
            throw new StorageException(self::INCOMPLETE);
        }

        // Last, so a receipt never exists while any of the tool does.
        @file_put_contents(
            $receipt,
            "WP Safe Mode was uninstalled on " . gmdate('Y-m-d H:i:s') . " UTC.\n\n"
                . "The tool removed its own folder and its data directory, including the stored API key.\n"
                . "Nothing of it remains except this note, which is safe to delete.\n\n"
                . "If you used the tool to change your site, those changes are still in place — uninstalling\n"
                . "does not undo them.\n"
        );

        return $receipt;
    }

    private static function removeRecursively(string $path): void
    {
        if (@is_link($path) || @is_file($path)) {
            @unlink($path);

            return;
        }

        if (!@is_dir($path)) {
            return;
        }

        foreach (@scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            self::removeRecursively($path . '/' . $entry);
        }

        @rmdir($path);
    }
}
