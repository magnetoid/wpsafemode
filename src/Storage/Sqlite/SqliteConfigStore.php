<?php

declare(strict_types=1);

namespace WPSafeMode\Storage\Sqlite;

use WPSafeMode\Storage\Contracts\ConfigStore;

final class SqliteConfigStore implements ConfigStore
{
    public function __construct(private SqliteConnection $connection)
    {
    }

    public function get(string $key, ?string $default = null): ?string
    {
        $stmt = $this->connection->pdo()->prepare('SELECT value FROM config WHERE key = :k');
        $stmt->execute([':k' => $key]);
        $row = $stmt->fetch();

        return $row === false ? $default : (string) $row['value'];
    }

    public function set(string $key, string $value): void
    {
        $stmt = $this->connection->pdo()->prepare(
            'INSERT INTO config (key, value) VALUES (:k, :v)
             ON CONFLICT (key) DO UPDATE SET value = excluded.value'
        );
        $stmt->execute([':k' => $key, ':v' => $value]);
    }

    public function has(string $key): bool
    {
        $stmt = $this->connection->pdo()->prepare('SELECT 1 FROM config WHERE key = :k');
        $stmt->execute([':k' => $key]);

        return $stmt->fetch() !== false;
    }

    public function delete(string $key): void
    {
        $stmt = $this->connection->pdo()->prepare('DELETE FROM config WHERE key = :k');
        $stmt->execute([':k' => $key]);
    }

    public function all(): array
    {
        $rows = $this->connection->pdo()->query('SELECT key, value FROM config ORDER BY key')->fetchAll();

        $out = [];
        foreach ($rows as $row) {
            $out[(string) $row['key']] = (string) $row['value'];
        }

        return $out;
    }
}
