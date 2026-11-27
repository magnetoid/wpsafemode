<?php

declare(strict_types=1);

namespace WPSafeMode\Storage\Sqlite;

if (!defined('WPSM_ENTRY')) {
    http_response_code(404);
    exit;
}

use Generator;
use Throwable;
use WPSafeMode\Storage\Contracts\MessageLog;
use WPSafeMode\Storage\Contracts\StorageException;
use WPSafeMode\Storage\Message;

final class SqliteMessageLog implements MessageLog
{
    public function __construct(private SqliteConnection $connection)
    {
    }

    public function append(string $sessionId, string $role, array $content, ?string $apiMessageId = null): int
    {
        $pdo = $this->connection->pdo();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare('SELECT COALESCE(MAX(seq), 0) + 1 AS next FROM messages WHERE session_id = :s');
            $stmt->execute([':s' => $sessionId]);
            $seq = (int) $stmt->fetch()['next'];

            $insert = $pdo->prepare(
                'INSERT INTO messages (session_id, seq, role, content_json, api_message_id, created_at)
                 VALUES (:s, :seq, :role, :content, :api_id, :created)'
            );
            $insert->execute([
                ':s'       => $sessionId,
                ':seq'     => $seq,
                ':role'    => $role,
                ':content' => json_encode($content, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                ':api_id'  => $apiMessageId,
                ':created' => time(),
            ]);

            $pdo->commit();

            return $seq;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw new StorageException('Cannot append message: ' . $e->getMessage(), 0, $e);
        }
    }

    public function all(string $sessionId): Generator
    {
        $stmt = $this->connection->pdo()->prepare(
            'SELECT * FROM messages WHERE session_id = :s ORDER BY seq ASC'
        );
        $stmt->execute([':s' => $sessionId]);

        while (($row = $stmt->fetch()) !== false) {
            yield new Message(
                sessionId:    (string) $row['session_id'],
                seq:          (int) $row['seq'],
                role:         (string) $row['role'],
                content:      json_decode((string) $row['content_json'], true, 512, JSON_THROW_ON_ERROR),
                apiMessageId: $row['api_message_id'] === null ? null : (string) $row['api_message_id'],
                createdAt:    (int) $row['created_at'],
            );
        }
    }

    public function count(string $sessionId): int
    {
        $stmt = $this->connection->pdo()->prepare('SELECT COUNT(*) AS n FROM messages WHERE session_id = :s');
        $stmt->execute([':s' => $sessionId]);

        return (int) $stmt->fetch()['n'];
    }
}
