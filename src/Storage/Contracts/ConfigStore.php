<?php

declare(strict_types=1);

namespace WPSafeMode\Storage\Contracts;

if (!defined('WPSM_ENTRY')) {
    http_response_code(404);
    exit;
}

/**
 * Flat key-value configuration.
 *
 * Values are stored verbatim. Encryption is NOT this interface's concern —
 * M1 introduces an encrypting decorator around a ConfigStore so that the
 * key vault is a separate, independently testable unit (spec 5.5).
 */
interface ConfigStore
{
    public function get(string $key, ?string $default = null): ?string;

    public function set(string $key, string $value): void;

    public function has(string $key): bool;

    public function delete(string $key): void;

    /** @return array<string, string> */
    public function all(): array;
}
