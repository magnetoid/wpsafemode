<?php

declare(strict_types=1);

namespace WPSafeMode\Storage;

/**
 * Key-name redaction for anything written to a log.
 *
 * This is by KEY NAME only. Each key is lowercased and stripped of "-", "_",
 * "." and spaces, then matched by substring against a list of secret-looking
 * names, so api_key, API-KEY, apiKey and session_token are all caught, at any
 * depth. It does not inspect values: a secret stored under a benign key is
 * written verbatim, so callers must never put a secret value in meta. Plain
 * "key" is deliberately not on the list, because config_change meta names the
 * changed setting under it.
 */
final class Redact
{
    private const SECRET_KEYS = [
        'apikey', 'password', 'passwd', 'pwd', 'token', 'secret', 'hash',
        'authorization', 'cookie', 'bearer', 'credential', 'privatekey',
    ];

    /**
     * @param array<mixed> $meta
     * @return array<string, mixed>
     */
    public static function meta(array $meta): array
    {
        $clean = [];

        foreach ($meta as $key => $value) {
            $lower = str_replace(['-', '_', '.', ' '], '', strtolower((string) $key));
            $isSecret = false;

            foreach (self::SECRET_KEYS as $needle) {
                if (strpos($lower, $needle) !== false) {
                    $isSecret = true;
                    break;
                }
            }

            if ($isSecret) {
                $clean[(string) $key] = '[redacted]';
                continue;
            }

            $clean[(string) $key] = is_array($value) ? self::meta($value) : $value;
        }

        return $clean;
    }
}
