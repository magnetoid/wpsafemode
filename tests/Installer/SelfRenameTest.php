<?php

declare(strict_types=1);

namespace WPSafeMode\Tests\Installer;

use PHPUnit\Framework\TestCase;
use WPSafeMode\Installer\SelfRename;
use WPSafeMode\Storage\Contracts\StorageException;

final class SelfRenameTest extends TestCase
{
    private string $parent;

    protected function setUp(): void
    {
        $this->parent = sys_get_temp_dir() . '/wpsm-rename-' . bin2hex(random_bytes(6));
        mkdir($this->parent . '/wpsafemode', 0755, true);
        file_put_contents($this->parent . '/wpsafemode/index.php', '<?php');
    }

    protected function tearDown(): void
    {
        foreach (glob($this->parent . '/*/*') ?: [] as $file) {
            unlink($file);
        }
        foreach (glob($this->parent . '/*') ?: [] as $dir) {
            rmdir($dir);
        }
        @rmdir($this->parent);
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
        foreach (['../escape', 'a/b', 'a\\b', '..', '.', '', 'has space'] as $bad) {
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
