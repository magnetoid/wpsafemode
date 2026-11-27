<?php

declare(strict_types=1);

namespace WPSafeMode\Tests\Storage;

use WPSafeMode\Security\GuardedJson;
use WPSafeMode\Security\GuardedJsonLines;
use WPSafeMode\Storage\Contracts\AuditLog;
use WPSafeMode\Storage\Contracts\AuthSessionStore;
use WPSafeMode\Storage\Contracts\ConfigStore;
use WPSafeMode\Storage\Contracts\MessageLog;
use WPSafeMode\Storage\Contracts\RateLimiter;
use WPSafeMode\Storage\Contracts\SessionRepo;
use WPSafeMode\Storage\Contracts\StorageException;
use WPSafeMode\Storage\Contracts\UserStore;
use WPSafeMode\Storage\Json\JsonAuditLog;
use WPSafeMode\Storage\Json\JsonAuthSessionStore;
use WPSafeMode\Storage\Json\JsonConfigStore;
use WPSafeMode\Storage\Json\JsonMessageLog;
use WPSafeMode\Storage\Json\JsonRateLimiter;
use WPSafeMode\Storage\Json\JsonSessionRepo;
use WPSafeMode\Storage\Json\JsonUserStore;

final class JsonStorageTest extends StorageConformanceTest
{
    protected function makeConfigStore(string $dataDir): ConfigStore
    {
        return new JsonConfigStore($dataDir . '/config.php');
    }

    protected function makeSessionRepo(string $dataDir): SessionRepo
    {
        return new JsonSessionRepo($dataDir . '/sessions');
    }

    protected function makeMessageLog(string $dataDir): MessageLog
    {
        return new JsonMessageLog($dataDir . '/sessions');
    }

    protected function makeAuditLog(string $dataDir): AuditLog
    {
        return new JsonAuditLog($dataDir . '/audit_log.php');
    }

    protected function writeMalformedAuditRow(string $dataDir): void
    {
        GuardedJsonLines::append($dataDir . '/audit_log.php', [
            'at' => 3000, 'actor' => '', 'event' => 'login', 'subject' => null, 'meta' => [], 'ip' => null,
        ]);
    }

    protected function makeUserStore(string $dataDir): UserStore
    {
        return new JsonUserStore($dataDir . '/users.php');
    }

    protected function makeAuthSessionStore(string $dataDir): AuthSessionStore
    {
        return new JsonAuthSessionStore($dataDir . '/auth_sessions.php');
    }

    protected function writeUserField(string $dataDir, int $id, string $field, string $value): void
    {
        $path = $dataDir . '/users.php';
        $file = GuardedJson::read($path);
        $file['users'][(string) $id][$field] = $value;
        GuardedJson::write($path, $file);
    }

    protected function writeAuthSessionField(string $dataDir, string $tokenHash, string $field, string $value): void
    {
        $path = $dataDir . '/auth_sessions.php';
        $rows = GuardedJson::read($path);
        $rows[$tokenHash][$field] = $value;
        GuardedJson::write($path, $rows);
    }

    /** Raw GuardedJson write: a role the application would never write. */
    protected function writeMalformedUserRecord(string $dataDir): void
    {
        $path = $dataDir . '/users.php';
        $file = GuardedJson::read($path);
        $file['users']['1']['role'] = 'root';
        GuardedJson::write($path, $file);
    }

    protected function makeRateLimiter(string $dataDir): RateLimiter
    {
        return new JsonRateLimiter($dataDir . '/rate_limit.php');
    }

    protected function assertNoSessionStorageLeftBehind(string $dataDir, string $id): void
    {
        self::assertDirectoryDoesNotExist($dataDir . '/sessions/' . $id);
    }

    /**
     * Bypasses JsonRateLimiter entirely: hit() can never write a
     * non-numeric locked_until, so the only way to get one into the file is
     * a raw GuardedJson::write(), exactly as a hand-edited file would.
     */
    protected function writeMalformedRateLimitEntry(string $dataDir, string $bucket): void
    {
        $path = $dataDir . '/rate_limit.php';
        $all = GuardedJson::read($path);
        $all[$bucket] = ['window_start' => 1000, 'attempts' => 2, 'locked_until' => 'not-a-number'];
        GuardedJson::write($path, $all);
    }

