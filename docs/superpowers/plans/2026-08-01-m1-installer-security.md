# M1 — Installer and Security Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the tool refuse to do anything until it has been installed, and make an installed copy defensible — password-gated, rate-limited, CSRF-protected, with its data directory proven unreachable over HTTP before an API key is ever accepted.

**Architecture:** A `Security\` namespace holds the guard rails (data-directory protection, key vault, password hashing, sessions, CSRF, the web-accessibility self-test). An `Installer\` namespace drives a five-step wizard, and `bootstrap.php` grows a gate that returns `not_installed` for every endpoint except the installer's own. Two more storage contracts — `AuthStore` and `RateLimiter` — join the three M0 proved, and every file the JSON backend writes gets a PHP die-guard so that a web server which serves `data/` statically discloses nothing.

**Tech Stack:** PHP 8.0 (no Composer, no runtime dependencies), SQLite via PDO or JSON files, libsodium with an OpenSSL fallback, cURL, PHPUnit 9.6, PHPStan 2.1, Preact + Vite + Tailwind.

## Global Constraints

Every task's requirements implicitly include this section.

- **PHP 8.0.0 is the floor.** No `readonly`, no enums, no `never`, no first-class callable syntax, no `array_is_list()`, no `new` in initializers. `match` and constructor property promotion are allowed. Verify with `vendor/bin/phpstan analyse` — `phpstan.neon.dist` pins `phpVersion: 80000`.
- **Nothing under `src/` may require anything from `vendor/`.** The shipped ZIP has zero dependencies (spec §2). `vendor/` is dev tooling only.
- **Never modify v1.1 files.** `controller/`, `model/`, `view/`, `core/`, `services/`, `helpers/`, `security/`, `ext/`, `assets/`, `sfstore/`, and the root `autoload.php` and `index.php` belong to v1.1 and are out of scope for every task in this plan. Note the collision: v1.1 has a lowercase root `security/`; v2's is `src/Security/`. They are different directories and only the latter may be touched.
- **`data/` is never committed.** It is already in `.gitignore`.
- **No absolute server path may reach the browser or the model** (spec §5.7). `EnvironmentReport::toArray()` already omits `wpRoot`; every new response follows the same rule.
- **No file under `data/` may carry an extension a web server will serve as a static asset** (spec §15.1 corollary). This overrides the `.json`/`.jsonl` filenames drawn in spec §7.2 — see Task 1.
- **Commit after every task**, conventional commit messages, small commits.
- **Run `vendor/bin/phpstan analyse --no-progress` and `vendor/bin/phpunit` before every commit.** Where Docker is available, `make test` is the reference; `make check-host` is the fallback.

## Where M1 starts

M0 is complete on `feat/m0-skeleton` (PR #1): autoloader, `Environment` probe, `Router`/`Request`/`Response`, `/api/env`, and the storage layer — `ConfigStore`, `SessionRepo`, `MessageLog` — implemented twice behind `Storage`, with `StorageConformanceTest` run against both backends.

M1 builds on exactly that and changes three M0 files: `JsonFile` (die-guard), `StorageFactory` (guarded backend marker, random SQLite filename), and `src/index.php` (install gate, new routes).

## File Structure

**New — security primitives** (`src/Security/`, one responsibility each):

| File | Responsibility |
|---|---|
| `DataDir.php` | Creates and protects `data/`: `0700`, `.htaccess`, `index.php` blocker. Owns the guard string and the list of protection files. |
| `GuardedJson.php` | Read/write a die-guarded JSON document. The single place that knows the guard is a line prefix. |
| `GuardedJsonLines.php` | Append/stream a die-guarded JSONL log. |
| `KeyVault.php` | Master secret at `0600`; `encrypt()`/`decrypt()` via sodium, falling back to OpenSSL AES-256-GCM. |
| `PasswordHasher.php` | argon2id when the build has it, bcrypt cost 12 otherwise; records which, and rehashes on login. |
| `AuthSessions.php` | Our own session storage. Issues, validates, refreshes and revokes login tokens; sets the cookie flags. |
| `Csrf.php` | Issues and verifies a token bound to the login session. |
| `WebAccessibilitySelfTest.php` | Writes a canary into `data/`, fetches it over HTTP, reports whether `data/` is web-readable. |

**New — storage contracts and their two implementations:**

`src/Storage/Contracts/AuthStore.php`, `RateLimiter.php`; `src/Storage/{Sqlite,Json}/{SqliteAuthStore,SqliteRateLimiter,JsonAuthStore,JsonRateLimiter}.php`; value objects `src/Storage/Credential.php` and `src/Storage/RateLimitState.php`.

**New — installer and API:**

`src/Installer/Installer.php` (state machine for the wizard), `src/Installer/SelfRename.php`, `src/Installer/Uninstaller.php`, `src/Api/InstallController.php`, `src/Api/AuthController.php`, `src/Api/Middleware.php` (install gate + auth gate + CSRF gate).

**Modified:** `src/Storage/Json/JsonFile.php`, `src/Storage/JsonStorage.php`, `src/Storage/SqliteStorage.php`, `src/Storage/StorageFactory.php`, `src/Storage/Sqlite/SqliteSchema.php`, `src/Storage/Contracts/Storage.php`, `src/index.php`, `tests/Storage/StorageConformanceTest.php` and both backend subclasses, `frontend/src/app.jsx`.

## Two spec conflicts this plan resolves

**1. Filenames under `data/`.** §7.2 draws `config.json`, `messages.jsonl`, `auth.json`. §15.1's corollary says no file under `data/` may have an extension the web server serves statically, and that state files are `.php` with a leading `die()` guard. The corollary wins: it is the lesson from the v1.1 failure that shipped, where `GET /<install-dir>/sfstore/global_settings.json` returned the API key in plaintext. Every JSON and JSONL file in §7.2 becomes `.php` with the guard, keeping its base name (`config.php`, `messages.php`). Task 1 does this.

**2. The SQLite file cannot be guarded.** A `.php`-named SQLite database is worse than a `.sqlite` one: the server hands it to PHP, PHP finds no opening tag and echoes the whole binary verbatim. Renaming does not help. So the SQLite file is defended by three other means together — directory denial (`.htaccess`/`index.php`), a 32-hex-character random filename an attacker cannot guess, and the self-test that refuses to store an API key while `data/` is reachable. The random name is recorded in `data/install.php`, which *is* guarded. Task 1 does this too.

---

## Task 1: Data-directory protection and die-guarded state files

**Files:**
- Create: `src/Security/DataDir.php`
- Create: `src/Security/GuardedJson.php`
- Create: `src/Security/GuardedJsonLines.php`
- Modify: `src/Storage/Json/JsonFile.php` (delegate to `GuardedJson`)
- Modify: `src/Storage/Json/JsonMessageLog.php` (delegate to `GuardedJsonLines`, rename `messages.jsonl` → `messages.php`)
- Modify: `src/Storage/Json/JsonSessionRepo.php` (`session.json` → `session.php`)
- Modify: `src/Storage/JsonStorage.php` (`config.json` → `config.php`)
- Modify: `src/Storage/StorageFactory.php` (guarded install record, random SQLite filename)
- Test: `tests/Security/DataDirTest.php`, `tests/Security/GuardedFileTest.php`

**Interfaces:**
- Consumes: `StorageException` from M0.
- Produces: `DataDir::ensure(string $dir): void`, `DataDir::GUARD` (string), `DataDir::protectionFiles(): array<string,string>`, `DataDir::isServableExtension(string $path): bool`. `GuardedJson::read(string $path): array`, `GuardedJson::write(string $path, array $data): void`. `GuardedJsonLines::append(string $path, array $row): void`, `GuardedJsonLines::stream(string $path): Generator`, `GuardedJsonLines::count(string $path): int`. `StorageFactory::open()` keeps its M0 signature. Tasks 2–12 store everything through these.

- [ ] **Step 1: Write the failing tests**

Create `tests/Security/GuardedFileTest.php`:

```php
<?php

declare(strict_types=1);

namespace WPSafeMode\Tests\Security;

use PHPUnit\Framework\TestCase;
use WPSafeMode\Security\DataDir;
use WPSafeMode\Security\GuardedJson;
use WPSafeMode\Security\GuardedJsonLines;

final class GuardedFileTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/wpsm-guard-' . bin2hex(random_bytes(6));
        mkdir($this->dir, 0700, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->dir);
    }

    public function testJsonRoundTripsThroughTheGuard(): void
    {
        $path = $this->dir . '/config.php';
        GuardedJson::write($path, ['api_key' => 'sk-ant-secret', 'model' => 'claude-opus-5']);

        self::assertSame(
            ['api_key' => 'sk-ant-secret', 'model' => 'claude-opus-5'],
            GuardedJson::read($path)
        );
    }

    public function testGuardIsTheFirstThingInTheFile(): void
    {
        $path = $this->dir . '/config.php';
        GuardedJson::write($path, ['secret' => 'value']);

        $raw = (string) file_get_contents($path);

        self::assertStringStartsWith('<?php', $raw);
        // The guard must terminate the script before any payload byte is
        // reachable, so the die() has to precede the first newline.
        $firstLine = strtok($raw, "\n");
        self::assertStringContainsString('die', (string) $firstLine);
        self::assertStringNotContainsString('value', (string) $firstLine);
    }

    public function testReadingAnUnguardedLegacyFileStillWorks(): void
    {
        $path = $this->dir . '/plain.php';
        file_put_contents($path, '{"a":1}');

        self::assertSame(['a' => 1], GuardedJson::read($path));
    }

    public function testMissingFileReadsAsEmpty(): void
    {
        self::assertSame([], GuardedJson::read($this->dir . '/absent.php'));
    }

    public function testWrittenFilesAreNotGroupOrWorldReadable(): void
    {
        $path = $this->dir . '/config.php';
        GuardedJson::write($path, ['a' => 1]);

        self::assertSame('0600', substr(sprintf('%o', fileperms($path)), -4));
    }

    public function testJsonLinesAppendsBelowTheGuardAndStreamsBack(): void
    {
        $path = $this->dir . '/audit.php';
        GuardedJsonLines::append($path, ['event' => 'install']);
        GuardedJsonLines::append($path, ['event' => 'login']);

        $rows = iterator_to_array(GuardedJsonLines::stream($path), false);

        self::assertSame([['event' => 'install'], ['event' => 'login']], $rows);
        self::assertSame(2, GuardedJsonLines::count($path));
        self::assertStringStartsWith('<?php', (string) file_get_contents($path));
    }

    public function testJsonLinesGuardIsWrittenOnlyOnce(): void
    {
        $path = $this->dir . '/audit.php';
        GuardedJsonLines::append($path, ['n' => 1]);
        GuardedJsonLines::append($path, ['n' => 2]);
        GuardedJsonLines::append($path, ['n' => 3]);

        $guards = substr_count((string) file_get_contents($path), '<?php');

        self::assertSame(1, $guards);
        self::assertSame(3, GuardedJsonLines::count($path));
    }

    public function testStreamingAnAbsentLogYieldsNothing(): void
    {
        self::assertSame([], iterator_to_array(GuardedJsonLines::stream($this->dir . '/none.php'), false));
        self::assertSame(0, GuardedJsonLines::count($this->dir . '/none.php'));
    }
}
```

Create `tests/Security/DataDirTest.php`:

```php
<?php

declare(strict_types=1);

namespace WPSafeMode\Tests\Security;

use PHPUnit\Framework\TestCase;
use WPSafeMode\Security\DataDir;

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
}
```

- [ ] **Step 2: Run to verify failure**

Run: `vendor/bin/phpunit --filter 'GuardedFileTest|DataDirTest'`
Expected: FAIL — `Class "WPSafeMode\Security\DataDir" not found`.

- [ ] **Step 3: Write DataDir**

Create `src/Security/DataDir.php`:

```php
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
```

- [ ] **Step 4: Write GuardedJson and GuardedJsonLines**

Create `src/Security/GuardedJson.php`:

```php
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
        $dir = dirname($path);

        if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new StorageException('Cannot create data directory: ' . $dir);
        }

        try {
            $json = json_encode(
                $data,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            );
        } catch (JsonException $e) {
            throw new StorageException('Cannot encode state file: ' . basename($path), 0, $e);
        }

        $tmp = $path . '.tmp.' . bin2hex(random_bytes(4));

        if (file_put_contents($tmp, DataDir::GUARD . $json, LOCK_EX) === false) {
            throw new StorageException('Cannot write state file: ' . basename($path));
        }

        @chmod($tmp, 0600);

        if (!rename($tmp, $path)) {
            @unlink($tmp);
            throw new StorageException('Cannot commit state file: ' . basename($path));
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

        return $newline === false ? '' : substr($raw, $newline + 1);
    }
}
```

Create `src/Security/GuardedJsonLines.php`:

```php
<?php

declare(strict_types=1);

namespace WPSafeMode\Security;

use Generator;
use JsonException;
use WPSafeMode\Storage\Contracts\StorageException;

/**
 * An append-only JSONL log behind the same die() guard.
 *
 * The guard occupies the first line and every later line is one JSON object.
 * Appending is O(1) under LOCK_EX and a crashed request truncates at most the
 * final line. Readers stream: a long session's message log is the largest
 * object in the data directory and must never be loaded whole (spec 15.2).
 */
final class GuardedJsonLines
{
    /** @param array<string, mixed> $row */
    public static function append(string $path, array $row): void
    {
        $dir = dirname($path);

        if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new StorageException('Cannot create data directory: ' . $dir);
        }

        try {
            $line = json_encode(
                $row,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );
        } catch (JsonException $e) {
            throw new StorageException('Cannot encode log line: ' . basename($path), 0, $e);
        }

        $isNew = !is_file($path);
        $handle = fopen($path, 'ab');

        if ($handle === false) {
            throw new StorageException('Cannot open log: ' . basename($path));
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new StorageException('Cannot lock log: ' . basename($path));
            }

            // Re-check under the lock: two processes can both see the file as
            // absent, and two guards would corrupt the log.
            if ($isNew && ftell($handle) === 0) {
                fwrite($handle, DataDir::GUARD);
            }

            fwrite($handle, $line . "\n");
            fflush($handle);
            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }

        if ($isNew) {
            @chmod($path, 0600);
        }
    }

    /** @return Generator<int, array<string, mixed>> */
    public static function stream(string $path): Generator
    {
        if (!is_file($path)) {
            return;
        }

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new StorageException('Cannot read log: ' . basename($path));
        }

        try {
            while (($line = fgets($handle)) !== false) {
                $line = trim($line);

                if ($line === '' || strncmp($line, '<?php', 5) === 0) {
                    continue;
                }

                try {
                    $row = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                } catch (JsonException $e) {
                    throw new StorageException('Corrupt log line in ' . basename($path), 0, $e);
                }

                if (is_array($row)) {
                    yield $row;
                }
            }
        } finally {
            fclose($handle);
        }
    }

    public static function count(string $path): int
    {
        $count = 0;

        foreach (self::stream($path) as $ignored) {
            $count++;
        }

        return $count;
    }
}
```

- [ ] **Step 5: Run to verify both test files pass**

Run: `vendor/bin/phpunit --filter 'GuardedFileTest|DataDirTest'`
Expected: `OK (14 tests, ...)`.

- [ ] **Step 6: Move the JSON backend onto the guard**

In `src/Storage/Json/JsonFile.php`, replace the bodies of `read()` and `write()` so the class becomes a thin path holder over `GuardedJson` — the atomic-write and JSON-error handling now live in one place:

```php
    /** @return array<string, mixed> */
    public function read(): array
    {
        return GuardedJson::read($this->path);
    }

    /** @param array<string, mixed> $data */
    public function write(array $data): void
    {
        GuardedJson::write($this->path, $data);
    }
```

Add `use WPSafeMode\Security\GuardedJson;` and delete the now-unused `JsonException` import.

In `src/Storage/Json/JsonMessageLog.php`, delete `append()`, `all()` and `count()`'s file handling and delegate:

```php
    public function append(string $sessionId, string $role, array $content, ?string $apiMessageId = null): int
    {
        $seq = $this->count($sessionId) + 1;

        GuardedJsonLines::append($this->pathFor($sessionId), [
            'session_id'     => $sessionId,
            'seq'            => $seq,
            'role'           => $role,
            'content'        => $content,
            'api_message_id' => $apiMessageId,
            'created_at'     => time(),
        ]);

        return $seq;
    }

    public function all(string $sessionId): Generator
    {
        foreach (GuardedJsonLines::stream($this->pathFor($sessionId)) as $row) {
            yield new Message(
                sessionId:    (string) $row['session_id'],
                seq:          (int) $row['seq'],
                role:         (string) $row['role'],
                content:      $row['content'],
                apiMessageId: $row['api_message_id'] === null ? null : (string) $row['api_message_id'],
                createdAt:    (int) $row['created_at'],
            );
        }
    }

    public function count(string $sessionId): int
    {
        return GuardedJsonLines::count($this->pathFor($sessionId));
    }
```

Add `use WPSafeMode\Security\GuardedJsonLines;`, drop `StorageException` if it becomes unused, and change the filename in `pathFor()`:

```php
        return $this->baseDir . '/' . $sessionId . '/messages.php';
```

In `src/Storage/Json/JsonSessionRepo.php::pathFor()`, change `session.json` to `session.php`.

In `src/Storage/JsonStorage.php::config()`, change `config.json` to `config.php`.

- [ ] **Step 7: Guard the install record and randomise the SQLite filename**

Replace `src/Storage/StorageFactory.php::open()` and add two helpers:

```php
    /**
     * Opens storage for a live install, recording the chosen backend and the
     * SQLite filename the first time so later requests stay on both.
     */
    public static function open(string $dataDir, EnvironmentReport $env): Storage
    {
        DataDir::ensure($dataDir);

        $record = GuardedJson::read($dataDir . '/install.php');
        $recorded = isset($record['backend']) ? (string) $record['backend'] : null;

        $backend = self::chooseBackend($env->hasPdoSqlite, $recorded === '' ? null : $recorded);

        if ($recorded === null || $recorded === '') {
            $record['backend'] = $backend;

            // The SQLite file cannot carry the die() guard: a .php-named
            // database is handed to PHP, which finds no opening tag and echoes
            // the binary verbatim. An unguessable name is the part of its
            // defence that does not depend on server configuration.
            $record['sqlite_file'] = 'store-' . bin2hex(random_bytes(16)) . '.db';

            GuardedJson::write($dataDir . '/install.php', $record);
        }

        return $backend === self::BACKEND_SQLITE
            ? new SqliteStorage($dataDir, self::sqliteFilename($dataDir))
            : new JsonStorage($dataDir);
    }

    /** The recorded SQLite filename, or a deterministic default for a fresh directory. */
    public static function sqliteFilename(string $dataDir): string
    {
        $record = GuardedJson::read($dataDir . '/install.php');

        return isset($record['sqlite_file']) && is_string($record['sqlite_file']) && $record['sqlite_file'] !== ''
            ? $record['sqlite_file']
            : 'store-' . bin2hex(random_bytes(16)) . '.db';
    }
