<?php

declare(strict_types=1);

namespace WPSafeMode\Tests\Api;

use PHPUnit\Framework\Assert;

/**
 * Drives one request through a copy of src/ in a separate PHP process, using
 * the copy's own bootstrap and autoloader. In-process tests load classes from
 * the repository and cannot see a class that is no longer loadable once the
 * copy has been moved or deleted.
 */
final class ShippedCopy
{
    /** Copies the repository's src/ (without its data/) to $to. */
    public static function copySrc(string $to): void
    {
        $src = dirname(__DIR__, 2) . '/src';
        self::copy($src, $to, $src);
    }

    private static function copy(string $from, string $to, string $root): void
    {
        mkdir($to, 0755, true);
        foreach (scandir($from) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..' || ($entry === 'data' && $from === $root)) {
                continue;
            }
            is_dir($from . '/' . $entry)
                ? self::copy($from . '/' . $entry, $to . '/' . $entry, $root)
                : copy($from . '/' . $entry, $to . '/' . $entry);
        }
    }

    public static function remove(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                $full = $path . '/' . $entry;
                is_dir($full) && !is_link($full) ? self::remove($full) : unlink($full);
            }
        }
        rmdir($path);
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, string> $server
     * @return array{status: int, body: array<string, mixed>}
     */
    public static function run(string $runner, string $appDir, string $uri, array $body, array $server): array
    {
        file_put_contents($runner, self::RUNNER);

        $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($runner) . ' '
            . escapeshellarg($appDir) . ' ' . escapeshellarg($uri) . ' '
            . escapeshellarg((string) json_encode($body)) . ' ' . escapeshellarg((string) json_encode($server))
            . ' 2>&1';
        $output = (string) shell_exec($cmd);
        $decoded = json_decode($output, true);

        Assert::assertIsArray($decoded, 'The subprocess did not answer with JSON: ' . $output);
        $inner = json_decode((string) $decoded['body'], true);
        Assert::assertIsArray($inner, 'The subprocess body was not JSON: ' . $output);

        return ['status' => (int) $decoded['status'], 'body' => $inner];
    }

    // The wrapper deliberately names no WPSafeMode\Core\Response and builds
    // nothing the handler needs before handle(), as src/index.php does. It
    // writes nothing to storage after handle() returns.
    private const RUNNER = <<<'PHP'
<?php
declare(strict_types=1);
$app = $argv[1];
require $app . '/bootstrap.php';

$dataDir = $app . '/data';
$env = \WPSafeMode\Core\Environment::probe();
$storage = \WPSafeMode\Storage\StorageFactory::open($dataDir, $env);
$hasher = new \WPSafeMode\Security\PasswordHasher();
$installer = new \WPSafeMode\Installer\Installer($storage, new \WPSafeMode\Security\KeyVault($dataDir), $hasher, $dataDir, $env);
$sessions = new \WPSafeMode\Security\AuthSessions($storage, $hasher);
$router = new \WPSafeMode\Core\Router();
\WPSafeMode\Api\Routes::register($router, $storage, $sessions, $installer, $app, $dataDir);
$request = new \WPSafeMode\Core\Request('POST', $argv[2], '/index.php', $argv[3], json_decode($argv[4], true));
$response = (new \WPSafeMode\Api\Kernel($router, $storage, $sessions, $installer))->handle($request, time());
echo json_encode(['status' => $response->status, 'body' => $response->body]);
PHP;
}
