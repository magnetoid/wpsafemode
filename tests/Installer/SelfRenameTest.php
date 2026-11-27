<?php

declare(strict_types=1);

namespace WPSafeMode\Tests\Installer;

use PHPUnit\Framework\TestCase;
use WPSafeMode\Installer\SelfRename;
use WPSafeMode\Storage\Contracts\StorageException;
use WPSafeMode\Tests\Support\ServedCopy;

final class SelfRenameTest extends TestCase
{
    private string $parent;

    protected function setUp(): void
    {
        $this->parent = sys_get_temp_dir() . '/wpsm-rename-' . bin2hex(random_bytes(6));
        mkdir($this->parent . '/wpsafemode/data', 0755, true);
        file_put_contents($this->parent . '/wpsafemode/index.php', '<?php');
        file_put_contents($this->parent . '/wpsafemode/bootstrap.php', '<?php');
    }

    protected function tearDown(): void
    {
        ServedCopy::remove($this->parent);
    }

    /** Renaming must refuse, with a fixed message, and leave everything where it was. */
    private function assertRefusedAndUnmoved(string $appDir): void
    {
        try {
            SelfRename::rename($appDir, 'wpsm-abc123abc123');
            self::fail('A folder that holds more than the tool was renamed.');
        } catch (StorageException $e) {
            self::assertStringNotContainsString($this->parent, $e->getMessage());
            self::assertStringNotContainsString('/', $e->getMessage());
        }

        self::assertDirectoryExists($appDir);
        self::assertFileExists($appDir . '/bootstrap.php');
        self::assertDirectoryDoesNotExist(dirname($appDir) . '/wpsm-abc123abc123');
    }

    public function testADocrootLikeFolderIsRefusedWithNothingMoved(): void
    {
        // The tool's files extracted flat into the site's own root.
        $docroot = $this->parent . '/public_html';
        mkdir($docroot . '/wp-includes', 0755, true);
        mkdir($docroot . '/wp-content/plugins', 0755, true);
        foreach (['index.php', 'bootstrap.php', 'autoload.php', 'wp-config.php', 'wp-load.php'] as $file) {
            file_put_contents($docroot . '/' . $file, '<?php');
        }

        $this->assertRefusedAndUnmoved($docroot);
        self::assertFileExists($docroot . '/wp-config.php');
    }

    public function testAFolderWithABlogBesideTheToolIsRefusedWithNothingMoved(): void
    {
        $appDir = $this->parent . '/wpsafemode';
        mkdir($appDir . '/blog', 0755, true);
        file_put_contents($appDir . '/blog/index.php', '<?php');

        $this->assertRefusedAndUnmoved($appDir);
        self::assertFileExists($appDir . '/blog/index.php');
    }

    public function testAFolderWithoutTheToolsBootstrapIsRefused(): void
    {
        $appDir = $this->parent . '/wpsafemode';
        unlink($appDir . '/bootstrap.php');

        try {
            SelfRename::rename($appDir, 'wpsm-abc123abc123');
            self::fail('A folder without the tool was renamed.');
        } catch (StorageException $e) {
            self::assertStringNotContainsString($this->parent, $e->getMessage());
        }

        self::assertDirectoryExists($appDir);
    }

    public function testDotfilesAndAnErrorLogDoNotBlockARename(): void
    {
        $appDir = $this->parent . '/wpsafemode';
        file_put_contents($appDir . '/.htaccess', 'Require all denied');
        file_put_contents($appDir . '/error_log', '');

        $new = SelfRename::rename($appDir, 'wpsm-abc123abc123');

        self::assertFileExists($new . '/.htaccess');
        self::assertFileExists($new . '/error_log');
    }

    public function testSuggestedNamesAreRandomAndWellFormed(): void
    {
        $a = SelfRename::suggest();
        $b = SelfRename::suggest();

        self::assertNotSame($a, $b);
        self::assertMatchesRegularExpression('/^wpsm-[0-9a-f]{12}$/', $a);
        self::assertTrue(SelfRename::isSafeName($a));
    }

    public function testRenamingMovesTheDirectory(): void
    {
        $new = SelfRename::rename($this->parent . '/wpsafemode', 'wpsm-abc123abc123');

        self::assertSame($this->parent . '/wpsm-abc123abc123', $new);
        self::assertDirectoryExists($new);
        self::assertFileExists($new . '/index.php');
        self::assertDirectoryDoesNotExist($this->parent . '/wpsafemode');
    }

    public function testTraversalAndSeparatorsAreRejected(): void
    {
        foreach (['../escape', 'a/b', 'a\\b', '..', '.', '', 'has space', "wpsm-abc123\n"] as $bad) {
            self::assertFalse(SelfRename::isSafeName($bad), $bad . ' should be rejected');
        }
    }

    public function testRenamingToAnUnsafeNameThrows(): void
    {
        $this->expectException(StorageException::class);
        SelfRename::rename($this->parent . '/wpsafemode', '../escape');
    }

    public function testRenamingOntoAnExistingDirectoryThrows(): void
    {
        mkdir($this->parent . '/wpsm-occupied00', 0755, true);

        $this->expectException(StorageException::class);
        SelfRename::rename($this->parent . '/wpsafemode', 'wpsm-occupied00');
    }
}
