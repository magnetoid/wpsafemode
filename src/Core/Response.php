<?php

declare(strict_types=1);

namespace WPSafeMode\Core;

final class Response
{
    /**
     * Sent with every answer: the shell, the assets, the API and the errors.
     * The install folder's random name is the secret part of its address, so
     * no page of it may hand that address to another site in a Referer.
     */
    public const SECURITY_HEADERS = [
        'Referrer-Policy'        => 'no-referrer',
        'X-Frame-Options'        => 'DENY',
        'X-Content-Type-Options' => 'nosniff',
    ];

    /** @param array<string, string> $headers */
    public function __construct(
        public int $status,
        public string $body,
        public array $headers = [],
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function json(array $data, int $status = 200): self
    {
        return new self(
            status:  $status,
            body:    (string) json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            headers: [
                'Content-Type'  => 'application/json; charset=utf-8',
                'Cache-Control' => 'no-store',
            ] + self::SECURITY_HEADERS,
        );
    }

    public static function notFound(): self
    {
        return self::json(['error' => 'not_found'], 404);
    }

    public static function methodNotAllowed(): self
    {
        return self::json(['error' => 'method_not_allowed'], 405);
    }

    public function send(): void
    {
        http_response_code($this->status);

        foreach ($this->headers + self::SECURITY_HEADERS as $name => $value) {
            header($name . ': ' . $value);
        }

        echo $this->body;
    }
}