    /**
     * Review round 1, CRITICAL 1: GuardedJson::write()'s LOCK_EX is taken on
     * a freshly-named temp file, never on the shared rate_limit.php itself,
     * so two concurrent hit() calls had no exclusion at all across their
     * read-modify-write — both could read the same stale count and the
     * later rename() would win outright. Proven empirically before the fix
     * (ten concurrent processes against the old code consistently returned
     * "OK" from every worker while the persisted count reflected only 2-5 of
     * the 10 increments - silent, total data loss, no exceptions at all) and
     * confirmed absent after wrapping hit()/clear() in
     * GuardedJson::withExclusiveLock() (five repeats, zero errors, zero
     * undercounts every time).
     *
     * This exercises the real hit() method under genuine OS-level
     * concurrency (see hitConcurrently()'s docblock for why a single PHP
     * process cannot simulate this interleaving on its own).
     */
    public function testConcurrentHitsDoNotLoseAnUpdateUnderRealConcurrency(): void
    {
        $limiter = $this->makeRateLimiter($this->dataDir);
        $limiter->hit('race', 1000); // primes attempts=1, and creates the file up front

        $limiterExpr = 'new \WPSafeMode\Storage\Json\JsonRateLimiter($dataDir . \'/rate_limit.php\')';

        $outputs = $this->hitConcurrently($limiterExpr, 'race', 1000, 10);

        foreach ($outputs as $output) {
            self::assertStringStartsWith(
                'OK:',
                $output,
                'every concurrent hit() must succeed: ' . $output
            );
        }

        self::assertSame(
            11,
            $limiter->peek('race', 1000)->attempts,
            'ten real, concurrent hit() calls plus the priming one must all be counted - none silently lost to a stale write'
        );
    }

    /**
     * IMPORTANT 1: SQLite has no equivalent state - a row either exists with
     * its declared columns or it does not; there is no "the row is a scalar
     * instead of a row" for a typed SQL table. Only the JSON backend's
     * per-bucket entry can be something other than an array at all, so this
     * is JSON-only, not part of the shared conformance suite.
     */
    public function testNonArrayBucketEntryFailsClosedNotFresh(): void
    {
        $path = $this->dataDir . '/rate_limit.php';
        GuardedJson::write($path, ['ip:9.9.9.9' => 'not-an-array']);

        $state = $this->makeRateLimiter($this->dataDir)->peek('ip:9.9.9.9', 1000);

        self::assertTrue(
            $state->isLocked(1000),
            'a bucket entry that is not even an array must fail closed, not read as "0 attempts, unlocked" - the most permissive reading possible for a security counter'
        );
    }

    /**
     * Review round 2, IMPORTANT E: JSON-only, since it is JsonRateLimiter's
     * read-whole-file/write-whole-file/single-lock shape that turns
     * unbounded bucket growth into a DoS amplifier on the login path;
     * SQLite's per-row storage does not pay a whole-file cost for every
     * other bucket that has ever existed, so it was not asked for there.
     */
    public function testExpiredBucketsAreEvictedOnASubsequentHit(): void
    {
        $limiter = $this->makeRateLimiter($this->dataDir);
        $path = $this->dataDir . '/rate_limit.php';

        $limiter->hit('ip:1.2.3.4', 1000); // this bucket is left to fully expire

        $later = 1000 + RateLimiter::WINDOW_SECONDS + 1;
        $limiter->hit('ip:5.6.7.8', $later); // an unrelated, later hit should sweep the first

        $stored = GuardedJson::read($path);

        self::assertArrayNotHasKey(
            'ip:1.2.3.4',
            $stored,
            'a bucket whose window and lock have both elapsed must be evicted, not left to grow the file forever'
        );
        self::assertArrayHasKey('ip:5.6.7.8', $stored);
    }

