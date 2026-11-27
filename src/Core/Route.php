<?php

declare(strict_types=1);

namespace WPSafeMode\Core;

final class Route
{
    /**
     * @param callable(Request): Response $handler
     * @param list<string>                $channels
     */
    public function __construct(
        public string $method,
        public string $pattern,
        /** @var callable(Request): Response */
        public $handler,
        public string $permission,
        public array $channels,
        public bool $preInstall,
    ) {
    }
}