```

Add `use WPSafeMode\Security\DataDir;` and `use WPSafeMode\Security\GuardedJson;`.

`create()` keeps its M0 signature and behaviour, so `StorageFactoryTest` continues to pass; give `SqliteStorage` an optional filename so both callers work:

```php
    public function __construct(string $dataDir, string $filename = 'wpsm.sqlite')
    {
        $this->connection = new SqliteConnection($dataDir . '/' . $filename);
    }
```

- [ ] **Step 8: Assert the policy holds for every file the app writes**

Append to `tests/Security/DataDirTest.php`:

```php
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
```

- [ ] **Step 9: Run the whole suite**

Run: `vendor/bin/phpunit && vendor/bin/phpstan analyse --no-progress`
Expected: every M0 test still passes — the conformance suite is behaviour-only and does not name files — plus the new security tests. PHPStan clean.

- [ ] **Step 10: Commit**

```bash
git add src/Security/ src/Storage/ tests/Security/
git commit -m "feat(security): protect the data directory and die-guard every state file"
```

---

## Task 2: Key vault

**Files:**
- Create: `src/Security/KeyVault.php`
- Test: `tests/Security/KeyVaultTest.php`

**Interfaces:**
- Consumes: `DataDir::ensure()`, `GuardedJson`.
- Produces: `new KeyVault(string $dataDir)`, `KeyVault::encrypt(string $plaintext): string`, `KeyVault::decrypt(string $envelope): string`, `KeyVault::algorithm(): string` (`sodium` or `openssl`), `KeyVault::isAvailable(): bool`. Task 8 encrypts the API key with it; Task 12 reports the algorithm in the UI.

The master secret lives in the data directory, so anyone who can read that directory can decrypt the key. That is a stated limitation, not an oversight (spec §5.5): encryption at rest here defends against *partial* disclosure — a leaked backup, a stray directory listing, a misconfigured `data/` on nginx — and the self-test plus one-click uninstall are what defend against the rest. Task 8 puts that sentence in front of the user rather than burying it in a README.

- [ ] **Step 1: Write the failing test**

Create `tests/Security/KeyVaultTest.php`:

```php
<?php

declare(strict_types=1);

namespace WPSafeMode\Tests\Security;

use PHPUnit\Framework\TestCase;
use WPSafeMode\Security\KeyVault;
use WPSafeMode\Storage\Contracts\StorageException;

final class KeyVaultTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/wpsm-vault-' . bin2hex(random_bytes(6));
        mkdir($this->dir, 0700, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->dir);
    }

    public function testRoundTripsASecret(): void
    {
        $vault = new KeyVault($this->dir);
        $key = 'sk-ant-api03-' . str_repeat('x', 80);

        self::assertSame($key, $vault->decrypt($vault->encrypt($key)));
    }

    public function testCiphertextDoesNotContainThePlaintext(): void
    {
        $vault = new KeyVault($this->dir);

        self::assertStringNotContainsString('sk-ant-secret', $vault->encrypt('sk-ant-secret'));
    }

    public function testEncryptingTwiceProducesDifferentCiphertext(): void
    {
        // A fixed nonce would leak equality of secrets across config writes.
        $vault = new KeyVault($this->dir);

        self::assertNotSame($vault->encrypt('same'), $vault->encrypt('same'));
    }

    public function testASecondInstanceDecryptsWhatTheFirstWrote(): void
    {
        $envelope = (new KeyVault($this->dir))->encrypt('persisted');

        self::assertSame('persisted', (new KeyVault($this->dir))->decrypt($envelope));
    }

    public function testMasterSecretIsWrittenOwnerReadableOnly(): void
    {
        (new KeyVault($this->dir))->encrypt('x');

        self::assertFileExists($this->dir . '/keyvault.php');
        self::assertSame('0600', substr(sprintf('%o', fileperms($this->dir . '/keyvault.php')), -4));
    }

    public function testTamperedCiphertextIsRejectedRatherThanReturningGarbage(): void
    {
        $vault = new KeyVault($this->dir);
        $envelope = $vault->encrypt('sk-ant-secret');

        // Flip the last byte of the ciphertext segment.
        $parts = explode('.', $envelope);
        $raw = base64_decode(array_pop($parts), true);
        $raw[strlen($raw) - 1] = $raw[strlen($raw) - 1] === 'A' ? 'B' : 'A';
        $parts[] = base64_encode($raw);

        $this->expectException(StorageException::class);
        $vault->decrypt(implode('.', $parts));
    }

    public function testAnEnvelopeFromAnotherInstallIsRejected(): void
    {
        $other = sys_get_temp_dir() . '/wpsm-vault-other-' . bin2hex(random_bytes(6));
        mkdir($other, 0700, true);

        $envelope = (new KeyVault($other))->encrypt('sk-ant-secret');

        foreach (glob($other . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($other);

        $this->expectException(StorageException::class);
        (new KeyVault($this->dir))->decrypt($envelope);
    }

    public function testMalformedEnvelopeIsRejected(): void
    {
        $this->expectException(StorageException::class);
        (new KeyVault($this->dir))->decrypt('not-an-envelope');
    }
}
```

- [ ] **Step 2: Run to verify failure**

Run: `vendor/bin/phpunit --filter KeyVaultTest`
Expected: FAIL — `Class "WPSafeMode\Security\KeyVault" not found`.

- [ ] **Step 3: Write the KeyVault**

Create `src/Security/KeyVault.php`:

```php
<?php

declare(strict_types=1);

namespace WPSafeMode\Security;

use Throwable;
use WPSafeMode\Storage\Contracts\StorageException;

/**
 * Encrypts the Anthropic API key at rest.
 *
 * libsodium's crypto_secretbox when the build has it, OpenSSL aes-256-gcm
 * otherwise. Both are authenticated, so a tampered envelope fails loudly
 * instead of decrypting to garbage that later reaches the API as a malformed
 * key. The envelope records which was used, so a host that gains sodium later
 * can still read what OpenSSL wrote.
 *
 * Envelope format, all segments base64:
 *   v1.sodium.{nonce}.{ciphertext}
 *   v1.openssl.{iv}.{tag}.{ciphertext}
 *
 * Honest limitation, stated in spec 5.5 and shown to the user by the installer:
 * the master secret lives beside the data it protects, so an attacker who can
 * read the data directory can decrypt the key. This defends against partial
 * disclosure — a leaked backup, a directory listing, a misconfigured data/ on
 * nginx — not against full filesystem read.
 */
final class KeyVault
{
    private const SECRET_FILE = 'keyvault.php';
    private const SECRET_BYTES = 32;
    private const CONTEXT = 'wpsm:config:v1';

    private ?string $key = null;

    public function __construct(private string $dataDir)
    {
    }

    public static function isAvailable(): bool
    {
        return function_exists('sodium_crypto_secretbox')
            || (extension_loaded('openssl') && in_array('aes-256-gcm', openssl_get_cipher_methods(), true));
    }

    /** Either 'sodium' or 'openssl'. */
    public function algorithm(): string
    {
        return function_exists('sodium_crypto_secretbox') ? 'sodium' : 'openssl';
    }

    public function encrypt(string $plaintext): string
    {
        $key = $this->key();

        if ($this->algorithm() === 'sodium') {
            $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

            return implode('.', [
                'v1',
                'sodium',
                base64_encode($nonce),
                base64_encode(sodium_crypto_secretbox($plaintext, $nonce, $key)),
            ]);
        }

        if (!extension_loaded('openssl')) {
            throw new StorageException('No encryption extension is available: install sodium or openssl.');
        }

        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);

        if ($cipher === false) {
            throw new StorageException('Encryption failed.');
        }

        return implode('.', [
            'v1',
            'openssl',
            base64_encode($iv),
            base64_encode($tag),
            base64_encode($cipher),
        ]);
    }

    public function decrypt(string $envelope): string
    {
        $parts = explode('.', $envelope);

        if (count($parts) < 4 || $parts[0] !== 'v1') {
            throw new StorageException('Unrecognised encrypted value.');
        }

        $key = $this->key();

        try {
            if ($parts[1] === 'sodium' && count($parts) === 4) {
                $plain = sodium_crypto_secretbox_open(
                    self::decode($parts[3]),
                    self::decode($parts[2]),
                    $key
                );

                if ($plain === false) {
                    throw new StorageException('Could not decrypt: wrong key or tampered value.');
                }

                return $plain;
            }

            if ($parts[1] === 'openssl' && count($parts) === 5) {
                $plain = openssl_decrypt(
                    self::decode($parts[4]),
                    'aes-256-gcm',
                    $key,
                    OPENSSL_RAW_DATA,
                    self::decode($parts[2]),
                    self::decode($parts[3])
                );

                if ($plain === false) {
                    throw new StorageException('Could not decrypt: wrong key or tampered value.');
                }

                return $plain;
            }
        } catch (StorageException $e) {
            throw $e;
        } catch (Throwable $e) {
            // sodium throws SodiumException on a malformed nonce or key length.
            throw new StorageException('Could not decrypt: malformed value.', 0, $e);
        }

        throw new StorageException('Unrecognised encrypted value.');
    }

    /**
     * The 32-byte encryption key, derived from the stored master secret so the
     * secret itself is never used directly and can serve other contexts later.
     */
    private function key(): string
    {
        if ($this->key !== null) {
            return $this->key;
        }

        return $this->key = hash_hkdf('sha256', $this->masterSecret(), self::SECRET_BYTES, self::CONTEXT);
    }

    private function masterSecret(): string
    {
        $path = $this->dataDir . '/' . self::SECRET_FILE;
        $stored = GuardedJson::read($path);

        if (isset($stored['secret']) && is_string($stored['secret'])) {
            $secret = base64_decode($stored['secret'], true);

            if ($secret !== false && strlen($secret) === self::SECRET_BYTES) {
                return $secret;
            }

            throw new StorageException('The key vault is corrupt; the stored master secret is unusable.');
        }

        DataDir::ensure($this->dataDir);

        $secret = random_bytes(self::SECRET_BYTES);
        GuardedJson::write($path, ['secret' => base64_encode($secret), 'created_at' => time()]);
        @chmod($path, 0600);

        return $secret;
    }

    private static function decode(string $segment): string
    {
        $raw = base64_decode($segment, true);

        if ($raw === false) {
            throw new StorageException('Unrecognised encrypted value.');
        }

        return $raw;
    }
}
```

- [ ] **Step 4: Run to verify it passes**

Run: `vendor/bin/phpunit --filter KeyVaultTest`
Expected: `OK (8 tests, ...)`.

Note the test suite runs on whichever algorithm the host provides. Run it a second time with sodium disabled to prove the fallback: `php -d disable_functions=sodium_crypto_secretbox,sodium_crypto_secretbox_open vendor/bin/phpunit --filter KeyVaultTest`. Both must pass.

- [ ] **Step 5: Commit**

```bash
git add src/Security/KeyVault.php tests/Security/KeyVaultTest.php
git commit -m "feat(security): add key vault with sodium and openssl backends"
```

---

## Task 3: AuthStore, credentials, and password hashing

**Files:**
- Create: `src/Storage/Credential.php`
- Create: `src/Storage/Contracts/AuthStore.php`
- Create: `src/Storage/Sqlite/SqliteAuthStore.php`
- Create: `src/Storage/Json/JsonAuthStore.php`
- Create: `src/Security/PasswordHasher.php`
- Modify: `src/Storage/Sqlite/SqliteSchema.php` (add the `auth` table)
- Modify: `tests/Storage/StorageConformanceTest.php`, `SqliteStorageTest.php`, `JsonStorageTest.php`
- Test: `tests/Security/PasswordHasherTest.php`

**Interfaces:**
- Consumes: `SqliteConnection`, `GuardedJson`.
- Produces: `Credential` value object (`hash`, `algo`, `createdAt`, `changedAt`). `AuthStore::isConfigured(): bool`, `AuthStore::store(Credential $c): void`, `AuthStore::current(): ?Credential`. `PasswordHasher::algorithm(): string`, `PasswordHasher::hash(string $password): Credential`, `PasswordHasher::verify(string $password, Credential $c): bool`, `PasswordHasher::needsRehash(Credential $c): bool`. Task 5 verifies logins with these; Task 8 sets the first password.

- [ ] **Step 1: Add the auth table to the SQLite schema**

In `src/Storage/Sqlite/SqliteSchema.php::install()`, after the `config` table:

```php
        // CHECK(id = 1) is the single-row constraint from spec 7.1: there is one
        // operator, not a user table, and the schema should make a second row
        // impossible rather than rely on the application never inserting one.
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS auth (
                id INTEGER PRIMARY KEY CHECK (id = 1),
                password_hash TEXT NOT NULL,
                hash_algo TEXT NOT NULL,
                created_at INTEGER NOT NULL,
                password_changed_at INTEGER NOT NULL
            )'
        );
```

- [ ] **Step 2: Write the Credential value object and the contract**

Create `src/Storage/Credential.php`:

```php
<?php

declare(strict_types=1);

namespace WPSafeMode\Storage;

/**
 * The single operator credential. Records which algorithm produced the hash so
 * a later login can transparently rehash when the host gains libargon2
 * (spec 5.1).
 */
final class Credential
{
    public function __construct(
        public string $hash,
        public string $algo,
        public int $createdAt,
        public int $changedAt,
    ) {
    }

    /** @return array<string, int|string> */
    public function toRow(): array
    {
        return [
            'password_hash'       => $this->hash,
            'hash_algo'           => $this->algo,
            'created_at'          => $this->createdAt,
            'password_changed_at' => $this->changedAt,
        ];
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            hash:      (string) $row['password_hash'],
            algo:      (string) $row['hash_algo'],
            createdAt: (int) $row['created_at'],
            changedAt: (int) $row['password_changed_at'],
        );
    }
}
```

Create `src/Storage/Contracts/AuthStore.php`:

```php
<?php

declare(strict_types=1);

namespace WPSafeMode\Storage\Contracts;

use WPSafeMode\Storage\Credential;

/**
 * Exactly one credential, or none. There is no user table: this is a
 * single-operator tool (spec 5.1).
 */
interface AuthStore
{
    public function isConfigured(): bool;

    /** Replaces any existing credential. */
    public function store(Credential $credential): void;

    public function current(): ?Credential;
}
```

- [ ] **Step 3: Add the failing conformance tests**

In `tests/Storage/StorageConformanceTest.php` add `use WPSafeMode\Storage\Contracts\AuthStore;`, `use WPSafeMode\Storage\Credential;`, the abstract factory and the tests:

```php
    abstract protected function makeAuthStore(string $dataDir): AuthStore;

    public function testAuthStoreStartsUnconfigured(): void
    {
        $store = $this->makeAuthStore($this->dataDir);

        self::assertFalse($store->isConfigured());
        self::assertNull($store->current());
    }

    public function testCredentialRoundTripsEveryField(): void
    {
        $credential = new Credential('$argon2id$hash', 'argon2id', 1000, 2000);
        $this->makeAuthStore($this->dataDir)->store($credential);

        $found = $this->makeAuthStore($this->dataDir)->current();

        self::assertNotNull($found);
        self::assertSame($credential->toRow(), $found->toRow());
        self::assertTrue($this->makeAuthStore($this->dataDir)->isConfigured());
    }

    public function testStoringASecondCredentialReplacesTheFirst(): void
    {
        $store = $this->makeAuthStore($this->dataDir);
        $store->store(new Credential('$2y$first', 'bcrypt', 1000, 1000));
        $store->store(new Credential('$argon2id$second', 'argon2id', 1000, 3000));

        $found = $this->makeAuthStore($this->dataDir)->current();

        self::assertNotNull($found);
        self::assertSame('$argon2id$second', $found->hash);
        self::assertSame(3000, $found->changedAt);
    }
```

In `tests/Storage/SqliteStorageTest.php`:

```php
    protected function makeAuthStore(string $dataDir): AuthStore
    {
        return new SqliteAuthStore(new SqliteConnection($dataDir . '/wpsm.sqlite'));
    }
```

In `tests/Storage/JsonStorageTest.php`:

```php
    protected function makeAuthStore(string $dataDir): AuthStore
    {
        return new JsonAuthStore($dataDir . '/auth.php');
    }
```

Add the matching `use` statements in all three files.

- [ ] **Step 4: Run to verify failure**

Run: `vendor/bin/phpunit --filter 'SqliteStorageTest|JsonStorageTest'`
Expected: FAIL — `Class "WPSafeMode\Storage\Sqlite\SqliteAuthStore" not found`.

- [ ] **Step 5: Write both implementations**

Create `src/Storage/Sqlite/SqliteAuthStore.php`:

```php
<?php

declare(strict_types=1);

namespace WPSafeMode\Storage\Sqlite;

use WPSafeMode\Storage\Contracts\AuthStore;
use WPSafeMode\Storage\Credential;

final class SqliteAuthStore implements AuthStore
{
    public function __construct(private SqliteConnection $connection)
    {
    }

    public function isConfigured(): bool
    {
        return $this->current() !== null;
    }

    public function store(Credential $credential): void
    {
        $stmt = $this->connection->pdo()->prepare(
            'INSERT INTO auth (id, password_hash, hash_algo, created_at, password_changed_at)
             VALUES (1, :hash, :algo, :created, :changed)
             ON CONFLICT (id) DO UPDATE SET
                password_hash = excluded.password_hash,
                hash_algo = excluded.hash_algo,
                password_changed_at = excluded.password_changed_at'
        );

        $stmt->execute([
            ':hash'    => $credential->hash,
            ':algo'    => $credential->algo,
            ':created' => $credential->createdAt,
            ':changed' => $credential->changedAt,
        ]);
    }

    public function current(): ?Credential
    {
        $row = $this->connection->pdo()->query('SELECT * FROM auth WHERE id = 1')->fetch();

        return $row === false ? null : Credential::fromRow($row);
    }
}
```

Create `src/Storage/Json/JsonAuthStore.php`:

```php
<?php

