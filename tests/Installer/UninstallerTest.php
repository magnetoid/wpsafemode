<?php

declare(strict_types=1);

namespace WPSafeMode\Tests\Installer;

use PHPUnit\Framework\TestCase;
use WPSafeMode\Installer\InstallLayout;
use WPSafeMode\Installer\Uninstaller;
use WPSafeMode\Storage\Contracts\StorageException;

final class UninstallerTest extends TestCase
{
    private string $parent;
    private string $appDir;
    private string $dataDir;

    protected function setUp(): void
    {
        $this->parent = sys_get_temp_dir() . '/wpsm-uninstall-' . bin2hex(random_bytes(6));
        $this->appDir = $this->parent . '/wpsm-abc123';
        $this->dataDir = $this->appDir . '/data';

        mkdir($this->appDir . '/Security', 0755, true);
        mkdir($this->dataDir . '/sessions/sesn_1', 0700, true);

        file_put_contents($this->appDir . '/index.php', '<?php');
        file_put_contents($this->appDir . '/bootstrap.php', '<?php');
        file_put_contents($this->appDir . '/Security/KeyVault.php', '<?php');
        file_put_contents($this->dataDir . '/config.php', '<?php // api key');
        file_put_contents($this->dataDir . '/keyvault.php', '<?php // master secret');
        file_put_contents($this->dataDir . '/sessions/sesn_1/session.php', '<?php');
    }

    protected function tearDown(): void
    {
        $this->restorePermissions($this->parent);
        $this->removeRecursively($this->parent);
    }

