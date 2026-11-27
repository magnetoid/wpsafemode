<?php

declare(strict_types=1);

namespace WPSafeMode\Tests\Api;

use PHPUnit\Framework\TestCase;
use WPSafeMode\Api\Routes;
use WPSafeMode\Core\Environment;
use WPSafeMode\Core\Router;
use WPSafeMode\Installer\Installer;
use WPSafeMode\Security\AuthSessions;
use WPSafeMode\Security\KeyVault;
use WPSafeMode\Security\PasswordHasher;
use WPSafeMode\Storage\StorageFactory;

final class RoutesTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/wpsm-routes-' . bin2hex(random_bytes(6));
        mkdir($this->dir, 0700, true);
    }

    protected function tearDown(): void
    {
        foreach (scandir($this->dir) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                @unlink($this->dir . '/' . $entry);
            }
        }
        @rmdir($this->dir);
    }

    public function testEveryRouteMatchesTheDeclaredTable(): void
    {
        $storage = StorageFactory::create($this->dir, 'json');
        $installer = new Installer(
            $storage,
            new KeyVault($this->dir),
            new PasswordHasher(),
            $this->dir,
            Environment::probe($this->dir)
        );
        $router = new Router();

        Routes::register($router, $storage, new AuthSessions($storage, new PasswordHasher()), $installer, $this->dir, $this->dir);

        $actual = [];
        foreach ($router->routes() as $route) {
            $actual[] = [$route->method, $route->pattern, $route->permission, $route->channels, $route->preInstall];
        }

        self::assertSame([
            ['GET',  '/api/install/state',     'open', ['browser'], true],
            ['POST', '/api/install/token',     'open', ['browser'], true],
            ['POST', '/api/install/owner',     'open', ['browser'], true],
            ['POST', '/api/install/self-test', 'open', ['browser'], true],
            ['POST', '/api/install/api-key',   'open', ['browser'], true],
            ['POST', '/api/install/finish',    'open', ['browser'], true],
            ['POST', '/api/install/rename',    'open', ['browser'], true],
            ['POST', '/api/auth/login',        'open', ['browser'], false],
            ['POST', '/api/auth/logout',       'read', ['browser'], false],
            ['GET',  '/api/auth/me',           'read', ['browser'], false],
            ['GET',  '/api/env',               'read', ['browser'], false],
            ['POST', '/api/admin/rename',      'own',  ['browser'], false],
        ], $actual);
    }
}