declare(strict_types=1);

namespace WPSafeMode\Storage\Json;

use WPSafeMode\Security\GuardedJson;
use WPSafeMode\Storage\Contracts\AuthStore;
use WPSafeMode\Storage\Credential;

final class JsonAuthStore implements AuthStore
{
    public function __construct(private string $path)
    {
    }

    public function isConfigured(): bool
    {
        return $this->current() !== null;
    }

    public function store(Credential $credential): void
    {
        $existing = GuardedJson::read($this->path);

        $row = $credential->toRow();

        // Preserve the original created_at across a password change, matching
        // the SQLite ON CONFLICT clause, which does not update it.
        if (isset($existing['created_at'])) {
            $row['created_at'] = (int) $existing['created_at'];
        }

        GuardedJson::write($this->path, $row);
    }

    public function current(): ?Credential
    {
        $row = GuardedJson::read($this->path);

        return isset($row['password_hash']) ? Credential::fromRow($row) : null;
    }
}
```

- [ ] **Step 6: Run to verify both backends pass**

Run: `vendor/bin/phpunit --filter 'SqliteStorageTest|JsonStorageTest'`
Expected: `OK (48 tests, ...)` — twenty-four conformance tests, run twice.

- [ ] **Step 7: Write the failing PasswordHasher test**

Create `tests/Security/PasswordHasherTest.php`:

```php
<?php

declare(strict_types=1);

namespace WPSafeMode\Tests\Security;

use PHPUnit\Framework\TestCase;
use WPSafeMode\Security\PasswordHasher;
use WPSafeMode\Storage\Credential;

final class PasswordHasherTest extends TestCase
{
    public function testHashesAndVerifies(): void
    {
        $hasher = new PasswordHasher();
        $credential = $hasher->hash('correct horse battery staple');

        self::assertTrue($hasher->verify('correct horse battery staple', $credential));
        self::assertFalse($hasher->verify('wrong password', $credential));
    }

    public function testRecordsWhichAlgorithmProducedTheHash(): void
    {
        $credential = (new PasswordHasher())->hash('pw');

        self::assertContains($credential->algo, ['argon2id', 'bcrypt']);
        self::assertSame(defined('PASSWORD_ARGON2ID') ? 'argon2id' : 'bcrypt', $credential->algo);
    }

    public function testTheHashIsNeverThePlaintext(): void
    {
        $credential = (new PasswordHasher())->hash('plaintext-password');

        self::assertStringNotContainsString('plaintext-password', $credential->hash);
    }

    public function testABcryptHashOnAnArgonCapableHostNeedsRehash(): void
    {
        if (!defined('PASSWORD_ARGON2ID')) {
            self::markTestSkipped('This build has no argon2id, so there is nothing to upgrade to.');
        }

        $bcrypt = new Credential(password_hash('pw', PASSWORD_BCRYPT, ['cost' => 12]), 'bcrypt', 1, 1);

        self::assertTrue((new PasswordHasher())->needsRehash($bcrypt));
    }

    public function testAFreshHashDoesNotNeedRehash(): void
    {
        $hasher = new PasswordHasher();

        self::assertFalse($hasher->needsRehash($hasher->hash('pw')));
    }

    public function testVerifyingAgainstAnEmptyHashFails(): void
    {
        self::assertFalse((new PasswordHasher())->verify('pw', new Credential('', 'bcrypt', 1, 1)));
    }

    public function testWeakPasswordsAreRejectedBeforeHashing(): void
    {
        self::assertNotSame([], PasswordHasher::weaknesses('short'));
        self::assertNotSame([], PasswordHasher::weaknesses('password123'));
        self::assertSame([], PasswordHasher::weaknesses('correct horse battery staple'));
    }
}
```

- [ ] **Step 8: Run to verify failure**

Run: `vendor/bin/phpunit --filter PasswordHasherTest`
Expected: FAIL — `Class "WPSafeMode\Security\PasswordHasher" not found`.

- [ ] **Step 9: Write the PasswordHasher**

Create `src/Security/PasswordHasher.php`:

```php
<?php

declare(strict_types=1);

namespace WPSafeMode\Security;

use WPSafeMode\Storage\Credential;

/**
 * argon2id when the PHP build has libargon2, bcrypt cost 12 otherwise.
 *
 * PASSWORD_ARGON2ID is undefined on builds without libargon2, which is common
 * on shared hosting, so the constant is detected at runtime rather than
 * assumed. The chosen algorithm is recorded on the credential so a login on a
 * host that later gains argon2id can transparently upgrade the stored hash
 * (spec 5.1).
 */
final class PasswordHasher
{
    public const MIN_LENGTH = 12;

    private const BCRYPT_COST = 12;

    /** Rejected outright: the passwords a scanner tries first. */
    private const FORBIDDEN = [
        'password', 'passw0rd', 'admin', 'administrator', 'wordpress',
        'letmein', 'changeme', 'welcome', 'qwerty', '12345678', 'iloveyou',
        'wpsafemode', 'safemode',
    ];

    /** Either 'argon2id' or 'bcrypt'. */
    public function algorithm(): string
    {
        return defined('PASSWORD_ARGON2ID') ? 'argon2id' : 'bcrypt';
    }

    public function hash(string $password): Credential
    {
        $now = time();
        $algo = $this->algorithm();

        $hash = $algo === 'argon2id'
            ? password_hash($password, PASSWORD_ARGON2ID)
            : password_hash($password, PASSWORD_BCRYPT, ['cost' => self::BCRYPT_COST]);

        return new Credential((string) $hash, $algo, $now, $now);
    }

    public function verify(string $password, Credential $credential): bool
    {
        if ($credential->hash === '') {
            return false;
        }

        return password_verify($password, $credential->hash);
    }

    /**
     * True when the stored hash is weaker than what this host can now produce.
     * Callers rehash on the next successful login, which is the only moment the
     * plaintext is available.
     */
    public function needsRehash(Credential $credential): bool
    {
        if ($this->algorithm() === 'argon2id') {
            return $credential->algo !== 'argon2id'
                || password_needs_rehash($credential->hash, PASSWORD_ARGON2ID);
        }

        return password_needs_rehash($credential->hash, PASSWORD_BCRYPT, ['cost' => self::BCRYPT_COST]);
    }

    /**
     * Reasons this password is unacceptable, in plain language, empty when it
     * is fine. Returned as a list so the installer can show all of them at
     * once rather than making the user discover them one at a time.
     *
     * @return string[]
     */
    public static function weaknesses(string $password): array
    {
        $problems = [];

        if (strlen($password) < self::MIN_LENGTH) {
            $problems[] = 'Use at least ' . self::MIN_LENGTH . ' characters. Length matters more than symbols.';
        }

        $lower = strtolower($password);

        foreach (self::FORBIDDEN as $forbidden) {
            if (strpos($lower, $forbidden) !== false) {
                $problems[] = 'Avoid "' . $forbidden . '" — it is one of the first things an attacker tries.';
                break;
            }
        }

        if ($password !== '' && preg_match('/^(.)\1+$/', $password) === 1) {
            $problems[] = 'This is a single repeated character.';
        }

        return $problems;
    }
}
```

- [ ] **Step 10: Run the whole suite**

Run: `vendor/bin/phpunit && vendor/bin/phpstan analyse --no-progress`
Expected: all pass, PHPStan clean.

- [ ] **Step 11: Commit**

```bash
git add src/Storage/ src/Security/PasswordHasher.php tests/
git commit -m "feat(security): add auth store, credential shape, and password hashing"
```

---

## Task 4: RateLimiter with lockout

**Files:**
- Create: `src/Storage/RateLimitState.php`
- Create: `src/Storage/Contracts/RateLimiter.php`
- Create: `src/Storage/Sqlite/SqliteRateLimiter.php`
- Create: `src/Storage/Json/JsonRateLimiter.php`
- Modify: `src/Storage/Sqlite/SqliteSchema.php` (add the `rate_limit` table)
- Modify: `tests/Storage/StorageConformanceTest.php`, `SqliteStorageTest.php`, `JsonStorageTest.php`

**Interfaces:**
- Consumes: `SqliteConnection`, `GuardedJson`.
- Produces: `RateLimitState` (`attempts`, `lockedUntil`, `windowStart`) with `isLocked(int $now): bool` and `retryAfter(int $now): int`. `RateLimiter::hit(string $bucket, int $now): RateLimitState`, `RateLimiter::peek(string $bucket, int $now): RateLimitState`, `RateLimiter::clear(string $bucket): void`. Task 5 calls `hit()` on every login attempt and `clear()` on success.

Time is passed in rather than read from `time()` inside the limiter, so the lockout and window-expiry tests are deterministic instead of sleeping.

- [ ] **Step 1: Add the rate_limit table**

In `src/Storage/Sqlite/SqliteSchema.php::install()`:

```php
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS rate_limit (
                bucket TEXT PRIMARY KEY,
                window_start INTEGER NOT NULL,
                attempts INTEGER NOT NULL DEFAULT 0,
                locked_until INTEGER
            )'
        );
```

- [ ] **Step 2: Write the value object and contract**

Create `src/Storage/RateLimitState.php`:

```php
<?php

declare(strict_types=1);

namespace WPSafeMode\Storage;

final class RateLimitState
{
    public function __construct(
        public int $attempts,
        public int $windowStart,
        public ?int $lockedUntil,
    ) {
    }

    public function isLocked(int $now): bool
    {
        return $this->lockedUntil !== null && $this->lockedUntil > $now;
    }

    /** Seconds until another attempt is allowed; 0 when not locked. */
    public function retryAfter(int $now): int
    {
        return $this->isLocked($now) ? (int) $this->lockedUntil - $now : 0;
    }

    /**
     * This state as it should be read at $now, with an elapsed window or a
     * released lock already forgotten.
     *
     * It lives on the value object rather than in either backend so the two
     * implementations cannot drift: the conformance suite would catch a
     * divergence, but only after it had already shipped in one backend.
     */
    public function afterExpiry(int $now, int $windowSeconds): self
    {
        if ($this->isLocked($now)) {
            return $this;
        }

        if ($now - $this->windowStart >= $windowSeconds) {
            return new self(0, $now, null);
        }

        // The window is still open, but an expired lock must not keep counting
        // against the user, or one lockout would become permanent.
        return $this->lockedUntil !== null ? new self(0, $now, null) : $this;
    }
}
```

Create `src/Storage/Contracts/RateLimiter.php`:

```php
<?php

declare(strict_types=1);

namespace WPSafeMode\Storage\Contracts;

use WPSafeMode\Storage\RateLimitState;

/**
 * A fixed-window counter with lockout, keyed by an opaque bucket string.
 *
 * $now is a parameter rather than an internal time() call so callers can test
 * expiry and lockout without sleeping.
 */
interface RateLimiter
{
    public const WINDOW_SECONDS = 900;
    public const MAX_ATTEMPTS = 5;
    public const LOCKOUT_SECONDS = 900;

    /** Records one attempt and returns the state after it. */
    public function hit(string $bucket, int $now): RateLimitState;

    /** Reads the state without recording an attempt. */
    public function peek(string $bucket, int $now): RateLimitState;

    /** Forgets the bucket entirely. Called after a successful login. */
    public function clear(string $bucket): void;
}
```

- [ ] **Step 3: Add the failing conformance tests**

In `tests/Storage/StorageConformanceTest.php` add `use WPSafeMode\Storage\Contracts\RateLimiter;` and:

```php
    abstract protected function makeRateLimiter(string $dataDir): RateLimiter;

    public function testAnUnknownBucketIsUnlockedWithNoAttempts(): void
    {
        $state = $this->makeRateLimiter($this->dataDir)->peek('ip:1.2.3.4', 1000);

        self::assertSame(0, $state->attempts);
        self::assertFalse($state->isLocked(1000));
    }

    public function testAttemptsAccumulateWithinTheWindow(): void
    {
        $limiter = $this->makeRateLimiter($this->dataDir);

        self::assertSame(1, $limiter->hit('ip:1.2.3.4', 1000)->attempts);
        self::assertSame(2, $limiter->hit('ip:1.2.3.4', 1010)->attempts);
        self::assertSame(2, $this->makeRateLimiter($this->dataDir)->peek('ip:1.2.3.4', 1010)->attempts);
    }

    public function testExceedingTheLimitLocksTheBucket(): void
    {
        $limiter = $this->makeRateLimiter($this->dataDir);
        $state = new \WPSafeMode\Storage\RateLimitState(0, 0, null);

        for ($i = 0; $i < RateLimiter::MAX_ATTEMPTS; $i++) {
            $state = $limiter->hit('ip:1.2.3.4', 1000);
        }

        self::assertTrue($state->isLocked(1000));
        self::assertSame(RateLimiter::LOCKOUT_SECONDS, $state->retryAfter(1000));
    }

    public function testTheLockExpires(): void
    {
        $limiter = $this->makeRateLimiter($this->dataDir);

        for ($i = 0; $i < RateLimiter::MAX_ATTEMPTS; $i++) {
            $limiter->hit('ip:1.2.3.4', 1000);
        }

        $after = 1000 + RateLimiter::LOCKOUT_SECONDS + 1;

        self::assertFalse($limiter->peek('ip:1.2.3.4', $after)->isLocked($after));
    }

    public function testTheWindowResetsAfterItElapses(): void
    {
        $limiter = $this->makeRateLimiter($this->dataDir);
        $limiter->hit('ip:1.2.3.4', 1000);
        $limiter->hit('ip:1.2.3.4', 1000);

        $later = 1000 + RateLimiter::WINDOW_SECONDS + 1;

        self::assertSame(1, $limiter->hit('ip:1.2.3.4', $later)->attempts);
    }

    public function testBucketsAreIndependent(): void
    {
        $limiter = $this->makeRateLimiter($this->dataDir);

        for ($i = 0; $i < RateLimiter::MAX_ATTEMPTS; $i++) {
            $limiter->hit('ip:1.2.3.4', 1000);
        }

        self::assertFalse($limiter->peek('ip:5.6.7.8', 1000)->isLocked(1000));
    }

    public function testClearForgetsTheBucket(): void
    {
        $limiter = $this->makeRateLimiter($this->dataDir);

        for ($i = 0; $i < RateLimiter::MAX_ATTEMPTS; $i++) {
            $limiter->hit('ip:1.2.3.4', 1000);
        }

        $limiter->clear('ip:1.2.3.4');

        self::assertSame(0, $this->makeRateLimiter($this->dataDir)->peek('ip:1.2.3.4', 1000)->attempts);
    }
```

In `SqliteStorageTest.php`:

```php
    protected function makeRateLimiter(string $dataDir): RateLimiter
    {
        return new SqliteRateLimiter(new SqliteConnection($dataDir . '/wpsm.sqlite'));
    }
```

In `JsonStorageTest.php`:

```php
    protected function makeRateLimiter(string $dataDir): RateLimiter
    {
        return new JsonRateLimiter($dataDir . '/rate_limit.php');
    }
```

Add the matching `use` statements in all three files.

- [ ] **Step 4: Run to verify failure**

Run: `vendor/bin/phpunit --filter 'SqliteStorageTest|JsonStorageTest'`
Expected: FAIL — `Class "WPSafeMode\Storage\Sqlite\SqliteRateLimiter" not found`.

- [ ] **Step 5: Write both implementations**

Create `src/Storage/Sqlite/SqliteRateLimiter.php`:

```php
<?php

declare(strict_types=1);

namespace WPSafeMode\Storage\Sqlite;

use WPSafeMode\Storage\Contracts\RateLimiter;
use WPSafeMode\Storage\RateLimitState;

final class SqliteRateLimiter implements RateLimiter
{
    public function __construct(private SqliteConnection $connection)
    {
    }

