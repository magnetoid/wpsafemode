<?php

declare(strict_types=1);

namespace WPSafeMode\Storage\Sqlite;

use WPSafeMode\Storage\Contracts\AuthStore;
use WPSafeMode\Storage\Credential;

final class SqliteAuthStore implements AuthStore
{
    public function __construct(private SqliteConnection $connection)
    {
    }

    public function isConfigured(): bool
    {
        return $this->current() !== null;
    }

    public function store(Credential $credential): void
    {
        $stmt = $this->connection->pdo()->prepare(
            'INSERT INTO auth (id, password_hash, hash_algo, created_at, password_changed_at)
             VALUES (1, :hash, :algo, :created, :changed)
             ON CONFLICT (id) DO UPDATE SET
                password_hash = excluded.password_hash,
                hash_algo = excluded.hash_algo,
                password_changed_at = excluded.password_changed_at'
        );

        $stmt->execute([
            ':hash'    => $credential->hash,
            ':algo'    => $credential->algo,
            ':created' => $credential->createdAt,
            ':changed' => $credential->changedAt,
        ]);
    }

    public function current(): ?Credential
    {
        $row = $this->connection->pdo()->query('SELECT * FROM auth WHERE id = 1')->fetch();

        return $row === false ? null : Credential::fromRow($row);
    }
}
