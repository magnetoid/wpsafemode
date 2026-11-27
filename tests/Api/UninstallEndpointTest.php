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

/**
 * Every test points the controller at a directory under the system temp dir;
 * none ever uses the real checkout.
 */
final class UninstallEndpointTest extends TestCase
{
    private const PASSWORD = 'correct horse battery staple';
    private const UA = 'UninstallEndpointTest/1.0';
    private const NOW = 1_000_000;
    private const SECRET = 'abcdef0123456789';

    private string $parent;
    private string $appDir;
    private string $dataDir;
    private Storage $storage;
    private AuthSessions $sessions;
    private Kernel $kernel;

    protected function setUp(): void
    {
        $this->parent = sys_get_temp_dir() . '/wpsm-uninstallep-' . bin2hex(random_bytes(6));
        $this->appDir = $this->parent . '/wpsm-abc123';
        $this->dataDir = $this->appDir . '/data';
        mkdir($this->dataDir, 0700, true);
        file_put_contents($this->appDir . '/index.php', '<?php');

        $this->storage = StorageFactory::create($this->dataDir, 'json');
        $this->sessions = new AuthSessions($this->storage, new PasswordHasher());
        $installer = new Installer(
            $this->storage,
            new KeyVault($this->dataDir),
            new PasswordHasher(),
            $this->dataDir,
            Environment::probe($this->dataDir)
        );

        $router = new Router();
        Routes::register($router, $this->storage, $this->sessions, $installer, $this->appDir, $this->dataDir);
        $this->kernel = new Kernel($router, $this->storage, $this->sessions, $installer);
    }

    protected function tearDown(): void
    {
        ShippedCopy::remove($this->parent);
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

    /** @param array<string, mixed> $body */
    private function post(array $body, ?string $token = null, bool $csrf = true): Response
    {
        $server = ['REMOTE_ADDR' => '203.0.113.1', 'HTTP_USER_AGENT' => self::UA];

        if ($token !== null) {
            $server['HTTP_COOKIE'] = AuthSessions::COOKIE . '=' . $token;
            if ($csrf) {
                $server['HTTP_X_WPSM_CSRF'] = Csrf::issue($token, self::SECRET);
            }
        }

        return $this->kernel->handle(
            new Request('POST', '/api/uninstall', '/index.php', (string) json_encode($body), $server),
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

    private function auditedUninstalls(): int
    {
        return count(array_filter(
            $this->storage->auditLog()->recent(20),
            static fn ($event): bool => $event->event === 'uninstall'
        ));
    }

    public function testOnlyAnOwnerCanUninstall(): void
    {
        $tokens = $this->install();

        $forbidden = $this->post(['confirm' => 'DELETE'], $tokens[Role::ADMIN]);
        self::assertSame(403, $forbidden->status);
        self::assertSame('forbidden', $this->decode($forbidden)['error']);

        $anonymous = $this->post(['confirm' => 'DELETE']);
        self::assertSame(401, $anonymous->status);
        self::assertSame('unauthenticated', $this->decode($anonymous)['error']);

        self::assertDirectoryExists($this->appDir);
        self::assertDirectoryExists($this->dataDir);
        self::assertSame(0, $this->auditedUninstalls());
    }

    public function testUninstallNeedsTheTypedConfirmation(): void
    {
        $owner = $this->install()[Role::OWNER];

        foreach ([[], ['confirm' => 'delete'], ['confirm' => ['DELETE']], ['confirm' => true], ['confirm' => 'DELETE ']] as $body) {
            $response = $this->post($body, $owner);

            self::assertSame(422, $response->status);
            self::assertSame('confirmation_required', $this->decode($response)['error']);
        }

        self::assertDirectoryExists($this->appDir);
        self::assertFileExists($this->appDir . '/index.php');
        self::assertSame(0, $this->auditedUninstalls());
    }

    public function testTheOwnerCanUninstallFromATempCopy(): void
    {
        $owner = $this->install()[Role::OWNER];

        $response = $this->post(['confirm' => 'DELETE'], $owner);

        self::assertSame(200, $response->status);
        self::assertSame(
            ['ok' => true, 'message' => 'WP Safe Mode has removed itself. This page will not load again.'],
            $this->decode($response)
        );
        self::assertDirectoryDoesNotExist($this->appDir);
        self::assertFileExists($this->parent . '/WPSAFEMODE-REMOVED.txt');
    }
}
