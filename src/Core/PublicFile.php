<?php

declare(strict_types=1);

namespace WPSafeMode\Core;

/** Resolves a request path to a file inside public/, and to nothing outside it. */
final class PublicFile
{
    /**
     * The real path of the file $path names under $publicDir, or null when it
     * names no file there. The containment check ends in a separator, so a
     * sibling that merely shares the prefix (public-x/) is outside.
     */
    public static function resolve(string $publicDir, string $path): ?string
    {
        // A NUL byte cannot name a file, and realpath() throws on one.
        if (strpos($path, "\0") !== false) {
            return null;
        }

        $root = realpath($publicDir);
        $real = realpath($publicDir . '/' . ltrim($path, '/'));

        if ($root === false || $real === false || strpos($real, $root . DIRECTORY_SEPARATOR) !== 0 || !is_file($real)) {
            return null;
        }

        return $real;
    }
}
