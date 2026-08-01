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
     * verbatim. Deliberately the ONLY extensions this class trusts: a
     * denylist here only has to miss one hosting configuration (.sqlite3,
     * .bak, an atomic-write temp suffix, a bare filename with no extension
     * at all, ...) to serve a secret in plaintext, so the safe direction is
     * an allowlist of what we are sure is executed, not a denylist of what
     * we happen to have thought of as "servable".
     */
    private const EXECUTABLE = ['php', 'phtml', 'php4', 'php5', 'php7', 'php8'];

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
