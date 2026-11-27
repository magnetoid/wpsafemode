<?php

declare(strict_types=1);

namespace WPSafeMode\Core;

if (!defined('WPSM_ENTRY')) {
    http_response_code(404);
    exit;
}

final class RouteMatch
{
    public const FOUND = 'found';
    public const NOT_FOUND = 'not_found';
    public const METHOD_NOT_ALLOWED = 'method_not_allowed';

    /** @param array<string, string> $params */
    private function __construct(
        public string $status,
        public ?Route $route,
        public array $params,
    ) {
    }

    /** @param array<string, string> $params */
    public static function found(Route $route, array $params): self
    {
        return new self(self::FOUND, $route, $params);
    }

    public static function notFound(): self
    {
        return new self(self::NOT_FOUND, null, []);
    }

    public static function methodNotAllowed(): self
    {
        return new self(self::METHOD_NOT_ALLOWED, null, []);
    }
}
