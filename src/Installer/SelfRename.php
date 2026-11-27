<?php

declare(strict_types=1);

namespace WPSafeMode\Installer;

if (!defined('WPSM_ENTRY')) {
    http_response_code(404);
    exit;
}

use WPSafeMode\Storage\Contracts\StorageException;

/**
 * Renames the install directory to something unguessable (spec 5.2).
 *
 * The ZIP extracts to wpsafemode/, which is exactly the name a scanner looks
 * for. Renaming does not make the tool safe on its own — it buys the time in
 * which the password, the lockout and the data-directory denial do the actual
 * work.
 */
final class SelfRename
{
    public const UNSAFE_NAME_MESSAGE = 'That folder name is not allowed. Use letters, numbers, dashes and underscores only.';

    public const LAYOUT_MESSAGE = 'Refusing to rename: this folder holds more than the tool, so renaming it would move '
        . 'everything else in it too. Give the tool a folder of its own first.';

    public static function suggest(): string
    {
        return 'wpsm-' . bin2hex(random_bytes(6));
    }

    /**
     * A single path segment: letters, digits, dash and underscore only. This
     * value comes from a form field and is about to be concatenated into a
     * filesystem path, so it is validated by construction rather than filtered.
     */
    public static function isSafeName(string $name): bool
    {
        return preg_match('/^[A-Za-z0-9_-]{3,64}\z/', $name) === 1;
    }

    /** @return string the new absolute path */
    public static function rename(string $appDir, string $newName): string
    {
        if (!self::isSafeName($newName)) {
            throw new StorageException(self::UNSAFE_NAME_MESSAGE);
        }

        $appDir = rtrim($appDir, '/');
        $parent = dirname($appDir);
        $target = $parent . '/' . $newName;

        if (!is_dir($appDir)) {
            throw new StorageException('The application directory could not be found.');
        }

        // A docroot, or a folder that also holds a site, must never move.
        if (!InstallLayout::holdsOnlyTheTool($appDir)) {
            throw new StorageException(self::LAYOUT_MESSAGE);
        }

        if (file_exists($target)) {
            throw new StorageException('A folder called "' . $newName . '" already exists next to this one.');
        }

        if (!@rename($appDir, $target)) {
            throw new StorageException(
                'The folder could not be renamed. The web server may not have permission to write to its parent '
                    . 'directory — rename it over FTP instead.'
            );
        }

        return $target;
    }
}
