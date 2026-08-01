<?php

declare(strict_types=1);

namespace WPSafeMode\Tests\Storage;

use PHPUnit\Framework\TestCase;
use WPSafeMode\Storage\Contracts\AuthStore;
use WPSafeMode\Storage\Contracts\ConfigStore;
use WPSafeMode\Storage\Contracts\MessageLog;
use WPSafeMode\Storage\Contracts\RateLimiter;
use WPSafeMode\Storage\Contracts\SessionRepo;
use WPSafeMode\Storage\Contracts\StorageException;
use WPSafeMode\Storage\Credential;
use WPSafeMode\Storage\Message;
use WPSafeMode\Storage\Session;

/**
 * One suite, two backends. Every assertion here must hold identically for
 * SQLite and for the JSON fallback — that equivalence is the entire point
 * of the fallback (spec 7).
 */
abstract class StorageConformanceTest extends TestCase
{
    protected string $dataDir;

    abstract protected function makeConfigStore(string $dataDir): ConfigStore;

    abstract protected function makeSessionRepo(string $dataDir): SessionRepo;

    abstract protected function makeMessageLog(string $dataDir): MessageLog;

    abstract protected function makeAuthStore(string $dataDir): AuthStore;

    abstract protected function makeRateLimiter(string $dataDir): RateLimiter;

    /**
     * Backend-specific: the JSON backend keeps one directory per session and
     * must leave none behind after delete() removes an otherwise-untouched
     * session; SQLite has no per-session directory to check, so its
     * implementation is a documented no-op.
     */
    abstract protected function assertNoSessionStorageLeftBehind(string $dataDir, string $id): void;

    /**
     * Backend-specific, added in review round 1 (IMPORTANT 2): writes a
     * value for $bucket that cannot have been produced by this backend's own
     * hit() — directly through the backend's own storage (raw SQL for
     * SQLite, a raw GuardedJson::write() for JSON) rather than through
     * RateLimiter at all, since going through the interface could never
     * produce a malformed row in the first place. The two backends' storage
     * shapes are different enough (a typed SQL row vs. a JSON sub-array)
     * that there is no way to express "a malformed value" once, generically,
     * the way the other hooks do — the conformance test that uses this
     * (testMalformedStoredValueFailsClosedIdenticallyInBothBackends) is what
     * actually pins the two backends to one shared, fail-closed
     * interpretation; this hook only gets the bad data onto disk.
     */
    abstract protected function writeMalformedRateLimitEntry(string $dataDir, string $bucket): void;

    protected function setUp(): void
    {
        $this->dataDir = sys_get_temp_dir() . '/wpsm-store-' . bin2hex(random_bytes(6));
        mkdir($this->dataDir, 0700, true);
    }

    protected function tearDown(): void
    {
        $this->removeRecursively($this->dataDir);
    }

    private function removeRecursively(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $full = $path . '/' . $entry;
            is_dir($full) ? $this->removeRecursively($full) : unlink($full);
        }