    /**
     * A still-locked bucket must survive the sweep even once its WINDOW has
     * elapsed: pruning on window-elapsed alone, without also checking the
     * lock, would let a hit() during someone else's active lockout quietly
     * erase the evidence of it - the opposite of fail closed.
     *
     * Hand-crafted row, not five real hit() calls: with the real
     * WINDOW_SECONDS === LOCKOUT_SECONDS, a lock produced by hit() itself
     * always expires at the exact same instant its window does (never
     * later), so there is no $now at which a real hit()-produced row is
     * "window elapsed but still locked" — the same structural point noted
     * against afterExpiry()'s third branch in review round 1. The sweep is
     * a generic, defensive check that must get this right regardless, so it
     * is tested directly against a row shaped the way it would be on a
     * config where the lockout outlives the window, without needing to
     * change the shipped constants to do it.
     */
    public function testALockedBucketSurvivesTheSweepEvenAfterItsWindowElapses(): void
    {
        $limiter = $this->makeRateLimiter($this->dataDir);
        $path = $this->dataDir . '/rate_limit.php';

        GuardedJson::write($path, [
            'ip:1.2.3.4' => ['window_start' => 1000, 'attempts' => 5, 'locked_until' => 5000],
        ]);

        // The window (1000..1900) has elapsed by 2000; the lock (until 5000) has not.
        $limiter->hit('ip:5.6.7.8', 2000);

        $stored = GuardedJson::read($path);

        self::assertArrayHasKey(
            'ip:1.2.3.4',
            $stored,
            'a bucket that is still locked must not be evicted merely because its window has elapsed'
        );
    }

    /**
     * JSON-backend-specific: GuardedJson::read() returns [] both for a file
     * that was never written and for one whose content parses but is not a
     * users file. isEmpty() gates the installer, so a corrupt-but-present
     * users.php must never read as "no users yet" - anyone could then create
     * the owner. A missing file is the only empty store.
     */
    public function testACorruptUsersFileIsNotTreatedAsEmpty(): void
    {
        GuardedJson::write($this->dataDir . '/users.php', ['unexpected' => 'shape']);

        $store = $this->makeUserStore($this->dataDir);

        try {
            $store->isEmpty();
            self::fail('isEmpty() must throw on a corrupt users file');
        } catch (StorageException $e) {
            // expected
        }

        $this->expectException(StorageException::class);
        $store->all();
    }

    /**
     * A row with the hash present but another field missing would still
     * hydrate through Credential::fromRow(), which casts leniently, into a
     * user with a real hash and silently wrong metadata. Every field must be
     * required.
     */
    public function testAUserRecordMissingAnyFieldStillThrows(): void
    {
        $full = [
            'id' => 1, 'login' => 'marko', 'role' => 'owner',
            'password_hash' => '$argon2id$hash', 'hash_algo' => 'argon2id',
            'created_at' => 1000, 'password_changed_at' => 2000, 'disabled_at' => null,
        ];

        foreach (array_keys($full) as $missing) {
            $row = $full;
            unset($row[$missing]);
            GuardedJson::write($this->dataDir . '/users.php', ['next_id' => 2, 'users' => ['1' => $row]]);

            try {
                $this->makeUserStore($this->dataDir)->find(1);
                self::fail('A record without ' . $missing . ' must throw');
            } catch (StorageException $e) {
                self::assertInstanceOf(StorageException::class, $e);
            }
        }
    }

    public function testARecordWhoseKeyDisagreesWithItsIdThrows(): void
    {
        GuardedJson::write($this->dataDir . '/users.php', ['next_id' => 3, 'users' => ['2' => [
            'id' => 1, 'login' => 'marko', 'role' => 'owner',
            'password_hash' => '$argon2id$hash', 'hash_algo' => 'argon2id',
            'created_at' => 1000, 'password_changed_at' => 2000, 'disabled_at' => null,
        ]]]);

        $this->expectException(StorageException::class);
        $this->makeUserStore($this->dataDir)->all();
    }

    public function testAWrongTypeInARecordThrows(): void
    {
        GuardedJson::write($this->dataDir . '/users.php', ['next_id' => 2, 'users' => ['1' => [
            'id' => 1, 'login' => 'marko', 'role' => 'owner',
            'password_hash' => '$argon2id$hash', 'hash_algo' => 'argon2id',
            'created_at' => '1000', 'password_changed_at' => 2000, 'disabled_at' => null,
        ]]]);

        $this->expectException(StorageException::class);
        $this->makeUserStore($this->dataDir)->find(1);
    }
}