    private function restorePermissions(string $path): void
    {
        if (!is_dir($path) || is_link($path)) {
            return;
        }
        @chmod($path, 0755);
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                $this->restorePermissions($path . '/' . $entry);
            }
        }
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

    public function testEverySecretIsGone(): void
    {
        Uninstaller::run($this->appDir, $this->dataDir);

        self::assertDirectoryDoesNotExist($this->dataDir);
        self::assertFileDoesNotExist($this->dataDir . '/keyvault.php');
        self::assertFileDoesNotExist($this->dataDir . '/config.php');
    }

    public function testTheApplicationDirectoryIsGone(): void
    {
        Uninstaller::run($this->appDir, $this->dataDir);

        self::assertDirectoryDoesNotExist($this->appDir);
    }

    public function testAConfirmationFileIsLeftBehind(): void
    {
        $receipt = Uninstaller::run($this->appDir, $this->dataDir);

        self::assertFileExists($receipt);
        self::assertSame(realpath($this->parent) . '/' . Uninstaller::RECEIPT, $receipt);
        self::assertStringContainsString('WP Safe Mode', (string) file_get_contents($receipt));
    }

    public function testTheConfirmationFileHoldsNoSecrets(): void
    {
        $receipt = Uninstaller::run($this->appDir, $this->dataDir);
        $contents = (string) file_get_contents($receipt);

        foreach (['api key', 'master secret', 'sk-ant'] as $needle) {
            self::assertStringNotContainsString($needle, $contents);
        }
    }

    public function testItDoesNotTouchSiblingDirectories(): void
    {
        mkdir($this->parent . '/wp-content', 0755, true);
        file_put_contents($this->parent . '/wp-content/keep.php', 'keep me');

        Uninstaller::run($this->appDir, $this->dataDir);

        self::assertFileExists($this->parent . '/wp-content/keep.php');
    }

    public function testADataDirectoryOutsideTheAppDirectoryIsRefused(): void
    {
        $outside = $this->parent . '/data';
        mkdir($outside, 0700, true);
        file_put_contents($outside . '/keyvault.php', '<?php // master secret');

        try {
            Uninstaller::run($this->appDir, $outside);
            self::fail('A data directory outside the app directory must be refused.');
        } catch (StorageException $e) {
            self::assertStringNotContainsString($this->parent, $e->getMessage());
        }

        self::assertFileExists($outside . '/keyvault.php');
        self::assertFileExists($this->appDir . '/index.php');
        self::assertFileExists($this->dataDir . '/keyvault.php');
        self::assertFileDoesNotExist($this->parent . '/' . Uninstaller::RECEIPT);
    }

    public function testPathsThatDoNotExistAreRefused(): void
    {
        $this->expectException(StorageException::class);

        try {
            Uninstaller::run($this->appDir, $this->dataDir . '/missing');
        } finally {
            self::assertFileExists($this->appDir . '/index.php');
        }
    }

    public function testAnIncompleteRemovalIsReported(): void
    {
        if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
            self::markTestSkipped('Root ignores directory permissions.');
        }

        chmod($this->dataDir . '/sessions/sesn_1', 0555);

        try {
            Uninstaller::run($this->appDir, $this->dataDir);
            self::fail('A removal that left files behind must not be reported as done.');
        } catch (StorageException $e) {
            self::assertStringNotContainsString($this->parent, $e->getMessage());
        }

        self::assertFileExists($this->dataDir . '/sessions/sesn_1/session.php');
        self::assertFileDoesNotExist($this->parent . '/' . Uninstaller::RECEIPT);
    }

    public function testAnAppDirectoryThatIsTheWordPressRootIsRefused(): void
    {
        file_put_contents($this->appDir . '/wp-config.php', '<?php');

        try {
            Uninstaller::run($this->appDir, $this->dataDir);
            self::fail('A WordPress root must never be removed.');
        } catch (StorageException $e) {
            self::assertStringNotContainsString($this->parent, $e->getMessage());
        }

        self::assertFileExists($this->appDir . '/wp-config.php');
        self::assertFileExists($this->appDir . '/bootstrap.php');
        self::assertFileExists($this->dataDir . '/keyvault.php');
        self::assertFileDoesNotExist($this->parent . '/' . Uninstaller::RECEIPT);
    }

    public function testAnAppDirectoryWithoutTheToolsBootstrapIsRefused(): void
    {
        unlink($this->appDir . '/bootstrap.php');

        try {
            Uninstaller::run($this->appDir, $this->dataDir);
            self::fail('A folder that is not the tool must never be removed.');
        } catch (StorageException $e) {
            self::assertStringNotContainsString($this->parent, $e->getMessage());
        }

        self::assertFileExists($this->appDir . '/index.php');
        self::assertFileExists($this->dataDir . '/keyvault.php');
    }

    public function testEverythingTheToolShipsIsListedAsShipped(): void
    {
        foreach (scandir(dirname(__DIR__, 2) . '/src') ?: [] as $entry) {
            if ($entry === '.' || $entry === '..' || $entry[0] === '.' || $entry === 'data') {
                continue;
            }

            self::assertContains($entry, InstallLayout::SHIPPED, 'Add this to InstallLayout::SHIPPED: ' . $entry);
        }
    }

    public function testAFolderHoldingAWordPressSubfolderIsRefused(): void
    {
        mkdir($this->appDir . '/blog', 0755, true);
        file_put_contents($this->appDir . '/blog/wp-config.php', '<?php');

        $this->assertRefusedAndIntact();
        self::assertFileExists($this->appDir . '/blog/wp-config.php');
    }

    public function testAFolderThatLooksLikeWpContentIsRefused(): void
    {
        mkdir($this->appDir . '/uploads', 0755, true);
        mkdir($this->appDir . '/plugins', 0755, true);
        file_put_contents($this->appDir . '/uploads/photo.jpg', 'x');

        $this->assertRefusedAndIntact();
        self::assertFileExists($this->appDir . '/uploads/photo.jpg');
    }

    private function assertRefusedAndIntact(): void
    {
        try {
            Uninstaller::run($this->appDir, $this->dataDir);
            self::fail('A folder holding other content must be refused.');
        } catch (StorageException $e) {
            self::assertStringNotContainsString($this->parent, $e->getMessage());
        }

        self::assertFileExists($this->appDir . '/index.php');
        self::assertFileExists($this->dataDir . '/keyvault.php');
        self::assertFileDoesNotExist($this->parent . '/' . Uninstaller::RECEIPT);
    }

    public function testAnErrorLogAndDotfilesBesideTheToolAreRemovedWithIt(): void
    {
        file_put_contents($this->appDir . '/error_log', 'log');
        file_put_contents($this->appDir . '/.htaccess', 'x');

        Uninstaller::run($this->appDir, $this->dataDir);

        self::assertDirectoryDoesNotExist($this->appDir);
    }

    public function testAFailedApplicationRemovalIsReportedWithoutAReceipt(): void
    {
        if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
            self::markTestSkipped('Root ignores directory permissions.');
        }

        chmod($this->appDir . '/Security', 0555);

        try {
            Uninstaller::run($this->appDir, $this->dataDir);
            self::fail('A removal that left the tool behind must not be reported as done.');
        } catch (StorageException $e) {
            self::assertStringNotContainsString($this->parent, $e->getMessage());
        }

        self::assertDirectoryDoesNotExist($this->dataDir);
        self::assertFileExists($this->appDir . '/Security/KeyVault.php');
        self::assertFileDoesNotExist($this->parent . '/' . Uninstaller::RECEIPT);
    }

    public function testItNeverEmitsAPathEvenWhenPhpWarns(): void
    {
        $base = sys_get_temp_dir() . '/wpsm-uninstall-bd-' . bin2hex(random_bytes(6));
        mkdir($base . '/runner', 0700, true);
        $app = $base . '/wpsm-abc123';
        mkdir($app . '/data', 0700, true);
        file_put_contents($app . '/bootstrap.php', '<?php');
        file_put_contents($app . '/data/keyvault.php', '<?php');
        $runner = $base . '/runner/run.php';
        file_put_contents($runner, '<?php
define("WPSM_ENTRY", true);
require $argv[1] . "/autoload.php";
\\WPSafeMode\\Installer\\Uninstaller::run($argv[2], $argv[2] . "/data");
echo "finished";
');
        $src = dirname(__DIR__, 2) . '/src';

        $cmd = escapeshellarg(PHP_BINARY) . ' -d display_errors=1 -d error_reporting=-1'
            . ' -d open_basedir=' . escapeshellarg((string) realpath($app) . ':' . (string) realpath($base . '/runner') . ':' . $src)
            . ' ' . escapeshellarg($runner) . ' ' . escapeshellarg($src) . ' ' . escapeshellarg($app) . ' 2>&1';
        $output = (string) shell_exec($cmd);

        try {
            self::assertStringContainsString('finished', $output);
            self::assertSame(0, preg_match('#/[A-Za-z0-9_.-]+/#', $output), 'Output carried a path: ' . $output);
        } finally {
            $this->restorePermissions($base);
            $this->removeRecursively($base);
        }
    }
}
