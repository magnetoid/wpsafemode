<?php

declare(strict_types=1);

namespace WPSafeMode\Tests\Security;

use PHPUnit\Framework\TestCase;
use WPSafeMode\Security\DataDir;
use WPSafeMode\Storage\Contracts\StorageException;
use WPSafeMode\Storage\Message;
use WPSafeMode\Storage\Session;
use WPSafeMode\Storage\StorageFactory;

final class DataDirTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/wpsm-datadir-' . bin2hex(random_bytes(6)) . '/data';
    }

    protected function tearDown(): void
    {
        foreach (glob(dirname($this->dir) . '/data/*') ?: [] as $file) {
            unlink($file);
        }
        @rmdir($this->dir);
        @rmdir(dirname($this->dir));
    }

    public function testEnsureCreatesThePrivateDirectoryAndBothBlockers(): void
    {
        DataDir::ensure($this->dir);

        self::assertDirectoryExists($this->dir);
        self::assertSame('0700', substr(sprintf('%o', fileperms($this->dir)), -4));
        self::assertFileExists($this->dir . '/.htaccess');
        self::assertFileExists($this->dir . '/index.php');
    }

    public function testHtaccessDeniesOnBothApacheGenerations(): void
    {
        DataDir::ensure($this->dir);
        $htaccess = (string) file_get_contents($this->dir . '/.htaccess');

        // 2.4 syntax and the 2.2 fallback both matter: shared hosts run either,
        // and an unrecognised directive is a 500, not a silent no-op.
        self::assertStringContainsString('Require all denied', $htaccess);
        self::assertStringContainsString('Deny from all', $htaccess);
    }

    public function testEnsureIsIdempotentAndDoesNotClobberExistingState(): void
    {
        DataDir::ensure($this->dir);
        file_put_contents($this->dir . '/config.php', 'sentinel');

        DataDir::ensure($this->dir);

        self::assertSame('sentinel', file_get_contents($this->dir . '/config.php'));
    }

    public function testServableExtensionsAreRecognised(): void
    {
        self::assertTrue(DataDir::isServableExtension('data/config.json'));
        self::assertTrue(DataDir::isServableExtension('data/messages.jsonl'));
        self::assertTrue(DataDir::isServableExtension('data/store.sqlite'));
        self::assertTrue(DataDir::isServableExtension('data/keyvault'));
        self::assertFalse(DataDir::isServableExtension('data/config.php'));
        self::assertFalse(DataDir::isServableExtension('data/index.php'));
    }

    public function testExtensionsMissingFromAnyDenylistAreTreatedAsServable(): void
    {
        // isServableExtension() must fail closed: a denylist of "known
        // servable" extensions only has to miss one real hosting
        // configuration to wrongly declare a secret-bearing file safe. Every
        // one of these previously came back false (safe) under the old
        // allowlist-of-known-dangerous-extensions implementation, even
        // though a real web server would serve every one of them verbatim.
        $wronglyTrustedBefore = [
            'data/store.sqlite3',
            'data/store.db3',
            'data/keyvault.dat',
            'data/keyvault.bin',
            'data/keyvault.key',
            'data/keyvault.pem',
            'data/config.env',
            'data/config.php.old',
            'data/backup.tar',
            'data/backup.7z',
            'data/settings.toml',
            // The atomic-write temp suffix used by GuardedJson::write(): it
            // does not end in .php, so `location ~ \.php$` would not route
            // it to the PHP handler — nginx serves it as a static file.
            'data/config.php.tmp.a1b2c3d4',
        ];

        foreach ($wronglyTrustedBefore as $path) {
            self::assertTrue(
                DataDir::isServableExtension($path),
                $path . ' must be treated as servable (fail closed)'
            );
        }
    }

    public function testNoFileTheApplicationWritesIsServableAsAStaticAsset(): void
    {
        // The whole point of spec 15.1: every filename the storage layer and the
        // security layer produce must be guarded or unguessable, so this list is
        // the contract. A new state file added without a .php extension fails here.
        $written = [
            'data/install.php',
            'data/config.php',
            'data/auth.php',
            'data/auth_sessions.php',
            'data/rate_limit.php',
            'data/audit_log.php',
            'data/keyvault.php',
            'data/sessions/sesn_1/session.php',
            'data/sessions/sesn_1/messages.php',
        ];

        foreach ($written as $path) {
            self::assertFalse(
                DataDir::isServableExtension($path),
                $path . ' would be served as a static asset'
            );
        }
    }

    // --- review round 1 -----------------------------------------------

    public function testEnsureThrowsWhenABlockerCannotBeWritten(): void
    {
        mkdir($this->dir, 0700, true);
        // Forces file_put_contents() to fail deterministically and
        // portably — even running as root, which bypasses permission bits —
        // by making the write target itself a directory rather than relying
        // on chmod, which ensure()'s own @chmod($dir, 0700) would undo.
        mkdir($this->dir . '/.htaccess');

        try {
            $this->expectException(StorageException::class);
            DataDir::ensure($this->dir);
        } finally {
            @rmdir($this->dir . '/.htaccess');
        }
    }

    public function testEveryFileTheJsonBackendProducesIsGuardedOrUnguessable(): void
    {
        // Reverting config.php/session.php/messages.php back to
        // .json/.jsonl must fail a test somewhere. This one drives the real
        // production path — StorageFactory::create() through the actual
        // JsonStorage/JsonSessionRepo/JsonMessageLog classes, not a
        // test-only shortcut — and pins every filename it produces.
        $dir = sys_get_temp_dir() . '/wpsm-datadir-contract-' . bin2hex(random_bytes(6));

        try {
            $storage = StorageFactory::create($dir, 'json');

            $storage->config()->set('model', 'claude-opus-4-8');

            $storage->sessions()->create(new Session(
                id:        'sesn_1',
                title:     'White screen on example.com',
                blade:     Session::BLADE_RESCUE,
                status:    Session::STATUS_IDLE,
                model:     'claude-opus-4-8',
                effort:    'high',
                createdAt: 1000,
                updatedAt: 1000,
            ));

            $storage->messages()->append('sesn_1', Message::ROLE_USER, [['type' => 'text', 'text' => 'hi']]);

            // .htaccess/index.php are the directory's own protection
            // markers (asserted elsewhere in this file), not state produced
            // by the storage layer, so they are excluded from this check —
            // asserting isServableExtension() is false for .htaccess itself
            // would be asserting the wrong thing (its own extension,
            // "htaccess", is not PHP-executable, and that is a separate,
            // already-documented residual risk, not a state-file leak).
            $protectionNames = array_keys(DataDir::protectionFiles());
            $stateFiles = array_values(array_filter(
                $this->filesUnder($dir),
                static fn (string $file): bool => !in_array(basename($file), $protectionNames, true)
            ));

            self::assertNotEmpty(
                $stateFiles,
                'the storage layer produced no files — this assertion would be vacuous otherwise'
            );

            foreach ($stateFiles as $file) {
                self::assertFalse(
                    DataDir::isServableExtension($file),
                    $file . ' would be served as a static asset'
                );
            }
        } finally {
            $this->removeRecursively($dir);
        }
    }

    /** @return list<string> */
    private function filesUnder(string $dir): array
    {
        $files = [];

        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $full = $dir . '/' . $entry;

            if (is_dir($full)) {
                foreach ($this->filesUnder($full) as $nested) {
                    $files[] = $nested;
                }
            } else {
                $files[] = $full;
            }
        }

        return $files;
    }

    private function removeRecursively(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $full = $dir . '/' . $entry;
            is_dir($full) ? $this->removeRecursively($full) : unlink($full);
        }

        rmdir($dir);
    }
}
