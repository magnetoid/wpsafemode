<?php

declare(strict_types=1);

namespace WPSafeMode\Core;

if (!defined('WPSM_ENTRY')) {
    http_response_code(404);
    exit;
}

use WPSafeMode\Security\DataDir;

/**
 * Where PHP's own error log goes, and what the composition root writes to it.
 *
 * PHP's default log on cPanel-style hosts is a file named error_log beside the
 * script, which the web server can serve. A stack trace there carries absolute
 * paths, and one from inside the installer could carry the API key. So the log
 * is pointed at a die-guarded file inside the data directory, and when that
 * directory cannot be prepared PHP's own logging is turned off and only an
 * exception's class name is written anywhere.
 */
final class ErrorLog
{
    public const FILE = 'php-errors.php';

    private static bool $ready = false;

    /** True when PHP now logs to <dataDir>/php-errors.php. */
    public static function prepare(string $dataDir): bool
    {
        try {
            DataDir::ensure($dataDir);

            $path = $dataDir . '/' . self::FILE;
            $head = is_file($path) ? @file_get_contents($path, false, null, 0, strlen(DataDir::GUARD)) : '';

            // A file that does not begin with the guard would be served as
            // text by the first PHP request that reached it: start it over.
            if ($head !== DataDir::GUARD) {
                if (@file_put_contents($path, DataDir::GUARD, LOCK_EX) !== strlen(DataDir::GUARD)) {
                    throw new \RuntimeException('The error log could not be created.');
                }

                @chmod($path, 0600);
            }

            if (ini_set('error_log', $path) === false) {
                throw new \RuntimeException('The error log could not be selected.');
            }

            ini_set('log_errors', '1');

            return self::$ready = true;
        } catch (\Throwable $e) {
            // Nowhere safe to write: PHP logs nothing of its own.
            ini_set('log_errors', '0');

            return self::$ready = false;
        }
    }

    /**
     * Class and message when the guarded log is in use, the class alone
     * otherwise: a message can carry an absolute path. Never the trace.
     */
    public static function record(\Throwable $e): void
    {
        error_log(
            'WP Safe Mode: ' . get_class($e) . (self::$ready ? ': ' . $e->getMessage() : '')
        );
    }
}
