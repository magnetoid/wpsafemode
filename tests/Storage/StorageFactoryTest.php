<?php

declare(strict_types=1);

namespace WPSafeMode\Tests\Storage;

use PHPUnit\Framework\TestCase;
use WPSafeMode\Storage\Contracts\StorageException;
use WPSafeMode\Storage\StorageFactory;

final class StorageFactoryTest extends TestCase
{
    /** @var string[] */
    private array $tempDirs = [];

    protected function tearDown(): void
    {
        foreach ($this->tempDirs as $dir) {
            $this->removeRecursively($dir);
        }

        $this->tempDirs = [];
    }

    private function makeTempDir(): string
    {
        $dir = sys_get_temp_dir() . '/wpsm-factory-' . bin2hex(random_bytes(4));
        mkdir($dir, 0700, true);
        $this->tempDirs[] = $dir;

        return $dir;
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

    public function testPrefersSqliteWhenAvailableAndNothingRecorded(): void
    {
        self::assertSame('sqlite', StorageFactory::chooseBackend(true, null));
    }

    public function testFallsBackToJsonWhenSqliteMissing(): void
    {
        self::assertSame('json', StorageFactory::chooseBackend(false, null));
    }

    public function testRecordedBackendWinsOverDetection(): void
    {
        // An install that chose JSON keeps using JSON even if the host later
        // gains pdo_sqlite — there is no migration between backends (spec 7).
        self::assertSame('json', StorageFactory::chooseBackend(true, 'json'));
    }

    public function testRecordedSqliteOnHostWithoutSqliteIsAnError(): void
    {
        $this->expectException(StorageException::class);
        StorageFactory::chooseBackend(false, 'sqlite');
    }

    public function testCreateReturnsWorkingStorageForBothBackends(): void
    {
        foreach (['sqlite', 'json'] as $backend) {
            $storage = StorageFactory::create($this->makeTempDir(), $backend);
            $storage->config()->set('model', 'claude-opus-4-8');

            self::assertSame($backend, $storage->backend());
            self::assertSame('claude-opus-4-8', $storage->config()->get('model'));
        }
    }

    public function testCreateRejectsUnknownBackend(): void
    {
        $this->expectException(StorageException::class);
        StorageFactory::create(sys_get_temp_dir(), 'postgres');
    }
}
