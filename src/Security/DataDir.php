<?php

declare(strict_types=1);

namespace WPSafeMode\Security;

use WPSafeMode\Storage\Contracts\StorageException;

/**
 * Creates and protects the data directory.
 *
 * v1.1 protected its state directory with an index.php containing a comment and
 * nothing else. That stops a directory listing and nothing more: both Apache and
 * nginx serve a sibling .json as a static file without ever invoking PHP, so
 * GET /<install-dir>/sfstore/global_settings.json returned the API key in
 * plaintext on every deployed install (spec 15.1). Three defences replace it,
 * and none of them is trusted alone:
 *
 *   1. this directory denial, which nginx ignores entirely,
 *   2. the die() guard every state file carries (GuardedJson), and
 *   3. the self-test in Task 7, which fetches our own URL and refuses to store
 *      an API key while the directory answers.
 */
final class DataDir
{
    /**
     * Prefixed to every state file. It must terminate the request before a
     * single payload byte is reachable, so die() precedes the newline that
     * separates it from the JSON.
     */
    public const GUARD = '<?php http_response_code(404); die("Not found"); ?>' . "\n";

    private const HTACCESS = <<<'HTACCESS'
        # WP Safe Mode data directory. Nothing here is meant to be reachable
        # over HTTP. nginx ignores this file entirely, which is why the
        # installer also runs a live web-accessibility self-test.
        <IfModule mod_authz_core.c>
            Require all denied
        </IfModule>
        <IfModule !mod_authz_core.c>
            Order allow,deny
            Deny from all
        </IfModule>
        HTACCESS;

    private const INDEX = '<?php http_response_code(404); die("Not found");' . "\n";

    /**
     * Extensions a PHP-capable web server executes rather than serves
     * verbatim. Deliberately the ONLY extensions this class trusts. The
     * previous version instead listed extensions it considered servable and
     * returned true only for names on that list — a list like that only has
     * to miss one hosting configuration (.sqlite3, .bak, an atomic-write
     * temp suffix, a bare filename with no extension at all, ...) to call a
     * secret-bearing file safe. Listing what we are sure IS executed, and
     * treating everything else as servable, is the fail-closed direction.
     */
    private const EXECUTABLE = ['php', 'phtml', 'php4', 'php5', 'php7', 'php8'];

    /**
     * How long a *.tmp.* file (GuardedJson's atomic-write naming
     * convention) is left alone before ensure() treats it as abandoned
     * rather than belonging to a write still in flight.
     */
    private const STALE_TEMP_FILE_AGE_SECONDS = 300;

    public static function ensure(string $dir): void
    {
        if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new StorageException('Cannot create data directory: ' . $dir);
        }

        @chmod($dir, 0700);

        foreach (self::protectionFiles() as $name => $contents) {
            $path = $dir . '/' . $name;

            // Written every time rather than only when absent: a host migration
            // or a control-panel "fix permissions" can silently remove them.
            if (!is_file($path) || file_get_contents($path) !== $contents) {
                // Checked against strlen(), not just !== false: a disk-full or
                // quota-limited write can return a short byte count rather than
                // false, and a caller that only checked for outright failure
                // would believe the directory was protected when it was not.
                if (@file_put_contents($path, $contents, LOCK_EX) !== strlen($contents)) {
                    throw new StorageException('Cannot write data directory blocker: ' . $path);
                }

                @chmod($path, 0600);
            }
        }

        self::sweepStaleTempFiles($dir);
    }

    /**
     * Removes leftover *.tmp.* files older than a few minutes. A fatal,
     * timeout, or OOM between GuardedJson::write()'s file_put_contents()
     * and its rename() leaves one of these behind holding a full,
     * UNGUARDED copy of whatever was being written — for KeyVault, the
     * master secret itself, in plaintext base64 — at a filename extension
     * isServableExtension() already classifies as servable, since it does
     * not end in .php. That turns the "partial disclosure" threat model
     * KeyVault's docblock names (a leaked backup, a directory listing, a
     * misconfigured data/ on nginx) into total vault compromise on exactly
     * that misconfigured host.
     *
     * Age-gated rather than swept on sight: a concurrent write's temp file
     * is legitimately present for the brief window between its own
     * creation and its rename, and removing it out from under that write
     * would turn a healthy request into a failed one. Every genuine
     * leftover was orphaned by a request that already ended, so it is
     * still here minutes later — nothing real is lost by waiting.
     */
    private static function sweepStaleTempFiles(string $dir): void
    {
        $cutoff = time() - self::STALE_TEMP_FILE_AGE_SECONDS;

        foreach (scandir($dir) ?: [] as $entry) {
            // Matches GuardedJson::write()'s '.tmp.' . bin2hex(random_bytes(4))
            // suffix precisely, so this cannot sweep a differently-named
            // file that merely happens to contain the substring '.tmp.'.
            if (!preg_match('/\.tmp\.[0-9a-f]{8}$/', $entry)) {
                continue;
            }

            $path = $dir . '/' . $entry;
            $modified = @filemtime($path);

            if (is_file($path) && $modified !== false && $modified < $cutoff) {
                @unlink($path);
            }
        }
    }

    /** @return array<string, string> filename => contents */
    public static function protectionFiles(): array
    {
        return [
            '.htaccess' => self::HTACCESS,
            'index.php' => self::INDEX,
        ];
    }

    /**
     * True when a web server would serve this path as a static asset rather
     * than executing it as PHP. Fails closed: anything other than the small,
     * precise set of extensions PHP is configured to execute — including an
     * empty extension, which nginx serves as application/octet-stream — is
     * treated as servable.
     */
    public static function isServableExtension(string $path): bool
    {
        $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));

        return !in_array($extension, self::EXECUTABLE, true);
    }
}
