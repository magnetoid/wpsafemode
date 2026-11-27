<?php

declare(strict_types=1);

namespace WPSafeMode\Tests\Api;

use PHPUnit\Framework\TestCase;
use WPSafeMode\Core\Environment;
use WPSafeMode\Installer\Installer;
use WPSafeMode\Security\AuthSessions;
use WPSafeMode\Security\Csrf;
use WPSafeMode\Security\KeyVault;
use WPSafeMode\Security\PasswordHasher;
use WPSafeMode\Security\Role;
use WPSafeMode\Storage\StorageFactory;

/**
 * Runs the shipped code from a copy of src/ in a separate PHP process, because
 * the rename moves the directory the autoloader reads from. In-process tests
 * load classes from the repository and cannot see a class that is no longer
 * loadable after the move.
 */
final class RenameFromShippedCopyTest extends TestCase
{
    private const SECRET = 'abcdef0123456789';
    private const UA = 'RenameFromShippedCopyTest/1.0';

    private string $parent;
    private string $runner;

    protected function setUp(): void
    {
        $this->parent = sys_get_temp_dir() . '/wpsm-shipped-' . bin2hex(random_bytes(6));
        mkdir($this->parent, 0700, true);
        $this->runner = $this->parent . '/run.php';
        ShippedCopy::copySrc($this->parent . '/wpsafemode');
    }

    protected function tearDown(): void
    {
        ShippedCopy::remove($this->parent);
    }

    /** @return array{0: \WPSafeMode\Storage\Contracts\Storage, 1: Installer, 2: AuthSessions} */
    private function seeded(string $backend): array
    {
        $dataDir = $this->parent . '/wpsafemode/data';
        ShippedCopy::seedBackend($dataDir, $backend);
        $env = Environment::probe();
        $storage = StorageFactory::open($dataDir, $env);
        self::assertSame($backend, $storage->backend());
        $hasher = new PasswordHasher();

        return [
            $storage,
            new Installer($storage, new KeyVault($dataDir), $hasher, $dataDir, $env),
            new AuthSessions($storage, $hasher),
        ];
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, string> $server
     * @return array{status: int, body: array<string, mixed>}
     */
    private function runCopy(string $uri, array $body, array $server): array
    {
        return ShippedCopy::run($this->runner, $this->parent . '/wpsafemode', $uri, $body, $server);
    }

    private function assertMoved(string $redirect): void
    {
        self::assertSame(1, preg_match('#^\.\./(wpsm-[0-9a-f]{12})/$#', $redirect, $m));
        self::assertDirectoryExists($this->parent . '/' . $m[1]);
        self::assertDirectoryDoesNotExist($this->parent . '/wpsafemode');
    }

    /** @return array<string, array{0: string}> */
    public static function backends(): array
    {
        return ShippedCopy::backends();
    }

    /** @dataProvider backends */
    public function testTheInstallerRenameAnswersFromAMovedCopy(string $backend): void
    {
        [, $installer] = $this->seeded($backend);
        $token = $installer->issueToken();

        $result = $this->runCopy('/api/install/rename', ['token' => $token], ['REMOTE_ADDR' => '203.0.113.1', 'HTTP_USER_AGENT' => self::UA]);

        self::assertSame(200, $result['status']);
        self::assertTrue($result['body']['ok']);
        $this->assertMoved((string) $result['body']['redirect']);
    }

    /** @dataProvider backends */
    public function testTheOwnerRenameAnswersFromAMovedCopy(string $backend): void
    {
        [$storage, , $sessions] = $this->seeded($backend);
        $storage->users()->create('owner', Role::OWNER, (new PasswordHasher())->hash('correct horse battery staple'));
        $storage->config()->set('installed_at', '1');
        $storage->config()->set('install_id', self::SECRET);
        $login = $sessions->login('owner', 'correct horse battery staple', '203.0.113.1', self::UA, time());
        self::assertTrue($login->ok);
        $token = (string) $login->token;

        $result = $this->runCopy('/api/admin/rename', [], [
            'REMOTE_ADDR' => '203.0.113.1',
            'HTTP_USER_AGENT' => self::UA,
            'HTTP_COOKIE' => AuthSessions::COOKIE . '=' . $token,
            'HTTP_X_WPSM_CSRF' => Csrf::issue($token, self::SECRET),
        ]);

        self::assertSame(200, $result['status']);
        self::assertTrue($result['body']['sign_in_again']);
        $this->assertMoved((string) $result['body']['redirect']);
    }
}
