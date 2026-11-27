<?php

declare(strict_types=1);

namespace WPSafeMode\Security;

/**
 * Proves whether the data directory is readable over HTTP.
 *
 * Writes a canary with a deliberately servable extension into data/, then
 * fetches it over the network from our own URL. If the body comes back, the
 * directory is exposed: .htaccess is being ignored, which is what nginx always
 * does, and every state file is one guessed filename from disclosure.
 *
 * The canary uses .txt on purpose. Testing with a .php file would prove only
 * that PHP executes, not that static files are denied — and static files are
 * exactly what leaked in v1.1 (spec 15.1).
 */
final class WebAccessibilitySelfTest
{
    private const TIMEOUT_SECONDS = 5;

    public static function run(string $dataDir, string $baseUrl): SelfTestResult
    {
        $now = time();
        $name = 'canary-' . bin2hex(random_bytes(8)) . '.txt';
        $secret = bin2hex(random_bytes(16));
        $path = $dataDir . '/' . $name;

        if (@file_put_contents($path, $secret, LOCK_EX) === false) {
            return new SelfTestResult(
                false,
                true,
                'Could not write a test file into the data directory, so its exposure could not be checked.',
                null,
                $now
            );
        }

        $url = rtrim($baseUrl, '/') . '/data/' . $name;

        try {
            $response = self::fetch($url);
        } finally {
            @unlink($path);
        }

        if ($response === null) {
            return new SelfTestResult(
                false,
                true,
                'No way to make an HTTP request was available (cURL and remote file access are both disabled), '
                    . 'so the data directory could not be checked. Open ' . $url . ' in a browser yourself '
                    . 'and confirm it is refused.',
                $url,
                $now
            );
        }

        $status = $response['status'];
        $body = $response['body'];

        if ($status === 200 && $body === $secret) {
            return new SelfTestResult(
                true,
                false,
                'The data directory is readable over the web. Anything stored there, including the API key, '
                    . 'can be downloaded by anyone who guesses the filename.',
                $url,
                $now
            );
        }

        // The server answered and did not serve the file.
        if ($status === 200 || in_array($status, [401, 403, 404, 410], true)) {
            return new SelfTestResult(
                false,
                false,
                'The data directory is not readable over the web.',
                $url,
                $now
            );
        }

        // Transport failure, redirect, 5xx, 429 or anything else: undecided, so fail closed.
        return new SelfTestResult(
            false,
            true,
            'This server could not check its own URL (loopback requests may be blocked, or the request failed '
                . 'or was redirected). Open ' . $url . ' in a browser yourself and confirm it is refused.',
            $url,
            $now
        );
    }

    /**
     * @return array{status: int, body: string}|null status 0 means the request failed;
     *         null means no HTTP client exists. Redirects are never followed.
     */
    private static function fetch(string $url): ?array
    {
        if (function_exists('curl_init')) {
            $handle = curl_init($url);

            if ($handle !== false) {
                curl_setopt_array($handle, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT        => self::TIMEOUT_SECONDS,
                    CURLOPT_CONNECTTIMEOUT => self::TIMEOUT_SECONDS,
                    CURLOPT_FOLLOWLOCATION => false,
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
        }

        if (filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) {
            $context = stream_context_create([
                'http' => [
                    'timeout'         => self::TIMEOUT_SECONDS,
                    'ignore_errors'   => true,
                    'follow_location' => 0,
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

        return null;
    }

    /**
     * The public URL of the install directory, derived from the request.
     *
     * @param array<string, mixed> $server
     */
    public static function baseUrlFrom(array $server): ?string
    {
        $host = (string) ($server['HTTP_HOST'] ?? $server['SERVER_NAME'] ?? '');

        // The Host header is client-controlled. Anything that is not a plain
        // host[:port] could truncate the fixed path suffix and aim the request
        // elsewhere (SSRF), so refuse it.
        $valid = preg_match('/^[A-Za-z0-9.-]+(:[0-9]{1,5})?\z/', $host) === 1
            || preg_match('/^\[[0-9A-Fa-f:.]+\](:[0-9]{1,5})?\z/', $host) === 1;

        if (!$valid) {
            return null;
        }

        $https = ($server['HTTPS'] ?? '') !== '' && strtolower((string) $server['HTTPS']) !== 'off';
        $forwarded = strtolower((string) ($server['HTTP_X_FORWARDED_PROTO'] ?? ''));
        $scheme = ($https || $forwarded === 'https') ? 'https' : 'http';

        $dir = rtrim(str_replace('\\', '/', dirname((string) ($server['SCRIPT_NAME'] ?? '/index.php'))), '/');

        return $scheme . '://' . $host . ($dir === '.' ? '' : $dir);
    }
}
