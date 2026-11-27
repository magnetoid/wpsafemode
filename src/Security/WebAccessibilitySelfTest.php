<?php

declare(strict_types=1);

namespace WPSafeMode\Security;

if (!defined('WPSM_ENTRY')) {
    http_response_code(404);
    exit;
}

use WPSafeMode\Core\Request;

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
 *
 * A refusal is trusted only after a positive control: the same transport,
 * through the same base URL, must reach this tool's own state route. Without
 * it, any other responder's 403 or 404 would count as "not readable", and an
 * undecided result is never a pass (amendment 11.5).
 */
final class WebAccessibilitySelfTest
{
    /**
     * The positive control: this tool's own unauthenticated state route,
     * which answers a JSON object with a boolean "installed" before and
     * after install. Every URL goes through index.php/ (R29).
     */
    public const CONTROL_PATH = '/index.php/api/install/state';

    public static function run(string $dataDir, ?string $baseUrl, ?SelfTestFetcher $fetcher = null): SelfTestResult
    {
        $now = time();

        if ($baseUrl === null) {
            return new SelfTestResult(
                false,
                true,
                "This site's address could not be worked out from the request, so the data directory could not "
                    . "be checked. Open the data folder's address in a browser yourself and confirm it is refused.",
                null,
                $now
            );
        }

        $fetcher = $fetcher ?? new SelfTestHttp();
        $baseUrl = rtrim($baseUrl, '/');
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

        $url = $baseUrl . '/data/' . $name;

        try {
            $response = $fetcher->fetch($url);
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

        // Reachable needs no control: only this server could have answered
        // with the random body just written.
        if ($status === 200 && $response['body'] === $secret) {
            return new SelfTestResult(
                true,
                false,
                'The data directory is readable over the web. Anything stored there, including the API key, '
                    . 'can be downloaded by anyone who guesses the filename.',
                $url,
                $now
            );
        }

        // Transport failure, redirect, 5xx, 429 or anything else: undecided, so fail closed.
        if ($status !== 200 && !in_array($status, [401, 403, 404, 410], true)) {
            return new SelfTestResult(
                false,
                true,
                'This server could not check its own URL (loopback requests may be blocked, or the request failed '
                    . 'or was redirected). Open ' . $url . ' in a browser yourself and confirm it is refused.',
                $url,
                $now
            );
        }

        // A refusal only counts if it came from this tool's own server. A WAF
        // or CDN challenge, a module that rejects the request, or a default
        // virtual host answering the loopback call all refuse too, while a
        // real browser may still fetch data/.
        if (!self::controlAnswers($fetcher, $baseUrl . self::CONTROL_PATH)) {
            return new SelfTestResult(
                false,
                true,
                'The data directory was refused, but the same address did not answer as this tool, so the refusal '
                    . 'may have come from a firewall, a CDN or another site. Open ' . $url . ' in a browser '
                    . 'yourself and confirm it is refused.',
                $url,
                $now
            );
        }

        return new SelfTestResult(false, false, 'The data directory is not readable over the web.', $url, $now);
    }

    /** True only for this tool's own answer: a JSON object with a boolean "installed". */
    private static function controlAnswers(SelfTestFetcher $fetcher, string $url): bool
    {
        $response = $fetcher->fetch($url);

        if ($response === null || $response['status'] !== 200) {
            return false;
        }

        $decoded = json_decode($response['body'], true);

        return is_array($decoded) && array_key_exists('installed', $decoded) && is_bool($decoded['installed']);
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

        // SCRIPT_NAME is decoded; a URL needs each segment encoded again.
        return $scheme . '://' . $host . ($dir === '.' ? '' : Request::encodePath($dir));
    }
}
