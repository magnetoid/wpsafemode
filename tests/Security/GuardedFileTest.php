<?php

declare(strict_types=1);

namespace WPSafeMode\Tests\Security;

use PHPUnit\Framework\TestCase;
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
}
