<?php

declare(strict_types=1);

namespace WPSafeMode\Core;

use WPSafeMode\Security\Principal;

final class Request
{
    public function __construct(
        public string $method,
        public string $uri,
        public string $scriptName = '/index.php',
        public string $rawBody = '',
        /** @var array<string, mixed> */
        public array $server = [],
    ) {
    }

    /** Set by the Kernel once it has resolved who is calling. */
    public ?Principal $principal = null;

    /** @var array<string, string> Route placeholders, set by the Kernel. */
    public array $params = [];

    public static function fromGlobals(): self
    {
        return new self(
            method:     strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')),
            uri:        (string) ($_SERVER['REQUEST_URI'] ?? '/'),
            scriptName: (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'),
            rawBody:    (string) file_get_contents('php://input'),
            server:     $_SERVER,
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

    /** Case-insensitive; null when the header was not sent. */
    public function header(string $name): ?string
    {
        $upper = strtoupper(str_replace('-', '_', $name));
        $key = ($upper === 'CONTENT_TYPE' || $upper === 'CONTENT_LENGTH') ? $upper : 'HTTP_' . $upper;

        return isset($this->server[$key]) && is_scalar($this->server[$key]) ? (string) $this->server[$key] : null;
    }

    /**
     * Read from the Cookie header, never $_COOKIE. The first occurrence of a
     * name wins and the value is returned raw: session and CSRF tokens are
     * URL-safe by construction.
     */
    public function cookie(string $name): ?string
    {
        $header = $this->header('Cookie');

        if ($header === null) {
            return null;
        }

        foreach (explode(';', $header) as $pair) {
            $pos = strpos($pair, '=');

            if ($pos === false) {
                continue;
            }

            if (trim(substr($pair, 0, $pos)) === $name) {
                return trim(substr($pair, $pos + 1));
            }
        }

        return null;
    }

    /**
     * The direct peer only. X-Forwarded-For is never read: this value keys the
     * login lockout, and a client-set header would let an attacker spread
     * attempts across unlimited buckets.
     */
    public function clientIp(): string
    {
        $ip = $this->server['REMOTE_ADDR'] ?? '';

        return is_string($ip) && $ip !== '' ? $ip : 'unknown';
    }

    public function isHttps(): bool
    {
        $https = $this->server['HTTPS'] ?? '';

        if (is_scalar($https) && (string) $https !== '' && strtolower((string) $https) !== 'off') {
            return true;
        }

        return strtolower((string) $this->header('X-Forwarded-Proto')) === 'https';
    }

    public function userAgent(): string
    {
        return $this->header('User-Agent') ?? '';
    }

    /** The install directory as a URL path, used as the cookie path. */
    public function basePath(): string
    {
        return rtrim(dirname(str_replace('\\', '/', $this->scriptName)), '/');
    }

    /**
     * True when every listed field the JSON body carries has its declared
     * type. An absent field passes, and the caller applies its default; a
     * present one of any other type makes the whole request malformed, so a
     * string "false" is never read as a yes and an array never becomes text.
     *
     * @param array<string, string> $types field => 'string' or 'bool'
     */
    public function bodyHasTypes(array $types): bool
    {
        $body = $this->json();

        foreach ($types as $field => $type) {
            if (!array_key_exists($field, $body)) {
                continue;
            }

            $ok = $type === 'bool' ? is_bool($body[$field]) : is_string($body[$field]);

            if (!$ok) {
                return false;
            }
        }

        return true;
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
