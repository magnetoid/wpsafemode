<?php

declare(strict_types=1);

namespace WPSafeMode\Tests\Storage;

use PHPUnit\Framework\TestCase;
use WPSafeMode\Core\Version;
use WPSafeMode\Security\GuardedJson;
use WPSafeMode\Storage\Contracts\StorageException;
use WPSafeMode\Storage\Json\JsonConfigStore;
use WPSafeMode\Storage\Json\JsonMigrator;

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
}
