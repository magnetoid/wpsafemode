<?php

declare(strict_types=1);

namespace WPSafeMode\Storage;

use WPSafeMode\Core\EnvironmentReport;
use WPSafeMode\Security\DataDir;
use WPSafeMode\Security\GuardedJson;
use WPSafeMode\Storage\Contracts\Storage;
use WPSafeMode\Storage\Contracts\StorageException;

final class StorageFactory
{
    public const BACKEND_SQLITE = 'sqlite';
    public const BACKEND_JSON   = 'json';

    /**
     * The backend is decided once, at install, and recorded. There is no
     * runtime switching and no migration between backends in v1 (spec 7).
     *
     * @param string|null $recorded the backend recorded at install, if any
     */
    public static function chooseBackend(bool $hasPdoSqlite, ?string $recorded): string
    {
        if ($recorded === self::BACKEND_JSON) {
            return self::BACKEND_JSON;
        }

        if ($recorded === self::BACKEND_SQLITE) {
            if (!$hasPdoSqlite) {
                throw new StorageException(
                    'This install uses SQLite storage but pdo_sqlite is no longer available on this server.'
                );
            }

            return self::BACKEND_SQLITE;
        }

        return $hasPdoSqlite ? self::BACKEND_SQLITE : self::BACKEND_JSON;
    }

    public static function create(string $dataDir, string $backend): Storage
    {
        return match ($backend) {
            self::BACKEND_SQLITE => new SqliteStorage($dataDir),
            self::BACKEND_JSON   => new JsonStorage($dataDir),
            default              => throw new StorageException('Unknown storage backend: ' . $backend),
        };
    }

    /**
     * Opens storage for a live install, recording the chosen backend and the
     * SQLite filename the first time so later requests stay on both.
     */
    public static function open(string $dataDir, EnvironmentReport $env): Storage
    {
        DataDir::ensure($dataDir);

        $record = GuardedJson::read($dataDir . '/install.php');
        $recorded = isset($record['backend']) ? (string) $record['backend'] : null;

        $backend = self::chooseBackend($env->hasPdoSqlite, $recorded === '' ? null : $recorded);

        if ($recorded === null || $recorded === '') {
            $record['backend'] = $backend;

            // The SQLite file cannot carry the die() guard: a .php-named
            // database is handed to PHP, which finds no opening tag and echoes
            // the binary verbatim. An unguessable name is the part of its
            // defence that does not depend on server configuration.
            $record['sqlite_file'] = 'store-' . bin2hex(random_bytes(16)) . '.db';

            GuardedJson::write($dataDir . '/install.php', $record);
        }

        return $backend === self::BACKEND_SQLITE
            ? new SqliteStorage($dataDir, self::sqliteFilename($dataDir))
            : new JsonStorage($dataDir);
    }

    /** The recorded SQLite filename, or a deterministic default for a fresh directory. */
    public static function sqliteFilename(string $dataDir): string
    {
        $record = GuardedJson::read($dataDir . '/install.php');

        return isset($record['sqlite_file']) && is_string($record['sqlite_file']) && $record['sqlite_file'] !== ''
            ? $record['sqlite_file']
            : 'store-' . bin2hex(random_bytes(16)) . '.db';
    }
}
