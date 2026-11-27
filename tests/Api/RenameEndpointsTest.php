<?php

declare(strict_types=1);

namespace WPSafeMode\Tests\Api;

use PHPUnit\Framework\TestCase;
use WPSafeMode\Api\Kernel;
use WPSafeMode\Api\Routes;
use WPSafeMode\Core\Environment;
use WPSafeMode\Core\Request;
use WPSafeMode\Core\Response;
use WPSafeMode\Core\Router;
use WPSafeMode\Installer\Installer;
use WPSafeMode\Security\AuthSessions;
use WPSafeMode\Security\Csrf;
use WPSafeMode\Security\KeyVault;
use WPSafeMode\Security\PasswordHasher;
use WPSafeMode\Security\Role;
use WPSafeMode\Storage\Contracts\Storage;
use WPSafeMode\Storage\StorageFactory;
use WPSafeMode\Tests\Support\FakeFetcher;

/**
 * Every test renames a directory under the system temp dir; none ever points
 * a controller at the real checkout.
 */
final class RenameEndpointsTest extends TestCase
{
    private const PASSWORD = 'correct horse battery staple';
    private const UA = 'RenameEndpointsTest/1.0';
    private const NOW = 1_000_000;
    private const SECRET = 'abcdef0123456789';
    private const NAME = 'wpsm-abc123abc123';

    private string $parent;
    private string $appDir;
    private string $dataDir;
    private Storage $storage;
    private AuthSessions $sessions;
    private Installer $installer;
    private Kernel $kernel;

    protected function setUp(): void
    {
        $this->parent = sys_get_temp_dir() . '/wpsm-renameep-' . bin2hex(random_bytes(6));
        $this->appDir = $this->parent . '/wpsafemode';
        $this->dataDir = $this->appDir . '/data';
        mkdir($this->dataDir, 0700, true);
        file_put_contents($this->appDir . '/index.php', '<?php');

        $this->storage = StorageFactory::create($this->dataDir, 'json');
        $this->sessions = new AuthSessions($this->storage, new PasswordHasher());
        $this->installer = new Installer(
            $this->storage,
            new KeyVault($this->dataDir),
            new PasswordHasher(),
            $this->dataDir,
            Environment::probe($this->dataDir),
            FakeFetcher::unreachable()
        );

        $router = new Router();
        Routes::register($router, $this->storage, $this->sessions, $this->installer, $this->appDir, $this->dataDir);
        $this->kernel = new Kernel($router, $this->storage, $this->sessions, $this->installer);
    }