        rmdir($path);
    }

    /**
     * Spawns $workers independent PHP processes, each performing exactly one
     * hit() against $bucket, and returns each process's raw stdout
     * ("OK:<attempts>" or "ERR:<message>").
     *
     * Added in review round 1 (CRITICAL 1), to prove the concurrent
     * lost-update fix against the real hit() method rather than against a
     * hand-rolled stand-in for it. That requires genuine OS-level
     * concurrency, not a simulated interleaving: hit() is one atomic PHP
     * method call with no seam that would let a single test process pause
     * it mid-way to interleave it with itself, so the only way to make two
     * "logical" callers' read and write steps actually overlap is to run
     * hit() in two real, independent processes and let the OS schedule
     * them. This is a concrete helper, not a new abstract factory hook: the
     * only backend-specific part is the constructor expression each test
     * passes in as a string, evaluated inside the worker script.
     *
     * @return list<string>
     */
    protected function hitConcurrently(string $limiterExpr, string $bucket, int $now, int $workers): array
    {
        $bootstrap = dirname(__DIR__, 2) . '/src/autoload.php';
        $script = $this->dataDir . '/_concurrent_hit_worker.php';

        file_put_contents(
            $script,
            '<?php' . "\n"
            . 'require ' . var_export($bootstrap, true) . ';' . "\n"
            . '$dataDir = ' . var_export($this->dataDir, true) . ';' . "\n"
            . '$limiter = ' . $limiterExpr . ';' . "\n"
            . 'try {' . "\n"
            . '    $state = $limiter->hit(' . var_export($bucket, true) . ', ' . $now . ');' . "\n"
            . '    fwrite(STDOUT, \'OK:\' . $state->attempts);' . "\n"
            . '} catch (\Throwable $e) {' . "\n"
            . '    fwrite(STDOUT, \'ERR:\' . $e->getMessage());' . "\n"
            . '}' . "\n"
        );

        $handles = [];
        for ($i = 0; $i < $workers; $i++) {
            $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
            $proc = proc_open([PHP_BINARY, $script], $descriptors, $pipes);
            self::assertTrue(is_resource($proc), 'proc_open() must succeed for this test to run');
            $handles[] = ['proc' => $proc, 'pipes' => $pipes];
        }

        $outputs = [];

        foreach ($handles as $h) {
            $stdout = (string) stream_get_contents($h['pipes'][1]);
            // Drained, but not asserted empty (review round 2, "also fix" b):
            // workers run under whatever default php.ini this machine has,
            // so a benign startup notice on some environment would fail this
            // helper on output that has nothing to do with the code under
            // test. The worker's own OK:/ERR: protocol on stdout is the
            // signal that matters; stderr is folded in only as context for
            // whichever assertion the caller makes against $stdout.
            $stderr = (string) stream_get_contents($h['pipes'][2]);
            fclose($h['pipes'][1]);
            fclose($h['pipes'][2]);
            proc_close($h['proc']);

            $outputs[] = $stderr === '' ? $stdout : $stdout . ' [stderr: ' . $stderr . ']';
        }

        return $outputs;
    }

    public function testGetReturnsDefaultWhenKeyIsAbsent(): void
    {
        self::assertNull($this->makeConfigStore($this->dataDir)->get('nope'));
        self::assertSame('fb', $this->makeConfigStore($this->dataDir)->get('nope', 'fb'));
    }

    public function testSetThenGetRoundTrips(): void
    {
        $store = $this->makeConfigStore($this->dataDir);
        $store->set('model', 'claude-opus-4-8');

        self::assertSame('claude-opus-4-8', $store->get('model'));
    }

    public function testSetOverwritesExistingKey(): void
    {
        $store = $this->makeConfigStore($this->dataDir);
        $store->set('effort', 'high');
        $store->set('effort', 'xhigh');

        self::assertSame('xhigh', $store->get('effort'));
        self::assertCount(1, $store->all());
    }

    public function testValuesPersistAcrossInstances(): void
    {
        $this->makeConfigStore($this->dataDir)->set('model', 'claude-sonnet-5');

        self::assertSame('claude-sonnet-5', $this->makeConfigStore($this->dataDir)->get('model'));
    }

    public function testHasReflectsPresence(): void
    {
        $store = $this->makeConfigStore($this->dataDir);
        self::assertFalse($store->has('k'));

        $store->set('k', 'v');
        self::assertTrue($store->has('k'));
    }

    public function testDeleteRemovesKeyAndIsIdempotent(): void
    {
        $store = $this->makeConfigStore($this->dataDir);
        $store->set('k', 'v');
        $store->delete('k');
        $store->delete('k');

        self::assertFalse($store->has('k'));
    }

    public function testAllReturnsEveryPair(): void
    {
        $store = $this->makeConfigStore($this->dataDir);
        $store->set('a', '1');
        $store->set('b', '2');

        self::assertSame(['a' => '1', 'b' => '2'], $store->all());
    }

    public function testEmptyStringIsStoredAndIsNotConfusedWithAbsence(): void
    {
        $store = $this->makeConfigStore($this->dataDir);
        $store->set('blank', '');

        self::assertTrue($store->has('blank'));
        self::assertSame('', $store->get('blank', 'fallback'));
    }

    public function testValuesSurviveHostileContent(): void
    {
        $store = $this->makeConfigStore($this->dataDir);
        $nasty = "line1\nline2\t\"quoted\" 'single' \\slash\\ {\"json\":true} émoji-✓ \0nul";
        $store->set('nasty', $nasty);

        self::assertSame($nasty, $this->makeConfigStore($this->dataDir)->get('nasty'));
    }

    private function sampleSession(string $id = 'sesn_1', int $createdAt = 1000): Session
    {
        return new Session(
            id:        $id,
            title:     'White screen on example.com',
            blade:     Session::BLADE_RESCUE,
            status:    Session::STATUS_IDLE,
            model:     'claude-opus-4-8',
            effort:    'high',
            createdAt: $createdAt,
            updatedAt: $createdAt,
        );
    }

    public function testFindReturnsNullForUnknownSession(): void
    {
        self::assertNull($this->makeSessionRepo($this->dataDir)->find('nope'));
    }

    public function testCreateThenFindRoundTripsEveryField(): void
    {
        $repo = $this->makeSessionRepo($this->dataDir);
        $repo->create($this->sampleSession());

        $found = $this->makeSessionRepo($this->dataDir)->find('sesn_1');

        self::assertNotNull($found);
        self::assertSame($this->sampleSession()->toRow(), $found->toRow());
    }

    public function testCreateRejectsDuplicateId(): void
    {
        $repo = $this->makeSessionRepo($this->dataDir);
        $repo->create($this->sampleSession());

        $this->expectException(StorageException::class);
        $repo->create($this->sampleSession());
    }

    public function testUpdatePersistsChangedFields(): void
    {
        $repo = $this->makeSessionRepo($this->dataDir);
        $repo->create($this->sampleSession());

        $changed = $this->sampleSession();
        $changed->status = Session::STATUS_AWAITING_LLM;
        $changed->inTokens = 4096;
        $changed->lockToken = 'tok_abc';
        $repo->update($changed);

        $found = $this->makeSessionRepo($this->dataDir)->find('sesn_1');

        self::assertNotNull($found);
        self::assertSame(Session::STATUS_AWAITING_LLM, $found->status);
        self::assertSame(4096, $found->inTokens);
        self::assertSame('tok_abc', $found->lockToken);
    }

    public function testUpdateRejectsUnknownSession(): void
    {
        $this->expectException(StorageException::class);
        $this->makeSessionRepo($this->dataDir)->update($this->sampleSession('ghost'));
    }

    public function testListReturnsNewestFirstAndHonoursLimit(): void
    {
        $repo = $this->makeSessionRepo($this->dataDir);
        $repo->create($this->sampleSession('sesn_a', 1000));
        $repo->create($this->sampleSession('sesn_b', 3000));
        $repo->create($this->sampleSession('sesn_c', 2000));

        $ids = array_map(static fn (Session $s): string => $s->id, $repo->list());
        self::assertSame(['sesn_b', 'sesn_c', 'sesn_a'], $ids);

        self::assertCount(2, $repo->list(2));
    }

    public function testDeleteRemovesSessionAndIsIdempotent(): void
    {
        $repo = $this->makeSessionRepo($this->dataDir);
        $repo->create($this->sampleSession());
        $repo->delete('sesn_1');
        $repo->delete('sesn_1');

        self::assertNull($repo->find('sesn_1'));
        $this->assertNoSessionStorageLeftBehind($this->dataDir, 'sesn_1');
    }

    public function testAppendAssignsSequentialSeqStartingAtOne(): void
    {
        $log = $this->makeMessageLog($this->dataDir);

        self::assertSame(1, $log->append('sesn_1', Message::ROLE_USER, [['type' => 'text', 'text' => 'hi']]));
        self::assertSame(2, $log->append('sesn_1', Message::ROLE_ASSISTANT, [['type' => 'text', 'text' => 'hello']]));
    }

    public function testSeqIsPerSessionNotGlobal(): void
    {
        $log = $this->makeMessageLog($this->dataDir);
        $log->append('sesn_a', Message::ROLE_USER, [['type' => 'text', 'text' => 'a']]);

        self::assertSame(1, $log->append('sesn_b', Message::ROLE_USER, [['type' => 'text', 'text' => 'b']]));
    }

    public function testAllYieldsMessagesInSeqOrderWithContentIntact(): void
    {
        $log = $this->makeMessageLog($this->dataDir);

        $toolUse = [[
            'type'  => 'tool_use',
            'id'    => 'toolu_01',
            'name'  => 'read_debug_log',
            'input' => ['lines' => 400, 'note' => "multi\nline \"quoted\""],
        ]];

        $log->append('sesn_1', Message::ROLE_USER, [['type' => 'text', 'text' => 'diagnose']]);
        $log->append('sesn_1', Message::ROLE_ASSISTANT, $toolUse, 'msg_abc');

        $messages = iterator_to_array($this->makeMessageLog($this->dataDir)->all('sesn_1'), false);

        self::assertCount(2, $messages);
        self::assertSame(1, $messages[0]->seq);
        self::assertSame(2, $messages[1]->seq);
        self::assertSame($toolUse, $messages[1]->content, 'Content blocks must round-trip verbatim');
        self::assertSame('msg_abc', $messages[1]->apiMessageId);
    }

    public function testAllIsEmptyForUnknownSession(): void
    {
        self::assertSame([], iterator_to_array($this->makeMessageLog($this->dataDir)->all('ghost'), false));
    }

    public function testCountTracksAppends(): void
    {
        $log = $this->makeMessageLog($this->dataDir);
        self::assertSame(0, $log->count('sesn_1'));

        $log->append('sesn_1', Message::ROLE_USER, [['type' => 'text', 'text' => 'x']]);
        self::assertSame(1, $log->count('sesn_1'));
    }

    public function testAuthStoreStartsUnconfigured(): void
    {
        $store = $this->makeAuthStore($this->dataDir);

        self::assertFalse($store->isConfigured());
        self::assertNull($store->current());
    }

    public function testCredentialRoundTripsEveryField(): void
    {
        $credential = new Credential('$argon2id$hash', 'argon2id', 1000, 2000);
        $this->makeAuthStore($this->dataDir)->store($credential);

        $found = $this->makeAuthStore($this->dataDir)->current();

        self::assertNotNull($found);
        self::assertSame($credential->toRow(), $found->toRow());
        self::assertTrue($this->makeAuthStore($this->dataDir)->isConfigured());
    }

    public function testStoringASecondCredentialReplacesTheFirst(): void
    {
        $store = $this->makeAuthStore($this->dataDir);
        $store->store(new Credential('$2y$first', 'bcrypt', 1000, 1000));
        // The second credential asks for a DIFFERENT createdAt (9000) than the
        // first (1000). If the store preserved created_at, $found->createdAt
        // is 1000; if it overwrote it, $found->createdAt is 9000 — a single
        // shared value for both calls could never tell those apart.
        $store->store(new Credential('$argon2id$second', 'argon2id', 9000, 3000));

        $found = $this->makeAuthStore($this->dataDir)->current();

        self::assertNotNull($found);
        self::assertSame('$argon2id$second', $found->hash);
        self::assertSame(1000, $found->createdAt, 'created_at must survive a password change unchanged');
        self::assertSame(3000, $found->changedAt);
    }

    public function testAnUnknownBucketIsUnlockedWithNoAttempts(): void
    {
        $state = $this->makeRateLimiter($this->dataDir)->peek('ip:1.2.3.4', 1000);

        self::assertSame(0, $state->attempts);
        self::assertFalse($state->isLocked(1000));
    }

    public function testAttemptsAccumulateWithinTheWindow(): void
    {
        $limiter = $this->makeRateLimiter($this->dataDir);

        self::assertSame(1, $limiter->hit('ip:1.2.3.4', 1000)->attempts);
        self::assertSame(2, $limiter->hit('ip:1.2.3.4', 1010)->attempts);
        self::assertSame(2, $this->makeRateLimiter($this->dataDir)->peek('ip:1.2.3.4', 1010)->attempts);
    }

    public function testExceedingTheLimitLocksTheBucket(): void
    {
        $limiter = $this->makeRateLimiter($this->dataDir);
        $state = new \WPSafeMode\Storage\RateLimitState(0, 0, null);

        for ($i = 0; $i < RateLimiter::MAX_ATTEMPTS; $i++) {
            $state = $limiter->hit('ip:1.2.3.4', 1000);
        }

        self::assertTrue($state->isLocked(1000));
        self::assertSame(RateLimiter::LOCKOUT_SECONDS, $state->retryAfter(1000));
    }

    public function testTheLockExpires(): void
    {
        $limiter = $this->makeRateLimiter($this->dataDir);

        for ($i = 0; $i < RateLimiter::MAX_ATTEMPTS; $i++) {
            $limiter->hit('ip:1.2.3.4', 1000);
        }

        $after = 1000 + RateLimiter::LOCKOUT_SECONDS + 1;

        self::assertFalse($limiter->peek('ip:1.2.3.4', $after)->isLocked($after));
    }

    public function testTheWindowResetsAfterItElapses(): void
    {
        $limiter = $this->makeRateLimiter($this->dataDir);
        $limiter->hit('ip:1.2.3.4', 1000);
        $limiter->hit('ip:1.2.3.4', 1000);

        $later = 1000 + RateLimiter::WINDOW_SECONDS + 1;

        self::assertSame(1, $limiter->hit('ip:1.2.3.4', $later)->attempts);
    }

    public function testBucketsAreIndependent(): void
    {
        $limiter = $this->makeRateLimiter($this->dataDir);

        for ($i = 0; $i < RateLimiter::MAX_ATTEMPTS; $i++) {
            $limiter->hit('ip:1.2.3.4', 1000);
        }

        self::assertFalse($limiter->peek('ip:5.6.7.8', 1000)->isLocked(1000));
    }

    public function testClearForgetsTheBucket(): void
    {
        $limiter = $this->makeRateLimiter($this->dataDir);

        for ($i = 0; $i < RateLimiter::MAX_ATTEMPTS; $i++) {
            $limiter->hit('ip:1.2.3.4', 1000);
        }

        $limiter->clear('ip:1.2.3.4');

        self::assertSame(0, $this->makeRateLimiter($this->dataDir)->peek('ip:1.2.3.4', 1000)->attempts);
    }

    /**
     * Review round 1, IMPORTANT 2: before this test, nothing wrote a
     * malformed row/entry directly, so the conformance suite could not see
     * that the two backends disagreed on what a corrupt stored value means —
     * SQLite blindly (int)-cast garbage (an arbitrary result depending on
     * the garbage), while JSON collapsed a shape it did not recognise to a
     * clean, unlocked slate (the most permissive reading possible for a
     * security counter). Both must now fail closed the same way: a value
     * that cannot be trusted to mean anything else reads as already at the
     * limit, not as a fresh bucket.
     */
    public function testMalformedStoredValueFailsClosedIdenticallyInBothBackends(): void
    {
        $this->writeMalformedRateLimitEntry($this->dataDir, 'ip:9.9.9.9');

        $state = $this->makeRateLimiter($this->dataDir)->peek('ip:9.9.9.9', 1000);

        self::assertTrue(
            $state->isLocked(1000),
            'a stored value that cannot be trusted to mean anything else must fail closed (locked), never silently read as a fresh, unlocked bucket'
        );
        self::assertSame(RateLimiter::MAX_ATTEMPTS, $state->attempts);
        self::assertSame(RateLimiter::LOCKOUT_SECONDS, $state->retryAfter(1000));
    }
}
