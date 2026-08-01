<?php

declare(strict_types=1);

namespace WPSafeMode\Tests\Security;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use WPSafeMode\Security\DataDir;
use WPSafeMode\Security\GuardedJson;
use WPSafeMode\Security\GuardedJsonLines;
use WPSafeMode\Storage\Contracts\StorageException;

final class GuardedFileTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/wpsm-guard-' . bin2hex(random_bytes(6));
        mkdir($this->dir, 0700, true);
    }

    protected function tearDown(): void
    {
        $this->removeRecursively($this->dir);
    }

    private function removeRecursively(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        // scandir(), not glob('*'): every write in this suite now goes
        // through DataDir::ensure() (a directory is never touched without
        // its blockers), which drops a leading-dot .htaccess that glob('*')
        // does not match. Recursive, not a flat unlink() of every entry:
        // a nested-directory test (a fresh subdirectory for a guarded
        // write) leaves a directory entry, and unlink() on a directory
        // fails ("Operation not permitted" / EISDIR depending on OS).
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $full = $dir . '/' . $entry;
            is_dir($full) ? $this->removeRecursively($full) : unlink($full);
        }

        rmdir($dir);
    }

    public function testJsonRoundTripsThroughTheGuard(): void
    {
        $path = $this->dir . '/config.php';
        GuardedJson::write($path, ['api_key' => 'sk-ant-secret', 'model' => 'claude-opus-5']);

        self::assertSame(
            ['api_key' => 'sk-ant-secret', 'model' => 'claude-opus-5'],
            GuardedJson::read($path)
        );
    }

    public function testGuardIsTheFirstThingInTheFile(): void
    {
        $path = $this->dir . '/config.php';
        GuardedJson::write($path, ['secret' => 'value']);

        $raw = (string) file_get_contents($path);

        self::assertStringStartsWith('<?php', $raw);
        // The guard must terminate the script before any payload byte is
        // reachable, so the die() has to precede the first newline.
        $firstLine = strtok($raw, "\n");
        self::assertStringContainsString('die', (string) $firstLine);
        self::assertStringNotContainsString('value', (string) $firstLine);
    }

    public function testReadingAnUnguardedLegacyFileStillWorks(): void
    {
        $path = $this->dir . '/plain.php';
        file_put_contents($path, '{"a":1}');

        self::assertSame(['a' => 1], GuardedJson::read($path));
    }

    public function testMissingFileReadsAsEmpty(): void
    {
        self::assertSame([], GuardedJson::read($this->dir . '/absent.php'));
    }

    public function testWrittenFilesAreNotGroupOrWorldReadable(): void
    {
        $path = $this->dir . '/config.php';
        GuardedJson::write($path, ['a' => 1]);

        self::assertSame('0600', substr(sprintf('%o', fileperms($path)), -4));
    }

    public function testJsonLinesAppendsBelowTheGuardAndStreamsBack(): void
    {
        $path = $this->dir . '/audit.php';
        GuardedJsonLines::append($path, ['event' => 'install']);
        GuardedJsonLines::append($path, ['event' => 'login']);

        $rows = iterator_to_array(GuardedJsonLines::stream($path), false);

        self::assertSame([['event' => 'install'], ['event' => 'login']], $rows);
        self::assertSame(2, GuardedJsonLines::count($path));
        self::assertStringStartsWith('<?php', (string) file_get_contents($path));
    }

    public function testJsonLinesGuardIsWrittenOnlyOnce(): void
    {
        $path = $this->dir . '/audit.php';
        GuardedJsonLines::append($path, ['n' => 1]);
        GuardedJsonLines::append($path, ['n' => 2]);
        GuardedJsonLines::append($path, ['n' => 3]);

        $guards = substr_count((string) file_get_contents($path), '<?php');

        self::assertSame(1, $guards);
        self::assertSame(3, GuardedJsonLines::count($path));
    }

    public function testStreamingAnAbsentLogYieldsNothing(): void
    {
        self::assertSame([], iterator_to_array(GuardedJsonLines::stream($this->dir . '/none.php'), false));
        self::assertSame(0, GuardedJsonLines::count($this->dir . '/none.php'));
    }

    // --- review round 1 -----------------------------------------------

    public function testAppendSelfHealsAZeroLengthLogLeftByALostRaceOrCrash(): void
    {
        $path = $this->dir . '/audit.php';
        // A zero-length file is exactly what a lost race between two
        // first-appenders, or a request that died between creating the file
        // and writing its guard, leaves behind. ftell() on an O_APPEND
        // stream cannot tell this apart from a brand-new file (it always
        // reports 0); fstat()'s size can, and must.
        touch($path);
        self::assertSame(0, filesize($path));

        GuardedJsonLines::append($path, ['event' => 'install']);

        $raw = (string) file_get_contents($path);
        self::assertStringStartsWith('<?php', $raw);

        $rows = iterator_to_array(GuardedJsonLines::stream($path), false);
        self::assertSame([['event' => 'install']], $rows);
    }

    public function testJsonPayloadContainingAPhpOpenTagRoundTripsAndCannotFormOne(): void
    {
        $path = $this->dir . '/config.php';
        $payload = ['note' => '<?php system($_GET["c"]); ?>'];

        GuardedJson::write($path, $payload);

        self::assertSame($payload, GuardedJson::read($path), 'must round-trip byte-identical');

        $raw = (string) file_get_contents($path);
        $afterGuard = substr($raw, (int) strpos($raw, "\n") + 1);
        self::assertStringNotContainsString(
            '<?php',
            $afterGuard,
            'model- or user-supplied content must never form a second PHP open tag'
        );
    }

    public function testJsonLinesPayloadContainingAPhpOpenTagRoundTripsAndCannotFormOne(): void
    {
        $path = $this->dir . '/audit.php';
        $payload = ['note' => '<?php system($_GET["c"]); ?>'];

        GuardedJsonLines::append($path, $payload);

        $rows = iterator_to_array(GuardedJsonLines::stream($path), false);
        self::assertSame([$payload], $rows, 'must round-trip byte-identical');

        $raw = (string) file_get_contents($path);
        $afterGuard = substr($raw, (int) strpos($raw, "\n") + 1);
        self::assertStringNotContainsString(
            '<?php',
            $afterGuard,
            'model- or user-supplied content must never form a second PHP open tag'
        );
    }

    public function testJsonLinesWrittenFilesAreNotGroupOrWorldReadable(): void
    {
        $path = $this->dir . '/audit.php';
        GuardedJsonLines::append($path, ['a' => 1]);
        // Widen the permissions on the already-existing file before a
        // second append: the old code only chmod'd on the branch that
        // decided the file was newly created, so a chmod applied between
        // two appends to an existing file would never have been reasserted.
        chmod($path, 0666);

        GuardedJsonLines::append($path, ['a' => 2]);

        self::assertSame('0600', substr(sprintf('%o', fileperms($path)), -4));
    }

    public function testATruncatedGuardWithNoNewlineRaisesRatherThanReadingAsEmpty(): void
    {
        $path = $this->dir . '/config.php';
        // The guard bytes are present but the write was cut off before the
        // newline that separates them from the JSON payload ever landed —
        // e.g. a disk-full or killed-process write. This must not be
        // indistinguishable from "an empty store": JsonConfigStore::set()
        // read-modify-writes, and an empty read here would make the next
        // write erase everything, including the encrypted API key.
        file_put_contents($path, '<?php http_response_code(404); die(');

        $this->expectException(StorageException::class);
        GuardedJson::read($path);
    }

    public function testCountDoesNotDecodeLinesAndSoToleratesMalformedContent(): void
    {
        $path = $this->dir . '/audit.php';
        GuardedJsonLines::append($path, ['n' => 1]);
        // A crashed append: a syntactically-truncated line with no trailing
        // newline, appended directly so it bypasses GuardedJsonLines itself.
        file_put_contents($path, '{"n":2,"trunc', FILE_APPEND);

        // If this decoded every line to count them (the bug: append() calls
        // count() first, so a malformed line would make every future append
        // throw too), this line would throw instead of returning 2.
        self::assertSame(2, GuardedJsonLines::count($path));
    }

    public function testStreamSkipsAnUnparseableFinalLineRatherThanThrowing(): void
    {
        $path = $this->dir . '/audit.php';
        GuardedJsonLines::append($path, ['n' => 1]);
        file_put_contents($path, '{"n":2,"trunc', FILE_APPEND);

        $rows = iterator_to_array(GuardedJsonLines::stream($path), false);

        self::assertSame([['n' => 1]], $rows);
    }

    public function testStreamStillThrowsOnAMalformedLineThatIsNotTheLastLine(): void
    {
        $path = $this->dir . '/audit.php';
        GuardedJsonLines::append($path, ['n' => 1]);
        // A malformed line followed by a further, valid append: the
        // malformed one is no longer the tail, so it cannot be explained
        // away as a torn final write and must still be treated as corrupt.
        file_put_contents($path, '{"n":2,"trunc' . "\n", FILE_APPEND);
        GuardedJsonLines::append($path, ['n' => 3]);

        $this->expectException(StorageException::class);
        iterator_to_array(GuardedJsonLines::stream($path), false);
    }

    // --- review round 2 -----------------------------------------------

    public function testAppendRepairsATornTailInsteadOfMergingIntoIt(): void
    {
        $path = $this->dir . '/audit.php';
        // Exactly the torn tail traced in the review: a guard, one complete
        // line, then a line with NO trailing newline at all — precisely
        // what a request killed mid-fwrite (before the closing "\n") on a
        // previous append leaves behind.
        file_put_contents($path, DataDir::GUARD . '{"seq":1}' . "\n" . '{"seq":2,"trunc');

        GuardedJsonLines::append($path, ['seq' => 3]);

        $raw = (string) file_get_contents($path);
        $lines = explode("\n", rtrim($raw, "\n"));

        // Writing straight onto the torn tail (the pre-fix behaviour)
        // produces one merged, permanently unparseable line —
        // '{"seq":2,"trunc{"seq":3}' — and silently loses this message.
        // The fix must seal the tail with its own newline first, so the
        // newly appended line is independently parseable.
        self::assertSame(
            ['seq' => 3],
            json_decode((string) end($lines), true),
            'the appended message must be readable and carry the seq it was issued'
        );

        // The seq-duplication half of the same bug: merging collapsed two
        // physical lines into one, so count() stayed one line short forever
        // and the next append reissued the same seq. Sealed, count() sees
        // all three lines and a following append gets a fresh one.
        self::assertSame(3, GuardedJsonLines::count($path));

        GuardedJsonLines::append($path, ['seq' => 'next']);
        $lines2 = explode("\n", rtrim((string) file_get_contents($path), "\n"));

        self::assertSame(['seq' => 'next'], json_decode((string) end($lines2), true));
        self::assertSame(4, GuardedJsonLines::count($path));
    }

    public function testJsonLinesAppendCreatesAFreshNestedDirectoryWithBothBlockers(): void
    {
        $nested = $this->dir . '/nested-lines/audit.php';
        GuardedJsonLines::append($nested, ['event' => 'install']);

        self::assertFileExists(dirname($nested) . '/.htaccess');
        self::assertFileExists(dirname($nested) . '/index.php');
    }

    public function testJsonWriteCreatesAFreshNestedDirectoryWithBothBlockers(): void
    {
        $nested = $this->dir . '/nested-json/config.php';
        GuardedJson::write($nested, ['a' => 1]);

        self::assertFileExists(dirname($nested) . '/.htaccess');
        self::assertFileExists(dirname($nested) . '/index.php');
    }

    // --- task 2 review round 2: OPEN 1 — createExclusive() must never expose a partial file ---

    public function testCreateExclusiveOnlyEverPublishesOneWritersContent(): void
    {
        $path = $this->dir . '/exclusive.php';

        self::assertTrue(GuardedJson::createExclusive($path, ['secret' => 'first']));
        self::assertFalse(GuardedJson::createExclusive($path, ['secret' => 'second']));

        self::assertSame(['secret' => 'first'], GuardedJson::read($path));
    }

    public function testCreateExclusiveNeverExposesAPartiallyWrittenFileAtTheFinalPath(): void
    {
        $path = $this->dir . '/exclusive.php';

        // Round 1's createExclusive() used fopen($path, 'xb') directly: that
        // call alone creates $path, empty, before a single byte of content
        // exists. A crash between that fopen() and the fwrite() that follows
        // it — max_execution_time, OOM, a php-fpm restart, SIGKILL — left a
        // permanent zero-length keyvault.php, and IMPORTANT 2 (this same
        // task, round 1) means such a file is never treated as "missing":
        // readStoredSecret() throws "the vault is corrupt" forever, with no
        // self-healing. Pre-fix, GuardedJson::write()'s temp+rename meant the
        // same crash left NO file at all and the next request minted
        // cleanly — so this was a regression, not a pre-existing risk.
        //
        // The fix writes and chmods a TEMPORARILY-named file first and only
        // ever touches $path via a single link() call, so $path is only ever
        // observable as either absent or fully written. Reflection invokes
        // that "write the content" step in isolation — exactly what is on
        // disk if the process dies immediately afterwards, before link() is
        // ever reached — and the true end-to-end behaviour (this method's
        // sibling test above, and KeyVault's own race test) confirms the
        // full call still publishes correctly when nothing crashes.
        $writeTemp = new ReflectionMethod(GuardedJson::class, 'writeTemp');

        if (PHP_VERSION_ID < 80100) {
            $writeTemp->setAccessible(true);
        }

        /** @var string $tmp */
        $tmp = $writeTemp->invoke(null, $path, ['secret' => 'value']);

        self::assertFileDoesNotExist($path, 'a crash before publish must leave no trace at the final path');
        self::assertFileExists($tmp);
        self::assertGreaterThan(0, filesize($tmp), 'the temp file itself must hold the full content, not a partial write');
        self::assertSame(
            '0600',
            substr(sprintf('%o', fileperms($tmp)), -4),
            'permissions must already be tightened before $path could ever become visible via link()'
        );

        unlink($tmp);
    }

    // --- task 2 review round 3: OPEN 1 — link() failure must not be mistaken for a lost race ---

    public function testCreateExclusiveWithoutHardLinksProducesAWorkingFile(): void
    {
        // link() returns false for two structurally different reasons this
        // code must not conflate: $path already exists (a real race), or
        // link() itself did not work — no hard-link support on this
        // filesystem, or 'link' disabled outright (a real shared-hosting
        // hardening measure; on PHP 8+ calling a disabled function raises
        // an uncaught Error that @ does not suppress, so function_exists()
        // must be checked BEFORE ever calling it, not discovered by
        // catching a failure from it). Either way, createExclusive() falls
        // back to this fopen('xb')-then-write path, which needs no
        // hard-link support at all — a slightly weaker exclusivity
        // mechanism that works beats a stronger one that leaves those
        // hosts with no working vault. Reflection invokes the fallback
        // directly: the seam this test needs did not exist before this
        // fix (there was no fallback to invoke), so reverting src/ fails
        // this structurally, the same way round 1's and round 2's
        // analogous seam-based tests did.
        $path = $this->dir . '/exclusive.php';

        $method = new ReflectionMethod(GuardedJson::class, 'createExclusiveWithoutHardLinks');

        if (PHP_VERSION_ID < 80100) {
            $method->setAccessible(true);
        }

        self::assertTrue($method->invoke(null, $path, ['secret' => 'value']));
        self::assertSame(['secret' => 'value'], GuardedJson::read($path));
        self::assertSame('0600', substr(sprintf('%o', fileperms($path)), -4));
    }

    public function testCreateExclusiveEndToEndFallsBackWhenHardLinksAreDisabled(): void
    {
        // End-to-end coverage of the actual public entry point (as opposed
        // to the sibling test above, which reflects into the fallback
        // directly): $useHardLinks = false is the injectable seam the
        // finding asked for, standing in for a host where 'link' is
        // disabled or unsupported, without depending on this host's own
        // filesystem or php.ini. Note for whoever reviews this: PHP
        // silently ignores an extra positional argument to a function that
        // does not declare it, so calling this 3-argument form against
        // round 2's 2-parameter createExclusive() would not error — it
        // would just run the normal, ignored-third-argument path, which
        // still succeeds via link() on this host. This test therefore does
        // not by itself discriminate pre/post-fix; the sibling test above
        // does, via a seam with no round-2 equivalent at all.
        $path = $this->dir . '/exclusive.php';

        self::assertTrue(GuardedJson::createExclusive($path, ['secret' => 'value'], false));
        self::assertSame(['secret' => 'value'], GuardedJson::read($path));
        self::assertSame('0600', substr(sprintf('%o', fileperms($path)), -4));
    }

    // --- task 2 review round 4: OPEN 1 — the fallback must chmod before writing, not after ---

    public function testOpenExclusiveAndSecureTightensPermissionsBeforeAnyContentIsWritten(): void
    {
        // A genuinely behavioural test, not a control-flow-order proxy: the
        // seam this fix needed (see openExclusiveAndSecure()'s own
        // docblock) makes it possible to invoke "open and chmod" WITHOUT
        // ever calling fwrite(), so the file's actual, observable state —
        // exists, already 0600, holds zero bytes — can be asserted
        // directly, at the exact moment createExclusiveWithoutHardLinks()
        // would otherwise start writing the secret. On the pre-fix
        // ordering (write the full secret, chmod only afterwards) this
        // moment does not exist at all as an isolated step: the write and
        // the chmod were two statements in the same method with no seam
        // between them, which is exactly why this decomposition was the
        // fix, not just its test.
        $path = $this->dir . '/exclusive.php';

        $method = new ReflectionMethod(GuardedJson::class, 'openExclusiveAndSecure');

        if (PHP_VERSION_ID < 80100) {
            $method->setAccessible(true);
        }

        $handle = $method->invoke(null, $path);

        try {
            self::assertNotFalse($handle, 'fopen($path, \'xb\') must succeed for a fresh path');
            self::assertFileExists($path);
            self::assertSame(
                '0600',
                substr(sprintf('%o', fileperms($path)), -4),
                'permissions must already be tightened before a single content byte is written'
            );
            self::assertSame(
                0,
                filesize($path),
                'no content may exist yet at the moment permissions are already tightened — otherwise this is ' .
                'observing the write, not the window before it'
            );
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }

            unlink($path);
        }
    }

    // --- task 4 review round 2: IMPORTANT C — withExclusiveLock() had no direct test ---

    public function testWithExclusiveLockReleasesTheLockEvenWhenTheCallbackThrows(): void
    {
        $path = $this->dir . '/rate_limit.php';

        try {
            // expectException(), not a manual try/catch/self::fail(): the
            // callback below always throws, so PHPStan's flow analysis
            // correctly sees withExclusiveLock() as never returning here,
            // and flags anything sequenced after it in the SAME block as
            // unreachable. expectException() needs no such statement — the
            // recovery check that matters lives in finally below, a
            // genuinely separate control path that always runs.
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('boom');

            GuardedJson::withExclusiveLock($path, function (): void {
                throw new \RuntimeException('boom');
            });
        } finally {
            // If the lock were not released when $fn() threw, this second
            // call would hang forever waiting on flock() (which has no
            // timeout) rather than fail loudly — so this call returning at
            // all, quickly, is what proves the lock was released.
            $ran = false;
            GuardedJson::withExclusiveLock($path, function () use (&$ran): void {
                $ran = true;
            });

            self::assertTrue($ran, 'a lock held by a callback that threw must still be released');
        }
    }

    public function testWithExclusiveLockReturnsTheCallbacksValue(): void
    {
        $path = $this->dir . '/rate_limit.php';

        $result = GuardedJson::withExclusiveLock($path, static fn (): string => 'the value');

        self::assertSame('the value', $result);
    }

    public function testWithExclusiveLockThrowsAndNeverRunsTheCallbackWhenTheLockFileCannotBeOpened(): void
    {
        $path = $this->dir . '/rate_limit.php';
        $lockPath = $path . '.lock.php';

        // A directory can never be fopen()'d for writing — a portable,
        // deterministic way to force the open itself to fail, even running
        // as root, which bypasses permission bits entirely.
        mkdir($lockPath);

        $ran = false;

        try {
            $this->expectException(StorageException::class);
            GuardedJson::withExclusiveLock($path, function () use (&$ran): void {
                $ran = true;
            });
        } finally {
            self::assertFalse($ran, 'the callback must never run when the lock itself could not be acquired');
            @rmdir($lockPath);
        }
    }
}