    protected function tearDown(): void
    {
        $this->removeRecursively($this->parent);
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

    /** @return array<string, string> role => session token */
    private function install(): array
    {
        $hash = (new PasswordHasher())->hash(self::PASSWORD);
        $tokens = [];

        foreach ([Role::OWNER, Role::ADMIN] as $role) {
            $this->storage->users()->create($role, $role, $hash);
        }

        $this->storage->config()->set('installed_at', '1');
        $this->storage->config()->set('install_id', self::SECRET);

        foreach ([Role::OWNER, Role::ADMIN] as $role) {
            $result = $this->sessions->login($role, self::PASSWORD, '203.0.113.1', self::UA, self::NOW);
            self::assertTrue($result->ok);
            $tokens[$role] = (string) $result->token;
        }

        return $tokens;
    }

    /** @param array<string, mixed> $body @param array<string, string> $extra */
    private function post(string $uri, array $body, ?string $token = null, array $extra = []): Response
    {
        $server = ['REMOTE_ADDR' => '203.0.113.1', 'HTTP_USER_AGENT' => self::UA] + $extra;

        if ($token !== null) {
            $server['HTTP_COOKIE'] = AuthSessions::COOKIE . '=' . $token;
        }

        return $this->kernel->handle(
            new Request('POST', $uri, '/index.php', (string) json_encode($body), $server),
            self::NOW
        );
    }

    /** @return array<string, mixed> */
    private function decode(Response $response): array
    {
        $decoded = json_decode($response->body, true);
        self::assertIsArray($decoded);

        return $decoded;
    }

    public function testTheInstallerRenameRequiresTheToken(): void
    {
        $this->installer->issueToken();

        foreach ([[], ['token' => 'not-the-token']] as $body) {
            $response = $this->post('/api/install/rename', $body + ['name' => self::NAME]);

            self::assertSame(403, $response->status);
            self::assertSame('bad_token', $this->decode($response)['error']);
        }

        // A token that is not a string is a malformed request.
        $response = $this->post('/api/install/rename', ['token' => 5, 'name' => self::NAME]);
        self::assertSame(422, $response->status);
        self::assertSame('bad_request', $this->decode($response)['error']);

        self::assertDirectoryExists($this->appDir);
        self::assertDirectoryDoesNotExist($this->parent . '/' . self::NAME);
        self::assertSame([], $this->storage->auditLog()->recent(5));
    }

    public function testTheInstallerRenameMovesTheDirectoryWithTheToken(): void
    {
        $token = $this->installer->issueToken();

        $response = $this->post('/api/install/rename', ['token' => $token, 'name' => self::NAME]);

        self::assertSame(200, $response->status);
        self::assertSame(['ok' => true, 'redirect' => '../' . self::NAME . '/'], $this->decode($response));
        self::assertDirectoryDoesNotExist($this->appDir);
        self::assertDirectoryExists($this->parent . '/' . self::NAME);

        $event = StorageFactory::create($this->parent . '/' . self::NAME . '/data', 'json')->auditLog()->recent(1)[0];
        self::assertSame('anonymous', $event->actor);
        self::assertSame('rename', $event->event);
        self::assertSame(['to' => self::NAME], $event->meta);
    }

    public function testAnUnsafeNameIsRefusedWithoutMovingOrAuditing(): void
    {
        $token = $this->installer->issueToken();

        foreach (['../escape', 'a/b', 'ab', ''] as $bad) {
            $response = $this->post('/api/install/rename', ['token' => $token, 'name' => $bad]);

            self::assertSame(422, $response->status);
            self::assertSame('rename_failed', $this->decode($response)['error']);
        }

        foreach ([['x'], 7, null] as $bad) {
            $response = $this->post('/api/install/rename', ['token' => $token, 'name' => $bad]);

            self::assertSame(422, $response->status);
            self::assertSame(['error' => 'bad_request'], $this->decode($response));
        }

        self::assertDirectoryExists($this->appDir);
        self::assertSame([], $this->storage->auditLog()->recent(5));
    }

    public function testTheInstallerRenameIsRefusedOnceInstalled(): void
    {
        $token = $this->installer->issueToken();
        $this->install();

        $response = $this->post('/api/install/rename', ['token' => $token, 'name' => self::NAME]);

        self::assertSame(409, $response->status);
        self::assertSame('already_installed', $this->decode($response)['error']);
        self::assertDirectoryExists($this->appDir);
    }

    public function testOnlyAnOwnerCanRenameAfterInstall(): void
    {
        $tokens = $this->install();
        $admin = $tokens[Role::ADMIN];

        $forbidden = $this->post(
            '/api/admin/rename',
            ['name' => self::NAME],
            $admin,
            ['HTTP_X_WPSM_CSRF' => Csrf::issue($admin, self::SECRET)]
        );
        self::assertSame(403, $forbidden->status);
        self::assertSame('forbidden', $this->decode($forbidden)['error']);

        $anonymous = $this->post('/api/admin/rename', ['name' => self::NAME]);
        self::assertSame(401, $anonymous->status);
        self::assertSame('unauthenticated', $this->decode($anonymous)['error']);

        self::assertDirectoryExists($this->appDir);
        self::assertDirectoryDoesNotExist($this->parent . '/' . self::NAME);
        self::assertSame([], $this->storage->auditLog()->recent(5));
    }

    public function testAnOwnerRenameIsAuditedAndAsksForANewSignIn(): void
    {
        $owner = $this->install()[Role::OWNER];

        $response = $this->post(
            '/api/admin/rename',
            ['name' => self::NAME],
            $owner,
            ['HTTP_X_WPSM_CSRF' => Csrf::issue($owner, self::SECRET)]
        );

        self::assertSame(200, $response->status);
        $body = $this->decode($response);
        self::assertTrue($body['ok']);
        self::assertSame('../' . self::NAME . '/', $body['redirect']);
        self::assertTrue($body['sign_in_again']);

        // Nothing may have written to the old address after the move.
        self::assertDirectoryDoesNotExist($this->appDir);
        self::assertDirectoryExists($this->parent . '/' . self::NAME);

        $moved = StorageFactory::create($this->parent . '/' . self::NAME . '/data', 'json');
        $event = $moved->auditLog()->recent(1)[0];
        self::assertSame('user:1', $event->actor);
        self::assertSame('rename', $event->event);
        self::assertSame(['to' => self::NAME], $event->meta);
    }

    public function testAFailedRenameIsAuditedAndLeaksNoPath(): void
    {
        $owner = $this->install()[Role::OWNER];
        mkdir($this->parent . '/' . self::NAME);

        $response = $this->post(
            '/api/admin/rename',
            ['name' => self::NAME],
            $owner,
            ['HTTP_X_WPSM_CSRF' => Csrf::issue($owner, self::SECRET)]
        );

        self::assertSame(422, $response->status);
        self::assertSame('rename_failed', $this->decode($response)['error']);
        self::assertStringNotContainsString($this->parent, $response->body);
        self::assertDirectoryExists($this->appDir);

        $events = $this->storage->auditLog()->recent(2);
        self::assertSame(['to' => self::NAME, 'outcome' => 'failed'], $events[0]->meta);
        self::assertSame(['to' => self::NAME], $events[1]->meta);
    }

    public function testAnOwnerRenameWithANonStringNameIsABadRequest(): void
    {
        $owner = $this->install()[Role::OWNER];

        foreach ([['x'], 7, true, 'a/b'] as $bad) {
            $response = $this->post(
                '/api/admin/rename',
                ['name' => $bad],
                $owner,
                ['HTTP_X_WPSM_CSRF' => Csrf::issue($owner, self::SECRET)]
            );

            self::assertSame(422, $response->status);
            self::assertSame(is_string($bad) ? 'rename_failed' : 'bad_request', $this->decode($response)['error']);
        }

        self::assertDirectoryExists($this->appDir);
    }
}
