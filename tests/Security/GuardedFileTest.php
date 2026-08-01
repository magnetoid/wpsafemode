<?php

declare(strict_types=1);

namespace WPSafeMode\Tests\Security;

use PHPUnit\Framework\TestCase;
use WPSafeMode\Security\DataDir;
use WPSafeMode\Security\GuardedJson;
use WPSafeMode\Security\GuardedJsonLines;

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
        foreach (glob($this->dir . '/*') ?: [] as $file) {
            unlink($file);
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
}
