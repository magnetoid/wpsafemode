<?php

declare(strict_types=1);

namespace WPSafeMode\Tests\Core;

use PHPUnit\Framework\TestCase;
use WPSafeMode\Core\Environment;
use WPSafeMode\Tests\Support\ServedCopy;

final class EnvironmentTest extends TestCase
{
    /** @var list<string> */
    private array $made = [];

    protected function tearDown(): void
    {
        foreach ($this->made as $dir) {
            ServedCopy::remove($dir);
        }
    }

    private function tempDir(): string
    {
        return $this->made[] = sys_get_temp_dir() . '/wpsm-env-' . bin2hex(random_bytes(4));
    }

    public function testProbeReportsRunningPhpVersionAsSatisfied(): void
    {
        $report = Environment::probe();

        self::assertSame(PHP_VERSION, $report->phpVersion);
        self::assertTrue($report->phpSatisfied, 'Container must run PHP >= 8.0.0');
    }

    public function testProbeDetectsSqliteInTheDevContainer(): void
    {
        self::assertTrue(Environment::probe()->hasPdoSqlite);
    }

    public function testWpRootIsNullWhenNoWordPressAbove(): void
    {
        $dir = $this->tempDir() . '/nested';
        mkdir($dir, 0777, true);

        self::assertNull(Environment::probe($dir)->wpRoot);
    }

    public function testWpRootIsFoundInParentDirectory(): void
    {
        $base = $this->tempDir();
        mkdir($base . '/wp-includes', 0777, true);
        mkdir($base . '/wpsm-abc123', 0777, true);
        file_put_contents($base . '/wp-config.php', "<?php\n");
        file_put_contents($base . '/wp-includes/version.php', "<?php\n\$wp_version = '6.4';\n");

        self::assertSame(realpath($base), Environment::probe($base . '/wpsm-abc123')->wpRoot);
    }

    public function testToArrayIsJsonSerialisableAndLeaksNoPaths(): void
    {
        $data = Environment::probe()->toArray();

        self::assertArrayHasKey('php_version', $data);
        self::assertArrayHasKey('has_pdo_sqlite', $data);
        self::assertArrayNotHasKey('wp_root', $data, 'Absolute paths must not reach the browser');
        self::assertIsString(json_encode($data, JSON_THROW_ON_ERROR));
    }
}
