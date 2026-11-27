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
use WPSafeMode\Storage\Sqlite\SqliteSessionRepo;
use WPSafeMode\Storage\Sqlite\SqliteUserStore;

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

    public function testVersionOneCredentialIsDroppedAndUsersStartEmpty(): void
    {
        $pdo = $this->rawPdo();
        SqliteMigrator::migrate($pdo, array_slice(SqliteMigrator::steps(), 0, 1, true));
        self::assertSame(1, SqliteMigrator::currentVersion($pdo));

        $pdo->exec("INSERT INTO auth (id, password_hash, hash_algo, created_at, password_changed_at) VALUES (1, 'h', 'argon2id', 1, 1)");
        $pdo->exec("INSERT INTO config (key, value) VALUES ('k', 'v')");
        $pdo->exec(
            "INSERT INTO sessions (id, title, blade, status, model, effort, created_at, updated_at)
             VALUES ('sesn_1', 't', 'b', 'idle', 'm', 'e', 1, 1)"
        );

        SqliteMigrator::migrate($pdo);

        self::assertSame(2, SqliteMigrator::currentVersion($pdo));

        $connection = new SqliteConnection($this->dir . '/wpsm.sqlite');
        self::assertTrue((new SqliteUserStore($connection))->isEmpty());
        self::assertSame('v', (new SqliteConfigStore($connection))->get('k'));
        self::assertNotNull((new SqliteSessionRepo($connection))->find('sesn_1'));
        self::assertSame(
            [],
            $pdo->query("SELECT name FROM sqlite_master WHERE name = 'auth'")->fetchAll()
        );
    }

    public function testAStepFailingWithAStorageExceptionNamesTheStep(): void
    {
        $pdo = $this->rawPdo();
        $steps = [
            1 => SqliteMigrator::steps()[1],
            2 => function (): void {
                throw new StorageException('inner');
            },
        ];

        try {
            SqliteMigrator::migrate($pdo, $steps);
            self::fail('Expected StorageException');
        } catch (StorageException $e) {
            self::assertStringContainsString('schema version 2', $e->getMessage());
            self::assertInstanceOf(StorageException::class, $e->getPrevious());
        }
    }
}
