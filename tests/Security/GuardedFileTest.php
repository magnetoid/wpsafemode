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
        // scandir(), not glob('*'): every write in this suite now goes
        // through DataDir::ensure() (a directory is never touched without
        // its blockers), which drops a leading-dot .htaccess that glob('*')
        // does not match. Left behind, it made the plain rmdir() below fail
        // with "Directory not empty" on every test in this class.
        foreach (scandir($this->dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            unlink($this->dir . '/' . $entry);
        }

        rmdir($this->dir);
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
}
