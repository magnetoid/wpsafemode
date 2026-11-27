<?php

declare(strict_types=1);

namespace WPSafeMode\Security;

if (!defined('WPSM_ENTRY')) {
    http_response_code(404);
    exit;
}

/**
 * The real transport: cURL when it exists, PHP's http stream otherwise.
 * Both send the same generic User-Agent (some WAFs reject a request with
 * none, which would read as "refused") and neither follows a redirect.
 */
final class SelfTestHttp implements SelfTestFetcher
{
    public const USER_AGENT = 'WPSafeMode-SelfTest';

    private const TIMEOUT_SECONDS = 5;

    public function fetch(string $url): ?array
    {
        if (function_exists('curl_init')) {
            $response = self::viaCurl($url);

            if ($response !== null) {
                return $response;
            }
        }

        if (filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) {
            return self::viaStream($url);
        }

        return null;
    }

    /** @return array{status: int, body: string}|null null when cURL cannot start */
    public static function viaCurl(string $url): ?array
    {
        $handle = curl_init($url);

        if ($handle === false) {
            return null;
        }

        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TIMEOUT_SECONDS,
            CURLOPT_CONNECTTIMEOUT => self::TIMEOUT_SECONDS,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_USERAGENT      => self::USER_AGENT,
            // Self-signed certificates are common on staging hosts. The
            // canary is a random string we just wrote and are only
            // comparing against; there is nothing here to protect.
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ]);

        $body = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);

        if ($body === false) {
            return ['status' => 0, 'body' => ''];
        }

        return ['status' => $status, 'body' => (string) $body];
    }

    /** @return array{status: int, body: string} */
    public static function viaStream(string $url): array
    {
        $context = stream_context_create([
            'http' => [
                'timeout'         => self::TIMEOUT_SECONDS,
                'ignore_errors'   => true,
                'follow_location' => 0,
                'max_redirects'   => 0,
                'user_agent'      => self::USER_AGENT,
            ],
            'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false],
        ]);

        // The stream's own metadata carries the response headers. The magic
        // local variable is not used: PHP 8.4+ warns about every mention of
        // it, and that warning would be printed into the JSON answer.
        $stream = @fopen($url, 'r', false, $context);

        if ($stream === false) {
            return ['status' => 0, 'body' => ''];
        }

        $body = @stream_get_contents($stream);
        $meta = stream_get_meta_data($stream);
        fclose($stream);

        $headers = isset($meta['wrapper_data']) && is_array($meta['wrapper_data']) ? $meta['wrapper_data'] : [];
        $status = 0;

        if ($headers !== [] && preg_match('~^HTTP/\S+\s+(\d{3})~', (string) reset($headers), $m) === 1) {
            $status = (int) $m[1];
        }

        return ['status' => $body === false ? 0 : $status, 'body' => $body === false ? '' : (string) $body];
    }
}
