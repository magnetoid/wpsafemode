<?php

declare(strict_types=1);

namespace WPSafeMode\Storage\Sqlite;

use PDO;
use WPSafeMode\Core\Version;

final class SqliteSchema
{
    /**
     * Idempotent. Safe to call on every connection.
     *
     * Only the tables M0 needs are created here. Later milestones add their
     * own tables through this same class (spec 7.1).
     */
    public static function install(PDO $pdo): void
    {
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

        $stmt = $pdo->prepare('INSERT OR IGNORE INTO meta (key, value) VALUES (:k, :v)');
        $stmt->execute([':k' => 'schema_version', ':v' => (string) Version::SCHEMA]);
    }
}
