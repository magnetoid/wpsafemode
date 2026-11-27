<?php

declare(strict_types=1);

namespace WPSafeMode\Storage\Sqlite;

if (!defined('WPSM_ENTRY')) {
    http_response_code(404);
    exit;
}

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

        // BEGIN IMMEDIATE, not $pdo->beginTransaction() (a plain, DEFERRED
        // BEGIN): a deferred transaction takes no lock until its first
        // statement runs, and that first statement here is a SELECT, which
        // only takes a SHARED lock — one several connections hold at once
        // without conflict. Two concurrent hit() calls would both pass that
        // SELECT having read the same attempt count, both compute the same
        // "+1", and only the later of the two writes/commits would stick —
        // exactly the silent lost-update review round 1 found, for the one
        // component whose entire job is counting attempts correctly.
        // IMMEDIATE takes the write lock up front, before the read, so a
        // second concurrent call cannot get past its own BEGIN until the
        // first has committed and released it.
        //
        // Ended with exec('COMMIT')/exec('ROLLBACK') below, NOT
        // $pdo->commit()/$pdo->rollBack() (review round 2, CRITICAL A):
        // PDO's own commit()/rollBack() are gated on an internal in_txn
        // flag that ONLY $pdo->beginTransaction() sets — never a raw
        // exec('BEGIN ...') — whenever the driver itself supplies no
        // in_transaction hook to consult instead. pdo_sqlite's hook is a
        // NULL entry in the driver's method table on PHP 8.0 through 8.3
        // (confirmed directly against php-src for both branches); a real
        // pdo_sqlite_in_transaction() first appears in 8.4. So on the whole
        // 8.0–8.3 floor this project declares, beginTransaction() this
        // BEGIN IMMEDIATE did NOT call would make $pdo->commit() throw
        // "There is no active transaction" on every single hit() — and the
        // catch block's $pdo->rollBack() would throw the identical
        // exception for the identical reason, masking the original error
        // AND leaving the transaction open, holding the write lock, for the
        // rest of the request. exec('COMMIT')/exec('ROLLBACK') are plain
        // SQL sent straight through; neither one consults in_txn, so they
        // pair correctly with exec('BEGIN IMMEDIATE') on every version.
        $pdo->exec('BEGIN IMMEDIATE');

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

            $pdo->exec('COMMIT');

            return new RateLimitState($attempts, $windowStart, $lockedUntil);
        } catch (\Throwable $e) {
            try {
                $pdo->exec('ROLLBACK');
            } catch (\Throwable $ignored) {
                // A failed ROLLBACK must not replace $e: whatever the
                // SELECT/INSERT/COMMIT above actually threw is the error
                // the caller needs to see. If ROLLBACK itself cannot run,
                // the transaction ends when this connection closes at the
                // end of the request either way; there is nothing further
                // this method can do about it.
            }

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

        // fromRawRow(), not a bare (int) cast of whatever the columns hold:
        // review round 1 (IMPORTANT 2) found that blindly casting garbage
        // (e.g. a hand-edited or migration-corrupted locked_until that is
        // not actually numeric) produced an arbitrary state that read as
        // locked or unlocked depending on the garbage. A row that cannot be
        // trusted to mean what it looks like it means must fail closed.
        return RateLimitState::fromRawRow($row, $now, self::MAX_ATTEMPTS, self::LOCKOUT_SECONDS)
            ->afterExpiry($now, self::WINDOW_SECONDS);
    }
}