    public function hit(string $bucket, int $now): RateLimitState
    {
        $pdo = $this->connection->pdo();
        $pdo->beginTransaction();

        try {
            $state = $this->read($bucket, $now);

            $attempts = $state->attempts + 1;
            $windowStart = $state->attempts === 0 ? $now : $state->windowStart;
            $lockedUntil = $state->lockedUntil;

            if ($attempts >= self::MAX_ATTEMPTS && !$state->isLocked($now)) {
                $lockedUntil = $now + self::LOCKOUT_SECONDS;
            }

            $stmt = $pdo->prepare(
                'INSERT INTO rate_limit (bucket, window_start, attempts, locked_until)
                 VALUES (:b, :ws, :a, :lu)
                 ON CONFLICT (bucket) DO UPDATE SET
                    window_start = excluded.window_start,
                    attempts = excluded.attempts,
                    locked_until = excluded.locked_until'
            );
            $stmt->execute([':b' => $bucket, ':ws' => $windowStart, ':a' => $attempts, ':lu' => $lockedUntil]);

            $pdo->commit();

            return new RateLimitState($attempts, $windowStart, $lockedUntil);
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function peek(string $bucket, int $now): RateLimitState
    {
        return $this->read($bucket, $now);
    }

    public function clear(string $bucket): void
    {
        $stmt = $this->connection->pdo()->prepare('DELETE FROM rate_limit WHERE bucket = :b');
        $stmt->execute([':b' => $bucket]);
    }

    /** Applies window expiry on read, so a stale row behaves as an empty one. */
    private function read(string $bucket, int $now): RateLimitState
    {
        $stmt = $this->connection->pdo()->prepare('SELECT * FROM rate_limit WHERE bucket = :b');
        $stmt->execute([':b' => $bucket]);
        $row = $stmt->fetch();

        if ($row === false) {
            return new RateLimitState(0, $now, null);
        }

        $state = new RateLimitState(
            (int) $row['attempts'],
            (int) $row['window_start'],
            $row['locked_until'] === null ? null : (int) $row['locked_until']
        );

        return $state->afterExpiry($now, self::WINDOW_SECONDS);
    }
}
```

Create `src/Storage/Json/JsonRateLimiter.php`:

```php
<?php

declare(strict_types=1);

namespace WPSafeMode\Storage\Json;

use WPSafeMode\Security\GuardedJson;
use WPSafeMode\Storage\Contracts\RateLimiter;
use WPSafeMode\Storage\RateLimitState;

final class JsonRateLimiter implements RateLimiter
{
    public function __construct(private string $path)
    {
    }

    public function hit(string $bucket, int $now): RateLimitState
    {
        $state = $this->peek($bucket, $now);

        $attempts = $state->attempts + 1;
        $windowStart = $state->attempts === 0 ? $now : $state->windowStart;
        $lockedUntil = $state->lockedUntil;

        if ($attempts >= self::MAX_ATTEMPTS && !$state->isLocked($now)) {
            $lockedUntil = $now + self::LOCKOUT_SECONDS;
        }

        $all = GuardedJson::read($this->path);
        $all[$bucket] = ['window_start' => $windowStart, 'attempts' => $attempts, 'locked_until' => $lockedUntil];
        GuardedJson::write($this->path, $all);

        return new RateLimitState($attempts, $windowStart, $lockedUntil);
    }

    public function peek(string $bucket, int $now): RateLimitState
    {
        $all = GuardedJson::read($this->path);

        if (!isset($all[$bucket]) || !is_array($all[$bucket])) {
            return new RateLimitState(0, $now, null);
        }

        $row = $all[$bucket];

        $state = new RateLimitState(
            (int) ($row['attempts'] ?? 0),
            (int) ($row['window_start'] ?? $now),
            isset($row['locked_until']) && $row['locked_until'] !== null ? (int) $row['locked_until'] : null
        );

        return $state->afterExpiry($now, self::WINDOW_SECONDS);
    }

    public function clear(string $bucket): void
    {
        $all = GuardedJson::read($this->path);

        if (!array_key_exists($bucket, $all)) {
            return;
        }

        unset($all[$bucket]);
        GuardedJson::write($this->path, $all);
    }
}
```

- [ ] **Step 6: Run to verify both backends pass**

Run: `vendor/bin/phpunit --filter 'SqliteStorageTest|JsonStorageTest'`
Expected: `OK (62 tests, ...)` — thirty-one conformance tests, run twice.

- [ ] **Step 7: Commit**

```bash
git add src/Storage/ tests/Storage/
git commit -m "feat(security): add rate limiter with lockout for both backends"
```

---

## Task 5: Login sessions

**Files:**
- Create: `src/Security/AuthSessions.php`
- Create: `src/Security/LoginResult.php`
- Modify: `src/Storage/Sqlite/SqliteSchema.php` (add `auth_sessions`)
- Test: `tests/Security/AuthSessionsTest.php`

**Interfaces:**
- Consumes: `AuthStore`, `RateLimiter`, `PasswordHasher`, `ConfigStore`.
- Produces: `LoginResult` (`ok`, `reason`, `token`, `retryAfter`). `AuthSessions::login(string $password, string $ip, string $userAgent, int $now): LoginResult`, `AuthSessions::validate(string $token, string $ip, string $userAgent, int $now): bool`, `AuthSessions::logout(string $token): void`, `AuthSessions::cookieHeader(string $token, bool $https, string $path, int $now): string`, `AuthSessions::clearCookieHeader(bool $https, string $path): string`. Task 6 binds CSRF to the token; Task 10 gates the API on `validate()`.

Sessions live in our own storage rather than PHP's, because `session.save_path` is frequently a shared, world-readable directory on shared hosting (spec §5.3) — on those hosts every other tenant can read the session file.

- [ ] **Step 1: Add the auth_sessions table**

```php
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS auth_sessions (
                token_hash TEXT PRIMARY KEY,
                created_at INTEGER NOT NULL,
                last_seen_at INTEGER NOT NULL,
                expires_at INTEGER NOT NULL,
                ip TEXT NOT NULL,
                ua_hash TEXT NOT NULL
            )'
        );
```

The JSON backend keeps the same rows in `data/auth_sessions.php`, keyed by token hash.

- [ ] **Step 2: Write the failing test**

Create `tests/Security/AuthSessionsTest.php`:

```php
<?php

declare(strict_types=1);

namespace WPSafeMode\Tests\Security;

use PHPUnit\Framework\TestCase;
use WPSafeMode\Security\AuthSessions;
use WPSafeMode\Security\PasswordHasher;
use WPSafeMode\Storage\Contracts\RateLimiter;
use WPSafeMode\Storage\StorageFactory;

final class AuthSessionsTest extends TestCase
{
    private string $dir;
    private AuthSessions $auth;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/wpsm-auth-' . bin2hex(random_bytes(6));
        mkdir($this->dir, 0700, true);

        $storage = StorageFactory::create($this->dir, 'json');
        $storage->auth()->store((new PasswordHasher())->hash('correct horse battery staple'));

        $this->auth = new AuthSessions($storage, new PasswordHasher());
    }

    protected function tearDown(): void
    {
        $this->removeRecursively($this->dir);
    }

    private function removeRecursively(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $full = $path . '/' . $entry;
            is_dir($full) ? $this->removeRecursively($full) : unlink($full);
        }
        rmdir($path);
    }

    public function testCorrectPasswordIssuesAToken(): void
    {
        $result = $this->auth->login('correct horse battery staple', '1.2.3.4', 'UA', 1000);

        self::assertTrue($result->ok);
        self::assertNotSame('', (string) $result->token);
    }

    public function testWrongPasswordIsRejectedWithoutAToken(): void
    {
        $result = $this->auth->login('wrong', '1.2.3.4', 'UA', 1000);

        self::assertFalse($result->ok);
        self::assertNull($result->token);
        self::assertSame('bad_credentials', $result->reason);
    }

    public function testAnIssuedTokenValidates(): void
    {
        $token = (string) $this->auth->login('correct horse battery staple', '1.2.3.4', 'UA', 1000)->token;

        self::assertTrue($this->auth->validate($token, '1.2.3.4', 'UA', 1010));
    }

    public function testAnUnknownTokenDoesNotValidate(): void
    {
        self::assertFalse($this->auth->validate('made-up-token', '1.2.3.4', 'UA', 1000));
    }

    public function testTheRawTokenIsNeverStored(): void
    {
        $token = (string) $this->auth->login('correct horse battery staple', '1.2.3.4', 'UA', 1000)->token;

        // A stolen data directory must not yield usable session tokens.
        $stored = (string) file_get_contents($this->dir . '/auth_sessions.php');

        self::assertStringNotContainsString($token, $stored);
    }

    public function testAnExpiredTokenDoesNotValidate(): void
    {
        $token = (string) $this->auth->login('correct horse battery staple', '1.2.3.4', 'UA', 1000)->token;

        self::assertFalse($this->auth->validate($token, '1.2.3.4', 'UA', 1000 + AuthSessions::LIFETIME_SECONDS + 1));
    }

    public function testAChangedUserAgentInvalidatesTheSession(): void
    {
        $token = (string) $this->auth->login('correct horse battery staple', '1.2.3.4', 'UA', 1000)->token;

        self::assertFalse($this->auth->validate($token, '1.2.3.4', 'A Different UA', 1010));
    }

    public function testLogoutRevokesTheToken(): void
    {
        $token = (string) $this->auth->login('correct horse battery staple', '1.2.3.4', 'UA', 1000)->token;
        $this->auth->logout($token);

        self::assertFalse($this->auth->validate($token, '1.2.3.4', 'UA', 1010));
    }

    public function testRepeatedFailuresLockTheAccountAndReportRetryAfter(): void
    {
        for ($i = 0; $i < RateLimiter::MAX_ATTEMPTS; $i++) {
            $this->auth->login('wrong', '1.2.3.4', 'UA', 1000);
        }

        $result = $this->auth->login('correct horse battery staple', '1.2.3.4', 'UA', 1000);

        // Correct password, still refused: otherwise the lockout is trivially
        // bypassed by guessing until you get it right.
        self::assertFalse($result->ok);
        self::assertSame('locked_out', $result->reason);
        self::assertGreaterThan(0, $result->retryAfter);
    }

    public function testASuccessfulLoginClearsTheLockoutCounter(): void
    {
        $this->auth->login('wrong', '1.2.3.4', 'UA', 1000);
        $this->auth->login('wrong', '1.2.3.4', 'UA', 1000);
        $this->auth->login('correct horse battery staple', '1.2.3.4', 'UA', 1000);

        for ($i = 0; $i < RateLimiter::MAX_ATTEMPTS - 1; $i++) {
            $this->auth->login('wrong', '1.2.3.4', 'UA', 1000);
        }

        self::assertTrue($this->auth->login('correct horse battery staple', '1.2.3.4', 'UA', 1000)->ok);
    }

    public function testCookieCarriesHttpOnlyAndSameSiteStrict(): void
    {
        $header = $this->auth->cookieHeader('tok', false, '/wpsm-abc', 1000);

        self::assertStringContainsString('HttpOnly', $header);
        self::assertStringContainsString('SameSite=Strict', $header);
        self::assertStringContainsString('Path=/wpsm-abc', $header);
        self::assertStringNotContainsString('Secure', $header);
    }

    public function testCookieIsSecureOverHttps(): void
    {
        self::assertStringContainsString('Secure', $this->auth->cookieHeader('tok', true, '/', 1000));
    }

    public function testLoginOnAnUnconfiguredInstallFails(): void
    {
        $empty = sys_get_temp_dir() . '/wpsm-auth-empty-' . bin2hex(random_bytes(6));
        mkdir($empty, 0700, true);

        $auth = new AuthSessions(StorageFactory::create($empty, 'json'), new PasswordHasher());
        $result = $auth->login('anything', '1.2.3.4', 'UA', 1000);

        self::assertFalse($result->ok);
        self::assertSame('not_installed', $result->reason);

        $this->removeRecursively($empty);
    }
}
```

- [ ] **Step 3: Run to verify failure**

Run: `vendor/bin/phpunit --filter AuthSessionsTest`
Expected: FAIL — `Class "WPSafeMode\Security\AuthSessions" not found`.

- [ ] **Step 4: Extend the Storage contract**

`AuthSessions` needs four stores, so `Storage` grows accessors for the two Task 3 and Task 4 added, plus one for session records. In `src/Storage/Contracts/Storage.php`:

```php
    public function auth(): AuthStore;

    public function rateLimiter(): RateLimiter;

    public function authSessions(): AuthSessionStore;
```

Create `src/Storage/Contracts/AuthSessionStore.php`:

```php
<?php

declare(strict_types=1);

namespace WPSafeMode\Storage\Contracts;

/**
 * Login session records, keyed by the SHA-256 of the token. The raw token
 * exists only in the user's cookie: a stolen data directory must not yield
 * usable sessions.
 */
interface AuthSessionStore
{
    /** @param array{created_at:int,last_seen_at:int,expires_at:int,ip:string,ua_hash:string} $row */
    public function put(string $tokenHash, array $row): void;

    /** @return array<string, mixed>|null */
    public function get(string $tokenHash): ?array;

    public function touch(string $tokenHash, int $now): void;

    public function delete(string $tokenHash): void;

    /** Removes every record whose expires_at has passed. */
    public function purgeExpired(int $now): void;
}
```

Implement it twice — `src/Storage/Sqlite/SqliteAuthSessionStore.php` against the `auth_sessions` table, and `src/Storage/Json/JsonAuthSessionStore.php` over `GuardedJson` keyed by token hash — and wire all five accessors into `SqliteStorage` and `JsonStorage`:

```php
    public function auth(): AuthStore
    {
        return new SqliteAuthStore($this->connection);
    }

    public function rateLimiter(): RateLimiter
    {
        return new SqliteRateLimiter($this->connection);
    }

    public function authSessions(): AuthSessionStore
    {
        return new SqliteAuthSessionStore($this->connection);
    }
```

and the JSON equivalents, taking `$this->dataDir . '/auth.php'`, `'/rate_limit.php'` and `'/auth_sessions.php'`.

- [ ] **Step 5: Write LoginResult and AuthSessions**

Create `src/Security/LoginResult.php`:

```php
<?php

declare(strict_types=1);

namespace WPSafeMode\Security;

final class LoginResult
{
    /** @param string|null $reason one of: not_installed, bad_credentials, locked_out */
    private function __construct(
        public bool $ok,
        public ?string $reason = null,
        public ?string $token = null,
        public int $retryAfter = 0,
    ) {
    }

    public static function success(string $token): self
    {
        return new self(true, null, $token);
    }

    public static function failure(string $reason, int $retryAfter = 0): self
    {
        return new self(false, $reason, null, $retryAfter);
    }
}
```

Create `src/Security/AuthSessions.php`:

```php
<?php

declare(strict_types=1);

namespace WPSafeMode\Security;

use WPSafeMode\Storage\Contracts\RateLimiter;
use WPSafeMode\Storage\Contracts\Storage;

/**
 * Login sessions in our own storage.
 *
 * PHP's own sessions are not used: session.save_path is frequently a shared,
 * world-readable directory on shared hosting, where every other tenant on the
 * box can read the session file (spec 5.3).
 *
 * Only the SHA-256 of the token is stored. The raw token exists in the user's
 * cookie and nowhere else, so a leaked data directory yields no usable session.
 */
final class AuthSessions
{
    public const COOKIE = 'wpsm_session';
    public const LIFETIME_SECONDS = 43200; // 12 hours
    public const IDLE_SECONDS = 3600;

    public function __construct(
        private Storage $storage,
        private PasswordHasher $hasher,
    ) {
    }

    public function login(string $password, string $ip, string $userAgent, int $now): LoginResult
    {
        $bucket = 'login:' . $ip;
        $limiter = $this->storage->rateLimiter();

        $state = $limiter->peek($bucket, $now);

        if ($state->isLocked($now)) {
            // Checked before the password so a correct guess during lockout is
            // still refused; otherwise the lockout means nothing.
            return LoginResult::failure('locked_out', $state->retryAfter($now));
        }

        $credential = $this->storage->auth()->current();

        if ($credential === null) {
            return LoginResult::failure('not_installed');
        }

        if (!$this->hasher->verify($password, $credential)) {
            $after = $limiter->hit($bucket, $now);

            return LoginResult::failure(
                $after->isLocked($now) ? 'locked_out' : 'bad_credentials',
                $after->retryAfter($now)
            );
        }

        $limiter->clear($bucket);

        // The only moment the plaintext exists, so it is the only moment an
        // upgrade from bcrypt to argon2id can happen (spec 5.1).
        if ($this->hasher->needsRehash($credential)) {
            $upgraded = $this->hasher->hash($password);
            $this->storage->auth()->store($upgraded);
        }

        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');

        $this->storage->authSessions()->purgeExpired($now);
        $this->storage->authSessions()->put(self::hashToken($token), [
            'created_at'   => $now,
            'last_seen_at' => $now,
            'expires_at'   => $now + self::LIFETIME_SECONDS,
            'ip'           => $ip,
            'ua_hash'      => self::hashAgent($userAgent),
        ]);

        return LoginResult::success($token);
    }

    public function validate(string $token, string $ip, string $userAgent, int $now): bool
    {
        if ($token === '') {
            return false;
        }

        $store = $this->storage->authSessions();
        $hash = self::hashToken($token);
        $row = $store->get($hash);

        if ($row === null) {
            return false;
        }

        if ((int) $row['expires_at'] <= $now || $now - (int) $row['last_seen_at'] > self::IDLE_SECONDS) {
            $store->delete($hash);

            return false;
        }

        // The user agent is bound but the IP is not: mobile networks and
        // CGNAT rotate addresses mid-session, and logging someone out of a
        // rescue because their phone changed tower is worse than the marginal
        // protection. The IP is recorded for the audit log either way.
        if (!hash_equals((string) $row['ua_hash'], self::hashAgent($userAgent))) {
            $store->delete($hash);

            return false;
        }

        $store->touch($hash, $now);

        return true;
    }

    public function logout(string $token): void
    {
        $this->storage->authSessions()->delete(self::hashToken($token));
    }

    public function cookieHeader(string $token, bool $https, string $path, int $now): string
    {
        $parts = [
            self::COOKIE . '=' . $token,
            'Path=' . ($path === '' ? '/' : $path),
            'Max-Age=' . self::LIFETIME_SECONDS,
            'Expires=' . gmdate('D, d M Y H:i:s T', $now + self::LIFETIME_SECONDS),
            'HttpOnly',
            'SameSite=Strict',
        ];

        if ($https) {
            $parts[] = 'Secure';
        }

        return implode('; ', $parts);
    }

    public function clearCookieHeader(bool $https, string $path): string
    {
        $parts = [
            self::COOKIE . '=',
            'Path=' . ($path === '' ? '/' : $path),
            'Max-Age=0',
            'Expires=Thu, 01 Jan 1970 00:00:00 GMT',
            'HttpOnly',
            'SameSite=Strict',
        ];

        if ($https) {
            $parts[] = 'Secure';
        }

        return implode('; ', $parts);
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    private static function hashAgent(string $userAgent): string
    {
        return hash('sha256', $userAgent);
    }
}
```

- [ ] **Step 6: Run to verify it passes**

Run: `vendor/bin/phpunit --filter AuthSessionsTest`
Expected: `OK (13 tests, ...)`.

- [ ] **Step 7: Run the whole suite and commit**

```bash
vendor/bin/phpunit && vendor/bin/phpstan analyse --no-progress
git add src/Security/ src/Storage/ tests/
git commit -m "feat(security): add login sessions with lockout and cookie hardening"
```

---

## Task 6: CSRF tokens

**Files:**
- Create: `src/Security/Csrf.php`
- Test: `tests/Security/CsrfTest.php`

**Interfaces:**
- Consumes: `AuthSessions::hashToken()`.
- Produces: `Csrf::issue(string $sessionToken, string $secret): string`, `Csrf::verify(string $sessionToken, string $secret, string $presented): bool`, `Csrf::HEADER` (`X-WPSM-CSRF`). Task 10's middleware calls `verify()` on every mutating request.

The token is derived from the session token and the install secret rather than stored, so there is no table to expire and no way for a token to outlive its session.

- [ ] **Step 1: Write the failing test**

Create `tests/Security/CsrfTest.php`:

```php
<?php

declare(strict_types=1);

namespace WPSafeMode\Tests\Security;

use PHPUnit\Framework\TestCase;
use WPSafeMode\Security\Csrf;

final class CsrfTest extends TestCase
{
    private const SECRET = 'install-secret';

    public function testIssuedTokenVerifies(): void
    {
        $token = Csrf::issue('session-token', self::SECRET);

        self::assertTrue(Csrf::verify('session-token', self::SECRET, $token));
    }

    public function testIssuingIsStableForTheSameSession(): void
    {
        self::assertSame(
            Csrf::issue('session-token', self::SECRET),
            Csrf::issue('session-token', self::SECRET)
        );
    }

    public function testATokenFromAnotherSessionIsRejected(): void
    {
        $other = Csrf::issue('another-session', self::SECRET);

        self::assertFalse(Csrf::verify('session-token', self::SECRET, $other));
    }

