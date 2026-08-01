<?php

declare(strict_types=1);

namespace WPSafeMode\Tests\Storage;

use WPSafeMode\Security\GuardedJson;
use WPSafeMode\Storage\Contracts\AuthStore;
use WPSafeMode\Storage\Contracts\ConfigStore;
use WPSafeMode\Storage\Contracts\MessageLog;
use WPSafeMode\Storage\Contracts\RateLimiter;
use WPSafeMode\Storage\Contracts\SessionRepo;
use WPSafeMode\Storage\Contracts\StorageException;
use WPSafeMode\Storage\Json\JsonAuthStore;
use WPSafeMode\Storage\Json\JsonConfigStore;
use WPSafeMode\Storage\Json\JsonMessageLog;
use WPSafeMode\Storage\Json\JsonRateLimiter;
use WPSafeMode\Storage\Json\JsonSessionRepo;

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

    protected function makeAuthStore(string $dataDir): AuthStore
    {
        return new JsonAuthStore($dataDir . '/auth.php');
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
     * JSON-backend-specific, not part of the shared conformance suite: SQLite
     * has no equivalent state, since a NOT NULL column makes a row that
     * exists-but-lacks-a-password_hash impossible.
     *
     * GuardedJson::read() returns [] both for a file that was never written
     * and for one whose content parses but is not a credential (empty
     * payload, or valid JSON that isn't shaped like a row). If isConfigured()
     * or current() treated that [] the same way for both cases, a corrupted
     * — but PRESENT — auth.php would look identical to a fresh install, and
     * whatever first-run flow that unlocks (Task 8) would silently overwrite
     * it. is_file() is checked separately from the parsed result precisely so
     * "present but unreadable" and "never written" stay distinguishable.
     */
    public function testCorruptCredentialFileIsNotTreatedAsUnconfigured(): void
    {
        $path = $this->dataDir . '/auth.php';
        GuardedJson::write($path, ['unexpected' => 'shape']);

        $store = $this->makeAuthStore($this->dataDir);

        self::assertTrue(
            $store->isConfigured(),
            'A present-but-unparseable credential file must not read as a fresh install.'
        );

        $this->expectException(StorageException::class);
        $store->current();
    }

    /**
     * A row with password_hash present but one of the other three fields
     * missing still has isset($row['password_hash']) === true, so a check
     * that only looked at that one key would parse this "successfully" —
     * Credential::fromRow() would read the missing key as null and hand
     * back a Credential with a real hash and a bogus (0 or '') timestamp,
     * silently, instead of throwing. current() must require every field the
     * row is supposed to carry, not just the one isConfigured() cares about.
     */
    public function testCorruptCredentialFileMissingAnyFieldStillThrows(): void
    {
        $path = $this->dataDir . '/auth.php';
        GuardedJson::write($path, [
            'password_hash' => '$argon2id$hash',
            'hash_algo'     => 'argon2id',
            // created_at and password_changed_at deliberately omitted.
        ]);

        $store = $this->makeAuthStore($this->dataDir);

        self::assertTrue($store->isConfigured());

        $this->expectException(StorageException::class);
        $store->current();
    }
}
