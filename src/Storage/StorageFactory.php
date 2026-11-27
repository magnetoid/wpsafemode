<?php

declare(strict_types=1);

namespace WPSafeMode\Storage;

if (!defined('WPSM_ENTRY')) {
    http_response_code(404);
    exit;
}

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

        $path = $dataDir . '/install.php';
        $decision = self::decide(GuardedJson::read($path), $env);

        if ($decision['dirty']) {
            // The first requests on a fresh copy race to choose the backend
            // and the SQLite filename. Each one re-reads and decides again
            // under the lock, so the first to get in records its choice and
            // every other request adopts that record instead of minting a
            // filename of its own. A complete record never takes the lock.
            $decision = GuardedJson::withExclusiveLock($path, static function () use ($path, $env): array {
                $decision = self::decide(GuardedJson::read($path), $env);

                if ($decision['dirty']) {
                    GuardedJson::write($path, $decision['record']);
                }

                return $decision;
            });
        }

        return $decision['backend'] === self::BACKEND_SQLITE
            ? new SqliteStorage($dataDir, (string) $decision['sqlite_file'])
            : new JsonStorage($dataDir);
    }

    /**
     * What this install's record says, plus whatever must be added to it.
     *
     * @param array<string, mixed> $record
     * @return array{record: array<string, mixed>, backend: string, sqlite_file: ?string, dirty: bool}
     */
    private static function decide(array $record, EnvironmentReport $env): array
    {
        $recorded = isset($record['backend']) ? (string) $record['backend'] : null;

        $backend = self::chooseBackend($env->hasPdoSqlite, $recorded === '' ? null : $recorded);

        $sqliteFile = isset($record['sqlite_file']) && is_string($record['sqlite_file']) && $record['sqlite_file'] !== ''
            ? $record['sqlite_file']
            : null;

        $dirty = false;

        if ($recorded === null || $recorded === '') {
            $record['backend'] = $backend;
            $dirty = true;
        }

        // Guarded on sqlite_file being absent, not on backend being absent:
        // those are two different facts, and a future writer that creates
        // install.php for some other reason without a backend key must not
        // make this branch mint a second random filename and abandon the
        // database (and the encrypted API key inside it) the first one
        // already recorded.
        if ($backend === self::BACKEND_SQLITE && $sqliteFile === null) {
            // The SQLite file cannot carry the die() guard: a .php-named
            // database is handed to PHP, which finds no opening tag and echoes
            // the binary verbatim. An unguessable name is the part of its
            // defence that does not depend on server configuration.
            $sqliteFile = 'store-' . bin2hex(random_bytes(16)) . '.db';
            $record['sqlite_file'] = $sqliteFile;
            $dirty = true;
        }

        return ['record' => $record, 'backend' => $backend, 'sqlite_file' => $sqliteFile, 'dirty' => $dirty];
    }

    /** The recorded SQLite filename, or null when this install has not recorded one yet. */
    public static function sqliteFilename(string $dataDir): ?string
    {
        $record = GuardedJson::read($dataDir . '/install.php');

        return isset($record['sqlite_file']) && is_string($record['sqlite_file']) && $record['sqlite_file'] !== ''
            ? $record['sqlite_file']
            : null;
    }
}