    public function testATokenFromAnotherInstallIsRejected(): void
    {
        $other = Csrf::issue('session-token', 'different-install-secret');

        self::assertFalse(Csrf::verify('session-token', self::SECRET, $other));
    }

    public function testEmptyAndMalformedTokensAreRejected(): void
    {
        self::assertFalse(Csrf::verify('session-token', self::SECRET, ''));
        self::assertFalse(Csrf::verify('session-token', self::SECRET, 'nonsense'));
    }

    public function testTheTokenIsNotTheSessionToken(): void
    {
        // Leaking the CSRF token in a URL or a log must not leak the session.
        self::assertStringNotContainsString('session-token', Csrf::issue('session-token', self::SECRET));
    }
}
```

- [ ] **Step 2: Run to verify failure**

Run: `vendor/bin/phpunit --filter CsrfTest`
Expected: FAIL — `Class "WPSafeMode\Security\Csrf" not found`.

- [ ] **Step 3: Write Csrf**

Create `src/Security/Csrf.php`:

```php
<?php

declare(strict_types=1);

namespace WPSafeMode\Security;

/**
 * A CSRF token derived from the login session rather than stored alongside it.
 *
 * Derivation means there is no table to grow, nothing to expire separately, and
 * no way for a token to outlive the session it belongs to: revoke the session
 * and every token it ever issued stops verifying.
 *
 * SameSite=Strict on the session cookie already blocks the classic cross-site
 * form post. This is the second layer, for the cases SameSite does not cover —
 * an older browser, or a same-site subdomain an attacker controls.
 */
final class Csrf
{
    public const HEADER = 'X-WPSM-CSRF';

    private const CONTEXT = 'wpsm:csrf:v1';

    public static function issue(string $sessionToken, string $secret): string
    {
        return hash_hmac('sha256', self::CONTEXT . '|' . $sessionToken, $secret);
    }

    public static function verify(string $sessionToken, string $secret, string $presented): bool
    {
        if ($presented === '' || $sessionToken === '') {
            return false;
        }

        return hash_equals(self::issue($sessionToken, $secret), $presented);
    }
}
```

- [ ] **Step 4: Run and commit**

```bash
vendor/bin/phpunit --filter CsrfTest
git add src/Security/Csrf.php tests/Security/CsrfTest.php
git commit -m "feat(security): derive csrf tokens from the login session"
```

---

## Task 7: Audit log

**Files:**
- Create: `src/Storage/Contracts/AuditLog.php`
- Create: `src/Storage/AuditEvent.php`
- Create: `src/Storage/Sqlite/SqliteAuditLog.php`
- Create: `src/Storage/Json/JsonAuditLog.php`
- Modify: `src/Storage/Sqlite/SqliteSchema.php`, `src/Storage/Contracts/Storage.php`, `SqliteStorage.php`, `JsonStorage.php`
- Modify: `tests/Storage/StorageConformanceTest.php` and both subclasses

**Interfaces:**
- Consumes: `SqliteConnection`, `GuardedJsonLines`.
- Produces: `AuditEvent` (`at`, `actor`, `event`, `subject`, `meta`, `ip`). `AuditLog::record(AuditEvent $e): void`, `AuditLog::recent(int $limit = 100): array` newest first. Tasks 9–12 record `install`, `login`, `login_failed`, `logout`, `config_change`, `rename`, `uninstall`.

Spec §7.1: `audit_log` covers non-agent events only. The agent's own actions live in `steps`, which M5 builds, so that there is exactly one place that answers "what did this thing do to my site".

- [ ] **Step 1: Add the table**

```php
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS audit_log (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                at INTEGER NOT NULL,
                actor TEXT NOT NULL,
                event TEXT NOT NULL,
                subject TEXT,
                meta_json TEXT NOT NULL,
                ip TEXT
            )'
        );
```

- [ ] **Step 2: Write the value object and contract**

Create `src/Storage/AuditEvent.php`:

```php
<?php

declare(strict_types=1);

namespace WPSafeMode\Storage;

final class AuditEvent
{
    public const INSTALL       = 'install';
    public const LOGIN         = 'login';
    public const LOGIN_FAILED  = 'login_failed';
    public const LOGOUT        = 'logout';
    public const CONFIG_CHANGE = 'config_change';
    public const RENAME        = 'rename';
    public const UNINSTALL     = 'uninstall';
    public const SELF_TEST     = 'self_test';

    /** @param array<string, mixed> $meta */
    public function __construct(
        public int $at,
        public string $actor,
        public string $event,
        public ?string $subject,
        public array $meta,
        public ?string $ip,
    ) {
    }

    /** @param array<string, mixed> $meta */
    public static function now(string $event, ?string $subject = null, array $meta = [], ?string $ip = null): self
    {
        return new self(time(), 'operator', $event, $subject, $meta, $ip);
    }
}
```

Create `src/Storage/Contracts/AuditLog.php`:

```php
<?php

declare(strict_types=1);

namespace WPSafeMode\Storage\Contracts;

use WPSafeMode\Storage\AuditEvent;

/**
 * Append-only, like MessageLog. Non-agent events only: install, login, config
 * change, restore, uninstall (spec 7.1).
 */
interface AuditLog
{
    public function record(AuditEvent $event): void;

    /** @return AuditEvent[] newest first */
    public function recent(int $limit = 100): array;
}
```

- [ ] **Step 3: Add the failing conformance tests**

```php
    abstract protected function makeAuditLog(string $dataDir): AuditLog;

    public function testAuditLogStartsEmpty(): void
    {
        self::assertSame([], $this->makeAuditLog($this->dataDir)->recent());
    }

    public function testRecordedEventsComeBackNewestFirst(): void
    {
        $log = $this->makeAuditLog($this->dataDir);
        $log->record(new AuditEvent(1000, 'operator', AuditEvent::INSTALL, null, [], '1.2.3.4'));
        $log->record(new AuditEvent(2000, 'operator', AuditEvent::LOGIN, null, [], '1.2.3.4'));

        $events = $this->makeAuditLog($this->dataDir)->recent();

        self::assertCount(2, $events);
        self::assertSame(AuditEvent::LOGIN, $events[0]->event);
        self::assertSame(AuditEvent::INSTALL, $events[1]->event);
    }

    public function testEveryFieldRoundTrips(): void
    {
        $log = $this->makeAuditLog($this->dataDir);
        $log->record(new AuditEvent(1000, 'operator', AuditEvent::CONFIG_CHANGE, 'model', ['from' => 'a', 'to' => 'b'], '1.2.3.4'));

        $event = $this->makeAuditLog($this->dataDir)->recent()[0];

        self::assertSame(1000, $event->at);
        self::assertSame('operator', $event->actor);
        self::assertSame('model', $event->subject);
        self::assertSame(['from' => 'a', 'to' => 'b'], $event->meta);
        self::assertSame('1.2.3.4', $event->ip);
    }

    public function testRecentHonoursItsLimit(): void
    {
        $log = $this->makeAuditLog($this->dataDir);

        for ($i = 1; $i <= 5; $i++) {
            $log->record(new AuditEvent(1000 + $i, 'operator', AuditEvent::LOGIN, null, [], null));
        }

        self::assertCount(2, $log->recent(2));
    }

    public function testASecretPassedInMetaIsNotWrittenVerbatim(): void
    {
        // Nothing should ever put a key here, but the audit log is the file most
        // likely to be pasted into a support thread, so it redacts by field name.
        $log = $this->makeAuditLog($this->dataDir);
        $log->record(new AuditEvent(1000, 'operator', AuditEvent::CONFIG_CHANGE, 'api_key', ['api_key' => 'sk-ant-secret'], null));

        $event = $this->makeAuditLog($this->dataDir)->recent()[0];

        self::assertSame('[redacted]', $event->meta['api_key']);
    }
```

Factories: `new SqliteAuditLog(new SqliteConnection($dataDir . '/wpsm.sqlite'))` and `new JsonAuditLog($dataDir . '/audit_log.php')`.

- [ ] **Step 4: Run to verify failure**

Run: `vendor/bin/phpunit --filter 'SqliteStorageTest|JsonStorageTest'`
Expected: FAIL — `Class "WPSafeMode\Storage\Sqlite\SqliteAuditLog" not found`.

- [ ] **Step 5: Write both implementations**

Create `src/Storage/Sqlite/SqliteAuditLog.php`:

```php
<?php

declare(strict_types=1);

namespace WPSafeMode\Storage\Sqlite;

use WPSafeMode\Storage\AuditEvent;
use WPSafeMode\Storage\Contracts\AuditLog;
use WPSafeMode\Storage\Redact;

final class SqliteAuditLog implements AuditLog
{
    public function __construct(private SqliteConnection $connection)
    {
    }

    public function record(AuditEvent $event): void
    {
        $stmt = $this->connection->pdo()->prepare(
            'INSERT INTO audit_log (at, actor, event, subject, meta_json, ip)
             VALUES (:at, :actor, :event, :subject, :meta, :ip)'
        );

        $stmt->execute([
            ':at'      => $event->at,
            ':actor'   => $event->actor,
            ':event'   => $event->event,
            ':subject' => $event->subject,
            ':meta'    => (string) json_encode(Redact::meta($event->meta), JSON_UNESCAPED_SLASHES),
            ':ip'      => $event->ip,
        ]);
    }

    public function recent(int $limit = 100): array
    {
        $stmt = $this->connection->pdo()->prepare(
            'SELECT * FROM audit_log ORDER BY at DESC, id DESC LIMIT :limit'
        );
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        $events = [];

        foreach ($stmt->fetchAll() as $row) {
            $meta = json_decode((string) $row['meta_json'], true);

            $events[] = new AuditEvent(
                (int) $row['at'],
                (string) $row['actor'],
                (string) $row['event'],
                $row['subject'] === null ? null : (string) $row['subject'],
                is_array($meta) ? $meta : [],
                $row['ip'] === null ? null : (string) $row['ip'],
            );
        }

        return $events;
    }
}
```

Create `src/Storage/Json/JsonAuditLog.php`:

```php
<?php

declare(strict_types=1);

namespace WPSafeMode\Storage\Json;

use WPSafeMode\Security\GuardedJsonLines;
use WPSafeMode\Storage\AuditEvent;
use WPSafeMode\Storage\Contracts\AuditLog;
use WPSafeMode\Storage\Redact;

final class JsonAuditLog implements AuditLog
{
    public function __construct(private string $path)
    {
    }

    public function record(AuditEvent $event): void
    {
        GuardedJsonLines::append($this->path, [
            'at'      => $event->at,
            'actor'   => $event->actor,
            'event'   => $event->event,
            'subject' => $event->subject,
            'meta'    => Redact::meta($event->meta),
            'ip'      => $event->ip,
        ]);
    }

    public function recent(int $limit = 100): array
    {
        $events = [];

        foreach (GuardedJsonLines::stream($this->path) as $row) {
            $events[] = new AuditEvent(
                (int) $row['at'],
                (string) $row['actor'],
                (string) $row['event'],
                $row['subject'] === null ? null : (string) $row['subject'],
                is_array($row['meta'] ?? null) ? $row['meta'] : [],
                $row['ip'] === null ? null : (string) $row['ip'],
            );
        }

        // Newest first, and stable for events sharing a timestamp: later lines
        // in an append-only log are always the more recent ones.
        $events = array_reverse($events);
        usort($events, static fn (AuditEvent $a, AuditEvent $b): int => $b->at <=> $a->at);

        return array_slice($events, 0, $limit);
    }
}
```

Create `src/Storage/Redact.php`:

```php
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
     * @param array<string, mixed> $meta
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
```

- [ ] **Step 6: Run, then commit**

Run: `vendor/bin/phpunit && vendor/bin/phpstan analyse --no-progress`
Expected: `OK` — thirty-six conformance tests, run twice, plus everything else.

```bash
git add src/Storage/ tests/Storage/
git commit -m "feat(storage): add append-only audit log with field-name redaction"
```

---

## Task 8: Web-accessibility self-test

**Files:**
- Create: `src/Security/WebAccessibilitySelfTest.php`
- Create: `src/Security/SelfTestResult.php`
- Test: `tests/Security/WebAccessibilitySelfTestTest.php`

**Interfaces:**
- Consumes: `DataDir`.
- Produces: `SelfTestResult` (`reachable`, `inconclusive`, `detail`, `checkedUrl`, `at`) with `blocksApiKey(): bool`. `WebAccessibilitySelfTest::run(string $dataDir, string $baseUrl): SelfTestResult`, and `WebAccessibilitySelfTest::baseUrlFrom(array $server): ?string`. Task 9 refuses to store an API key while `blocksApiKey()` is true; Task 10 re-runs it on every login.

This is the launch blocker from spec §15.1. v1.1 shipped with `sfstore/global_settings.json` — containing the API key — served as a static file on every deployed install. The self-test is not defence against a hypothetical; it is defence against the failure that already shipped. It runs at install **and on every login**, because a host migration or a control-panel change can silently remove protection later.

- [ ] **Step 1: Write the failing test**

Create `tests/Security/WebAccessibilitySelfTestTest.php`:

```php
<?php

declare(strict_types=1);

namespace WPSafeMode\Tests\Security;

use PHPUnit\Framework\TestCase;
use WPSafeMode\Security\SelfTestResult;
use WPSafeMode\Security\WebAccessibilitySelfTest;

final class WebAccessibilitySelfTestTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/wpsm-selftest-' . bin2hex(random_bytes(6));
        mkdir($this->dir, 0700, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->dir);
    }

    public function testAnUnreachableUrlReportsNotReachable(): void
    {
        // Port 1 on localhost refuses immediately: a connection failure is the
        // same shape as a correctly denied directory.
        $result = WebAccessibilitySelfTest::run($this->dir, 'http://127.0.0.1:1/wpsm');

        self::assertFalse($result->reachable);
        self::assertFalse($result->blocksApiKey());
    }

    public function testTheCanaryIsAlwaysCleanedUp(): void
    {
        WebAccessibilitySelfTest::run($this->dir, 'http://127.0.0.1:1/wpsm');

        $leftovers = array_filter(
            glob($this->dir . '/*') ?: [],
            static fn (string $p): bool => strpos(basename($p), 'canary') === 0
        );

        self::assertSame([], $leftovers, 'A canary file was left in the data directory');
    }

    public function testAReachableCanaryReportsReachableAndBlocksTheApiKey(): void
    {
        $server = getenv('WPSM_SELFTEST_URL');

        if ($server === false || $server === '') {
            self::markTestSkipped('Set WPSM_SELFTEST_URL to a base URL serving the data dir; see Step 4.');
        }

        $result = WebAccessibilitySelfTest::run($this->dir, $server);

        self::assertTrue($result->reachable);
        self::assertTrue($result->blocksApiKey());
    }

    public function testAnInconclusiveResultAlsoBlocksTheApiKey(): void
    {
        // "We could not check" must fail closed. An installer that stores the
        // key when the check did not run is the v1.1 failure with extra steps.
        $result = new SelfTestResult(false, true, 'cURL is unavailable', null, 1000);

        self::assertTrue($result->blocksApiKey());
    }

    public function testBaseUrlIsDerivedFromTheRequestWithoutTheScriptName(): void
    {
        $url = WebAccessibilitySelfTest::baseUrlFrom([
            'HTTPS'       => 'on',
            'HTTP_HOST'   => 'example.com',
            'SCRIPT_NAME' => '/wpsm-a1b2c3/index.php',
        ]);

        self::assertSame('https://example.com/wpsm-a1b2c3', $url);
    }

    public function testBaseUrlIsNullWhenTheHostIsUnknown(): void
    {
        self::assertNull(WebAccessibilitySelfTest::baseUrlFrom(['SCRIPT_NAME' => '/index.php']));
    }
}
```

- [ ] **Step 2: Run to verify failure**

Run: `vendor/bin/phpunit --filter WebAccessibilitySelfTestTest`
Expected: FAIL — `Class "WPSafeMode\Security\SelfTestResult" not found`.

- [ ] **Step 3: Write both classes**

Create `src/Security/SelfTestResult.php`:

```php
<?php

declare(strict_types=1);

namespace WPSafeMode\Security;

final class SelfTestResult
{
    public function __construct(
        public bool $reachable,
        public bool $inconclusive,
        public string $detail,
        public ?string $checkedUrl,
        public int $at,
    ) {
    }

    /**
     * Fails closed. "We could not check" is treated exactly like "it is
     * reachable": an installer that stores the API key when the check did not
     * run is the v1.1 failure with extra steps.
     */
    public function blocksApiKey(): bool
    {
        return $this->reachable || $this->inconclusive;
    }

    /** @return array<string, bool|int|string|null> */
    public function toArray(): array
    {
        return [
            'reachable'    => $this->reachable,
            'inconclusive' => $this->inconclusive,
            'detail'       => $this->detail,
            'checked_url'  => $this->checkedUrl,
            'at'           => $this->at,
        ];
    }
}
```

Create `src/Security/WebAccessibilitySelfTest.php`:

```php
<?php

declare(strict_types=1);

namespace WPSafeMode\Security;

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
 */
final class WebAccessibilitySelfTest
{
    private const TIMEOUT_SECONDS = 5;

    public static function run(string $dataDir, string $baseUrl): SelfTestResult
    {
        $now = time();
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

        $url = rtrim($baseUrl, '/') . '/data/' . $name;

        try {
            $body = self::fetch($url);
        } finally {
            @unlink($path);
        }

        if ($body === null) {
            return new SelfTestResult(
                false,
                true,
                'No way to make an HTTP request was available (cURL and remote file access are both disabled), '
                    . 'so the data directory could not be checked.',
                $url,
                $now
            );
        }

        if (strpos($body, $secret) !== false) {
            return new SelfTestResult(
                true,
                false,
                'The data directory is readable over the web. Anything stored there — including the API key — '
                    . 'can be downloaded by anyone who guesses the filename.',
                $url,
                $now
            );
        }

        return new SelfTestResult(
            false,
            false,
            'The data directory is not readable over the web.',
            $url,
            $now
        );
    }

    /** Returns the body, '' for a non-200, or null when no transport exists. */
    private static function fetch(string $url): ?string
    {
        if (function_exists('curl_init')) {
            $handle = curl_init($url);

            if ($handle !== false) {
                curl_setopt_array($handle, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT        => self::TIMEOUT_SECONDS,
                    CURLOPT_CONNECTTIMEOUT => self::TIMEOUT_SECONDS,
                    CURLOPT_FOLLOWLOCATION => false,
                    // Self-signed certificates are common on staging hosts. The
                    // canary is a random string we just wrote and are only
                    // comparing against; there is nothing here to protect.
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => 0,
                ]);

                $body = curl_exec($handle);
                $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
                curl_close($handle);

                if ($body === false) {
                    return '';
                }

                return $status === 200 ? (string) $body : '';
            }
        }

