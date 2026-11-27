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
 * Runs the shipped code from a copy of src/ in a separate PHP process, because
 * the rename moves the directory the autoloader reads from. In-process tests
 * load classes from the repository and cannot see a class that is no longer
 * loadable after the move.
 */
final class RenameFromShippedCopyTest extends TestCase
{
    private const SECRET = 'abcdef0123456789';
    private const UA = 'RenameFromShippedCopyTest/1.0';

    private string $parent;
    private string $runner;

    protected function setUp(): void
    {
        $this->parent = sys_get_temp_dir() . '/wpsm-shipped-' . bin2hex(random_bytes(6));
        mkdir($this->parent, 0700, true);
        $this->runner = $this->parent . '/run.php';
        $this->copy(dirname(__DIR__, 2) . '/src', $this->parent . '/wpsafemode');
        file_put_contents($this->runner, self::RUNNER);
    }

    protected function tearDown(): void
    {
        $this->remove($this->parent);
    }

    private function copy(string $from, string $to): void
    {
        mkdir($to, 0755, true);
        foreach (scandir($from) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..' || ($entry === 'data' && $from === dirname(__DIR__, 2) . '/src')) {
                continue;
            }
            is_dir($from . '/' . $entry)
                ? $this->copy($from . '/' . $entry, $to . '/' . $entry)
                : copy($from . '/' . $entry, $to . '/' . $entry);
        }
    }

    private function remove(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                $full = $path . '/' . $entry;
                is_dir($full) && !is_link($full) ? $this->remove($full) : unlink($full);
            }
        }
        rmdir($path);
    }

    /** @return array{0: \WPSafeMode\Storage\Contracts\Storage, 1: Installer, 2: AuthSessions} */
    private function seeded(): array
    {
        $dataDir = $this->parent . '/wpsafemode/data';
        $env = Environment::probe();
        $storage = StorageFactory::open($dataDir, $env);
        $hasher = new PasswordHasher();

        return [
            $storage,
            new Installer($storage, new KeyVault($dataDir), $hasher, $dataDir, $env),
            new AuthSessions($storage, $hasher),
        ];
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, string> $server
     * @return array{status: int, body: array<string, mixed>}
     */
    private function runCopy(string $uri, array $body, array $server): array
    {
        $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($this->runner) . ' '
            . escapeshellarg($this->parent . '/wpsafemode') . ' ' . escapeshellarg($uri) . ' '
            . escapeshellarg((string) json_encode($body)) . ' ' . escapeshellarg((string) json_encode($server))
            . ' 2>&1';
        $output = (string) shell_exec($cmd);
        $decoded = json_decode($output, true);

        self::assertIsArray($decoded, 'The subprocess did not answer with JSON: ' . $output);
        $inner = json_decode((string) $decoded['body'], true);
        self::assertIsArray($inner);

        return ['status' => (int) $decoded['status'], 'body' => $inner];
    }

    private function assertMoved(string $redirect): void
    {
        self::assertSame(1, preg_match('#^\.\./(wpsm-[0-9a-f]{12})/$#', $redirect, $m));
        self::assertDirectoryExists($this->parent . '/' . $m[1]);
        self::assertDirectoryDoesNotExist($this->parent . '/wpsafemode');
    }

    public function testTheInstallerRenameAnswersFromAMovedCopy(): void
    {
        [, $installer] = $this->seeded();
        $token = $installer->issueToken();

        $result = $this->runCopy('/api/install/rename', ['token' => $token], ['REMOTE_ADDR' => '203.0.113.1', 'HTTP_USER_AGENT' => self::UA]);

        self::assertSame(200, $result['status']);
        self::assertTrue($result['body']['ok']);
        $this->assertMoved((string) $result['body']['redirect']);
    }

    public function testTheOwnerRenameAnswersFromAMovedCopy(): void
    {
        [$storage, , $sessions] = $this->seeded();
        $storage->users()->create('owner', Role::OWNER, (new PasswordHasher())->hash('correct horse battery staple'));
        $storage->config()->set('installed_at', '1');
        $storage->config()->set('install_id', self::SECRET);
        $login = $sessions->login('owner', 'correct horse battery staple', '203.0.113.1', self::UA, time());
        self::assertTrue($login->ok);
        $token = (string) $login->token;

        $result = $this->runCopy('/api/admin/rename', [], [
            'REMOTE_ADDR' => '203.0.113.1',
            'HTTP_USER_AGENT' => self::UA,
            'HTTP_COOKIE' => AuthSessions::COOKIE . '=' . $token,
            'HTTP_X_WPSM_CSRF' => Csrf::issue($token, self::SECRET),
        ]);

        self::assertSame(200, $result['status']);
        self::assertTrue($result['body']['sign_in_again']);
        $this->assertMoved((string) $result['body']['redirect']);
    }

    // The wrapper deliberately names no WPSafeMode\Core\Response and builds
    // nothing the handler needs before handle(), as src/index.php does.
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
