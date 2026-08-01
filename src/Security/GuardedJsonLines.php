<?php

declare(strict_types=1);

namespace WPSafeMode\Security;

use Generator;
use JsonException;
use WPSafeMode\Storage\Contracts\StorageException;

/**
 * An append-only JSONL log behind the same die() guard.
 *
 * The guard occupies the first line and every later line is one JSON object.
 * Appending is O(1) under LOCK_EX and a crashed request truncates at most the
 * final line. Readers stream: a long session's message log is the largest
 * object in the data directory and must never be loaded whole (spec 15.2).
 */
final class GuardedJsonLines
{
    /** @param array<string, mixed> $row */
    public static function append(string $path, array $row): void
    {
        $dir = dirname($path);

        if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new StorageException('Cannot create data directory: ' . $dir);
        }

        try {
            $line = json_encode(
                $row,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );
        } catch (JsonException $e) {
            throw new StorageException('Cannot encode log line: ' . basename($path), 0, $e);
        }

        $isNew = !is_file($path);
        $handle = fopen($path, 'ab');

        if ($handle === false) {
            throw new StorageException('Cannot open log: ' . basename($path));
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new StorageException('Cannot lock log: ' . basename($path));
            }

            // Re-check under the lock: two processes can both see the file as
            // absent, and two guards would corrupt the log.
            if ($isNew && ftell($handle) === 0) {
                fwrite($handle, DataDir::GUARD);
            }

            fwrite($handle, $line . "\n");
            fflush($handle);
            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }

        if ($isNew) {
            @chmod($path, 0600);
        }
    }

    /** @return Generator<int, array<string, mixed>> */
    public static function stream(string $path): Generator
    {
        if (!is_file($path)) {
            return;
        }

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new StorageException('Cannot read log: ' . basename($path));
        }

        try {
            while (($line = fgets($handle)) !== false) {
                $line = trim($line);

                if ($line === '' || strncmp($line, '<?php', 5) === 0) {
                    continue;
                }

                try {
                    $row = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                } catch (JsonException $e) {
                    throw new StorageException('Corrupt log line in ' . basename($path), 0, $e);
                }

                if (is_array($row)) {
                    yield $row;
                }
            }
        } finally {
            fclose($handle);
        }
    }

    public static function count(string $path): int
    {
        $count = 0;

        foreach (self::stream($path) as $ignored) {
            $count++;
        }

        return $count;
    }
}
