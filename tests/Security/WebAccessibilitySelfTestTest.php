<?php

declare(strict_types=1);

namespace WPSafeMode\Tests\Security;

use PHPUnit\Framework\TestCase;
use WPSafeMode\Security\SelfTestResult;
use WPSafeMode\Security\WebAccessibilitySelfTest;

final class WebAccessibilitySelfTestTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/wpsm-selftest-' . bin2hex(random_bytes(6));
        mkdir($this->dir, 0700, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->dir);
    }

    public function testAnUnreachableUrlReportsNotReachable(): void
    {
        // Port 1 on localhost refuses immediately: a connection failure is the
        // same shape as a correctly denied directory.
        $result = WebAccessibilitySelfTest::run($this->dir, 'http://127.0.0.1:1/wpsm');

        self::assertFalse($result->reachable);
        self::assertFalse($result->blocksApiKey());
    }

    public function testTheCanaryIsAlwaysCleanedUp(): void
    {
        WebAccessibilitySelfTest::run($this->dir, 'http://127.0.0.1:1/wpsm');

        $leftovers = array_filter(
            glob($this->dir . '/*') ?: [],
            static fn (string $p): bool => strpos(basename($p), 'canary') === 0
        );

        self::assertSame([], $leftovers, 'A canary file was left in the data directory');
    }

    public function testAReachableCanaryReportsReachableAndBlocksTheApiKey(): void
    {
        $server = getenv('WPSM_SELFTEST_URL');

        if ($server === false || $server === '') {
            self::markTestSkipped('Set WPSM_SELFTEST_URL (and WPSM_SELFTEST_DATA_DIR) to a base URL serving the data dir; see Step 4.');
        }

        // The canary must be written where the server actually serves /data/.
        $served = getenv('WPSM_SELFTEST_DATA_DIR');
        $dataDir = ($served === false || $served === '') ? $this->dir : $served;

        $result = WebAccessibilitySelfTest::run($dataDir, $server);

        self::assertTrue($result->reachable);
        self::assertTrue($result->blocksApiKey());
    }

    public function testAnInconclusiveResultAlsoBlocksTheApiKey(): void
    {
        // "We could not check" must fail closed. An installer that stores the
        // key when the check did not run is the v1.1 failure with extra steps.
        $result = new SelfTestResult(false, true, 'cURL is unavailable', null, 1000);

        self::assertTrue($result->blocksApiKey());
    }

    public function testBaseUrlIsDerivedFromTheRequestWithoutTheScriptName(): void
    {
        $url = WebAccessibilitySelfTest::baseUrlFrom([
            'HTTPS'       => 'on',
            'HTTP_HOST'   => 'example.com',
            'SCRIPT_NAME' => '/wpsm-a1b2c3/index.php',
        ]);

        self::assertSame('https://example.com/wpsm-a1b2c3', $url);
    }

    public function testBaseUrlIsNullWhenTheHostIsUnknown(): void
    {
        self::assertNull(WebAccessibilitySelfTest::baseUrlFrom(['SCRIPT_NAME' => '/index.php']));
    }
}
