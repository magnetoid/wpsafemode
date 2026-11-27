<?php

declare(strict_types=1);

namespace WPSafeMode\Storage\Sqlite;

use PDO;
use PDOException;
use WPSafeMode\Storage\Contracts\StorageException;

final class SqliteConnection
{
    private ?PDO $pdo = null;

    /**
     * @param array<int, mixed> $attributes extra PDO attributes, for tests
     *        only: the suite sets PDO::ATTR_STRINGIFY_FETCHES to read INTEGER
     *        columns back as strings, the way pdo_sqlite does before PHP 8.1.
     *        Production passes none and keeps the defaults below.
     */
    public function __construct(private string $path, private array $attributes = [])
    {
    }

    public function pdo(): PDO
    {
        if ($this->pdo !== null) {
            return $this->pdo;
        }

        $dir = dirname($this->path);

        if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new StorageException('Cannot create data directory: ' . $dir);
        }

        try {
            $pdo = new PDO('sqlite:' . $this->path, null, null, $this->attributes + [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            throw new StorageException('Cannot open SQLite database: ' . $e->getMessage(), 0, $e);
        }

        // Deliberately NOT enabling WAL. WAL needs shared memory, which fails
        // on the NFS-backed filesystems some shared hosts use. The default
        // rollback journal works everywhere; busy_timeout covers contention.
        $pdo->exec('PRAGMA busy_timeout = 5000');
        $pdo->exec('PRAGMA foreign_keys = ON');

        SqliteMigrator::migrate($pdo);

        return $this->pdo = $pdo;
    }
}
