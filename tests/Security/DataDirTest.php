<?php

declare(strict_types=1);

namespace WPSafeMode\Tests\Security;

use PHPUnit\Framework\TestCase;
use WPSafeMode\Security\DataDir;

final class DataDirTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/wpsm-datadir-' . bin2hex(random_bytes(6)) . '/data';
    }

    protected function tearDown(): void
    {
        foreach (glob(dirname($this->dir) . '/data/*') ?: [] as $file) {
            unlink($file);
        }
        @rmdir($this->dir);
        @rmdir(dirname($this->dir));
    }

    public function testEnsureCreatesThePrivateDirectoryAndBothBlockers(): void
    {
        DataDir::ensure($this->dir);

        self::assertDirectoryExists($this->dir);
        self::assertSame('0700', substr(sprintf('%o', fileperms($this->dir)), -4));
        self::assertFileExists($this->dir . '/.htaccess');
        self::assertFileExists($this->dir . '/index.php');
    }

    public function testHtaccessDeniesOnBothApacheGenerations(): void
    {
        DataDir::ensure($this->dir);
        $htaccess = (string) file_get_contents($this->dir . '/.htaccess');

        // 2.4 syntax and the 2.2 fallback both matter: shared hosts run either,
        // and an unrecognised directive is a 500, not a silent no-op.
        self::assertStringContainsString('Require all denied', $htaccess);
        self::assertStringContainsString('Deny from all', $htaccess);
    }

    public function testEnsureIsIdempotentAndDoesNotClobberExistingState(): void
    {
        DataDir::ensure($this->dir);
        file_put_contents($this->dir . '/config.php', 'sentinel');

        DataDir::ensure($this->dir);

        self::assertSame('sentinel', file_get_contents($this->dir . '/config.php'));
    }

    public function testServableExtensionsAreRecognised(): void
    {
        self::assertTrue(DataDir::isServableExtension('data/config.json'));
        self::assertTrue(DataDir::isServableExtension('data/messages.jsonl'));
        self::assertTrue(DataDir::isServableExtension('data/store.sqlite'));
        self::assertTrue(DataDir::isServableExtension('data/keyvault'));
        self::assertFalse(DataDir::isServableExtension('data/config.php'));
        self::assertFalse(DataDir::isServableExtension('data/index.php'));
    }

    public function testNoFileTheApplicationWritesIsServableAsAStaticAsset(): void
    {
        // The whole point of spec 15.1: every filename the storage layer and the
        // security layer produce must be guarded or unguessable, so this list is
        // the contract. A new state file added without a .php extension fails here.
        $written = [
            'data/install.php',
            'data/config.php',
            'data/auth.php',
            'data/auth_sessions.php',
            'data/rate_limit.php',
            'data/audit_log.php',
            'data/keyvault.php',
            'data/sessions/sesn_1/session.php',
            'data/sessions/sesn_1/messages.php',
        ];

        foreach ($written as $path) {
            self::assertFalse(
                DataDir::isServableExtension($path),
                $path . ' would be served as a static asset'
            );
        }
    }
}
