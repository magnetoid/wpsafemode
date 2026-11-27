<?php

declare(strict_types=1);

// Before anything else can fail: nothing PHP prints may reach the browser.
// A trace names absolute paths and, without exception_ignore_args, the
// arguments of every frame, which on the login route is the password.
ini_set('display_errors', '0');
ini_set('html_errors', '0');
ini_set('zend.exception_ignore_args', '1');
ini_set('log_errors', '1');

require __DIR__ . '/bootstrap.php';

use WPSafeMode\Api\Kernel;
use WPSafeMode\Api\Routes;
use WPSafeMode\Core\Environment;
use WPSafeMode\Core\ErrorLog;
use WPSafeMode\Core\PublicFile;
use WPSafeMode\Core\Request;
use WPSafeMode\Core\Response;
use WPSafeMode\Core\Router;
use WPSafeMode\Installer\Installer;
use WPSafeMode\Security\AuthSessions;
use WPSafeMode\Security\KeyVault;
use WPSafeMode\Security\PasswordHasher;
use WPSafeMode\Storage\Contracts\StorageException;
use WPSafeMode\Storage\StorageFactory;

// The data directory lives inside the install directory: <install-dir>/data/.
$dataDir = __DIR__ . '/data';

// PHP's own log goes into the data directory, behind the die guard, never to
// a web-readable error_log beside this script.
ErrorLog::prepare($dataDir);

$request = Request::fromGlobals();
$path = $request->path();

// Static assets and the SPA shell are served directly from public/.
if (strpos($path, '/api/') !== 0) {
    // A drop-in ZIP ships no rewrite rules, so the only address that reliably
    // reaches PHP is <install-dir>/index.php/... The document must live under
    // it, or its relative asset and API URLs resolve to places PHP never sees.
    $urlPath = (string) parse_url($request->uri, PHP_URL_PATH);
    $script = str_replace('\\', '/', $request->scriptName);
    // The bare script name (no trailing slash) counts as not passing through:
    // a relative URL there would resolve one level too high.
    $viaScript = strpos($urlPath, $script . '/') === 0;
    $isShell = $path === '/';

    $sendSecurityHeaders = static function (): void {
        foreach (Response::SECURITY_HEADERS as $name => $value) {
            header($name . ': ' . $value);
        }
    };

    $toShell = static function () use ($request, $sendSecurityHeaders): void {
        // Keep the query: the owner's move lands here with ?moved=1.
        $query = (string) parse_url($request->uri, PHP_URL_QUERY);
        header('Location: ' . $request->basePath() . '/index.php/' . ($query !== '' ? '?' . $query : ''), true, 302);
        header('Cache-Control: no-store');
        $sendSecurityHeaders();
        exit;
    };

    if ($isShell && !$viaScript) {
        $toShell();
    }

    $real = PublicFile::resolve(__DIR__ . '/public', $isShell ? '/index.html' : $path);

    if ($real !== null) {
        $types = [
            'html' => 'text/html; charset=utf-8',
            'js'   => 'text/javascript; charset=utf-8',
            'css'  => 'text/css; charset=utf-8',
            'svg'  => 'image/svg+xml',
            'png'  => 'image/png',
            'webp' => 'image/webp',
            'ico'  => 'image/x-icon',
        ];
        $extension = strtolower((string) pathinfo($real, PATHINFO_EXTENSION));

        header('Content-Type: ' . ($types[$extension] ?? 'application/octet-stream'));
        $sendSecurityHeaders();
        readfile($real);
        exit;
    }

    // Unknown non-API path: hand it to the SPA shell for client-side routing.
    $shell = __DIR__ . '/public/index.html';

    if (is_file($shell)) {
        if (!$viaScript) {
            $toShell();
        }

        header('Content-Type: text/html; charset=utf-8');
        $sendSecurityHeaders();
        readfile($shell);
        exit;
    }

    (new Response(404, 'Not found'))->send();
    exit;
}

try {
    $env = Environment::probe();
    $storage = StorageFactory::open($dataDir, $env);

    $hasher = new PasswordHasher();
    $installer = new Installer($storage, new KeyVault($dataDir), $hasher, $dataDir, $env);
    $sessions = new AuthSessions($storage, $hasher);

    $router = new Router();
    Routes::register($router, $storage, $sessions, $installer, __DIR__, $dataDir);

    (new Kernel($router, $storage, $sessions, $installer))->handle($request, time())->send();
} catch (\Throwable $e) {
    // Every failure gets the same two answers. The message can carry an
    // absolute server path: it goes to the guarded log, never to the browser.
    ErrorLog::record($e);
    Response::json(['error' => $e instanceof StorageException ? 'storage_unavailable' : 'server_error'], 500)->send();
}
