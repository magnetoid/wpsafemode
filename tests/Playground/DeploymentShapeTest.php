<?php

declare(strict_types=1);

namespace WPSafeMode\Tests\Playground;

use PHPUnit\Framework\TestCase;
use WPSafeMode\Core\Environment;

/**
 * Asserts the real deployment shape: the application directory sits beside
 * wp-admin, so WordPress must be discoverable one level up. This is the only
 * test that depends on the compose mount.
 */
final class DeploymentShapeTest extends TestCase
{
    private const WP_ROOT = '/var/www/html';

    protected function setUp(): void
    {
        if (!is_dir(self::WP_ROOT . '/wp-includes')) {
            self::markTestSkipped('Run inside the wordpress container: make up, then see Step 4.');
        }
    }

    public function testApplicationIsMountedBesideWordPress(): void
    {
        self::assertDirectoryExists(self::WP_ROOT . '/wpsafemode');
        self::assertFileExists(self::WP_ROOT . '/wpsafemode/index.php');
    }

    public function testWordPressRootIsDetectedFromTheInstallDirectory(): void
    {
        $report = Environment::probe(self::WP_ROOT . '/wpsafemode');

        self::assertSame(self::WP_ROOT, $report->wpRoot);
    }
}
