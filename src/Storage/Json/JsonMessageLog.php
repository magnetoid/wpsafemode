<?php

declare(strict_types=1);

namespace WPSafeMode\Storage\Json;

if (!defined('WPSM_ENTRY')) {
    http_response_code(404);
    exit;
}

use Generator;
use WPSafeMode\Security\GuardedJsonLines;
use WPSafeMode\Storage\Contracts\MessageLog;
use WPSafeMode\Storage\Contracts\StorageException;
use WPSafeMode\Storage\Message;

/**
 * Append-only JSONL, one message per line.
 *
 * Appending is O(1) and a crashed request truncates at most the final line.
 * Reads stream line by line and never load the whole file: a long session's
 * messages.php is the largest object in the data directory (spec 7.2).
 */
final class JsonMessageLog implements MessageLog
{
    public function __construct(private string $baseDir)
    {
    }

    public function append(string $sessionId, string $role, array $content, ?string $apiMessageId = null): int
    {
        $seq = $this->count($sessionId) + 1;

        GuardedJsonLines::append($this->pathFor($sessionId), [
            'session_id'     => $sessionId,
            'seq'            => $seq,
            'role'           => $role,
            'content'        => $content,
            'api_message_id' => $apiMessageId,
            'created_at'     => time(),
        ]);

        return $seq;
    }

    public function all(string $sessionId): Generator
    {
        foreach (GuardedJsonLines::stream($this->pathFor($sessionId)) as $row) {
            yield new Message(
                sessionId:    (string) $row['session_id'],
                seq:          (int) $row['seq'],
                role:         (string) $row['role'],
                content:      $row['content'],
                apiMessageId: $row['api_message_id'] === null ? null : (string) $row['api_message_id'],
                createdAt:    (int) $row['created_at'],
            );
        }
    }

    public function count(string $sessionId): int
    {
        return GuardedJsonLines::count($this->pathFor($sessionId));
    }

    private function pathFor(string $sessionId): string
    {
        if (preg_match('/^[A-Za-z0-9_-]{1,64}\z/', $sessionId) !== 1) {
            throw new StorageException('Illegal session id: ' . $sessionId);
        }

        return $this->baseDir . '/' . $sessionId . '/messages.php';
    }
}
