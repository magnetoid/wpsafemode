<?php

declare(strict_types=1);

namespace WPSafeMode\Tests\Storage;

use PDO;
use PHPUnit\Framework\TestCase;
use WPSafeMode\Storage\AuditEventRow;
use WPSafeMode\Storage\AuthSessionRow;
use WPSafeMode\Storage\Contracts\StorageException;
use WPSafeMode\Storage\Json\JsonMessageLog;
use WPSafeMode\Storage\Json\JsonSessionRepo;
use WPSafeMode\Storage\Sqlite\SqliteMigrator;
use WPSafeMode\Storage\User;
use WPSafeMode\Tests\Support\ServedCopy;

/**
 * In PCRE, `$` also matches before a final "\n", so '/^\d+$/' accepts "5\n".
 * Every pattern that validates stored or supplied data must end in `\z`.
 */
final class TrailingNewlineTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/wpsm-newline-' . bin2hex(random_bytes(6));
        mkdir($this->dir, 0700, true);
    }

    protected function tearDown(): void
    {
        ServedCopy::remove($this->dir);
    }

    public function testALoginWithATrailingNewlineIsNotValid(): void
    {
        self::assertTrue(User::isValidLogin('owner'));
        self::assertFalse(User::isValidLogin("owner\n"));
    }

    public function testASessionRowWithATrailingNewlineInAnIntegerIsRefused(): void
    {
        $row = [
            'user_id' => 1, 'created_at' => 1000, 'last_seen_at' => 1000, 'expires_at' => 5000,
            'ip' => '203.0.113.9', 'ua_hash' => 'abc',
        ];
        self::assertSame(1, AuthSessionRow::validate(['user_id' => '1'] + $row)['user_id']);

        $this->expectException(StorageException::class);
        AuthSessionRow::validate(['user_id' => "1\n"] + $row);
    }

    public function testAnAuditRowWithATrailingNewlineInItsTimeIsRefused(): void
    {
        $row = ['actor' => 'user:1', 'event' => 'login', 'subject' => null, 'ip' => null];
        self::assertSame(1000, AuditEventRow::hydrate(['at' => '1000'] + $row, [])->at);

        $this->expectException(StorageException::class);
        AuditEventRow::hydrate(['at' => "1000\n"] + $row, []);
    }

    public function testASchemaVersionWithATrailingNewlineIsRefused(): void
    {
        $pdo = new PDO('sqlite:' . $this->dir . '/wpsm.sqlite', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        SqliteMigrator::migrate($pdo);
        $pdo->exec("UPDATE meta SET value = value || char(10) WHERE key = 'schema_version'");

        $this->expectException(StorageException::class);
        SqliteMigrator::migrate($pdo);
    }

    public function testAJsonSessionIdWithATrailingNewlineIsRefused(): void
    {
        foreach ([
            'session repo' => fn (string $id) => (new JsonSessionRepo($this->dir . '/sessions'))->find($id),
            'message log'  => fn (string $id) => (new JsonMessageLog($this->dir . '/sessions'))->count($id),
        ] as $name => $call) {
            $call('sesn_1');

            try {
                $call("sesn_1\n");
                self::fail($name . ' accepted a session id with a trailing newline');
            } catch (StorageException $e) {
                self::assertStringContainsString('Illegal session id', $e->getMessage());
            }
        }
    }
}
