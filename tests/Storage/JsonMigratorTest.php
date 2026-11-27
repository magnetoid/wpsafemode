<?php

declare(strict_types=1);

namespace WPSafeMode\Tests\Storage;

use PHPUnit\Framework\TestCase;
use WPSafeMode\Core\Version;
use WPSafeMode\Security\GuardedJson;
use WPSafeMode\Security\Role;
use WPSafeMode\Storage\Credential;
use WPSafeMode\Storage\Contracts\StorageException;
use WPSafeMode\Storage\Json\JsonConfigStore;
use WPSafeMode\Storage\Json\JsonMigrator;
use WPSafeMode\Storage\Json\JsonUserStore;
use WPSafeMode\Storage\JsonStorage;

final class JsonMigratorTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/wpsm-jmig-' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        $this->remove($this->dir);
    }

    private function remove(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $full = $path . '/' . $entry;
            is_dir($full) ? $this->remove($full) : @unlink($full);
        }
        @rmdir($path);
    }

    public function testAFreshDataDirectoryIsBroughtToTheCurrentVersion(): void
    {
        JsonMigrator::migrate($this->dir);

        self::assertSame(Version::SCHEMA, JsonMigrator::currentVersion($this->dir));
    }

    public function testMigratingTwiceChangesNothing(): void
    {
        JsonMigrator::migrate($this->dir);
        $config = new JsonConfigStore($this->dir . '/config.php');
        $config->set('k', 'v');

        JsonMigrator::migrate($this->dir);

        self::assertSame('v', $config->get('k'));
        self::assertSame(Version::SCHEMA, JsonMigrator::currentVersion($this->dir));
    }

    public function testTheRealStepListEndsAtVersionSchema(): void
    {
        self::assertSame(Version::SCHEMA, max(array_keys(JsonMigrator::steps())));

        JsonMigrator::migrate($this->dir);

        self::assertSame(Version::SCHEMA, JsonMigrator::currentVersion($this->dir));
    }

    public function testAFutureVersionIsRefused(): void
    {
        mkdir($this->dir, 0700, true);
        GuardedJson::write($this->dir . '/meta.php', ['schema_version' => Version::SCHEMA + 1]);

        $this->expectException(StorageException::class);
        $this->expectExceptionMessage('newer version');

        JsonMigrator::migrate($this->dir);
    }

    public function testAFailingStepLeavesTheVersionUntouched(): void
    {
        $steps = [
            1 => JsonMigrator::steps()[1],
            2 => function (): void {
                throw new \RuntimeException('boom');
            },
        ];

        try {
            JsonMigrator::migrate($this->dir, $steps);
            self::fail('Expected StorageException');
        } catch (StorageException $e) {
            self::assertInstanceOf(\RuntimeException::class, $e->getPrevious());
        }

        self::assertSame(1, JsonMigrator::currentVersion($this->dir));
    }

    public function testAStepThatRanButWasNotRecordedRunsAgainSafely(): void
    {
        $marker = $this->dir . '/marker.php';
        $step2 = function (string $dir): void {
            GuardedJson::write($dir . '/marker.php', ['n' => 1]);
        };
        $steps = [1 => JsonMigrator::steps()[1], 2 => $step2];

        mkdir($this->dir, 0700, true);
        $step2($this->dir);

        JsonMigrator::migrate($this->dir, $steps);

        self::assertSame(['n' => 1], GuardedJson::read($marker));
        self::assertSame(2, JsonMigrator::currentVersion($this->dir));
    }

    public function testAMalformedMetaFileIsRefused(): void
    {
        mkdir($this->dir, 0700, true);
        GuardedJson::write($this->dir . '/meta.php', ['schema_version' => 'two']);

        $this->expectException(StorageException::class);

        JsonMigrator::currentVersion($this->dir);
    }

    public function testVersionOneCredentialIsDroppedAndUsersStartEmpty(): void
    {
        JsonMigrator::migrate($this->dir, array_slice(JsonMigrator::steps(), 0, 1, true));
        GuardedJson::write($this->dir . '/auth.php', ['password_hash' => 'h', 'hash_algo' => 'argon2id', 'created_at' => 1, 'password_changed_at' => 1]);
        GuardedJson::write($this->dir . '/config.php', ['k' => 'v']);
        self::assertSame(1, JsonMigrator::currentVersion($this->dir));

        JsonMigrator::migrate($this->dir);

        self::assertSame(Version::SCHEMA, JsonMigrator::currentVersion($this->dir));
        self::assertTrue((new JsonUserStore($this->dir . '/users.php'))->isEmpty());
        self::assertSame('v', (new JsonConfigStore($this->dir . '/config.php'))->get('k'));
        self::assertFileDoesNotExist($this->dir . '/auth.php');
    }

    public function testJsonStorageRunsTheMigratorBeforeHandingOutAStore(): void
    {
        (new JsonStorage($this->dir))->config();

        self::assertSame(Version::SCHEMA, JsonMigrator::currentVersion($this->dir));
    }

    public function testTheMigrationLockFileIsNamedAfterTheMigration(): void
    {
        JsonMigrator::migrate($this->dir, array_slice(JsonMigrator::steps(), 0, 1, true));

        self::assertFileExists($this->dir . '/migrate.lock.php');
        self::assertFileDoesNotExist($this->dir . '/migrate-lock.php.lock.php');
    }

    public function testAStepFailingWithAStorageExceptionNamesTheStep(): void
    {
        $steps = [
            1 => JsonMigrator::steps()[1],
            2 => function (): void {
                throw new StorageException('inner');
            },
        ];

        try {
            JsonMigrator::migrate($this->dir, $steps);
            self::fail('Expected StorageException');
        } catch (StorageException $e) {
            self::assertStringContainsString('schema version 2', $e->getMessage());
            self::assertInstanceOf(StorageException::class, $e->getPrevious());
        }
    }

    public function testStepThreeAddsLoginSessionsWithoutTouchingUsers(): void
    {
        JsonMigrator::migrate($this->dir, array_slice(JsonMigrator::steps(), 0, 2, true));
        self::assertSame(2, JsonMigrator::currentVersion($this->dir));
        $created = (new JsonUserStore($this->dir . '/users.php'))
            ->create('owner', Role::OWNER, new Credential('h', 'bcrypt', 1, 1));

        $storage = new JsonStorage($this->dir);

        $user = $storage->users()->findByLogin('owner');
        self::assertNotNull($user);
        self::assertSame($created->id, $user->id);

        $sessions = $storage->authSessions();
        $row = [
            'user_id' => $user->id, 'created_at' => 10, 'last_seen_at' => 11, 'expires_at' => 99,
            'ip' => '1.2.3.4', 'ua_hash' => 'abc',
        ];
        $sessions->put('tokenhash', $row);
        self::assertSame($row, $sessions->get('tokenhash'));
        self::assertSame(3, JsonMigrator::currentVersion($this->dir));
    }
}
