<?php

declare(strict_types=1);

namespace WPSafeMode\Storage\Sqlite;

use WPSafeMode\Storage\Contracts\RateLimiter;
use WPSafeMode\Storage\RateLimitState;

final class SqliteRateLimiter implements RateLimiter
{
    public function __construct(private SqliteConnection $connection)
    {
    }

    public function hit(string $bucket, int $now): RateLimitState
    {
        $pdo = $this->connection->pdo();
        $pdo->beginTransaction();

        try {
            $state = $this->read($bucket, $now);

            $attempts = $state->attempts + 1;
            $windowStart = $state->attempts === 0 ? $now : $state->windowStart;
            $lockedUntil = $state->lockedUntil;

            if ($attempts >= self::MAX_ATTEMPTS && !$state->isLocked($now)) {
                $lockedUntil = $now + self::LOCKOUT_SECONDS;
            }

            $stmt = $pdo->prepare(
                'INSERT INTO rate_limit (bucket, window_start, attempts, locked_until)
                 VALUES (:b, :ws, :a, :lu)
                 ON CONFLICT (bucket) DO UPDATE SET
                    window_start = excluded.window_start,
                    attempts = excluded.attempts,
                    locked_until = excluded.locked_until'
            );
            $stmt->execute([':b' => $bucket, ':ws' => $windowStart, ':a' => $attempts, ':lu' => $lockedUntil]);

            $pdo->commit();

            return new RateLimitState($attempts, $windowStart, $lockedUntil);
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function peek(string $bucket, int $now): RateLimitState
    {
        return $this->read($bucket, $now);
    }

    public function clear(string $bucket): void
    {
        $stmt = $this->connection->pdo()->prepare('DELETE FROM rate_limit WHERE bucket = :b');
        $stmt->execute([':b' => $bucket]);
    }

    /** Applies window expiry on read, so a stale row behaves as an empty one. */
    private function read(string $bucket, int $now): RateLimitState
    {
        $stmt = $this->connection->pdo()->prepare('SELECT * FROM rate_limit WHERE bucket = :b');
        $stmt->execute([':b' => $bucket]);
        $row = $stmt->fetch();

        if ($row === false) {
            return new RateLimitState(0, $now, null);
        }

        $state = new RateLimitState(
            (int) $row['attempts'],
            (int) $row['window_start'],
            $row['locked_until'] === null ? null : (int) $row['locked_until']
        );

        return $state->afterExpiry($now, self::WINDOW_SECONDS);
    }
}
