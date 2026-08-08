<?php

declare(strict_types=1);

namespace WPSafeMode\Core;

final class Router
{
    /** @var array<string, array<string, callable(Request): Response>> path => method => handler */
    private array $routes = [];

    /** @param callable(Request): Response $handler */
    public function add(string $method, string $path, callable $handler): void
    {
        $this->routes[$path][strtoupper($method)] = $handler;
    }

    public function dispatch(Request $request): Response
    {
        $path = $request->path();

        if (!isset($this->routes[$path])) {
            return Response::notFound();
        }

        $handler = $this->routes[$path][$request->method] ?? null;

        if ($handler === null) {
            return Response::methodNotAllowed();
        }

        return $handler($request);
    }
}
