<?php

declare(strict_types=1);

namespace WPSafeMode\Core;

final class Request
{
    public function __construct(
        public string $method,
        public string $uri,
        public string $scriptName = '/index.php',
        public string $rawBody = '',
    ) {
    }

    public static function fromGlobals(): self
    {
        return new self(
            method:     strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')),
            uri:        (string) ($_SERVER['REQUEST_URI'] ?? '/'),
            scriptName: (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'),
            rawBody:    (string) file_get_contents('php://input'),
        );
    }

    /**
     * Path with the query string, the install-directory prefix and — where the
     * host has no URL rewriting — the script name itself removed, always with
     * a leading slash.
     *
     * Both address forms have to work. With rewriting the tool is reached at
     * /install-dir/api/env, and only the directory is prefix. Without it, which
     * is what a drop-in ZIP must assume since it ships no .htaccess and cannot
     * count on mod_rewrite, the URL is /install-dir/index.php/api/env and the
     * script name is part of the path too.
     */
    public function path(): string
    {
        $path = (string) parse_url($this->uri, PHP_URL_PATH);
        $script = str_replace('\\', '/', $this->scriptName);

        $path = self::stripPrefix($path, $script)
            ?? self::stripPrefix($path, rtrim(dirname($script), '/'))
            ?? $path;

        return '/' . ltrim($path, '/');
    }

    /**
     * Removes $prefix from $path, but only on a segment boundary, so that
     * /index.phpsomething is never read as /index.php plus /something.
     * Returns null when the prefix does not apply.
     */
    private static function stripPrefix(string $path, string $prefix): ?string
    {
        if ($prefix === '' || $prefix === '.' || $prefix === '/') {
            return null;
        }

        if (strpos($path, $prefix) !== 0) {
            return null;
        }

        $rest = substr($path, strlen($prefix));

        return ($rest === '' || $rest[0] === '/') ? $rest : null;
    }

    /** @return array<string, mixed> */
    public function json(): array
    {
        if ($this->rawBody === '') {
            return [];
        }

        $decoded = json_decode($this->rawBody, true);

        return is_array($decoded) ? $decoded : [];
    }
}
