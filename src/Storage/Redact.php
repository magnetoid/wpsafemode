<?php

declare(strict_types=1);

namespace WPSafeMode\Storage;

/**
 * Field-name redaction for anything written to a log.
 *
 * The audit log is the file most likely to end up in a support thread or a
 * screenshot. Nothing should be putting a secret in it, but "nothing should"
 * is how v1.1 ended up with the API key in a world-readable JSON file.
 */
final class Redact
{
    private const SECRET_KEYS = ['api_key', 'apikey', 'password', 'token', 'secret', 'hash', 'authorization'];

    /**
     * @param array<mixed> $meta
     * @return array<string, mixed>
     */
    public static function meta(array $meta): array
    {
        $clean = [];

        foreach ($meta as $key => $value) {
            $lower = strtolower((string) $key);
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
