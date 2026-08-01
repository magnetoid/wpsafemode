<?php

declare(strict_types=1);

namespace WPSafeMode\Security;

use JsonException;
use WPSafeMode\Storage\Contracts\StorageException;

/**
 * A JSON document behind a PHP die() guard.
 *
 * The file is named .php so the server executes it, and the first line exits
 * before the payload, so a direct request returns 404 even when the directory
 * itself is reachable. Reads strip the guard; nothing else in the codebase
 * needs to know it exists.
 *
 * Writes go to a temporary file and are moved into place with rename(), which
 * is atomic within a filesystem, so a crashed request leaves either the
 * previous complete file or the new one, never a truncated one.
 */
final class GuardedJson
{
    /** @return array<string, mixed> */
    public static function read(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }

        $raw = (string) file_get_contents($path);

        if ($raw === '') {
            return [];
        }

        $payload = self::stripGuard($raw);

        if (trim($payload) === '') {
            return [];
        }

        try {
            $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new StorageException('Corrupt state file: ' . basename($path), 0, $e);
        }

        return is_array($decoded) ? $decoded : [];
    }

    /** @param array<string, mixed> $data */
    public static function write(string $path, array $data): void
    {
        DataDir::ensure(dirname($path));

        $payload = DataDir::GUARD . self::encode($path, $data);
        $tmp = $path . '.tmp.' . bin2hex(random_bytes(4));

        // Checked against strlen(), not just !== false: a disk-full write
        // returns a short byte count, not false, and that count would still
        // pass an === false test, get renamed over the previous good file,
        // and (see stripGuard()) read back as an empty store on the next
        // request — silently erasing everything that was there before.
        if (@file_put_contents($tmp, $payload, LOCK_EX) !== strlen($payload)) {
            @unlink($tmp);
            throw new StorageException('Cannot write state file: ' . basename($path));
        }

        @chmod($tmp, 0600);

        if (!rename($tmp, $path)) {
            @unlink($tmp);
            throw new StorageException('Cannot commit state file: ' . basename($path));
        }
    }

    /**
     * Like write(), but never overwrites an existing file: an atomic
     * O_CREAT|O_EXCL create rather than write()'s unconditional temp+rename,
     * which has no create-exclusive semantics of its own — that is the
     * right default for every OTHER caller (a config update should always
     * win), so the exclusivity lives here instead of in write() itself.
     *
     * Returns false, rather than throwing, when the create loses that race:
     * for a "first run" value like KeyVault's master secret, that means a
     * concurrent request already minted one, and the caller should read
     * what it wrote instead of treating the loss as a failure.
     *
     * link(), not a direct fopen($path, 'xb'): an earlier version of this
     * method opened $path itself in 'xb' mode and wrote the content into
     * that handle. fopen($path, 'xb') alone creates $path — empty — before
     * a single content byte exists, so a crash between that call and the
     * fwrite() that followed it (max_execution_time, OOM, a php-fpm
     * restart, SIGKILL — not hypothetical on the WordPress installs this
     * ships into, which are already fataling, timing out, or OOMing) left a
     * permanent zero-length file at $path. That is silent, permanent
     * poisoning for a caller like KeyVault: a file that exists but yields
     * no usable secret is never treated as "missing" (by design — see
     * readStoredSecret()), so every later request throws "corrupt" forever,
     * with no self-healing. Pre-fix (write()'s temp+rename), the same crash
     * left NO file at all and the next request minted cleanly — so the
     * exclusivity fix above had introduced a regression, not a pre-existing
     * risk. Writing the full, chmod'd content to a uniquely-named temp file
     * FIRST, then publishing it with a single link() call — which fails
     * with EEXIST if $path already exists, preserving the exact same
     * exclusivity — means $path is only ever observable as either absent or
     * completely written; there is no partial state left for a crash to
     * freeze. A crash before the link() leaves only a stray *.tmp.* file,
     * which DataDir::ensure()'s sweep already collects.
     *
     * @param array<string, mixed> $data
     */
    public static function createExclusive(string $path, array $data): bool
    {
        DataDir::ensure(dirname($path));

        $tmp = self::writeTemp($path, $data);
        $linked = @link($tmp, $path);
        @unlink($tmp);

        return $linked;
    }

    /**
     * Writes the full, guarded, chmod'd payload to a freshly-named
     * temporary file and returns its path, touching $path itself not at
     * all. Split out of createExclusive() so "the process died before
     * $path was ever touched" is a state a test can produce directly,
     * rather than something only true by argument.
     *
     * @param array<string, mixed> $data
     */
    private static function writeTemp(string $path, array $data): string
    {
        $payload = DataDir::GUARD . self::encode($path, $data);
        $tmp = $path . '.tmp.' . bin2hex(random_bytes(4));

        if (@file_put_contents($tmp, $payload, LOCK_EX) !== strlen($payload)) {
            @unlink($tmp);
            throw new StorageException('Cannot write state file: ' . basename($path));
        }

        @chmod($tmp, 0600);

        return $tmp;
    }

    /** @param array<string, mixed> $data */
    private static function encode(string $path, array $data): string
    {
        try {
            return (string) json_encode(
                $data,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                    | JSON_THROW_ON_ERROR | JSON_HEX_TAG
            );
        } catch (JsonException $e) {
            throw new StorageException('Cannot encode state file: ' . basename($path), 0, $e);
        }
    }

    /**
     * Tolerates a file written without the guard. Nothing in this codebase
     * writes one, but a hand-edited or restored-from-backup file should be
     * readable rather than an unexplained empty store.
     */
    private static function stripGuard(string $raw): string
    {
        if (strncmp($raw, '<?php', 5) !== 0) {
            return $raw;
        }

        $newline = strpos($raw, "\n");

        // No newline means the write was cut off before the payload
        // separator ever landed — truncated, not empty. Returning the raw
        // (still-guarded) bytes here sends them into the JSON decode below,
        // which is guaranteed to reject them loudly. The alternative, '',
        // reads back as an empty store and lets a caller's read-modify-write
        // persist that emptiness as the new truth.
        return $newline === false ? $raw : substr($raw, $newline + 1);
    }
}
