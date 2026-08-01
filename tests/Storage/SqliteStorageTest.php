<?php

declare(strict_types=1);

namespace WPSafeMode\Tests\Storage;

use WPSafeMode\Storage\Contracts\AuthStore;
use WPSafeMode\Storage\Contracts\ConfigStore;
use WPSafeMode\Storage\Contracts\MessageLog;
use WPSafeMode\Storage\Contracts\RateLimiter;
use WPSafeMode\Storage\Contracts\SessionRepo;
use WPSafeMode\Storage\Sqlite\SqliteAuthStore;
use WPSafeMode\Storage\Sqlite\SqliteConfigStore;
use WPSafeMode\Storage\Sqlite\SqliteConnection;
use WPSafeMode\Storage\Sqlite\SqliteMessageLog;
use WPSafeMode\Storage\Sqlite\SqliteRateLimiter;
use WPSafeMode\Storage\Sqlite\SqliteSessionRepo;

final class SqliteStorageTest extends StorageConformanceTest
{
    protected function makeConfigStore(string $dataDir): ConfigStore
    {
        return new SqliteConfigStore(new SqliteConnection($dataDir . '/wpsm.sqlite'));
    }

    protected function makeSessionRepo(string $dataDir): SessionRepo
    {
        return new SqliteSessionRepo(new SqliteConnection($dataDir . '/wpsm.sqlite'));
    }

    protected function makeMessageLog(string $dataDir): MessageLog
    {
        return new SqliteMessageLog(new SqliteConnection($dataDir . '/wpsm.sqlite'));
    }

    protected function makeAuthStore(string $dataDir): AuthStore
    {
        return new SqliteAuthStore(new SqliteConnection($dataDir . '/wpsm.sqlite'));
    }

    protected function makeRateLimiter(string $dataDir): RateLimiter
    {
        return new SqliteRateLimiter(new SqliteConnection($dataDir . '/wpsm.sqlite'));
    }

    protected function assertNoSessionStorageLeftBehind(string $dataDir, string $id): void
    {
        // No-op: SQLite storage has no per-session directory. delete()
        // removing the row is already covered by find() returning null.
    }

    /**
     * Bypasses SqliteRateLimiter entirely: hit() can never write a
     * non-numeric locked_until, so the only way to get one into the table
     * is a raw insert directly against the schema, exactly as a hand-edited
     * database or a future migration bug could.
     */
    protected function writeMalformedRateLimitEntry(string $dataDir, string $bucket): void
    {
        $connection = new SqliteConnection($dataDir . '/wpsm.sqlite');
        $stmt = $connection->pdo()->prepare(
            'INSERT INTO rate_limit (bucket, window_start, attempts, locked_until) VALUES (:b, :ws, :a, :lu)'
        );
        $stmt->execute([':b' => $bucket, ':ws' => 1000, ':a' => 2, ':lu' => 'not-a-number']);
    }

    /**
     * Review round 1, CRITICAL 1: PDO's beginTransaction() issues a plain,
     * DEFERRED BEGIN, so two concurrent hit() calls could both pass their
     * SELECT (only a SHARED lock, held by several connections at once)
     * having read the same stale attempt count, and the later commit would
     * silently win outright. Proven empirically before the fix (ten
     * concurrent processes against the old code: some runs threw "database
     * is locked", others returned success for every worker while still
     * losing one or more increments) and confirmed absent after switching to
     * BEGIN IMMEDIATE (thirty-plus runs at 3, 10 and 20 concurrent workers,
     * zero errors, zero undercounts).
     *
     * This exercises the real hit() method under genuine OS-level
     * concurrency (see hitConcurrently()'s docblock for why a single PHP
     * process cannot simulate this interleaving on its own).
     */
    public function testConcurrentHitsDoNotLoseAnUpdateUnderRealConcurrency(): void
    {
        $limiter = $this->makeRateLimiter($this->dataDir);
        $limiter->hit('race', 1000); // primes attempts=1, and creates the schema/file up front

        $limiterExpr = 'new \WPSafeMode\Storage\Sqlite\SqliteRateLimiter('
            . 'new \WPSafeMode\Storage\Sqlite\SqliteConnection($dataDir . \'/wpsm.sqlite\'))';

        $outputs = $this->hitConcurrently($limiterExpr, 'race', 1000, 10);

        foreach ($outputs as $output) {
            self::assertStringStartsWith(
                'OK:',
                $output,
                'every concurrent hit() must succeed, not fail under lock contention: ' . $output
            );
        }

        self::assertSame(
            11,
            $limiter->peek('race', 1000)->attempts,
            'ten real, concurrent hit() calls plus the priming one must all be counted - none silently lost to a stale read'
        );
    }
}
