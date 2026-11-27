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
 * The uninstall deletes the directory the autoloader reads from, so the answer
 * must be built from classes already loaded. Only a run from a copy of src/
 * in its own process can see a class that is no longer loadable.
 */
final class UninstallFromShippedCopyTest extends TestCase
{
    private const SECRET = 'abcdef0123456789';
    private const UA = 'UninstallFromShippedCopyTest/1.0';

    private string $base;
    private string $parent;
    private string $runnerDir;

    protected function setUp(): void
    {
        $this->base = sys_get_temp_dir() . '/wpsm-shipped-un-' . bin2hex(random_bytes(6));
        $this->parent = $this->base . '/site';
        $this->runnerDir = $this->base . '/runner';
        mkdir($this->parent, 0700, true);
        mkdir($this->runnerDir, 0700, true);
        ShippedCopy::copySrc($this->parent . '/wpsm-abc123');
    }

    protected function tearDown(): void
    {
        ShippedCopy::remove($this->base);
    }

    public function testTheUninstallAnswersFromACopyThatHasDeletedItself(): void
    {
        $app = $this->parent . '/wpsm-abc123';
        $dataDir = $app . '/data';
        $env = Environment::probe();
        $storage = StorageFactory::open($dataDir, $env);
        $hasher = new PasswordHasher();
        $installer = new Installer($storage, new KeyVault($dataDir), $hasher, $dataDir, $env);
        $storage->users()->create('owner', Role::OWNER, $hasher->hash('correct horse battery staple'));
        $storage->config()->set('installed_at', '1');
        $storage->config()->set('install_id', self::SECRET);
        $login = (new AuthSessions($storage, $hasher))->login('owner', 'correct horse battery staple', '203.0.113.1', self::UA, time());
        self::assertTrue($login->ok);
        $token = (string) $login->token;
        unset($installer);

        $result = ShippedCopy::run(
            $this->runnerDir . '/run.php',
            $app,
            '/api/uninstall',
            ['confirm' => 'DELETE'],
            [
                'REMOTE_ADDR' => '203.0.113.1',
                'HTTP_USER_AGENT' => self::UA,
                'HTTP_COOKIE' => AuthSessions::COOKIE . '=' . $token,
                'HTTP_X_WPSM_CSRF' => Csrf::issue($token, self::SECRET),
            ]
        );

        self::assertSame(200, $result['status']);
        self::assertSame(
            ['ok' => true, 'message' => 'WP Safe Mode has removed itself. This page will not load again.'],
            $result['body']
        );
        self::assertDirectoryDoesNotExist($app);
        self::assertFileExists($this->parent . '/WPSAFEMODE-REMOVED.txt');
        self::assertSame(['WPSAFEMODE-REMOVED.txt'], array_values(array_diff(scandir($this->parent) ?: [], ['.', '..'])));
    }
}
