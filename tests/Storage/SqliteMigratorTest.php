<?php

declare(strict_types=1);

namespace WPSafeMode\Tests\Storage;

use PDO;
use PHPUnit\Framework\TestCase;
use WPSafeMode\Core\Version;
use WPSafeMode\Storage\Contracts\StorageException;
use WPSafeMode\Storage\Sqlite\SqliteConfigStore;
use WPSafeMode\Storage\Sqlite\SqliteConnection;
use WPSafeMode\Storage\Sqlite\SqliteMigrator;

final class SqliteMigratorTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/wpsm-mig-' . bin2hex(random_bytes(6));
        mkdir($this->dir, 0700, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->dir);
    }

    private function rawPdo(): PDO
    {
        return new PDO('sqlite:' . $this->dir . '/wpsm.sqlite', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    public function testAFreshDataDirectoryIsBroughtToTheCurrentVersion(): void
    {
        $pdo = $this->rawPdo();
        SqliteMigrator::migrate($pdo);

        self::assertSame(Version::SCHEMA, SqliteMigrator::currentVersion($pdo));
    }

    public function testMigratingTwiceChangesNothing(): void
    {
        $connection = new SqliteConnection($this->dir . '/wpsm.sqlite');
        $config = new SqliteConfigStore($connection);
        $config->set('k', 'v');

        SqliteMigrator::migrate($connection->pdo());

        self::assertSame('v', $config->get('k'));
        self::assertSame(Version::SCHEMA, SqliteMigrator::currentVersion($connection->pdo()));
    }

    public function testTheRealStepListEndsAtVersionSchema(): void
    {
        self::assertSame(Version::SCHEMA, max(array_keys(SqliteMigrator::steps())));

        $pdo = $this->rawPdo();
        SqliteMigrator::migrate($pdo);

        self::assertSame(Version::SCHEMA, SqliteMigrator::currentVersion($pdo));
    }

    public function testAFutureVersionIsRefused(): void
    {
        $pdo = $this->rawPdo();
        SqliteMigrator::migrate($pdo);
        $pdo->exec("UPDATE meta SET value = '" . (Version::SCHEMA + 1) . "' WHERE key = 'schema_version'");

        $this->expectException(StorageException::class);
        $this->expectExceptionMessage('newer version');

        SqliteMigrator::migrate($pdo);
    }

    public function testAFailingStepLeavesTheVersionUntouched(): void
    {
        $pdo = $this->rawPdo();
        $steps = [
            1 => SqliteMigrator::steps()[1],
            2 => function (): void {
                throw new \RuntimeException('boom');
            },
        ];

        try {
            SqliteMigrator::migrate($pdo, $steps);
            self::fail('Expected StorageException');
        } catch (StorageException $e) {
            self::assertInstanceOf(\RuntimeException::class, $e->getPrevious());
        }

        self::assertSame(1, SqliteMigrator::currentVersion($pdo));
    }

    public function testAFailedStepRollsBackItsOwnWrites(): void
    {
        $pdo = $this->rawPdo();
        $steps = [
            1 => SqliteMigrator::steps()[1],
            2 => function (PDO $pdo): void {
                $pdo->exec('CREATE TABLE half_done (id INTEGER)');
                throw new \RuntimeException('boom');
            },
        ];

        try {
            SqliteMigrator::migrate($pdo, $steps);
            self::fail('Expected StorageException');
        } catch (StorageException $e) {
            // expected
        }

        $found = $pdo->query("SELECT name FROM sqlite_master WHERE name = 'half_done'")->fetchAll();
        self::assertSame([], $found);
    }
}
