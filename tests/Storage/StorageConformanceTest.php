<?php

declare(strict_types=1);

namespace WPSafeMode\Tests\Storage;

use PHPUnit\Framework\TestCase;
use WPSafeMode\Storage\Contracts\ConfigStore;

/**
 * One suite, two backends. Every assertion here must hold identically for
 * SQLite and for the JSON fallback — that equivalence is the entire point
 * of the fallback (spec 7).
 */
abstract class StorageConformanceTest extends TestCase
{
    protected string $dataDir;

    abstract protected function makeConfigStore(string $dataDir): ConfigStore;

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
}
