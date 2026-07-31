<?php

declare(strict_types=1);

namespace WPSafeMode\Storage;

use WPSafeMode\Core\EnvironmentReport;
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
     * Opens storage for a live install, recording the chosen backend the
     * first time so later requests stay on it.
     */
    public static function open(string $dataDir, EnvironmentReport $env): Storage
    {
        $marker = $dataDir . '/backend';
        $recorded = is_file($marker) ? trim((string) file_get_contents($marker)) : null;

        $backend = self::chooseBackend($env->hasPdoSqlite, $recorded === '' ? null : $recorded);

        if ($recorded === null || $recorded === '') {
            if (!is_dir($dataDir) && !@mkdir($dataDir, 0700, true) && !is_dir($dataDir)) {
                throw new StorageException('Cannot create data directory: ' . $dataDir);
            }

            file_put_contents($marker, $backend, LOCK_EX);
            @chmod($marker, 0600);
        }

        return self::create($dataDir, $backend);
    }
}
