<?php

declare(strict_types=1);

namespace WPSafeMode\Tests\Api;

use PHPUnit\Framework\TestCase;
use WPSafeMode\Api\Kernel;
use WPSafeMode\Core\Environment;
use WPSafeMode\Core\Request;
use WPSafeMode\Core\Response;
use WPSafeMode\Core\Router;
use WPSafeMode\Installer\Installer;
use WPSafeMode\Security\AuthSessions;
use WPSafeMode\Security\Channel;
use WPSafeMode\Security\Csrf;
use WPSafeMode\Security\KeyVault;
use WPSafeMode\Security\PasswordHasher;
use WPSafeMode\Security\Permission;
use WPSafeMode\Security\Role;
use WPSafeMode\Storage\Contracts\Storage;
use WPSafeMode\Storage\StorageFactory;
use WPSafeMode\Storage\User;
use WPSafeMode\Tests\Support\FakeFetcher;

final class KernelTest extends TestCase
{
    private const PASSWORD = 'correct horse battery staple';
    private const UA = 'KernelTest/1.0';
    private const NOW = 1_000_000;
    private const SECRET = 'abcdef0123456789';

    private string $dir;
    private Storage $storage;
    private AuthSessions $sessions;
    private Installer $installer;
    private Router $router;
    private Kernel $kernel;

    /** @var array<string, string> role => session token */
    private array $tokens = [];

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/wpsm-kernel-' . bin2hex(random_bytes(6));
        mkdir($this->dir, 0700, true);

