<?php

declare(strict_types=1);

namespace WPSafeMode\Tests\Storage;

use PHPUnit\Framework\TestCase;
use WPSafeMode\Core\EnvironmentReport;
use WPSafeMode\Security\GuardedJson;
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

    private function makeEnv(bool $hasPdoSqlite): EnvironmentReport
    {
        return new EnvironmentReport(
            phpVersion:    '8.5.9',
            phpSatisfied:  true,
            hasPdoSqlite:  $hasPdoSqlite,
            hasMysqli:     false,
            hasOpenssl:    true,
            hasSodium:     true,
            hasExec:       false,
            freeDiskBytes: 1_000_000_000,
            wpRoot:        null,
        );
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

    // --- review round 1: StorageFactory::open() -----------------------

    public function testOpenRecordsBackendAndSqliteFilenameOnFirstCall(): void
    {
        $dir = $this->makeTempDir();

        $storage = StorageFactory::open($dir, $this->makeEnv(true));

        self::assertSame('sqlite', $storage->backend());

        $record = GuardedJson::read($dir . '/install.php');
        self::assertSame('sqlite', $record['backend']);
        self::assertIsString($record['sqlite_file'] ?? null);
        self::assertNotSame('', $record['sqlite_file']);
    }

    public function testOpenReusesRecordedBackendAndSqliteFilenameOnSecondCall(): void
    {
        $dir = $this->makeTempDir();
        $env = $this->makeEnv(true);

        StorageFactory::open($dir, $env);
        $first = GuardedJson::read($dir . '/install.php');

        // A second call must not re-decide either fact: using
        // self::sqliteFilename($dataDir) here (a fresh re-read from disk)
        // instead of the value the first call already recorded was the
        // defect — it opened a window for a second, concurrent first
        // request's write to land in between, so a process could end up
        // opening a *different* SQLite file than the one it just recorded.
        StorageFactory::open($dir, $env);
        $second = GuardedJson::read($dir . '/install.php');

        self::assertSame($first['backend'], $second['backend']);
        self::assertSame($first['sqlite_file'], $second['sqlite_file']);
    }

    public function testOpenDoesNotChangeSqliteFilenameWhenBackendKeyIsMissing(): void
    {
        $dir = $this->makeTempDir();
        // Simulates install.php having been created by something other than
        // open() — a future writer that never sets 'backend'. Regenerating
        // sqlite_file whenever 'backend' is merely absent (rather than
        // checking whether sqlite_file itself is absent) would silently
        // abandon this already-recorded database.
        GuardedJson::write($dir . '/install.php', ['sqlite_file' => 'store-existing-name.db']);

        StorageFactory::open($dir, $this->makeEnv(true));

        $record = GuardedJson::read($dir . '/install.php');
        self::assertSame('store-existing-name.db', $record['sqlite_file']);
        self::assertSame('sqlite', $record['backend']);
    }

    public function testSqliteFilenameIsNullWhenNothingIsRecordedYet(): void
    {
        // Must be honest: it used to fabricate a fresh random filename on
        // every call once nothing was recorded — a public accessor that
        // returns a different answer each time it is asked the same
        // question, for a value nothing has actually committed to using.
        self::assertNull(StorageFactory::sqliteFilename($this->makeTempDir()));
    }

    // --- review round 2 -------------------------------------------------

    public function testOpenGeneratesSqliteFilenameOnceWhenBackendIsPresentButSqliteFileIsAbsent(): void
    {
        $dir = $this->makeTempDir();
        // The more dangerous mirror of
        // testOpenDoesNotChangeSqliteFilenameWhenBackendKeyIsMissing above:
        // an install.php that recorded a backend but never recorded a
        // filename alongside it. The original, pre-round-1 code gated the
        // entire "record it" block on the backend key alone, so this state
        // made every single open() call mint and use a brand-new random
        // filename — not just a one-time race between two concurrent first
        // requests, but an ongoing, every-request one, since sqlite_file was
        // never persisted at all.
        GuardedJson::write($dir . '/install.php', ['backend' => 'sqlite']);

        StorageFactory::open($dir, $this->makeEnv(true));
        $first = GuardedJson::read($dir . '/install.php');

        self::assertIsString($first['sqlite_file'] ?? null);
        self::assertNotSame('', $first['sqlite_file']);

        StorageFactory::open($dir, $this->makeEnv(true));
        $second = GuardedJson::read($dir . '/install.php');

        self::assertSame($first['sqlite_file'], $second['sqlite_file']);
    }
}
