<?php

declare(strict_types=1);

namespace WPSafeMode\Core;

if (!defined('WPSM_ENTRY')) {
    http_response_code(404);
    exit;
}

use WPSafeMode\Security\Channel;
use WPSafeMode\Security\Permission;

final class Router
{
    /** @var list<Route> */
    private array $routes = [];

    /**
     * @param callable(Request): Response $handler
     * @param list<string>                $channels
     */
    public function add(
        string $method,
        string $pattern,
        callable $handler,
        string $permission,
        array $channels = [Channel::BROWSER],
        bool $preInstall = false,
    ): void {
        if (!in_array($permission, Permission::ALL, true)) {
            throw new \InvalidArgumentException('Unknown permission: ' . $permission);
        }

        if ($channels === []) {
            throw new \InvalidArgumentException('A route needs at least one channel.');
        }

        foreach ($channels as $channel) {
            if (!in_array($channel, Channel::ALL, true)) {
                throw new \InvalidArgumentException('Unknown channel: ' . (string) $channel);
            }
        }

        if ($permission === Permission::OWN && in_array(Channel::REMOTE, $channels, true)) {
            throw new \InvalidArgumentException('Owner-only routes accept the browser channel only.');
        }

        if ($preInstall && $permission !== Permission::OPEN) {
            throw new \InvalidArgumentException('A pre-install route must be open: there are no users yet.');
        }

        $this->routes[] = new Route(strtoupper($method), $pattern, $handler, $permission, $channels, $preInstall);
    }

    /** @return list<Route> Every route, in registration order. */
    public function routes(): array
    {
        return $this->routes;
    }

    /**
     * Static routes win over placeholder routes; among placeholder routes the
     * first registered wins. A path known under another method only is 405.
     */
    public function match(string $method, string $path): RouteMatch
    {
        $method = strtoupper($method);
        $segments = explode('/', $path);
        $pathKnown = false;

        foreach ([false, true] as $withPlaceholders) {
            foreach ($this->routes as $route) {
                $hasPlaceholder = strpos($route->pattern, '{') !== false;

                if ($hasPlaceholder !== $withPlaceholders) {
                    continue;
                }

                $params = $this->matchPattern($route->pattern, $segments);

                if ($params === null) {
                    continue;
                }

                if ($route->method === $method) {
                    return RouteMatch::found($route, $params);
                }

                $pathKnown = true;
            }
        }

        return $pathKnown ? RouteMatch::methodNotAllowed() : RouteMatch::notFound();
    }

    /**
     * @param list<string> $segments the raw (still encoded) path split on '/'
     * @return array<string, string>|null params, or null when the pattern does not match
     */
    private function matchPattern(string $pattern, array $segments): ?array
    {
        $parts = explode('/', $pattern);

        if (count($parts) !== count($segments)) {
            return null;
        }

        $params = [];

        foreach ($parts as $i => $part) {
            if (preg_match('/^\{([A-Za-z_][A-Za-z0-9_]*)\}\z/', $part, $m) === 1) {
                if ($segments[$i] === '') {
                    return null;
                }

                $params[$m[1]] = rawurldecode($segments[$i]);
            } elseif ($part !== $segments[$i]) {
                return null;
            }
        }

        return $params;
    }
}
