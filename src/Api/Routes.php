<?php

declare(strict_types=1);

namespace WPSafeMode\Api;

use WPSafeMode\Core\Router;
use WPSafeMode\Installer\Installer;
use WPSafeMode\Security\AuthSessions;
use WPSafeMode\Security\Permission;
use WPSafeMode\Storage\Contracts\Storage;

/**
 * The only place routes are added. RoutesTest pins this table, so a route
 * added here without a row there fails the build.
 */
final class Routes
{
    public static function register(
        Router $router,
        Storage $storage,
        AuthSessions $sessions,
        Installer $installer,
        string $appDir,
        string $dataDir
    ): void {
        $install = new InstallController($installer, $appDir);
        $admin = new AdminController($storage, $appDir);
        $auth = new AuthController($storage, $sessions, $installer);

        $router->add('GET', '/api/install/state', [$install, 'state'], Permission::OPEN, ['browser'], true);
        $router->add('POST', '/api/install/token', [$install, 'verifyToken'], Permission::OPEN, ['browser'], true);
        $router->add('POST', '/api/install/owner', [$install, 'createOwner'], Permission::OPEN, ['browser'], true);
        $router->add('POST', '/api/install/self-test', [$install, 'selfTest'], Permission::OPEN, ['browser'], true);
        $router->add('POST', '/api/install/api-key', [$install, 'apiKey'], Permission::OPEN, ['browser'], true);
        $router->add('POST', '/api/install/finish', [$install, 'finish'], Permission::OPEN, ['browser'], true);
        $router->add('POST', '/api/install/rename', [$install, 'rename'], Permission::OPEN, ['browser'], true);

        $router->add('POST', '/api/auth/login', [$auth, 'login'], Permission::OPEN);
        $router->add('POST', '/api/auth/logout', [$auth, 'logout'], Permission::READ);
        $router->add('GET', '/api/auth/me', [$auth, 'me'], Permission::READ);

        $router->add('GET', '/api/env', new EnvController($storage), Permission::READ);

        $router->add('POST', '/api/admin/rename', [$admin, 'rename'], Permission::OWN);

        $router->add('POST', '/api/uninstall', new UninstallController($storage, $appDir, $dataDir), Permission::OWN);
    }
}
