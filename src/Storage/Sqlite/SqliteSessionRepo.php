<?php

declare(strict_types=1);

namespace WPSafeMode\Storage\Sqlite;

if (!defined('WPSM_ENTRY')) {
    http_response_code(404);
    exit;
}

use PDO;
use PDOException;
use WPSafeMode\Storage\Contracts\SessionRepo;
use WPSafeMode\Storage\Contracts\StorageException;
use WPSafeMode\Storage\Session;

final class SqliteSessionRepo implements SessionRepo
{
    private const COLUMNS = [
        'id', 'title', 'blade', 'status', 'model', 'effort',
        'created_at', 'updated_at', 'lock_token', 'lock_expires_at',
        'retry_count', 'retry_after_at', 'in_tokens', 'out_tokens',
        'cache_read_tokens', 'cache_write_tokens', 'cost_micros',
    ];

    public function __construct(private SqliteConnection $connection)
    {
    }

    public function create(Session $session): void
    {
        $placeholders = array_map(static fn (string $c): string => ':' . $c, self::COLUMNS);

        $sql = 'INSERT INTO sessions (' . implode(', ', self::COLUMNS) . ')'
             . ' VALUES (' . implode(', ', $placeholders) . ')';

        try {
            $this->connection->pdo()->prepare($sql)->execute($this->bindings($session));
        } catch (PDOException $e) {
            throw new StorageException('Session already exists: ' . $session->id, 0, $e);
        }
    }

    public function find(string $id): ?Session
    {
        $stmt = $this->connection->pdo()->prepare('SELECT * FROM sessions WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : Session::fromRow($row);
    }

    public function update(Session $session): void
    {
        $assignments = [];
        foreach (self::COLUMNS as $column) {
            if ($column !== 'id') {
                $assignments[] = $column . ' = :' . $column;
            }
        }

        $sql = 'UPDATE sessions SET ' . implode(', ', $assignments) . ' WHERE id = :id';

        $stmt = $this->connection->pdo()->prepare($sql);
        $stmt->execute($this->bindings($session));

        if ($stmt->rowCount() === 0 && $this->find($session->id) === null) {
            throw new StorageException('Unknown session: ' . $session->id);
        }
    }

    public function list(int $limit = 50): array
    {
        $stmt = $this->connection->pdo()->prepare(
            'SELECT * FROM sessions ORDER BY created_at DESC, id DESC LIMIT :limit'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return array_map(
            static fn (array $row): Session => Session::fromRow($row),
            $stmt->fetchAll()
        );
    }

    public function delete(string $id): void
    {
        $stmt = $this->connection->pdo()->prepare('DELETE FROM sessions WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    /** @return array<string, int|string|null> */
    private function bindings(Session $session): array
    {
        $bindings = [];
        foreach ($session->toRow() as $column => $value) {
            $bindings[':' . $column] = $value;
        }

        return $bindings;
    }
}