        if (filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) {
            $context = stream_context_create([
                'http' => ['timeout' => self::TIMEOUT_SECONDS, 'ignore_errors' => true],
                'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false],
            ]);

            $body = @file_get_contents($url, false, $context);

            return $body === false ? '' : (string) $body;
        }

        return null;
    }

    /**
     * The public URL of the install directory, derived from the request.
     *
     * @param array<string, mixed> $server
     */
    public static function baseUrlFrom(array $server): ?string
    {
        $host = (string) ($server['HTTP_HOST'] ?? $server['SERVER_NAME'] ?? '');

        if ($host === '') {
            return null;
        }

        $https = ($server['HTTPS'] ?? '') !== '' && strtolower((string) $server['HTTPS']) !== 'off';
        $forwarded = strtolower((string) ($server['HTTP_X_FORWARDED_PROTO'] ?? ''));
        $scheme = ($https || $forwarded === 'https') ? 'https' : 'http';

        $dir = rtrim(str_replace('\\', '/', dirname((string) ($server['SCRIPT_NAME'] ?? '/index.php'))), '/');

        return $scheme . '://' . $host . ($dir === '.' ? '' : $dir);
    }
}
```

- [ ] **Step 4: Run, including the positive case**

Run: `vendor/bin/phpunit --filter WebAccessibilitySelfTestTest`
Expected: `OK (6 tests, ...)` with one skipped.

Then prove the positive case, which is the one that matters. Serve the parent of the data directory over HTTP and point the test at it:

```bash
TMP=$(mktemp -d) && mkdir -p "$TMP/data"
php -S 127.0.0.1:8125 -t "$TMP" &
WPSM_SELFTEST_URL=http://127.0.0.1:8125 vendor/bin/phpunit --filter testAReachableCanaryReportsReachableAndBlocksTheApiKey
kill %1
```

Expected: PASS — the canary is retrieved and `blocksApiKey()` is true. A self-test that cannot detect an exposed directory is worse than none, so do not skip this step.

- [ ] **Step 5: Commit**

```bash
git add src/Security/ tests/Security/
git commit -m "feat(security): add the data-directory web-accessibility self-test"
```

---

## Task 9: The installer

**Files:**
- Create: `src/Installer/Installer.php`
- Create: `src/Installer/InstallState.php`
- Create: `src/Api/InstallController.php`
- Test: `tests/Installer/InstallerTest.php`

**Interfaces:**
- Consumes: `Storage`, `KeyVault`, `PasswordHasher`, `WebAccessibilitySelfTest`, `AuditLog`, `DataDir`.
- Produces: `InstallState` (`installed`, `step`, `hasPassword`, `hasApiKey`, `selfTest`, `encryption`). `Installer::state(): InstallState`, `Installer::issueToken(): string`, `Installer::verifyToken(string $presented): bool`, `Installer::setPassword(string $password): void`, `Installer::runSelfTest(string $baseUrl): SelfTestResult`, `Installer::storeApiKey(string $key, bool $acknowledgedRisk, string $baseUrl): void`, `Installer::finalize(string $ip): void`. Task 10 routes to `InstallController`; Task 13 renders the wizard.

**The install token.** On first run the installer writes a random token into `data/install-token.php` and refuses to accept a password until the user pastes it back. A public URL is discoverable, and whoever reaches an unconfigured copy first would otherwise own it — the token proves the person setting the password can read the filesystem, i.e. is the site owner. This is the same defence WordPress core uses for recovery mode and phpMyAdmin uses for setup. Spec §5.1 says "generate a random install token" without saying what it is for; this is that purpose, and it is the only reading under which the token does any work.

**Order is deliberate:** token → password → self-test → API key. The key is the only thing worth stealing, so it is accepted last, only after the directory holding it has been proven unreachable.

- [ ] **Step 1: Write the failing test**

Create `tests/Installer/InstallerTest.php`:

```php
<?php

declare(strict_types=1);

namespace WPSafeMode\Tests\Installer;

use PHPUnit\Framework\TestCase;
use WPSafeMode\Installer\Installer;
use WPSafeMode\Security\KeyVault;
use WPSafeMode\Security\PasswordHasher;
use WPSafeMode\Storage\Contracts\StorageException;
use WPSafeMode\Storage\StorageFactory;

final class InstallerTest extends TestCase
{
    private const UNREACHABLE = 'http://127.0.0.1:1/wpsm';

    private string $dir;
    private Installer $installer;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/wpsm-install-' . bin2hex(random_bytes(6));
        mkdir($this->dir, 0700, true);

        $this->installer = new Installer(
            StorageFactory::create($this->dir, 'json'),
            new KeyVault($this->dir),
            new PasswordHasher(),
            $this->dir
        );
    }

    protected function tearDown(): void
    {
        $this->removeRecursively($this->dir);
    }

    private function removeRecursively(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $full = $path . '/' . $entry;
            is_dir($full) ? $this->removeRecursively($full) : unlink($full);
        }
        rmdir($path);
    }

    public function testAFreshCopyIsNotInstalled(): void
    {
        $state = $this->installer->state();

        self::assertFalse($state->installed);
        self::assertFalse($state->hasPassword);
        self::assertFalse($state->hasApiKey);
    }

    public function testTheInstallTokenIsWrittenToTheDataDirectoryAndVerifies(): void
    {
        $token = $this->installer->issueToken();

        self::assertNotSame('', $token);
        self::assertFileExists($this->dir . '/install-token.php');
        self::assertTrue($this->installer->verifyToken($token));
        self::assertFalse($this->installer->verifyToken('wrong-token'));
    }

    public function testTheTokenIsStableAcrossCalls(): void
    {
        // Re-issuing on every page load would make the printed token useless.
        self::assertSame($this->installer->issueToken(), $this->installer->issueToken());
    }

    public function testSettingAPasswordRequiresAStrongOne(): void
    {
        $this->expectException(StorageException::class);
        $this->installer->setPassword('short');
    }

    public function testSettingAPasswordMakesItVerifiable(): void
    {
        $this->installer->setPassword('correct horse battery staple');

        self::assertTrue($this->installer->state()->hasPassword);
    }

    public function testTheApiKeyIsRefusedUntilAPasswordExists(): void
    {
        $this->expectException(StorageException::class);
        $this->installer->storeApiKey('sk-ant-key', false, self::UNREACHABLE);
    }

    public function testTheApiKeyIsStoredEncryptedAndNeverInPlaintext(): void
    {
        $this->installer->setPassword('correct horse battery staple');
        $this->installer->storeApiKey('sk-ant-api03-secret', false, self::UNREACHABLE);

        $onDisk = (string) file_get_contents($this->dir . '/config.php');

        self::assertStringNotContainsString('sk-ant-api03-secret', $onDisk);
        self::assertTrue($this->installer->state()->hasApiKey);
    }

    public function testTheStoredApiKeyDecryptsBack(): void
    {
        $this->installer->setPassword('correct horse battery staple');
        $this->installer->storeApiKey('sk-ant-api03-secret', false, self::UNREACHABLE);

        $stored = StorageFactory::create($this->dir, 'json')->config()->get('api_key');

        self::assertNotNull($stored);
        self::assertSame('sk-ant-api03-secret', (new KeyVault($this->dir))->decrypt($stored));
    }

    public function testAnExposedDataDirectoryBlocksTheApiKeyUntilAcknowledged(): void
    {
        $server = getenv('WPSM_SELFTEST_URL');

        if ($server === false || $server === '') {
            self::markTestSkipped('Set WPSM_SELFTEST_URL to a base URL serving the data dir.');
        }

        $this->installer->setPassword('correct horse battery staple');

        try {
            $this->installer->storeApiKey('sk-ant-key', false, $server);
            self::fail('The API key was stored while the data directory was web-readable');
        } catch (StorageException $e) {
            self::assertStringContainsString('readable over the web', $e->getMessage());
        }

        // Acknowledged, the user may proceed — it is their site and their risk.
        $this->installer->storeApiKey('sk-ant-key', true, $server);
        self::assertTrue($this->installer->state()->hasApiKey);
    }

    public function testFinalizeMarksTheInstallCompleteAndRecordsIt(): void
    {
        $this->installer->setPassword('correct horse battery staple');
        $this->installer->storeApiKey('sk-ant-key', false, self::UNREACHABLE);
        $this->installer->finalize('1.2.3.4');

        self::assertTrue($this->installer->state()->installed);
        self::assertNotSame(
            [],
            array_filter(
                StorageFactory::create($this->dir, 'json')->auditLog()->recent(),
                static fn ($e): bool => $e->event === 'install'
            )
        );
    }

    public function testFinalizeIsRefusedWithoutAPassword(): void
    {
        $this->expectException(StorageException::class);
        $this->installer->finalize('1.2.3.4');
    }

    public function testTheInstallTokenFileIsRemovedOnceInstalled(): void
    {
        $this->installer->issueToken();
        $this->installer->setPassword('correct horse battery staple');
        $this->installer->storeApiKey('sk-ant-key', false, self::UNREACHABLE);
        $this->installer->finalize('1.2.3.4');

        // It has done its job, and a token lying around is one more thing to leak.
        self::assertFileDoesNotExist($this->dir . '/install-token.php');
    }
}
```

- [ ] **Step 2: Run to verify failure**

Run: `vendor/bin/phpunit --filter InstallerTest`
Expected: FAIL — `Class "WPSafeMode\Installer\Installer" not found`.

- [ ] **Step 3: Write InstallState and Installer**

Create `src/Installer/InstallState.php`:

```php
<?php

declare(strict_types=1);

namespace WPSafeMode\Installer;

use WPSafeMode\Security\SelfTestResult;

final class InstallState
{
    public function __construct(
        public bool $installed,
        public string $step,
        public bool $hasPassword,
        public bool $hasApiKey,
        public ?SelfTestResult $selfTest,
        public string $encryption,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'installed'    => $this->installed,
            'step'         => $this->step,
            'has_password' => $this->hasPassword,
            'has_api_key'  => $this->hasApiKey,
            'self_test'    => $this->selfTest === null ? null : $this->selfTest->toArray(),
            'encryption'   => $this->encryption,
        ];
    }
}
```

Create `src/Installer/Installer.php`:

```php
<?php

declare(strict_types=1);

namespace WPSafeMode\Installer;

use WPSafeMode\Security\DataDir;
use WPSafeMode\Security\GuardedJson;
use WPSafeMode\Security\KeyVault;
use WPSafeMode\Security\PasswordHasher;
use WPSafeMode\Security\SelfTestResult;
use WPSafeMode\Security\WebAccessibilitySelfTest;
use WPSafeMode\Storage\AuditEvent;
use WPSafeMode\Storage\Contracts\Storage;
use WPSafeMode\Storage\Contracts\StorageException;

/**
 * The first-run wizard: token, password, self-test, API key, finalize.
 *
 * The order is the security design, not a UI preference. The API key is the
 * only thing here worth stealing, so it is accepted last — after the user has
 * proved filesystem access, after a password exists, and after the directory
 * that will hold it has been proven unreachable over HTTP.
 */
final class Installer
{
    public const STEP_TOKEN    = 'token';
    public const STEP_PASSWORD = 'password';
    public const STEP_API_KEY  = 'api_key';
    public const STEP_DONE     = 'done';

    private const TOKEN_FILE = 'install-token.php';

    private ?SelfTestResult $lastSelfTest = null;

    public function __construct(
        private Storage $storage,
        private KeyVault $vault,
        private PasswordHasher $hasher,
        private string $dataDir,
    ) {
    }

    public function state(): InstallState
    {
        $hasPassword = $this->storage->auth()->isConfigured();
        $hasApiKey = $this->storage->config()->has('api_key');
        $installed = $this->storage->config()->get('installed_at') !== null;

        if ($installed) {
            $step = self::STEP_DONE;
        } elseif (!$hasPassword) {
            $step = is_file($this->dataDir . '/' . self::TOKEN_FILE) ? self::STEP_TOKEN : self::STEP_PASSWORD;
        } else {
            $step = self::STEP_API_KEY;
        }

        return new InstallState(
            $installed,
            $step,
            $hasPassword,
            $hasApiKey,
            $this->lastSelfTest,
            $this->vault->algorithm()
        );
    }

    /**
     * The token proves whoever is setting the password can read the filesystem.
     * A public URL is discoverable, and without this whoever reaches an
     * unconfigured copy first would own it.
     */
    public function issueToken(): string
    {
        DataDir::ensure($this->dataDir);

        $path = $this->dataDir . '/' . self::TOKEN_FILE;
        $existing = GuardedJson::read($path);

        if (isset($existing['token']) && is_string($existing['token']) && $existing['token'] !== '') {
            return $existing['token'];
        }

        $token = strtoupper(bin2hex(random_bytes(8)));
        GuardedJson::write($path, ['token' => $token, 'created_at' => time()]);

        return $token;
    }

    public function verifyToken(string $presented): bool
    {
        $stored = GuardedJson::read($this->dataDir . '/' . self::TOKEN_FILE);

        if (!isset($stored['token']) || !is_string($stored['token'])) {
            return false;
        }

        return hash_equals($stored['token'], strtoupper(trim($presented)));
    }

    public function setPassword(string $password): void
    {
        $weaknesses = PasswordHasher::weaknesses($password);

        if ($weaknesses !== []) {
            throw new StorageException(implode(' ', $weaknesses));
        }

        $this->storage->auth()->store($this->hasher->hash($password));
    }

    public function runSelfTest(string $baseUrl): SelfTestResult
    {
        return $this->lastSelfTest = WebAccessibilitySelfTest::run($this->dataDir, $baseUrl);
    }

    public function storeApiKey(string $key, bool $acknowledgedRisk, string $baseUrl): void
    {
        if (!$this->storage->auth()->isConfigured()) {
            throw new StorageException('Set a password before storing an API key.');
        }

        $key = trim($key);

        if ($key === '') {
            throw new StorageException('The API key is empty.');
        }

        $result = $this->runSelfTest($baseUrl);

        if ($result->blocksApiKey() && !$acknowledgedRisk) {
            throw new StorageException($result->detail);
        }

        $this->storage->config()->set('api_key', $this->vault->encrypt($key));
        $this->storage->config()->set('api_key_algo', $this->vault->algorithm());

        $this->storage->auditLog()->record(AuditEvent::now(
            AuditEvent::SELF_TEST,
            'data_dir',
            ['reachable' => $result->reachable, 'inconclusive' => $result->inconclusive]
        ));
    }

    public function finalize(string $ip): void
    {
        if (!$this->storage->auth()->isConfigured()) {
            throw new StorageException('Set a password before finishing setup.');
        }

        $this->storage->config()->set('installed_at', (string) time());
        $this->storage->config()->set('install_id', bin2hex(random_bytes(8)));

        // The token has done its job; leaving it is one more secret to leak.
        @unlink($this->dataDir . '/' . self::TOKEN_FILE);

        $this->storage->auditLog()->record(new AuditEvent(
            time(),
            'operator',
            AuditEvent::INSTALL,
            null,
            ['backend' => $this->storage->backend(), 'encryption' => $this->vault->algorithm()],
            $ip
        ));
    }
}
```

- [ ] **Step 4: Run to verify it passes**

Run: `vendor/bin/phpunit --filter InstallerTest`
Expected: `OK (12 tests, ...)` with one skipped. Re-run the skipped one against the `php -S` fixture from Task 8 Step 4 — it is the test that proves the key is actually withheld on an exposed directory.

- [ ] **Step 5: Write the controller**

Create `src/Api/InstallController.php`:

```php
<?php

declare(strict_types=1);

namespace WPSafeMode\Api;

use WPSafeMode\Core\Request;
use WPSafeMode\Core\Response;
use WPSafeMode\Installer\Installer;
use WPSafeMode\Security\WebAccessibilitySelfTest;
use WPSafeMode\Storage\Contracts\StorageException;

/**
 * The only controller reachable before installation completes. Every route
 * refuses once installed, so it cannot be replayed to reset a live copy.
 */
final class InstallController
{
    /** @param array<string, mixed> $server */
    public function __construct(
        private Installer $installer,
        private array $server,
    ) {
    }

    public function state(Request $request): Response
    {
        return Response::json($this->installer->state()->toArray());
    }

    public function verifyToken(Request $request): Response
    {
        if ($this->installer->state()->installed) {
            return Response::json(['error' => 'already_installed'], 409);
        }

        $token = (string) ($request->json()['token'] ?? '');

        return $this->installer->verifyToken($token)
            ? Response::json(['ok' => true])
            : Response::json(['error' => 'bad_token'], 403);
    }

    public function setPassword(Request $request): Response
    {
        if ($this->installer->state()->installed) {
            return Response::json(['error' => 'already_installed'], 409);
        }

        $body = $request->json();

        if (!$this->installer->verifyToken((string) ($body['token'] ?? ''))) {
            return Response::json(['error' => 'bad_token'], 403);
        }

        try {
            $this->installer->setPassword((string) ($body['password'] ?? ''));
        } catch (StorageException $e) {
            return Response::json(['error' => 'weak_password', 'message' => $e->getMessage()], 422);
        }

        return Response::json($this->installer->state()->toArray());
    }

    public function selfTest(Request $request): Response
    {
        $baseUrl = WebAccessibilitySelfTest::baseUrlFrom($this->server);

        if ($baseUrl === null) {
            return Response::json(['error' => 'no_base_url'], 409);
        }

        return Response::json($this->installer->runSelfTest($baseUrl)->toArray());
    }

    public function apiKey(Request $request): Response
    {
        if ($this->installer->state()->installed) {
            return Response::json(['error' => 'already_installed'], 409);
        }

        $baseUrl = WebAccessibilitySelfTest::baseUrlFrom($this->server);
        $body = $request->json();

        try {
            $this->installer->storeApiKey(
                (string) ($body['api_key'] ?? ''),
                (bool) ($body['acknowledged_risk'] ?? false),
                $baseUrl ?? 'http://127.0.0.1:1'
            );
        } catch (StorageException $e) {
            return Response::json(['error' => 'refused', 'message' => $e->getMessage()], 422);
        }

        return Response::json($this->installer->state()->toArray());
    }

