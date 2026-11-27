<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use WPSafeMode\Api\EnvController;
use WPSafeMode\Core\Environment;
use WPSafeMode\Core\Request;
use WPSafeMode\Core\Response;
use WPSafeMode\Core\RouteMatch;
use WPSafeMode\Core\Router;
use WPSafeMode\Security\Permission;
use WPSafeMode\Storage\Contracts\StorageException;
use WPSafeMode\Storage\StorageFactory;

$request = Request::fromGlobals();
$path = $request->path();

// Static assets and the SPA shell are served directly. Everything the browser
// loads lives under public/; nothing else in src/ is web-reachable by design.
if (strpos($path, '/api/') !== 0) {
    $file = __DIR__ . '/public' . ($path === '/' ? '/index.html' : $path);
    $real = realpath($file);
    $root = realpath(__DIR__ . '/public');

    if ($real !== false && $root !== false && strpos($real, $root) === 0 && is_file($real)) {
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
        header('X-Content-Type-Options: nosniff');
        readfile($real);
        exit;
    }

    // Unknown non-API path: hand it to the SPA shell for client-side routing.
    $shell = __DIR__ . '/public/index.html';

    if (is_file($shell)) {
        header('Content-Type: text/html; charset=utf-8');
        readfile($shell);
        exit;
    }

    (new Response(404, 'Not found'))->send();
    exit;
}

try {
    $storage = StorageFactory::open(__DIR__ . '/../data', Environment::probe());
} catch (StorageException $e) {
    Response::json(['error' => 'storage_unavailable', 'message' => $e->getMessage()], 500)->send();
    exit;
}

$router = new Router();
$router->add('GET', '/api/env', new EnvController($storage), Permission::OPEN);

// Interim: Task 11 replaces this block with the Kernel, which also checks the
// route's permission and channel before running the handler.
$match = $router->match($request->method, $path);

if ($match->status === RouteMatch::FOUND && $match->route !== null) {
    $request->params = $match->params;
    ($match->route->handler)($request)->send();
} elseif ($match->status === RouteMatch::METHOD_NOT_ALLOWED) {
    Response::methodNotAllowed()->send();
} else {
    Response::notFound()->send();
}
