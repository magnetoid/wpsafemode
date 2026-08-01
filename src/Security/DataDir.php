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

    /** Extensions a web server will hand back verbatim without invoking PHP. */
    private const SERVABLE = [
        'json', 'jsonl', 'txt', 'log', 'sqlite', 'db', 'sql', 'bak',
        'md', 'yml', 'yaml', 'ini', 'conf', 'xml', 'csv', 'zip', 'gz',
    ];

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
                file_put_contents($path, $contents, LOCK_EX);
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
     * True when a web server would serve this path as a static asset. Files
     * with no extension count: nginx serves them as application/octet-stream.
     */
    public static function isServableExtension(string $path): bool
    {
        $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));

        if ($extension === '') {
            return true;
        }

        return in_array($extension, self::SERVABLE, true);
    }
}
