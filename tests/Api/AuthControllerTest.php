<?php

declare(strict_types=1);

namespace WPSafeMode\Tests\Api;

use PHPUnit\Framework\TestCase;
use WPSafeMode\Api\AuthController;
use WPSafeMode\Core\Environment;
use WPSafeMode\Core\Request;
use WPSafeMode\Core\Response;
use WPSafeMode\Installer\Installer;
use WPSafeMode\Security\AuthSessions;
use WPSafeMode\Security\Channel;
use WPSafeMode\Security\Csrf;
use WPSafeMode\Security\KeyVault;
use WPSafeMode\Security\PasswordHasher;
use WPSafeMode\Security\Principal;
use WPSafeMode\Security\Role;
use WPSafeMode\Storage\Contracts\RateLimiter;
use WPSafeMode\Storage\Contracts\Storage;
use WPSafeMode\Storage\StorageFactory;
use WPSafeMode\Storage\User;

final class AuthControllerTest extends TestCase
{
    private const PASSWORD = 'correct horse battery staple';
    private const SECRET = 'abcdef0123456789';
    private const UA = 'AuthControllerTest/1.0';

    private string $dir;
    private Storage $storage;
    private AuthSessions $sessions;
    private AuthController $controller;
    private User $owner;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/wpsm-authctl-' . bin2hex(random_bytes(6));
        mkdir($this->dir, 0700, true);

        $this->storage = StorageFactory::create($this->dir, 'json');
        $this->owner = $this->storage->users()->create('owner', Role::OWNER, (new PasswordHasher())->hash(self::PASSWORD));
        $this->storage->config()->set('installed_at', '1');
        $this->storage->config()->set('install_id', self::SECRET);

        $this->sessions = new AuthSessions($this->storage, new PasswordHasher());
        $installer = new Installer(
            $this->storage,
            new KeyVault($this->dir),
            new PasswordHasher(),
            $this->dir,
            Environment::probe($this->dir)
        );
        $this->controller = new AuthController($this->storage, $this->sessions, $installer);
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

    private function loginRequest(string $login, string $password): Request
    {
        return new Request(
            'POST',
            '/api/auth/login',
            '/index.php',
            (string) json_encode(['login' => $login, 'password' => $password]),
            ['REMOTE_ADDR' => '203.0.113.7', 'HTTP_USER_AGENT' => self::UA]
        );
    }

    private function authedRequest(string $method, string $token): Request
    {
        $request = new Request(
            $method,
            '/api/auth/me',
            '/index.php',
            '',
            ['REMOTE_ADDR' => '203.0.113.7', 'HTTP_USER_AGENT' => self::UA, 'HTTP_COOKIE' => AuthSessions::COOKIE . '=' . $token]
        );
        $request->principal = Principal::forUser($this->owner, Channel::BROWSER);

        return $request;
    }

    /** @return array<string, mixed> */
    private function decode(Response $response): array
    {
        $decoded = json_decode($response->body, true);
        self::assertIsArray($decoded);

        return $decoded;
    }

    private function tokenFromCookie(string $header): string
    {
        self::assertSame(1, preg_match('/^' . AuthSessions::COOKIE . '=([A-Za-z0-9_-]+);/', $header, $m));

        return $m[1];
    }

    public function testLoginSetsTheCookieAndReturnsCsrfAndUser(): void
    {
        $response = $this->controller->login($this->loginRequest('owner', self::PASSWORD));

        self::assertSame(200, $response->status);
        $body = $this->decode($response);
        self::assertTrue($body['ok']);
        self::assertSame(['id' => $this->owner->id, 'login' => 'owner', 'role' => 'owner'], $body['user']);
        self::assertIsArray($body['self_test']);

        $cookie = $response->headers['Set-Cookie'];
        $token = $this->tokenFromCookie($cookie);
        self::assertStringContainsString('HttpOnly', $cookie);
        self::assertSame(Csrf::issue($token, self::SECRET), $body['csrf']);
        self::assertNotNull($this->sessions->validate($token, self::UA, time()));
        self::assertSame('login', $this->storage->auditLog()->recent(1)[0]->event);
    }

    public function testAFailedLoginIsAuditedWithoutTheAttemptedName(): void
    {
        $typed = 'my-real-password-typed-in-the-wrong-box';

        $response = $this->controller->login($this->loginRequest($typed, 'whatever'));

        self::assertSame(401, $response->status);
        self::assertSame('bad_credentials', $this->decode($response)['error']);
        self::assertArrayNotHasKey('Set-Cookie', $response->headers);

        $event = $this->storage->auditLog()->recent(1)[0];
        self::assertSame('login_failed', $event->event);
        self::assertSame('anonymous', $event->actor);
        self::assertSame(['reason' => 'bad_credentials'], $event->meta);
        self::assertStringNotContainsString($typed, (string) json_encode($event));
    }

    public function testLockoutAnswers429WithRetryAfter(): void
    {
        for ($i = 0; $i < RateLimiter::MAX_ATTEMPTS; $i++) {
            $this->controller->login($this->loginRequest('owner', 'wrong'));
        }

        $response = $this->controller->login($this->loginRequest('owner', self::PASSWORD));

        self::assertSame(429, $response->status);
        $body = $this->decode($response);
        self::assertSame('locked_out', $body['error']);
        self::assertGreaterThan(0, $body['retry_after']);
    }

    public function testMeReturnsTheUserAndAWorkingCsrfToken(): void
    {
        $token = $this->tokenFromCookie(
            $this->controller->login($this->loginRequest('owner', self::PASSWORD))->headers['Set-Cookie']
        );

        $response = $this->controller->me($this->authedRequest('GET', $token));

        self::assertSame(200, $response->status);
        $body = $this->decode($response);
        self::assertSame('owner', $body['user']['login']);
        self::assertTrue(Csrf::verify($token, self::SECRET, $body['csrf']));
    }

    public function testLogoutRevokesTheSessionAndClearsTheCookie(): void
    {
        $token = $this->tokenFromCookie(
            $this->controller->login($this->loginRequest('owner', self::PASSWORD))->headers['Set-Cookie']
        );

        $response = $this->controller->logout($this->authedRequest('POST', $token));

        self::assertSame(200, $response->status);
        self::assertSame(['ok' => true], $this->decode($response));
        self::assertStringContainsString('Max-Age=0', $response->headers['Set-Cookie']);
        self::assertNull($this->sessions->validate($token, self::UA, time()));
        self::assertSame('logout', $this->storage->auditLog()->recent(1)[0]->event);
    }

    public function testLoginAndMeRefuseWithoutAnInstallSecret(): void
    {
        $this->storage->config()->delete('install_id');

        $login = $this->controller->login($this->loginRequest('owner', self::PASSWORD));
        self::assertSame(500, $login->status);
        self::assertSame('corrupt_install', $this->decode($login)['error']);
        self::assertArrayNotHasKey('Set-Cookie', $login->headers);

        $me = $this->controller->me($this->authedRequest('GET', 'sometoken'));
        self::assertSame(500, $me->status);
        self::assertSame('corrupt_install', $this->decode($me)['error']);
    }
}
