<?php

declare(strict_types=1);

namespace WPSafeMode\Storage\Sqlite;

use PDO;
use WPSafeMode\Storage\Contracts\StorageException;

/**
 * Versioned schema steps for the SQLite backend. Each step runs, and its
 * version is recorded, inside one write transaction, so a crash leaves either
 * the old version with the old schema or the new version with the new one.
 */
final class SqliteMigrator
{
    /**
     * Step N produces schema version N. Later milestones append steps here.
     * A step must use only the PDO it is given, never SqliteConnection or
     * SqliteStorage: the migration lock is not re-entrant.
     *
     * @return array<int, callable(PDO): void>
     */
    public static function steps(): array
    {
        return [
            1 => static function (PDO $pdo): void {
                $pdo->exec('CREATE TABLE IF NOT EXISTS meta (key TEXT PRIMARY KEY, value TEXT NOT NULL)');
                $pdo->exec('CREATE TABLE IF NOT EXISTS config (key TEXT PRIMARY KEY, value TEXT NOT NULL)');

                // CHECK(id = 1) is the single-row constraint from spec 7.1: there is one
                // operator, not a user table, and the schema should make a second row
                // impossible rather than rely on the application never inserting one.
                $pdo->exec(
                    'CREATE TABLE IF NOT EXISTS auth (
                        id INTEGER PRIMARY KEY CHECK (id = 1),
                        password_hash TEXT NOT NULL,
                        hash_algo TEXT NOT NULL,
                        created_at INTEGER NOT NULL,
                        password_changed_at INTEGER NOT NULL
                    )'
                );

                $pdo->exec(
                    'CREATE TABLE IF NOT EXISTS sessions (
                        id TEXT PRIMARY KEY,
                        title TEXT NOT NULL,
                        blade TEXT NOT NULL,
                        status TEXT NOT NULL,
                        model TEXT NOT NULL,
                        effort TEXT NOT NULL,
                        created_at INTEGER NOT NULL,
                        updated_at INTEGER NOT NULL,
                        lock_token TEXT,
                        lock_expires_at INTEGER,
                        retry_count INTEGER NOT NULL DEFAULT 0,
                        retry_after_at INTEGER,
                        in_tokens INTEGER NOT NULL DEFAULT 0,
                        out_tokens INTEGER NOT NULL DEFAULT 0,
                        cache_read_tokens INTEGER NOT NULL DEFAULT 0,
                        cache_write_tokens INTEGER NOT NULL DEFAULT 0,
                        cost_micros INTEGER NOT NULL DEFAULT 0
                    )'
                );

                $pdo->exec(
                    'CREATE TABLE IF NOT EXISTS messages (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        session_id TEXT NOT NULL,
                        seq INTEGER NOT NULL,
                        role TEXT NOT NULL,
                        content_json TEXT NOT NULL,
                        api_message_id TEXT,
                        created_at INTEGER NOT NULL,
                        UNIQUE (session_id, seq)
                    )'
                );

                $pdo->exec(
                    'CREATE TABLE IF NOT EXISTS rate_limit (
                        bucket TEXT PRIMARY KEY,
                        window_start INTEGER NOT NULL,
                        attempts INTEGER NOT NULL DEFAULT 0,
                        locked_until INTEGER
                    )'
                );
            },

            // No data is carried over from the single credential: nothing was
            // released and no installer existed to create one.
            2 => static function (PDO $pdo): void {
                $pdo->exec('DROP TABLE IF EXISTS auth');
                $pdo->exec(
                    'CREATE TABLE users (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        login TEXT NOT NULL UNIQUE,
                        role TEXT NOT NULL,
                        password_hash TEXT NOT NULL,
                        hash_algo TEXT NOT NULL,
                        created_at INTEGER NOT NULL,
                        password_changed_at INTEGER NOT NULL,
                        disabled_at INTEGER
                    )'
                );
            },

            // Login sessions, each bound to the user who logged in. There is
            // nothing to carry over: no login session existed before users did.
            3 => static function (PDO $pdo): void {
                $pdo->exec(
                    'CREATE TABLE auth_sessions (
                        token_hash TEXT PRIMARY KEY,
                        user_id INTEGER NOT NULL,
                        created_at INTEGER NOT NULL,
                        last_seen_at INTEGER NOT NULL,
                        expires_at INTEGER NOT NULL,
                        ip TEXT NOT NULL,
                        ua_hash TEXT NOT NULL
                    )'
                );
            },
        ];
    }

    /**
     * @param array<int, callable(PDO): void>|null $steps Test seam; null means steps().
     */
    public static function migrate(PDO $pdo, ?array $steps = null): void
    {
        $steps ??= self::steps();

        if ($steps === []) {
            return;
        }

        ksort($steps);
        $target = (int) max(array_keys($steps));

        // Cheap pre-check outside any lock: refuse a newer directory before
        // taking the write lock, and skip the lock when already current.
        $current = self::currentVersion($pdo);
        self::refuseNewer($current, $target);

        if ($current >= $target) {
            return;
        }

        foreach ($steps as $version => $step) {
            if ($version <= $current) {
                continue;
            }

            self::runStep($pdo, $version, $step, $target);
        }
    }

    public static function currentVersion(PDO $pdo): int
    {
        $table = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'meta'");

        if ($table === false || $table->fetch() === false) {
            return 0;
        }

        $stmt = $pdo->prepare('SELECT value FROM meta WHERE key = :k');
        $stmt->execute([':k' => 'schema_version']);
        $value = $stmt->fetchColumn();

        if ($value === false) {
            return 0;
        }

        if (!is_string($value) || preg_match('/^\d+$/', $value) !== 1) {
            throw new StorageException('The stored schema version is not an integer.');
        }

        return (int) $value;
    }

    private static function refuseNewer(int $stored, int $target): void
    {
        if ($stored > $target) {
            throw new StorageException(sprintf(
                'This data directory was written by a newer version of WP Safe Mode '
                . '(schema %d; this copy understands up to %d). Upload the newer version again.',
                $stored,
                $target
            ));
        }
    }

    private static function runStep(PDO $pdo, int $version, callable $step, int $target): void
    {
        // BEGIN IMMEDIATE and exec('COMMIT')/exec('ROLLBACK'), not
        // beginTransaction()/commit(): see SqliteRateLimiter::hit(). IMMEDIATE
        // takes the write lock before the version is re-read, so a request
        // that waited here while another migrated sees the new version and
        // skips the step; and PDO's commit()/rollBack() do not pair with a raw
        // BEGIN on PHP 8.0 through 8.3.
        $pdo->exec('BEGIN IMMEDIATE');

        try {
            $current = self::currentVersion($pdo);
            self::refuseNewer($current, $target);

            if ($current < $version) {
                try {
                    $step($pdo);
                } catch (\Throwable $e) {
                    throw new StorageException(
                        sprintf('Migration to schema version %d failed: %s', $version, $e->getMessage()),
                        0,
                        $e
                    );
                }

                $stmt = $pdo->prepare(
                    'INSERT INTO meta (key, value) VALUES (:k, :v)
                     ON CONFLICT (key) DO UPDATE SET value = excluded.value'
                );
                $stmt->execute([':k' => 'schema_version', ':v' => (string) $version]);
            }

            $pdo->exec('COMMIT');
        } catch (\Throwable $e) {
            try {
                $pdo->exec('ROLLBACK');
            } catch (\Throwable $ignored) {
                // The original failure is the one worth reporting.
            }

            throw $e instanceof StorageException
                ? $e
                : new StorageException('Migration failed: ' . $e->getMessage(), 0, $e);
        }
    }
}