    public function finish(Request $request): Response
    {
        if ($this->installer->state()->installed) {
            return Response::json(['error' => 'already_installed'], 409);
        }

        try {
            $this->installer->finalize(Middleware::clientIp($this->server));
        } catch (StorageException $e) {
            return Response::json(['error' => 'incomplete', 'message' => $e->getMessage()], 422);
        }

        return Response::json($this->installer->state()->toArray());
    }
}
```

- [ ] **Step 6: Commit**

```bash
git add src/Installer/ src/Api/InstallController.php tests/Installer/
git commit -m "feat(installer): add the first-run wizard with token, password, and gated api key"
```

---

## Task 10: Middleware, auth endpoints, and the install gate

**Files:**
- Create: `src/Api/Middleware.php`
- Create: `src/Api/AuthController.php`
- Modify: `src/index.php`
- Test: `tests/Api/MiddlewareTest.php`

**Interfaces:**
- Consumes: everything from Tasks 1–9.
- Produces: `Middleware::clientIp(array $server): string`, `Middleware::isHttps(array $server): bool`, `Middleware::requireInstalled(InstallState $s): ?Response`, `Middleware::requireAuth(...): ?Response`, `Middleware::requireCsrf(...): ?Response`. Every M2+ endpoint is registered behind these.

Spec §5.1: **refuse to operate unconfigured** — every endpoint returns a "not installed" response until setup completes.

- [ ] **Step 1: Write the failing test**

Create `tests/Api/MiddlewareTest.php`:

```php
<?php

declare(strict_types=1);

namespace WPSafeMode\Tests\Api;

use PHPUnit\Framework\TestCase;
use WPSafeMode\Api\Middleware;
use WPSafeMode\Core\Request;
use WPSafeMode\Installer\InstallState;
use WPSafeMode\Security\Csrf;

final class MiddlewareTest extends TestCase
{
    private function state(bool $installed): InstallState
    {
        return new InstallState($installed, $installed ? 'done' : 'password', $installed, $installed, null, 'sodium');
    }

    public function testAnUninstalledCopyRefusesEveryGuardedRoute(): void
    {
        $response = Middleware::requireInstalled($this->state(false));

        self::assertNotNull($response);
        self::assertSame(409, $response->status);
        self::assertStringContainsString('not_installed', $response->body);
    }

    public function testAnInstalledCopyPassesTheGate(): void
    {
        self::assertNull(Middleware::requireInstalled($this->state(true)));
    }

    public function testClientIpPrefersTheDirectPeer(): void
    {
        // X-Forwarded-For is attacker-controlled unless a proxy is known to
        // rewrite it, and this IP feeds the login lockout bucket: trusting the
        // header would let one attacker exhaust every bucket but their own.
        $ip = Middleware::clientIp([
            'REMOTE_ADDR'          => '203.0.113.9',
            'HTTP_X_FORWARDED_FOR' => '1.2.3.4',
        ]);

        self::assertSame('203.0.113.9', $ip);
    }

    public function testClientIpFallsBackToAPlaceholder(): void
    {
        self::assertSame('unknown', Middleware::clientIp([]));
    }

    public function testHttpsIsDetectedFromBothTheServerVarAndTheProxyHeader(): void
    {
        self::assertTrue(Middleware::isHttps(['HTTPS' => 'on']));
        self::assertTrue(Middleware::isHttps(['HTTP_X_FORWARDED_PROTO' => 'https']));
        self::assertFalse(Middleware::isHttps(['HTTPS' => 'off']));
        self::assertFalse(Middleware::isHttps([]));
    }

    public function testCsrfIsRequiredOnMutatingRequests(): void
    {
        $request = new Request('POST', '/api/config', '/index.php', '{}');

        $response = Middleware::requireCsrf($request, 'session-token', 'secret', []);

        self::assertNotNull($response);
        self::assertSame(403, $response->status);
    }

    public function testAValidCsrfHeaderPasses(): void
    {
        $request = new Request('POST', '/api/config', '/index.php', '{}');
        $headers = ['HTTP_X_WPSM_CSRF' => Csrf::issue('session-token', 'secret')];

        self::assertNull(Middleware::requireCsrf($request, 'session-token', 'secret', $headers));
    }

    public function testCsrfIsNotRequiredOnReads(): void
    {
        $request = new Request('GET', '/api/env');

        self::assertNull(Middleware::requireCsrf($request, 'session-token', 'secret', []));
    }
}
```

- [ ] **Step 2: Run to verify failure**

Run: `vendor/bin/phpunit --filter MiddlewareTest`
Expected: FAIL — `Class "WPSafeMode\Api\Middleware" not found`.

- [ ] **Step 3: Write the middleware**

Create `src/Api/Middleware.php`:

```php
<?php

declare(strict_types=1);

namespace WPSafeMode\Api;

use WPSafeMode\Core\Request;
use WPSafeMode\Core\Response;
use WPSafeMode\Installer\InstallState;
use WPSafeMode\Security\AuthSessions;
use WPSafeMode\Security\Csrf;

/**
 * The three gates every non-installer endpoint passes through. Each returns
 * null to continue or a Response to short-circuit.
 */
final class Middleware
{
    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    /** Spec 5.1: refuse to operate unconfigured. */
    public static function requireInstalled(InstallState $state): ?Response
    {
        if ($state->installed) {
            return null;
        }

        return Response::json([
            'error' => 'not_installed',
            'step'  => $state->step,
        ], 409);
    }

    /** @param array<string, mixed> $server */
    public static function requireAuth(
        AuthSessions $sessions,
        array $server,
        int $now
    ): ?Response {
        $token = self::sessionToken($server);

        if ($token === '') {
            return Response::json(['error' => 'unauthenticated'], 401);
        }

        $ok = $sessions->validate(
            $token,
            self::clientIp($server),
            (string) ($server['HTTP_USER_AGENT'] ?? ''),
            $now
        );

        return $ok ? null : Response::json(['error' => 'unauthenticated'], 401);
    }

    /** @param array<string, mixed> $server */
    public static function requireCsrf(
        Request $request,
        string $sessionToken,
        string $secret,
        array $server
    ): ?Response {
        if (in_array($request->method, self::SAFE_METHODS, true)) {
            return null;
        }

        $presented = (string) ($server['HTTP_X_WPSM_CSRF'] ?? '');

        return Csrf::verify($sessionToken, $secret, $presented)
            ? null
            : Response::json(['error' => 'bad_csrf'], 403);
    }

    /** @param array<string, mixed> $server */
    public static function sessionToken(array $server): string
    {
        $cookies = (string) ($server['HTTP_COOKIE'] ?? '');

        foreach (explode(';', $cookies) as $pair) {
            $parts = explode('=', trim($pair), 2);

            if (count($parts) === 2 && $parts[0] === AuthSessions::COOKIE) {
                return $parts[1];
            }
        }

        return '';
    }

    /**
     * The direct peer, never X-Forwarded-For.
     *
     * This value keys the login lockout bucket. An attacker who can set the
     * header can spread their attempts across unlimited buckets, so the header
     * is ignored: a reverse proxy that needs it must be configured to rewrite
     * REMOTE_ADDR instead.
     *
     * @param array<string, mixed> $server
     */
    public static function clientIp(array $server): string
    {
        $ip = (string) ($server['REMOTE_ADDR'] ?? '');

        return $ip === '' ? 'unknown' : $ip;
    }

