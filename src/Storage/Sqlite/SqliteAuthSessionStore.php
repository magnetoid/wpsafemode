<?php

declare(strict_types=1);

namespace WPSafeMode\Storage\Sqlite;

use WPSafeMode\Storage\AuthSessionRow;
use WPSafeMode\Storage\Contracts\AuthSessionStore;

final class SqliteAuthSessionStore implements AuthSessionStore
{
    public function __construct(private SqliteConnection $connection)
    {
    }

    public function put(string $tokenHash, array $row): void
    {
        // The same check the JSON backend makes, so neither stores what the
        // other would refuse to read back.
        $row = AuthSessionRow::validate($row);

        $stmt = $this->connection->pdo()->prepare(
            'INSERT INTO auth_sessions (token_hash, user_id, created_at, last_seen_at, expires_at, ip, ua_hash)
             VALUES (:t, :user, :created, :seen, :expires, :ip, :ua)
             ON CONFLICT (token_hash) DO UPDATE SET
                user_id = excluded.user_id, created_at = excluded.created_at,
                last_seen_at = excluded.last_seen_at, expires_at = excluded.expires_at,
                ip = excluded.ip, ua_hash = excluded.ua_hash'
        );
        $stmt->execute([
            ':t'       => $tokenHash,
            ':user'    => $row['user_id'],
            ':created' => $row['created_at'],
            ':seen'    => $row['last_seen_at'],
            ':expires' => $row['expires_at'],
            ':ip'      => $row['ip'],
            ':ua'      => $row['ua_hash'],
        ]);
    }

    public function get(string $tokenHash): ?array
    {
        $stmt = $this->connection->pdo()->prepare('SELECT * FROM auth_sessions WHERE token_hash = :t');
        $stmt->execute([':t' => $tokenHash]);
        $row = $stmt->fetch();

        return $row === false ? null : AuthSessionRow::validate($row);
    }

    public function touch(string $tokenHash, int $now): void
    {
        $stmt = $this->connection->pdo()->prepare(
            'UPDATE auth_sessions SET last_seen_at = :now WHERE token_hash = :t'
        );
        $stmt->execute([':now' => $now, ':t' => $tokenHash]);
    }

    public function delete(string $tokenHash): void
    {
        $stmt = $this->connection->pdo()->prepare('DELETE FROM auth_sessions WHERE token_hash = :t');
        $stmt->execute([':t' => $tokenHash]);
    }

    public function purgeExpired(int $now): void
    {
        $stmt = $this->connection->pdo()->prepare('DELETE FROM auth_sessions WHERE expires_at <= :now');
        $stmt->execute([':now' => $now]);
    }
}
