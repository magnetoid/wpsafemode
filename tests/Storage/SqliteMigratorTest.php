<?php

declare(strict_types=1);

namespace WPSafeMode\Tests\Storage;

use PDO;
use PHPUnit\Framework\TestCase;
use WPSafeMode\Core\Version;
use WPSafeMode\Storage\Contracts\StorageException;
use WPSafeMode\Security\Role;
use WPSafeMode\Security\Channel;
use WPSafeMode\Security\Principal;
use WPSafeMode\Storage\AuditEvent;
use WPSafeMode\Storage\SqliteStorage;
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

        self::assertSame(Version::SCHEMA, SqliteMigrator::currentVersion($pdo));

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

    public function testStepThreeAddsLoginSessionsWithoutTouchingUsers(): void
    {
        $pdo = $this->rawPdo();
        SqliteMigrator::migrate($pdo, array_slice(SqliteMigrator::steps(), 0, 2, true));
        self::assertSame(2, SqliteMigrator::currentVersion($pdo));
        $pdo->exec(
            "INSERT INTO users (login, role, password_hash, hash_algo, created_at, password_changed_at)
             VALUES ('owner', 'owner', 'h', 'bcrypt', 1, 1)"
        );
        unset($pdo);

        $storage = new SqliteStorage($this->dir);

        $user = $storage->users()->findByLogin('owner');
        self::assertNotNull($user);
        self::assertSame(Role::OWNER, $user->role);

        $sessions = $storage->authSessions();
        $row = [
            'user_id' => $user->id, 'created_at' => 10, 'last_seen_at' => 11, 'expires_at' => 99,
            'ip' => '1.2.3.4', 'ua_hash' => 'abc',
        ];
        $sessions->put('tokenhash', $row);
        self::assertSame($row, $sessions->get('tokenhash'));
        self::assertSame(Version::SCHEMA, SqliteMigrator::currentVersion($this->rawPdo()));
    }

    public function testStepFourAddsTheAuditLog(): void
    {
        $storage = new SqliteStorage($this->dir);
        $actor = Principal::anonymous(Channel::BROWSER);
        $storage->auditLog()->record(AuditEvent::by($actor, AuditEvent::LOGIN_FAILED, '1.2.3.4', null, [], 1000));

        self::assertSame(4, SqliteMigrator::currentVersion($this->rawPdo()));

        $events = $storage->auditLog()->recent(1);
        self::assertCount(1, $events);
        self::assertSame('anonymous', $events[0]->actor);
        self::assertSame(AuditEvent::LOGIN_FAILED, $events[0]->event);
    }
}
