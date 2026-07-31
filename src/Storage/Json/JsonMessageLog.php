<?php

declare(strict_types=1);

namespace WPSafeMode\Storage\Json;

use Generator;
use WPSafeMode\Storage\Contracts\MessageLog;
use WPSafeMode\Storage\Contracts\StorageException;
use WPSafeMode\Storage\Message;

/**
 * Append-only JSONL, one message per line.
 *
 * Appending is O(1) and a crashed request truncates at most the final line.
 * Reads stream line by line and never load the whole file: a long session's
 * messages.jsonl is the largest object in the data directory (spec 7.2).
 */
final class JsonMessageLog implements MessageLog
{
    public function __construct(private string $baseDir)
    {
    }

    public function append(string $sessionId, string $role, array $content, ?string $apiMessageId = null): int
    {
        $path = $this->pathFor($sessionId);
        $dir = dirname($path);

        if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new StorageException('Cannot create session directory: ' . $dir);
        }

        $seq = $this->count($sessionId) + 1;

        $line = json_encode([
            'session_id'     => $sessionId,
            'seq'            => $seq,
            'role'           => $role,
            'content'        => $content,
            'api_message_id' => $apiMessageId,
            'created_at'     => time(),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $handle = fopen($path, 'ab');

        if ($handle === false) {
            throw new StorageException('Cannot open message log: ' . $path);
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new StorageException('Cannot lock message log: ' . $path);
            }

            fwrite($handle, $line . "\n");
            fflush($handle);
            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }

        return $seq;
    }

    public function all(string $sessionId): Generator
    {
        $path = $this->pathFor($sessionId);

        if (!is_file($path)) {
            return;
        }

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new StorageException('Cannot read message log: ' . $path);
        }

        try {
            while (($line = fgets($handle)) !== false) {
                $line = trim($line);

                if ($line === '') {
                    continue;
                }

                $row = json_decode($line, true, 512, JSON_THROW_ON_ERROR);

                yield new Message(
                    sessionId:    (string) $row['session_id'],
                    seq:          (int) $row['seq'],
                    role:         (string) $row['role'],
                    content:      $row['content'],
                    apiMessageId: $row['api_message_id'] === null ? null : (string) $row['api_message_id'],
                    createdAt:    (int) $row['created_at'],
                );
            }
        } finally {
            fclose($handle);
        }
    }

    public function count(string $sessionId): int
    {
        $path = $this->pathFor($sessionId);

        if (!is_file($path)) {
            return 0;
        }

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return 0;
        }

        $count = 0;

        try {
            while (($line = fgets($handle)) !== false) {
                if (trim($line) !== '') {
                    $count++;
                }
            }
        } finally {
            fclose($handle);
        }

        return $count;
    }

    private function pathFor(string $sessionId): string
    {
        if (preg_match('/^[A-Za-z0-9_-]{1,64}$/', $sessionId) !== 1) {
            throw new StorageException('Illegal session id: ' . $sessionId);
        }

        return $this->baseDir . '/' . $sessionId . '/messages.jsonl';
    }
}