    /** @param array<string, mixed> $server */
    public static function isHttps(array $server): bool
    {
        $https = strtolower((string) ($server['HTTPS'] ?? ''));

        if ($https !== '' && $https !== 'off') {
            return true;
        }

        return strtolower((string) ($server['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    }
}
```

- [ ] **Step 4: Write the auth controller**

Create `src/Api/AuthController.php`:

```php
<?php

declare(strict_types=1);

namespace WPSafeMode\Api;

use WPSafeMode\Core\Request;
use WPSafeMode\Core\Response;
use WPSafeMode\Installer\Installer;
use WPSafeMode\Security\AuthSessions;
use WPSafeMode\Security\Csrf;
use WPSafeMode\Security\WebAccessibilitySelfTest;
use WPSafeMode\Storage\AuditEvent;
use WPSafeMode\Storage\Contracts\Storage;

final class AuthController
{
    /** @param array<string, mixed> $server */
    public function __construct(
        private Storage $storage,
        private AuthSessions $sessions,
        private Installer $installer,
        private string $dataDir,
        private array $server,
    ) {
    }

    public function login(Request $request): Response
    {
        $now = time();
        $ip = Middleware::clientIp($this->server);
        $agent = (string) ($this->server['HTTP_USER_AGENT'] ?? '');

        $result = $this->sessions->login(
            (string) ($request->json()['password'] ?? ''),
            $ip,
            $agent,
            $now
        );

        if (!$result->ok) {
            $this->storage->auditLog()->record(new AuditEvent(
                $now,
                'anonymous',
                AuditEvent::LOGIN_FAILED,
                null,
                ['reason' => $result->reason],
                $ip
            ));

            $status = $result->reason === 'locked_out' ? 429 : 401;

            return Response::json(
                ['error' => $result->reason, 'retry_after' => $result->retryAfter],
                $status
            );
        }

        $token = (string) $result->token;

        $this->storage->auditLog()->record(new AuditEvent($now, 'operator', AuditEvent::LOGIN, null, [], $ip));

        // Spec 15.1: the self-test runs on every login, not only at install. A
        // host migration or a control-panel change can silently remove the
        // directory protection months after setup.
        $baseUrl = WebAccessibilitySelfTest::baseUrlFrom($this->server);
        $selfTest = $baseUrl === null ? null : $this->installer->runSelfTest($baseUrl);

        $response = Response::json([
            'ok'         => true,
            'csrf'       => Csrf::issue($token, $this->installSecret()),
            'self_test'  => $selfTest === null ? null : $selfTest->toArray(),
        ]);

        $response->headers['Set-Cookie'] = $this->sessions->cookieHeader(
            $token,
            Middleware::isHttps($this->server),
            rtrim(dirname((string) ($this->server['SCRIPT_NAME'] ?? '/index.php')), '/'),
            $now
        );

        return $response;
    }

    public function logout(Request $request): Response
    {
        $token = Middleware::sessionToken($this->server);

        if ($token !== '') {
            $this->sessions->logout($token);
            $this->storage->auditLog()->record(AuditEvent::now(
                AuditEvent::LOGOUT,
                null,
                [],
                Middleware::clientIp($this->server)
            ));
        }

        $response = Response::json(['ok' => true]);
        $response->headers['Set-Cookie'] = $this->sessions->clearCookieHeader(
            Middleware::isHttps($this->server),
            rtrim(dirname((string) ($this->server['SCRIPT_NAME'] ?? '/index.php')), '/')
        );

        return $response;
    }

    /** The install-scoped secret CSRF tokens are derived from. */
    private function installSecret(): string
    {
        return (string) $this->storage->config()->get('install_id', 'uninstalled');
    }
}
```

- [ ] **Step 5: Wire the routes**

Replace the routing block at the end of `src/index.php`:

```php
$server = $_SERVER;
$dataDir = __DIR__ . '/../data';

try {
    $storage = StorageFactory::open($dataDir, Environment::probe());
} catch (StorageException $e) {
    Response::json(['error' => 'storage_unavailable', 'message' => $e->getMessage()], 500)->send();
    exit;
}

$installer = new Installer($storage, new KeyVault($dataDir), new PasswordHasher(), $dataDir);
$sessions  = new AuthSessions($storage, new PasswordHasher());
$state     = $installer->state();

$router = new Router();

// Always reachable: the installer, and the environment report the installer's
// own first screen renders.
$install = new InstallController($installer, $server);
$router->add('GET',  '/api/install/state',    [$install, 'state']);
$router->add('POST', '/api/install/token',    [$install, 'verifyToken']);
$router->add('POST', '/api/install/password', [$install, 'setPassword']);
$router->add('POST', '/api/install/self-test', [$install, 'selfTest']);
$router->add('POST', '/api/install/api-key',  [$install, 'apiKey']);
$router->add('POST', '/api/install/finish',   [$install, 'finish']);
$router->add('GET',  '/api/env',              new EnvController($storage));

$auth = new AuthController($storage, $sessions, $installer, $dataDir, $server);
$router->add('POST', '/api/auth/login',  [$auth, 'login']);
$router->add('POST', '/api/auth/logout', [$auth, 'logout']);

$path = $request->path();
$isOpenRoute = strpos($path, '/api/install/') === 0
    || $path === '/api/env'
    || strpos($path, '/api/auth/') === 0;

if (!$isOpenRoute) {
    $gate = Middleware::requireInstalled($state)
        ?? Middleware::requireAuth($sessions, $server, time())
        ?? Middleware::requireCsrf(
            $request,
            Middleware::sessionToken($server),
            (string) $storage->config()->get('install_id', 'uninstalled'),
            $server
        );

    if ($gate !== null) {
        $gate->send();
        exit;
    }
}

$router->dispatch($request)->send();
```

Add the matching `use` statements. Note `Router::add()` already accepts any `callable`, so `[$object, 'method']` needs no change to M0 code.

- [ ] **Step 6: Verify the gate end to end**

Docker is unavailable, so use the same scratch tree as M0 Task 7:

```bash
WP=$(mktemp -d) && mkdir -p "$WP/wp-includes"
printf '<?php\n' > "$WP/wp-config.php"
printf "<?php \$wp_version = '6.9';\n" > "$WP/wp-includes/version.php"
cp -R src "$WP/wpsafemode"
php -S 127.0.0.1:8126 -t "$WP" &
sleep 1
B=http://127.0.0.1:8126/wpsafemode/index.php
curl -s "$B/api/install/state"
curl -s -o /dev/null -w 'guarded route: %{http_code}\n' "$B/api/sessions"
curl -s -o /dev/null -w 'login: %{http_code}\n' -X POST -d '{"password":"x"}' "$B/api/auth/login"
kill %1
```

Expected: `/api/install/state` returns `"installed":false`; the guarded route returns **409** with `not_installed`; the login attempt returns **401**.

- [ ] **Step 7: Commit**

```bash
git add src/Api/ src/index.php tests/Api/
git commit -m "feat(api): gate every endpoint behind install, auth, and csrf checks"
```

---

## Task 11: One-click self-rename

**Files:**
- Create: `src/Installer/SelfRename.php`
- Modify: `src/Api/InstallController.php` (add `rename`), `src/index.php` (add the route)
- Test: `tests/Installer/SelfRenameTest.php`

**Interfaces:**
- Consumes: `AuditLog`.
- Produces: `SelfRename::suggest(): string` (`wpsm-{12 hex}`), `SelfRename::rename(string $appDir, string $newName): string` returning the new absolute path, `SelfRename::isSafeName(string $name): bool`.

Spec §5.2: the ZIP extracts to `wpsafemode/`, and the installer offers — and strongly recommends — a one-click rename to `wpsm-{random}/`, then redirects. A predictable directory name is what turns "someone would have to find it" into "someone scans for it".

- [ ] **Step 1: Write the failing test**

Create `tests/Installer/SelfRenameTest.php`:

```php
<?php

declare(strict_types=1);

namespace WPSafeMode\Tests\Installer;

use PHPUnit\Framework\TestCase;
use WPSafeMode\Installer\SelfRename;
use WPSafeMode\Storage\Contracts\StorageException;

final class SelfRenameTest extends TestCase
{
    private string $parent;

    protected function setUp(): void
    {
        $this->parent = sys_get_temp_dir() . '/wpsm-rename-' . bin2hex(random_bytes(6));
        mkdir($this->parent . '/wpsafemode', 0755, true);
        file_put_contents($this->parent . '/wpsafemode/index.php', '<?php');
    }

    protected function tearDown(): void
    {
        foreach (glob($this->parent . '/*/*') ?: [] as $file) {
            unlink($file);
        }
        foreach (glob($this->parent . '/*') ?: [] as $dir) {
            rmdir($dir);
        }
        @rmdir($this->parent);
    }

    public function testSuggestedNamesAreRandomAndWellFormed(): void
    {
        $a = SelfRename::suggest();
        $b = SelfRename::suggest();

        self::assertNotSame($a, $b);
        self::assertMatchesRegularExpression('/^wpsm-[0-9a-f]{12}$/', $a);
        self::assertTrue(SelfRename::isSafeName($a));
    }

    public function testRenamingMovesTheDirectory(): void
    {
        $new = SelfRename::rename($this->parent . '/wpsafemode', 'wpsm-abc123abc123');

        self::assertSame($this->parent . '/wpsm-abc123abc123', $new);
        self::assertDirectoryExists($new);
        self::assertFileExists($new . '/index.php');
        self::assertDirectoryDoesNotExist($this->parent . '/wpsafemode');
    }

    public function testTraversalAndSeparatorsAreRejected(): void
    {
        foreach (['../escape', 'a/b', 'a\\b', '..', '.', '', 'has space'] as $bad) {
            self::assertFalse(SelfRename::isSafeName($bad), $bad . ' should be rejected');
        }
    }

    public function testRenamingToAnUnsafeNameThrows(): void
    {
        $this->expectException(StorageException::class);
        SelfRename::rename($this->parent . '/wpsafemode', '../escape');
    }

    public function testRenamingOntoAnExistingDirectoryThrows(): void
    {
        mkdir($this->parent . '/wpsm-occupied00', 0755, true);

        $this->expectException(StorageException::class);
        SelfRename::rename($this->parent . '/wpsafemode', 'wpsm-occupied00');
    }
}
```

- [ ] **Step 2: Run to verify failure, then write SelfRename**

Run: `vendor/bin/phpunit --filter SelfRenameTest` — expect `Class "WPSafeMode\Installer\SelfRename" not found`.

Create `src/Installer/SelfRename.php`:

```php
<?php

declare(strict_types=1);

namespace WPSafeMode\Installer;

use WPSafeMode\Storage\Contracts\StorageException;

/**
 * Renames the install directory to something unguessable (spec 5.2).
 *
 * The ZIP extracts to wpsafemode/, which is exactly the name a scanner looks
 * for. Renaming does not make the tool safe on its own — it buys the time in
 * which the password, the lockout and the data-directory denial do the actual
 * work.
 */
final class SelfRename
{
    public static function suggest(): string
    {
        return 'wpsm-' . bin2hex(random_bytes(6));
    }

    /**
     * A single path segment: letters, digits, dash and underscore only. This
     * value comes from a form field and is about to be concatenated into a
     * filesystem path, so it is validated by construction rather than filtered.
     */
    public static function isSafeName(string $name): bool
    {
        return preg_match('/^[A-Za-z0-9_-]{3,64}$/', $name) === 1;
    }

    /** @return string the new absolute path */
    public static function rename(string $appDir, string $newName): string
    {
        if (!self::isSafeName($newName)) {
            throw new StorageException(
                'That folder name is not allowed. Use letters, numbers, dashes and underscores only.'
            );
        }

        $appDir = rtrim($appDir, '/');
        $parent = dirname($appDir);
        $target = $parent . '/' . $newName;

        if (!is_dir($appDir)) {
            throw new StorageException('The application directory could not be found.');
        }

        if (file_exists($target)) {
            throw new StorageException('A folder called "' . $newName . '" already exists next to this one.');
        }

        if (!@rename($appDir, $target)) {
            throw new StorageException(
                'The folder could not be renamed. The web server may not have permission to write to its parent '
                    . 'directory — rename it over FTP instead.'
            );
        }

        return $target;
    }
}
```

- [ ] **Step 3: Add the endpoint**

In `src/Api/InstallController.php`:

```php
    public function rename(Request $request): Response
    {
        $body = $request->json();

        if (!$this->installer->verifyToken((string) ($body['token'] ?? ''))
            && !$this->installer->state()->installed) {
            return Response::json(['error' => 'bad_token'], 403);
        }

        $name = (string) ($body['name'] ?? SelfRename::suggest());

        try {
            SelfRename::rename(dirname(__DIR__), $name);
        } catch (StorageException $e) {
            return Response::json(['error' => 'rename_failed', 'message' => $e->getMessage()], 422);
        }

        // The browser must be told where the tool now lives; the current URL is
        // about to 404. A relative redirect keeps the host and scheme intact.
        return Response::json(['ok' => true, 'redirect' => '../' . $name . '/']);
    }
```

Add `use WPSafeMode\Installer\SelfRename;` and register `$router->add('POST', '/api/install/rename', [$install, 'rename']);` in `src/index.php`.

- [ ] **Step 4: Run and commit**

```bash
vendor/bin/phpunit && vendor/bin/phpstan analyse --no-progress
git add src/Installer/SelfRename.php src/Api/ src/index.php tests/Installer/
git commit -m "feat(installer): add one-click self-rename to an unguessable directory"
```

---

## Task 12: Uninstall self-destruct

**Files:**
- Create: `src/Installer/Uninstaller.php`
- Create: `src/Api/UninstallController.php`
- Modify: `src/index.php`
- Test: `tests/Installer/UninstallerTest.php`

**Interfaces:**
- Consumes: `AuditLog`.
- Produces: `Uninstaller::run(string $appDir, string $dataDir): string` returning the path of the confirmation file left behind.

Spec §5.8: one-click self-destruct that wipes the data directory and the script itself, leaving only a final confirmation file. **Leaving rescue scripts behind is how sites get hacked; cleanup must be easier than not cleaning up.**

- [ ] **Step 1: Write the failing test**

Create `tests/Installer/UninstallerTest.php`:

```php
<?php

declare(strict_types=1);

namespace WPSafeMode\Tests\Installer;

use PHPUnit\Framework\TestCase;
use WPSafeMode\Installer\Uninstaller;

final class UninstallerTest extends TestCase
{
    private string $parent;
    private string $appDir;
    private string $dataDir;

    protected function setUp(): void
    {
        $this->parent = sys_get_temp_dir() . '/wpsm-uninstall-' . bin2hex(random_bytes(6));
        $this->appDir = $this->parent . '/wpsm-abc123';
        $this->dataDir = $this->parent . '/data';

        mkdir($this->appDir . '/Security', 0755, true);
        mkdir($this->dataDir . '/sessions/sesn_1', 0700, true);

        file_put_contents($this->appDir . '/index.php', '<?php');
        file_put_contents($this->appDir . '/Security/KeyVault.php', '<?php');
        file_put_contents($this->dataDir . '/config.php', '<?php // api key');
        file_put_contents($this->dataDir . '/keyvault.php', '<?php // master secret');
        file_put_contents($this->dataDir . '/sessions/sesn_1/session.php', '<?php');
    }

    protected function tearDown(): void
    {
        $this->removeRecursively($this->parent);
    }

    private function removeRecursively(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $full = $path . '/' . $entry;
            is_dir($full) ? $this->removeRecursively($full) : unlink($full);
        }
        rmdir($path);
    }

    public function testEverySecretIsGone(): void
    {
        Uninstaller::run($this->appDir, $this->dataDir);

        self::assertDirectoryDoesNotExist($this->dataDir);
        self::assertFileDoesNotExist($this->dataDir . '/keyvault.php');
        self::assertFileDoesNotExist($this->dataDir . '/config.php');
    }

    public function testTheApplicationDirectoryIsGone(): void
    {
        Uninstaller::run($this->appDir, $this->dataDir);

        self::assertDirectoryDoesNotExist($this->appDir);
    }

    public function testAConfirmationFileIsLeftBehind(): void
    {
        $receipt = Uninstaller::run($this->appDir, $this->dataDir);

        self::assertFileExists($receipt);
        self::assertStringContainsString('WP Safe Mode', (string) file_get_contents($receipt));
    }

    public function testTheConfirmationFileHoldsNoSecrets(): void
    {
        $receipt = Uninstaller::run($this->appDir, $this->dataDir);
        $contents = (string) file_get_contents($receipt);

        foreach (['api key', 'master secret', 'sk-ant'] as $needle) {
            self::assertStringNotContainsString($needle, $contents);
        }
    }

    public function testItDoesNotTouchSiblingDirectories(): void
    {
        mkdir($this->parent . '/wp-content', 0755, true);
        file_put_contents($this->parent . '/wp-content/keep.php', 'keep me');

        Uninstaller::run($this->appDir, $this->dataDir);

        self::assertFileExists($this->parent . '/wp-content/keep.php');
    }
}
```

- [ ] **Step 2: Run to verify failure, then write the Uninstaller**

Create `src/Installer/Uninstaller.php`:

```php
<?php

declare(strict_types=1);

namespace WPSafeMode\Installer;

/**
 * Removes the data directory and then the application itself (spec 5.8).
 *
 * Order matters: secrets first. If the process dies halfway, what survives is
 * an application with nothing in it rather than an unreachable application
 * whose key vault is still on disk.
 *
 * A receipt is left beside the removed directory so the operator can see the
 * cleanup happened rather than wondering whether the tool half-deleted itself.
 */
final class Uninstaller
{
    public const RECEIPT = 'WPSAFEMODE-REMOVED.txt';

    /** @return string the path of the receipt */
    public static function run(string $appDir, string $dataDir): string
    {
        $appDir = rtrim($appDir, '/');
        $parent = dirname($appDir);

        self::removeRecursively($dataDir);

        $receipt = $parent . '/' . self::RECEIPT;

        file_put_contents(
            $receipt,
            "WP Safe Mode was uninstalled on " . gmdate('Y-m-d H:i:s') . " UTC.\n\n"
                . "The tool removed its own folder and its data directory, including the stored API key.\n"
                . "Nothing of it remains except this note, which is safe to delete.\n\n"
                . "If you used the tool to change your site, those changes are still in place — uninstalling\n"
                . "does not undo them.\n"
        );

        self::removeRecursively($appDir);

        return $receipt;
    }

    private static function removeRecursively(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            @unlink($path);

            return;
        }

        if (!is_dir($path)) {
            return;
        }

        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            self::removeRecursively($path . '/' . $entry);
        }

        @rmdir($path);
    }
}
```

- [ ] **Step 3: Add the endpoint**

Create `src/Api/UninstallController.php`:

```php
<?php

declare(strict_types=1);

namespace WPSafeMode\Api;

use WPSafeMode\Core\Request;
use WPSafeMode\Core\Response;
use WPSafeMode\Installer\Uninstaller;
use WPSafeMode\Storage\AuditEvent;
use WPSafeMode\Storage\Contracts\Storage;

final class UninstallController
{
    /** @param array<string, mixed> $server */
    public function __construct(
        private Storage $storage,
        private string $appDir,
        private string $dataDir,
        private array $server,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        // Typed confirmation rather than a bare POST: this is irreversible and
        // reachable from an authenticated session, so a mis-click must not do it.
        if ((string) ($request->json()['confirm'] ?? '') !== 'DELETE') {
            return Response::json(['error' => 'confirmation_required'], 422);
        }

        $this->storage->auditLog()->record(AuditEvent::now(
            AuditEvent::UNINSTALL,
            null,
            [],
            Middleware::clientIp($this->server)
        ));

        // The response is built before anything is deleted: afterwards there is
        // no storage left to read and no code left on disk to read it with.
        $body = Response::json([
            'ok'      => true,
            'message' => 'WP Safe Mode has removed itself. This page will not load again.',
        ]);

        Uninstaller::run($this->appDir, $this->dataDir);

        return $body;
    }
}
```

Register it behind the full gate in `src/index.php`:

```php
$router->add('POST', '/api/uninstall', new UninstallController($storage, __DIR__, $dataDir, $server));
```

- [ ] **Step 4: Run and commit**

```bash
vendor/bin/phpunit && vendor/bin/phpstan analyse --no-progress
git add src/Installer/Uninstaller.php src/Api/UninstallController.php src/index.php tests/Installer/
git commit -m "feat(installer): add one-click uninstall self-destruct"
```

---

## Task 13: Installer and login UI

**Files:**
- Create: `frontend/src/api.js`
- Create: `frontend/src/screens/Install.jsx`
- Create: `frontend/src/screens/Login.jsx`
- Create: `frontend/src/components/{Card,Field,Pill,Notice}.jsx`
- Modify: `frontend/src/app.jsx`
- Modify: `src/public/**` (rebuilt output, committed)

**Interfaces:**
- Consumes: `/api/install/*`, `/api/auth/*`, `/api/env`.
- Produces: the screens M2+ mounts inside. `api.js` exports `get(path)`, `post(path, body)` — `post` attaches the CSRF header automatically.

Use the design system already extracted to `docs/design/2026-07-31-visual-language.md` and the tokens already defined in `frontend/src/style.css`. The voice rules in §4 of that document apply to every string in this task; they matter most here, because the installer is where a worried person meets the tool for the first time.

- [ ] **Step 1: Write the API helper**

Create `frontend/src/api.js`:

```js
// The tool lives in a randomly named directory, so every request is relative
// to the current document — never rooted at /.
const BASE = 'api/'

let csrf = null

export function setCsrf(token) {
  csrf = token
}

async function request(path, options = {}) {
  const response = await fetch(BASE + path, {
    ...options,
    headers: {
      'Content-Type': 'application/json',
      ...(csrf ? { 'X-WPSM-CSRF': csrf } : {}),
      ...(options.headers || {}),
    },
  })

  const body = await response.json().catch(() => ({}))

  if (!response.ok) {
    const error = new Error(body.message || body.error || `HTTP ${response.status}`)
    error.code = body.error
    error.status = response.status
    error.retryAfter = body.retry_after ?? 0
    throw error
  }

  return body
}

export const get = (path) => request(path)
export const post = (path, body) => request(path, { method: 'POST', body: JSON.stringify(body ?? {}) })
```

- [ ] **Step 2: Write the shared components**

`Card`, `Field`, `Pill` and `Notice` are the four shapes every screen in M1 needs. Keep them presentational — no fetching, no routing.

```jsx
// frontend/src/components/Notice.jsx
export function Notice({ tone = 'info', title, children }) {
  const tones = {
    info: 'border-edge bg-surface-raised text-ink',
    warn: 'border-warn/40 bg-warn/10 text-warn-deep',
    danger: 'border-danger/40 bg-danger/10 text-danger-deep',
    good: 'border-green/30 bg-green/10 text-green',
  }

  return (
    <div class={`rounded-panel border p-4 ${tones[tone]}`} role={tone === 'danger' ? 'alert' : undefined}>
      {title && <p class="font-medium">{title}</p>}
      <div class="mt-1 text-sm leading-relaxed">{children}</div>
    </div>
  )
}
```

Write `Card.jsx` (rounded-card, `border-edge`, `bg-surface`, `shadow-card`, optional title and trailing slot), `Field.jsx` (label, input, hint, error, wired for `type="password"`), and `Pill.jsx` (the one already in `app.jsx`, extracted so both screens share it).

- [ ] **Step 3: Write the installer screen**

`Install.jsx` drives the wizard from `/api/install/state`, rendering one step at a time: token → password → API key → rename → done. Copy rules, applied:

- The token step says where to find it: *"Open `data/install-token.php` over FTP or your host's file manager and paste the token inside. This proves you're the site owner and not someone who found this page first."*
- The password step shows every weakness at once, from `weaknesses()`, rather than one at a time.
- The API-key step shows the self-test result **before** the input. When `reachable` is true, render a `Notice` with `tone="danger"`, the plain-language detail from the server, and the nginx remediation:

  ```
  location ~ ^/<install-dir>/data/ { deny all; return 404; }
  ```

  The key input stays disabled behind an explicit "I understand the risk and want to continue anyway" checkbox — the user may proceed, it is their site, but not by accident.
- The same screen states the honest limitation from spec §5.5 in one sentence: *"Your key is encrypted on disk, but the key that unlocks it lives in the same folder. That protects you from a leaked backup or a stray directory listing — not from someone who can read every file on the server."*
- The rename step leads with the consequence: *"Right now this tool sits at a name anyone can guess. Move it somewhere they can't."* Show the suggested name, let it be edited, and on success follow `redirect`.

- [ ] **Step 4: Write the login screen**

`Login.jsx`: one password field. On `429`, show the wall-clock wait — *"Too many attempts. Try again in 14 minutes."* — computed from `retry_after`, and disable the button until it elapses. On success, call `setCsrf(body.csrf)` and, when `body.self_test.reachable` is true, show the same danger notice as the installer, because a host change can expose the directory long after setup.

- [ ] **Step 5: Route in app.jsx**

`App` fetches `/api/install/state` first. Not installed → `<Install/>`. Installed but `/api/env` returns 401 → `<Login/>`. Otherwise → the M0 environment panel, which stays as the placeholder M2 replaces.

- [ ] **Step 6: Build, verify, and commit the output**

```bash
cd frontend && npm run build && cd ..
grep -o 'src="[^"]*"' src/public/index.html
```

Expected: a **relative** `./assets/index-<hash>.js`. Then run the scratch-tree server from Task 10 Step 6, open the URL in a browser, and confirm the installer's first screen renders and that a wrong install token is refused.

```bash
git add frontend/ src/public/
git commit -m "feat(frontend): add installer and login screens"
```

---

## Self-review

**Spec coverage for M1.** §14 defines M1 as: setup wizard (password, API key, self-rename), auth/sessions/rate-limit/CSRF, data-dir protection plus web-accessibility self-test, key vault.

| Spec | Task |
|---|---|
| §5.1 installer flow, install token, refuse-to-operate-unconfigured | 9, 10 |
| §5.1 argon2id with bcrypt fallback, recorded algo, transparent rehash | 3, 5 |
| §5.2 random directory name | 11 |
| §5.3 own session storage, lockout, cookie flags | 4, 5 |
| §5.4 `.htaccess` + `index.php` + web-accessibility self-test | 1, 8 |
| §5.5 key vault, sodium with OpenSSL fallback, never returned to browser | 2, 9 |
| §5.6 CSRF on every mutating endpoint | 6, 10 |
| §5.7 no credentials to the model | inherited from M0; nothing in M1 adds a path or key to any response |
| §5.8 uninstall self-destruct | 12 |
| §7 `AuthStore`, `RateLimiter` as narrow interfaces, both backends | 3, 4 |
| §7.1 `auth`, `auth_sessions`, `rate_limit`, `audit_log` tables | 3, 4, 5, 7 |
| §7.2 JSON fallback, `flock` + atomic rename, append-only JSONL | 1, 7 |
| §15.1 no servable extension under `data/` | 1 |

**Deliberately deferred, with the milestone that owns each.** IP allowlist (§5.3, "optional") is M3 — it needs the settings UI M2 introduces, and a wrong allowlist locks the operator out of their own rescue, so it wants a recovery path first. `StepLog`, `SnapshotIndex` (§7) are M3's safety engine. Structural tool-input validation and SQL identifier allowlisting (§5.6) are M4, where the first tool that takes an identifier is written; the rule is recorded in this plan's Global Constraints so it is not rediscovered then.

**Two spec conflicts resolved, both recorded above the tasks:** the `data/` filename policy (§7.2 vs §15.1) and the un-guardable SQLite file. Both are decisions a reviewer should be able to overturn, so they are stated as decisions rather than buried in code.

**One assumption flagged.** §5.1 says "generate a random install token" without stating its purpose. Task 9 uses it as proof-of-filesystem-access before the first password can be set, which is the only reading under which it does any work. If the spec meant something else, Task 9 Step 3 is the only place to change.

**Placeholder scan.** No "TBD", no "add error handling", no "similar to Task N". Task 13 Steps 3–4 describe screens in prose plus copy rather than full JSX — deliberate, because the components are conventional and the *copy* is the part that carries the requirement. Every PHP step carries complete, runnable code.

**Type consistency.** `Credential` is `hash/algo/createdAt/changedAt` in the value object (3), both stores (3), and `PasswordHasher` (3, 5). `RateLimitState` is `attempts/windowStart/lockedUntil` in the object (4), both limiters (4), and `AuthSessions::login()` (5). `SelfTestResult::blocksApiKey()` is defined in 8 and consumed by `Installer::storeApiKey()` (9). `InstallState` is produced by `Installer::state()` (9) and consumed by `Middleware::requireInstalled()` (10) and `InstallController` (9). `AuditEvent::now()` is defined in 7 and used in 9, 10, 12. `Csrf::issue()` is defined in 6, called by `AuthController::login()` (10) and verified by `Middleware::requireCsrf()` (10), both keyed on `config('install_id')`, which `Installer::finalize()` (9) writes. `Storage` grows `auth()`, `rateLimiter()`, `authSessions()` and `auditLog()` across Tasks 3, 4, 5 and 7; every one is added to both `SqliteStorage` and `JsonStorage` in the task that introduces it.

**Known soft spot.** Task 10 Step 6 and Task 8 Step 4 both need a live web server. Docker is unavailable on this machine, so both use `php -S` against a scratch tree, which is how M0 verified `/api/env`. That proves the request path and the self-test's positive case, but not Apache's `.htaccess` handling — the one thing `php -S` cannot model. Re-run Task 8 under the Apache playground the moment Docker works; until then the self-test's *negative* result on Apache is unproven, and the plan should not be read as claiming otherwise.