        $this->storage = StorageFactory::create($this->dir, 'json');
        $this->sessions = new AuthSessions($this->storage, new PasswordHasher());
        $this->installer = new Installer(
            $this->storage,
            new KeyVault($this->dir),
            new PasswordHasher(),
            $this->dir,
            Environment::probe($this->dir),
            FakeFetcher::unreachable()
        );
        $this->router = new Router();
        $this->kernel = new Kernel($this->router, $this->storage, $this->sessions, $this->installer);
    }

    protected function tearDown(): void
    {
        $this->removeRecursively($this->dir);
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

    private function ok(): callable
    {
        return static fn (Request $r): Response => Response::json(['ok' => true]);
    }

    /** Marks the copy installed, with a user of every role, and logs each one in. */
    private function install(): void
    {
        $hash = (new PasswordHasher())->hash(self::PASSWORD);

        foreach (Role::ALL as $role) {
            $this->storage->users()->create($role, $role, $hash);
        }

        $this->storage->config()->set('installed_at', '1');
        $this->storage->config()->set('install_id', self::SECRET);

        foreach (Role::ALL as $role) {
            $this->tokens[$role] = $this->loginAs($role);
        }
    }

    private function loginAs(string $login): string
    {
        $result = $this->sessions->login($login, self::PASSWORD, '203.0.113.1', self::UA, self::NOW);
        self::assertTrue($result->ok);
        self::assertNotNull($result->token);

        return (string) $result->token;
    }

    /** @param array<string, string> $extra */
    private function request(string $method, string $uri, ?string $token = null, array $extra = [], string $body = ''): Request
    {
        $server = ['REMOTE_ADDR' => '203.0.113.1', 'HTTP_USER_AGENT' => self::UA] + $extra;

        if ($token !== null) {
            $server['HTTP_COOKIE'] = AuthSessions::COOKIE . '=' . $token;
        }

        return new Request($method, $uri, '/index.php', $body, $server);
    }

    private function csrfFor(string $token): string
    {
        return Csrf::issue($token, self::SECRET);
    }

    /** @return array<string, mixed> */
    private function decode(Response $response): array
    {
        $decoded = json_decode($response->body, true);
        self::assertIsArray($decoded);

        return $decoded;
    }

    public function testUnknownPathIs404AndWrongMethodIs405(): void
    {
        $this->router->add('GET', '/api/thing', $this->ok(), Permission::OPEN);

        self::assertSame(404, $this->kernel->handle($this->request('GET', '/api/nope'), self::NOW)->status);
        self::assertSame(405, $this->kernel->handle($this->request('DELETE', '/api/thing'), self::NOW)->status);
    }

    public function testAnUninstalledCopyRefusesEveryNonInstallerRoute(): void
    {
        $this->router->add('GET', '/api/thing', $this->ok(), Permission::OPEN);

        $response = $this->kernel->handle($this->request('GET', '/api/thing'), self::NOW);

        self::assertSame(409, $response->status);
        $body = $this->decode($response);
        self::assertSame('not_installed', $body['error']);
        self::assertArrayHasKey('step', $body);
    }

    public function testInstallerRoutesAnswerBeforeInstall(): void
    {
        $this->router->add('GET', '/api/inst', $this->ok(), Permission::OPEN, [Channel::BROWSER], true);
        $this->router->add('POST', '/api/inst', $this->ok(), Permission::OPEN, [Channel::BROWSER], true);

        self::assertSame(200, $this->kernel->handle($this->request('GET', '/api/inst'), self::NOW)->status);
        self::assertSame(200, $this->kernel->handle($this->request('POST', '/api/inst'), self::NOW)->status);
    }

    public function testInstallerMutationsAreRefusedOnceInstalled(): void
    {
        $this->router->add('POST', '/api/inst', $this->ok(), Permission::OPEN, [Channel::BROWSER], true);
        $this->install();

        $response = $this->kernel->handle($this->request('POST', '/api/inst'), self::NOW);

        self::assertSame(409, $response->status);
        self::assertSame('already_installed', $this->decode($response)['error']);
    }

    public function testAnInstallerReadStillAnswersOnceInstalled(): void
    {
        $this->router->add('GET', '/api/inst', $this->ok(), Permission::OPEN, [Channel::BROWSER], true);
        $this->install();

        self::assertSame(200, $this->kernel->handle($this->request('GET', '/api/inst'), self::NOW)->status);
    }

    public function testAnAnonymousRequestToAProtectedRouteIs401(): void
    {
        $this->router->add('GET', '/api/thing', $this->ok(), Permission::READ);
        $this->install();

        $response = $this->kernel->handle($this->request('GET', '/api/thing'), self::NOW);

        self::assertSame(401, $response->status);
        self::assertSame('unauthenticated', $this->decode($response)['error']);
    }

    /** @return array<string, array{string, string, int}> */
    public function roleByPermission(): array
    {
        $rows = [];

        foreach (Role::ALL as $role) {
            foreach (Permission::ALL as $permission) {
                $rows[$role . ' x ' . $permission] = [
                    $role,
                    $permission,
                    Permission::allows($permission, $role) ? 200 : 403,
                ];
            }
        }

        return $rows;
    }

    /** @dataProvider roleByPermission */
    public function testEachRoleReachesExactlyTheRoutesItsPermissionsAllow(string $role, string $permission, int $expected): void
    {
        $this->router->add('GET', '/api/thing', $this->ok(), $permission);
        $this->install();

        $response = $this->kernel->handle($this->request('GET', '/api/thing', $this->tokens[$role]), self::NOW);

        self::assertSame($expected, $response->status);

        if ($expected === 403) {
            self::assertSame('forbidden', $this->decode($response)['error']);
        }
    }

    public function testAMutatingRequestWithoutCsrfIs403(): void
    {
        $this->router->add('POST', '/api/thing', $this->ok(), Permission::READ);
        $this->install();

        $response = $this->kernel->handle($this->request('POST', '/api/thing', $this->tokens['viewer']), self::NOW);

        self::assertSame(403, $response->status);
        self::assertSame('bad_csrf', $this->decode($response)['error']);
    }

    public function testAValidCsrfHeaderPasses(): void
    {
        $this->router->add('POST', '/api/thing', $this->ok(), Permission::READ);
        $this->install();
        $token = $this->tokens['viewer'];

        $response = $this->kernel->handle(
            $this->request('POST', '/api/thing', $token, ['HTTP_X_WPSM_CSRF' => $this->csrfFor($token)]),
            self::NOW
        );

        self::assertSame(200, $response->status);
    }

    public function testAForeignCsrfTokenIsRefused(): void
    {
        $this->router->add('POST', '/api/thing', $this->ok(), Permission::READ);
        $this->install();

        $response = $this->kernel->handle(
            $this->request('POST', '/api/thing', $this->tokens['viewer'], [
                'HTTP_X_WPSM_CSRF' => $this->csrfFor($this->tokens['owner']),
            ]),
            self::NOW
        );

        self::assertSame(403, $response->status);
    }

    public function testCsrfFailsClosedWhenTheInstallSecretIsMissing(): void
    {
        $this->router->add('POST', '/api/thing', $this->ok(), Permission::READ);
        $this->install();
        $token = $this->tokens['viewer'];
        $header = Csrf::issue($token, '');
        $this->storage->config()->delete('install_id');

        $response = $this->kernel->handle(
            $this->request('POST', '/api/thing', $token, ['HTTP_X_WPSM_CSRF' => $header]),
            self::NOW
        );

        self::assertSame(403, $response->status);
        self::assertSame('bad_csrf', $this->decode($response)['error']);
    }

    public function testReadsNeedNoCsrf(): void
    {
        $this->router->add('GET', '/api/thing', $this->ok(), Permission::READ);
        $this->install();

        self::assertSame(200, $this->kernel->handle($this->request('GET', '/api/thing', $this->tokens['viewer']), self::NOW)->status);
    }

    public function testAnAnonymousPostToAnOpenRouteNeedsNoCsrf(): void
    {
        $this->router->add('POST', '/api/login', $this->ok(), Permission::OPEN);
        $this->install();

        self::assertSame(200, $this->kernel->handle($this->request('POST', '/api/login'), self::NOW)->status);
    }

    public function testALoggedInPostToAnOpenRouteStillNeedsCsrf(): void
    {
        $this->router->add('POST', '/api/login', $this->ok(), Permission::OPEN);
        $this->install();

        $response = $this->kernel->handle($this->request('POST', '/api/login', $this->tokens['owner']), self::NOW);

        self::assertSame(403, $response->status);
        self::assertSame('bad_csrf', $this->decode($response)['error']);
    }

    public function testADisabledUsersCookieIsTreatedAsAnonymous(): void
    {
        $this->router->add('GET', '/api/thing', $this->ok(), Permission::READ);
        $this->install();

        $viewer = $this->storage->users()->findByLogin('viewer');
        self::assertInstanceOf(User::class, $viewer);
        $this->storage->users()->update(new User($viewer->id, $viewer->login, $viewer->role, $viewer->credential, 5));

        $response = $this->kernel->handle($this->request('GET', '/api/thing', $this->tokens['viewer']), self::NOW);

        self::assertSame(401, $response->status);
    }

    public function testARouteThatDoesNotAcceptTheBrowserChannelIs403(): void
    {
        $this->router->add('GET', '/api/thing', $this->ok(), Permission::READ, [Channel::REMOTE]);
        $this->install();

        $response = $this->kernel->handle($this->request('GET', '/api/thing', $this->tokens['owner']), self::NOW);

        self::assertSame(403, $response->status);
        self::assertSame('channel_not_allowed', $this->decode($response)['error']);
    }

    public function testTheHandlerReceivesThePrincipalAndTheParams(): void
    {
        $seen = [];
        $this->router->add('GET', '/api/things/{id}', function (Request $r) use (&$seen): Response {
            $seen = ['params' => $r->params, 'user' => $r->principal === null ? null : $r->principal->userId()];

            return Response::json(['ok' => true]);
        }, Permission::READ);
        $this->install();

        $owner = $this->storage->users()->findByLogin('owner');
        self::assertInstanceOf(User::class, $owner);

        $this->kernel->handle($this->request('GET', '/api/things/42', $this->tokens['owner']), self::NOW);

        self::assertSame(['id' => '42'], $seen['params']);
        self::assertSame($owner->id, $seen['user']);
    }
}
