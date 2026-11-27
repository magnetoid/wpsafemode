# M1 Re-cut — Identity, Installer and Gates Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Finish M1 so that an uploaded copy refuses to do anything until it is installed, is then usable only by a logged-in user whose role permits the route, and carries the identity, permission and migration foundations every later phase builds on.

**Architecture:** A migration runner replaces the idempotent schema installer in both storage backends. A `users` store replaces the single credential. Every API request goes through one `Kernel`, which matches the route, enforces the install gate, resolves a `Principal`, and checks the route's declared permission, channel and CSRF token before any handler runs. The installer creates the first user, the owner.

**Tech Stack:** PHP 8.0 (no Composer, no runtime dependencies), SQLite via PDO or guarded JSON files, libsodium with an OpenSSL fallback, cURL, PHPUnit 9.6, PHPStan 2.1, Preact + Vite + Tailwind.

**Spec:** `docs/superpowers/specs/2026-11-25-full-ai-control-design.md` (the amendment; §7, §8, §14.1, §16, §18 are the parts this plan implements) and the base spec it amends, `docs/superpowers/specs/2026-07-24-wp-safe-mode-design.md` (§5, §7).

**Companion:** `docs/superpowers/plans/2026-08-01-m1-installer-security.md`, called **the old plan** below. Its Tasks 1–4 are built and merged into this branch. Its Tasks 5–13 are superseded by this document. Where a task here says "as old Task N", build that task from the old plan's section of that name with the substitutions listed here; where the two disagree, this document wins.

## Global Constraints

Every task's requirements implicitly include this section. The first eleven lines are carried from the old plan unchanged.

- **PHP 8.0.0 is the floor.** No `readonly`, no enums, no `never`, no first-class callable syntax, no `array_is_list()`, no `new` in initializers. `match` and constructor property promotion are allowed. `phpstan.neon.dist` pins `phpVersion: 80000`.
- **Closed sets are `final class` constants, never enums** (roles, permissions, channels, install steps, audit events).
- **Nothing under `src/` may require anything from `vendor/`.** The shipped ZIP has zero dependencies.
- **Never modify v1.1 files:** `controller/`, `model/`, `view/`, `core/`, `services/`, `helpers/`, `security/`, `ext/`, `assets/`, `sfstore/`, and the root `autoload.php` and `index.php`. v2's security code is `src/Security/`, not the lowercase root `security/`.
- **`data/` is never committed.**
- **No absolute server path may reach the browser or the model.**
- **No file under `data/` may carry an extension a web server serves statically.** State files are `.php` with the die-guard, written through `GuardedJson` / `GuardedJsonLines`.
- **Every `json_encode` whose output lands in a `.php` file sets `JSON_HEX_TAG`.**
- **Fail closed on anything that classifies a file or a stored record as safe.** A stored record that cannot be read back intact throws `StorageException`; it is never treated as "absent".
- **A guard decision is made from a locked file handle, never from a pre-`fopen()` snapshot.**
- **Commit after every task**, conventional commit messages. Before every commit run the PHP 8.0 gate and the suite: `make test` where Docker works, `make check-host` otherwise.
- **Every schema change is a numbered migration step, on both backends, in the same task as the code that needs it.** Each such task bumps `Version::SCHEMA` by exactly one and adds a migration test.
- **Every route declares a permission.** Owner-only routes accept the browser channel only (amendment §8.1, §8.3).
- **Only `Request` reads `$_SERVER`, and only `Kernel` turns a cookie into a `Principal`.** Controllers receive both through the `Request`.
- **Emergency posture only.** The installer does not ask about posture; it records `emergency` (amendment §7).
- **The installer and login must work with no WordPress beside the tool** (amendment §3).

## Review Focus

Failure modes the spec implies that are most likely to hurt a real user. Each is pinned by a test in the task named.

1. **A login name can be probed.** An unknown or disabled login must be indistinguishable from a wrong password: same error code, same lockout accounting, and the same hashing work is still done. *Task 6.*
2. **A user disabled while logged in keeps working.** Their existing session must stop on the very next request, not at expiry. *Tasks 6 and 11.*
3. **A migration is cut off mid-step** by the 30-second limit or a crash. The next request must finish the job or find the data untouched; it must never see half a schema. *Task 2.*
4. **A route ships without anyone deciding who may call it.** The old plan's rename endpoint was callable by anyone once the tool was installed. Registration must reject an undeclared permission, and one table test must list every route. *Tasks 5 and 11.*
5. **Two people reach an uninstalled copy at once.** Only one owner may ever be created by the installer, and every installer mutation must prove filesystem access with the install token. *Task 10.*

## Where this plan starts

Branch `feat/m1-installer-security` holds M0 plus old Tasks 1–4. Before Task 1, fast-forward it to `design/full-ai-control`, which adds only documents (the amendment and this plan) and an uncommitted fix to `docker/dev/Dockerfile` that Task 1 commits. All work in this plan lands on `feat/m1-installer-security`.

What exists and is not yet wired in: `AuthStore`, `RateLimiter` and their two implementations each are built and conformance-tested, but `Storage` (`src/Storage/Contracts/Storage.php`) exposes only `config()`, `sessions()` and `messages()`.

## Two spec wordings this plan resolves

1. **Where the user fields live.** Amendment §8.2 says `Credential` gains `userId`, `login`, `role`, `disabledAt`. `PasswordHasher::hash()` returns a `Credential` and has no user to put in it, so those fields would force a change to reviewed hashing code. This plan adds a `User` value object that holds a `Credential`; `Credential` and `PasswordHasher` are untouched. The intent of §8.2 is met.
2. **Where the posture is recorded.** Amendment §7 says "in `meta`". No meta store is exposed through `Storage`; the posture is written to `ConfigStore` under the key `posture`.

## File Structure

| File | Responsibility | Task |
|---|---|---|
| `src/Storage/Sqlite/SqliteMigrator.php` | Ordered SQLite schema steps, one transaction each | 2 |
| `src/Storage/Json/JsonMigrator.php` | Ordered JSON-backend steps, idempotent, version recorded last | 2 |
| `src/Security/Role.php` | The four roles and their order | 3 |
| `src/Storage/User.php` | A user: id, login, role, credential, disabled-at | 3 |
| `src/Storage/Contracts/UserStore.php` + `Sqlite/SqliteUserStore.php`, `Json/JsonUserStore.php` | Users, replacing `AuthStore` and its two implementations | 3 |
| `src/Security/Permission.php`, `Channel.php`, `Principal.php` | What a route requires; how a caller arrived; who is acting | 5 |
| `src/Core/Route.php`, `RouteMatch.php` | One registered route; the result of matching a request | 5 |
| `src/Security/AuthSessions.php`, `LoginResult.php`; `src/Storage/Contracts/AuthSessionStore.php` + two implementations | Login sessions bound to a user | 6 |
| `src/Security/Csrf.php` | CSRF token derived from the session | 7 |
| `src/Storage/AuditEvent.php`, `Contracts/AuditLog.php` + two implementations | Non-agent audit trail, attributed to a principal | 8 |
| `src/Security/WebAccessibilitySelfTest.php`, `SelfTestResult.php` | Proves `data/` is not web-readable | 9 |
| `src/Installer/Installer.php`, `InstallState.php`; `src/Api/InstallController.php` | First-run wizard that creates the owner | 10 |
| `src/Api/Kernel.php`, `Routes.php`, `AuthController.php` | The one dispatcher; the route table; login, logout, me | 11 |
| `src/Installer/SelfRename.php`; `src/Api/AdminController.php` | Directory rename, before and after install | 12 |
| `src/Installer/Uninstaller.php`; `src/Api/UninstallController.php` | Self-destruct | 13 |
| `frontend/src/api.js`, `screens/Install.jsx`, `screens/Login.jsx`, `components/*` | Installer and login screens | 14 |

Deleted: `src/Storage/Contracts/AuthStore.php`, `src/Storage/Sqlite/SqliteAuthStore.php`, `src/Storage/Json/JsonAuthStore.php`, `src/Storage/Sqlite/SqliteSchema.php`.

---

### Task 1: Dev tooling runs again

**Files:**
- Modify: `docker/dev/Dockerfile` (already edited in the working tree; do not rewrite it)
- Modify: `Makefile` (`check-host` target)
- Modify: `composer.json` (`compat` script)

**Interfaces:**
- Consumes: nothing.
- Produces: a working `make test` and `make check-host` for every later task.

Background. `php:8.0-cli` is built on Debian 11, which is past end of life: its security package pool is gone from `deb.debian.org` and `apt-get install` fails with 404. The Dockerfile edit points that one source at `archive.debian.org` and adds retries. Separately, PHPStan's parallel worker runs out of memory at PHP's default limit and reports it as "Found 1 error".

- [ ] **Step 1: Give PHPStan memory in both places it is invoked**

In `Makefile`, `check-host` runs `vendor/bin/phpstan analyse --no-progress --memory-limit=1G`. In `composer.json`, the `compat` script becomes `phpstan analyse --memory-limit=1G`.

- [ ] **Step 2: Build the dev image**

Run: `docker compose -f docker/compose.yml build dev`
Expected: exit 0. If Docker itself will not start, stop and tell the owner; do not delete or reset the Docker VM yourself.

- [ ] **Step 3: Run the suite on the PHP 8.0 floor**

Run: `make install && make test`
Expected: `OK` with 173 tests; the two `DeploymentShapeTest` cases are skipped outside the WordPress container.

- [ ] **Step 4: Run the host gate**

Run: `make check-host`
Expected: PHPStan `[OK] No errors`, then PHPUnit `OK`.

- [ ] **Step 5: Commit**

```bash
git add docker/dev/Dockerfile Makefile composer.json
git commit -m "build: repair the dev image on end-of-life Debian, give phpstan memory"
```

---

### Task 2: Migration runner for both backends

**Files:**
- Create: `src/Storage/Sqlite/SqliteMigrator.php`
- Create: `src/Storage/Json/JsonMigrator.php`
- Delete: `src/Storage/Sqlite/SqliteSchema.php`
- Modify: `src/Storage/Sqlite/SqliteConnection.php` (call the migrator where it called `SqliteSchema::install`)
- Modify: `src/Storage/JsonStorage.php` (run the migrator once per instance, before the first store is handed out)
- Test: `tests/Storage/SqliteMigratorTest.php`, `tests/Storage/JsonMigratorTest.php`

**Interfaces:**
- Consumes: `Version::SCHEMA`, `GuardedJson`, `StorageException`.
- Produces:
  - `SqliteMigrator::migrate(PDO $pdo, ?array $steps = null): void` and `SqliteMigrator::currentVersion(PDO $pdo): int`.
  - `JsonMigrator::migrate(string $dataDir, ?array $steps = null): void` and `JsonMigrator::currentVersion(string $dataDir): int`.
  - `SqliteMigrator::steps(): array` and `JsonMigrator::steps(): array` — the real lists, `array<int, callable>` keyed by the version each step produces, taking `PDO` or the data directory. Later tasks add steps `2`, `3`, `4` here.
  - `$steps` on `migrate()` is a test seam: `null` means `steps()`. Tests pass a slice of the real list to stop at an earlier version ("migrate to 2" is `array_slice(steps(), 0, 2, true)`), or a list with a failing step.

Rules both runners follow:

- The current version is `0` when nothing records one. SQLite reads `meta.schema_version` (and treats a missing `meta` table as `0`). JSON reads `schema_version` from `data/meta.php` through `GuardedJson`.
- The target is the highest key in the step list. Steps above the current version run in ascending order.
- A stored version above the target throws `StorageException` with the message `This data directory was written by a newer version of WP Safe Mode (schema %d; this copy understands up to %d). Upload the newer version again.`
- SQLite: each step and its version write run inside one `BEGIN IMMEDIATE … COMMIT`. The version is re-read after `BEGIN IMMEDIATE`, so a request that waited on another's migration skips the step. Any throwable rolls back and is rethrown as `StorageException`.
- JSON: the whole run holds an exclusive `flock` on `data/migrate-lock.php`, and re-reads the version after taking it. Every step must be safe to run twice; the version is written only after the step returns. `migrate()` creates the directory `0700` if it is missing, as `SqliteConnection::pdo()` does.
- Real step `1`, SQLite: the six `CREATE TABLE IF NOT EXISTS` statements now in `SqliteSchema::install()`, unchanged. Real step `1`, JSON: nothing to do (files are created on first write).
- `Version::SCHEMA` stays `1` in this task.

- [ ] **Step 1: Write the failing tests**

Both test classes carry these tests, each against its own backend in a fresh temp directory:

```php
public function testAFreshDataDirectoryIsBroughtToTheCurrentVersion(): void
// migrate(); assertSame(Version::SCHEMA, currentVersion())

public function testMigratingTwiceChangesNothing(): void
// migrate(); write one config value through the real ConfigStore; migrate() again;
// assert the value is still readable and currentVersion() === Version::SCHEMA

public function testTheRealStepListEndsAtVersionSchema(): void
// a fresh migrate() must land exactly on Version::SCHEMA, so a step added
// without bumping the constant (or the reverse) fails here

public function testAFutureVersionIsRefused(): void
// record schema_version = Version::SCHEMA + 1 by hand;
// expectException(StorageException::class); expectExceptionMessage('newer version')

public function testAFailingStepLeavesTheVersionUntouched(): void
// steps = [1 => real step 1, 2 => function () { throw new \RuntimeException('boom'); }]
// expect StorageException; afterwards currentVersion() === 1
```

`SqliteMigratorTest` adds:

```php
public function testAFailedStepRollsBackItsOwnWrites(): void
// step 2 creates table `half_done` and then throws;
// afterwards `half_done` is absent from sqlite_master
```

`JsonMigratorTest` adds:

```php
public function testAStepThatRanButWasNotRecordedRunsAgainSafely(): void
// run a step-2 callable by hand (it writes data/marker.php via GuardedJson with ['n' => 1])
// without recording version 2; then migrate() with the same step list;
// no exception, GuardedJson::read(marker) === ['n' => 1], currentVersion() === 2
```

- [ ] **Step 2: Run to verify failure**

Run: `vendor/bin/phpunit --filter 'MigratorTest'`
Expected: FAIL, class `SqliteMigrator` not found.

- [ ] **Step 3: Write both migrators, switch the two call sites, delete `SqliteSchema`**

- [ ] **Step 4: Run the whole suite**

Run: `vendor/bin/phpunit`
Expected: PASS. Every existing storage test still passes, which is the proof that step 1 reproduces the old schema.

- [ ] **Step 5: Commit**

```bash
git add src/Storage tests/Storage
git commit -m "feat(storage): add a migration runner for both backends"
```

---

### Task 3: Users replace the single credential

**Files:**
- Create: `src/Security/Role.php`, `src/Storage/User.php`, `src/Storage/Contracts/UserStore.php`, `src/Storage/Sqlite/SqliteUserStore.php`, `src/Storage/Json/JsonUserStore.php`
- Delete: `src/Storage/Contracts/AuthStore.php`, `src/Storage/Sqlite/SqliteAuthStore.php`, `src/Storage/Json/JsonAuthStore.php`
- Modify: `src/Core/Version.php` (`SCHEMA = 2`), both migrators (step 2), `src/Storage/Contracts/Storage.php`, `src/Storage/SqliteStorage.php`, `src/Storage/JsonStorage.php`
- Modify: `tests/Storage/StorageConformanceTest.php`, `tests/Storage/SqliteStorageTest.php`, `tests/Storage/JsonStorageTest.php`, both migrator tests

**Interfaces:**
- Consumes: `Credential` and `PasswordHasher` (both unchanged), the migrators from Task 2.
- Produces:

```php
namespace WPSafeMode\Security;
final class Role
{
    public const VIEWER = 'viewer';  public const OPERATOR = 'operator';
    public const ADMIN  = 'admin';   public const OWNER    = 'owner';
    public const ALL = [self::VIEWER, self::OPERATOR, self::ADMIN, self::OWNER]; // ascending
    public static function isValid(string $role): bool;
    /** True when $role is $required or higher in ALL. Unknown roles are never enough. */
    public static function atLeast(string $role, string $required): bool;
}

namespace WPSafeMode\Storage;
final class User
{
    public const LOGIN_PATTERN = '/^[a-z0-9][a-z0-9._@-]{2,63}$/';
    public function __construct(
        public int $id, public string $login, public string $role,
        public Credential $credential, public ?int $disabledAt = null,
    ) {}
    public function isEnabled(): bool;
    public static function normalizeLogin(string $login): string;   // strtolower(trim($login))
    public static function isValidLogin(string $normalized): bool;  // matches LOGIN_PATTERN
}

namespace WPSafeMode\Storage\Contracts;
interface UserStore
{
    public function isEmpty(): bool;
    /** @throws StorageException invalid login, unknown role, or login already taken */
    public function create(string $login, string $role, Credential $credential): User;
    public function find(int $id): ?User;
    public function findByLogin(string $login): ?User;  // normalises first
    /** @return User[] ordered by id */
    public function all(): array;
    /** Persists login, role, credential and disabledAt; never created_at.
     *  @throws StorageException unknown id, invalid login or role, login taken by another user */
    public function update(User $user): void;
}
```

  `Storage` gains `users(): UserStore` and `rateLimiter(): RateLimiter` (the Task 4 store, wired in here). JSON paths: `data/users.php`, `data/rate_limit.php`.

Storage shape:

- SQLite step 2: `DROP TABLE IF EXISTS auth`, then

```sql
CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    login TEXT NOT NULL UNIQUE,
    role TEXT NOT NULL,
    password_hash TEXT NOT NULL,
    hash_algo TEXT NOT NULL,
    created_at INTEGER NOT NULL,
    password_changed_at INTEGER NOT NULL,
    disabled_at INTEGER
)
```

- JSON step 2: delete `data/auth.php` if it exists. `data/users.php` holds `{"next_id": int, "users": {"<id>": row}}` with the same row keys as the SQLite columns.
- No data is carried from `auth`: nothing has been released and no installer existed to create a credential (amendment §3).
- Ids start at 1 and are never reused, in both backends.

- [ ] **Step 1: Replace the three AuthStore conformance tests**

In `StorageConformanceTest`, replace `makeAuthStore()` with `abstract protected function makeUserStore(string $dataDir): UserStore;`, add `abstract protected function writeMalformedUserRecord(string $dataDir): void;` (same idea as the existing `writeMalformedRateLimitEntry`), and replace the three `Auth…` tests with:

```php
public function testUserStoreStartsEmpty(): void
// isEmpty() true; all() === []; find(1) null; findByLogin('owner') null

public function testCreateAssignsIdOneAndRoundTripsEveryField(): void
// $u = create('owner', Role::OWNER, $credential);
// assertSame(1, $u->id); find(1) equals: login 'owner', role 'owner',
// credential hash/algo/createdAt/changedAt identical, disabledAt null; isEmpty() false

public function testLoginIsNormalisedAndUnique(): void
// create('  Marko ', ...)->login === 'marko';
// create('MARKO', ...) throws StorageException

public function testFindByLoginIgnoresCaseAndPadding(): void

/** @dataProvider invalidLogins  — '', 'ab', 'has space', '-lead', str_repeat('a', 65), 'ünï' */
public function testAnInvalidLoginIsRejected(string $login): void

public function testAnUnknownRoleIsRejected(): void          // create('x1y', 'root', ...) throws

public function testAllReturnsUsersOrderedById(): void       // three creates → ids [1, 2, 3]

public function testUpdatePersistsRoleCredentialAndDisabledAt(): void

public function testUpdateKeepsTheOriginalCreationTime(): void
// update with a Credential whose createdAt differs; stored createdAt is the original

public function testUpdateRejectsAnUnknownUser(): void

public function testUpdateRejectsALoginTakenByAnotherUser(): void

public function testAMalformedUserRecordFailsClosedInBothBackends(): void
// create one user; writeMalformedUserRecord(); find(1), findByLogin(), all()
// and isEmpty() each throw StorageException
```

`JsonStorageTest` has two JSON-only tests on the old credential file, `testCorruptCredentialFileIsNotTreatedAsUnconfigured` and `testCorruptCredentialFileMissingAnyFieldStillThrows`. Port both to `data/users.php` with the same intent, as `testACorruptUsersFileIsNotTreatedAsEmpty` and `testAUserRecordMissingAnyFieldStillThrows`.

- [ ] **Step 2: Add the migration test to both migrator tests**

```php
public function testVersionOneCredentialIsDroppedAndUsersStartEmpty(): void
// build a version-1 directory by hand: SQLite — run real step 1, insert one `auth` row,
// one config row, one session row; JSON — GuardedJson::write auth.php and config.php,
// meta.php schema_version 1. Then migrate().
// assert: currentVersion() === 2; users()->isEmpty(); the config value and the session
// are still readable through the real stores; SQLite has no `auth` table, JSON no auth.php
```

- [ ] **Step 3: Run to verify failure**

Run: `vendor/bin/phpunit --filter 'StorageTest|MigratorTest'`
Expected: FAIL, `UserStore` not found.

- [ ] **Step 4: Write `Role`, `User`, `UserStore`, both implementations and migration step 2; wire `users()` and `rateLimiter()` into `Storage`; delete the three AuthStore files; set `Version::SCHEMA = 2`**

- [ ] **Step 5: Run the whole suite, then commit**

Run: `vendor/bin/phpunit` — Expected: PASS.

```bash
git add -A src/Security/Role.php src/Storage src/Core/Version.php tests/Storage
git commit -m "feat(storage): replace the single credential with a users store"
```

---

### Task 4: Purpose-scoped key-vault subkeys

**Files:**
- Modify: `src/Security/KeyVault.php`
- Test: `tests/Security/KeyVaultTest.php`

**Interfaces:**
- Consumes: the existing private `masterSecret()`.
- Produces: `KeyVault::PURPOSE_CONFIG = 'config'`, `PURPOSE_BRIDGE = 'bridge'`, `PURPOSE_TOTP = 'totp'`, `PURPOSE_REMOTE = 'remote'`; `public function subkey(string $purpose): string` returning 32 raw bytes, `hash_hkdf('sha256', masterSecret, 32, 'wpsm:' . $purpose . ':v1')`. An unknown purpose throws `InvalidArgumentException`. `encrypt()` and `decrypt()` use `subkey(self::PURPOSE_CONFIG)`.

The existing context string is `wpsm:config:v1`, which is exactly what the `config` purpose derives, so every envelope already written still decrypts. Nothing in M1 uses the other three purposes; they exist so later phases never reach for the master secret.

- [ ] **Step 1: Write the failing tests**

```php
public function testSubkeysAreThirtyTwoBytesAndDifferByPurpose(): void
// four purposes → four distinct 32-byte strings

public function testASubkeyIsStableAcrossInstances(): void
// two KeyVault instances over the same data dir give the same subkey('bridge')

public function testAnUnknownPurposeIsRefused(): void
// expectException(\InvalidArgumentException::class) for subkey('anything-else')

public function testAnEnvelopeWrittenBeforeSubkeysStillDecrypts(): void
// encrypt 'sk-ant-secret'; call subkey() for the other three purposes;
// a second instance decrypts the envelope to 'sk-ant-secret'
```

- [ ] **Step 2: Run to verify failure** — `vendor/bin/phpunit --filter KeyVaultTest` — Expected: FAIL, `subkey` undefined.

- [ ] **Step 3: Implement `subkey()` and route `key()` through it**

- [ ] **Step 4: Run and commit** — `vendor/bin/phpunit --filter KeyVaultTest` — Expected: PASS, including all twenty existing tests.

```bash
git add src/Security/KeyVault.php tests/Security/KeyVaultTest.php
git commit -m "feat(security): derive purpose-scoped subkeys from the key vault"
```

---

### Task 5: Request facts, identity value objects, and a router that knows permissions

**Files:**
- Create: `src/Security/Permission.php`, `src/Security/Channel.php`, `src/Security/Principal.php`
- Create: `src/Core/Route.php`, `src/Core/RouteMatch.php`
- Modify: `src/Core/Request.php`, `src/Core/Router.php`, `src/index.php` (compile against the new router; full wiring is Task 11)
- Test: `tests/Core/RequestTest.php` (new), `tests/Core/RouterTest.php`, `tests/Security/PrincipalTest.php` (new)

**Interfaces:**
- Consumes: `Role`, `User` from Task 3.
- Produces:

```php
namespace WPSafeMode\Security;
final class Permission
{
    public const OPEN = 'open';              // anyone, including anonymous
    public const READ = 'read';              // viewer and above
    public const OPERATE = 'operate';        // operator and above
    public const ADMINISTER = 'administer';  // admin and above
    public const OWN = 'own';                // owner only
    public const ALL = [self::OPEN, self::READ, self::OPERATE, self::ADMINISTER, self::OWN];
    /** $role is null for an anonymous caller. */
    public static function allows(string $permission, ?string $role): bool;
}
final class Channel
{
    public const BROWSER = 'browser';
    public const REMOTE  = 'remote';
    public const ALL = [self::BROWSER, self::REMOTE];
}
final class Principal
{
    public static function anonymous(string $channel): self;
    public static function forUser(User $user, string $channel): self;
    public string $channel;  public ?User $user;      // set by the two factories only
    public function isAnonymous(): bool;
    public function role(): ?string;
    public function userId(): ?int;
    public function label(): string;                  // 'anonymous' or 'user:{id}'
    public function can(string $permission): bool;    // Permission::allows($permission, role())
}

namespace WPSafeMode\Core;
final class Route
{
    public function __construct(
        public string $method, public string $pattern, public $handler,   // callable(Request): Response
        public string $permission, public array $channels, public bool $preInstall,
    ) {}
}
final class RouteMatch
{
    public const FOUND = 'found';
    public const NOT_FOUND = 'not_found';
    public const METHOD_NOT_ALLOWED = 'method_not_allowed';
    public string $status;  public ?Route $route;  /** @var array<string,string> */ public array $params;
}
```

  `Router::add(string $method, string $pattern, callable $handler, string $permission, array $channels = [Channel::BROWSER], bool $preInstall = false): void`
  `Router::match(string $method, string $path): RouteMatch`
  `Router::routes(): array` (every `Route`, in registration order)
  `Router::dispatch()` is removed. Only the Kernel (Task 11) runs handlers.

  `Request::__construct` gains a fifth promoted public parameter `array $server = []`, filled from `$_SERVER` by `fromGlobals()`, and two public properties the Kernel sets: `?Principal $principal = null`, `array $params = []`. Code that the old plan handed a `$server` array (for example `WebAccessibilitySelfTest::baseUrlFrom()`) is handed `$request->server`. New methods:
  - `header(string $name): ?string` — case-insensitive; `X-WPSM-CSRF` reads `HTTP_X_WPSM_CSRF`; `Content-Type` and `Content-Length` read their un-prefixed keys.
  - `cookie(string $name): ?string` — parsed from the `Cookie` header, never `$_COOKIE`.
  - `clientIp(): string` — `REMOTE_ADDR` only, `'unknown'` when empty. `X-Forwarded-For` is never read: this value keys the login lockout, and a client-set header would let an attacker spread attempts across unlimited buckets.
  - `isHttps(): bool` — `HTTPS` set and not `off`, or `X-Forwarded-Proto` equal to `https`.
  - `userAgent(): string`
  - `basePath(): string` — `rtrim(dirname($this->scriptName), '/')`, the cookie path.

Router rules:

- A `{name}` segment matches exactly one non-empty path segment and is URL-decoded into `params[name]`. A route with no placeholders wins over one with placeholders for the same path.
- `add()` throws `InvalidArgumentException` when the permission is not in `Permission::ALL`; when `$channels` is empty or names an unknown channel; when the permission is `OWN` and the channels include `REMOTE`; or when `$preInstall` is true and the permission is not `OPEN` (before install there are no users to hold a role).
- `match()` returns `METHOD_NOT_ALLOWED` when the path matches a registered pattern under another method, otherwise `NOT_FOUND`.

`Permission::allows`: `OPEN` is true for everyone; any other permission is false for a `null` role; `READ`, `OPERATE`, `ADMINISTER` use `Role::atLeast` against `VIEWER`, `OPERATOR`, `ADMIN`; `OWN` is true for `OWNER` only.

- [ ] **Step 1: Write the failing tests**

`tests/Core/RequestTest.php`:

```php
public function testHeadersAreReadCaseInsensitively(): void
// server ['HTTP_X_WPSM_CSRF' => 'abc', 'CONTENT_TYPE' => 'application/json']
// header('x-wpsm-csrf') === 'abc'; header('Content-Type') === 'application/json'; header('X-Missing') === null

public function testCookiesAreParsedFromTheHeader(): void
// HTTP_COOKIE 'a=1; wpsm_session=tok-en; b=2' → cookie('wpsm_session') === 'tok-en'; cookie('none') === null

public function testClientIpIsTheDirectPeerAndIgnoresForwardedHeaders(): void
// REMOTE_ADDR '203.0.113.9', HTTP_X_FORWARDED_FOR '10.0.0.1' → '203.0.113.9'

public function testClientIpFallsBackToAPlaceholder(): void          // no REMOTE_ADDR → 'unknown'

public function testHttpsIsDetectedFromBothTheServerVarAndTheProxyHeader(): void
// HTTPS 'on' → true; HTTPS 'off' → false; HTTP_X_FORWARDED_PROTO 'https' → true; neither → false

public function testBasePathIsTheInstallDirectory(): void
// scriptName '/wpsm-ab12/index.php' → '/wpsm-ab12'; '/index.php' → ''
```

`tests/Core/RouterTest.php` — the eight existing tests are about path handling; rewrite the three that call `dispatch()` to call `match($request->method, $request->path())` and assert on `RouteMatch`. Add:

```php
public function testPlaceholdersAreExtractedAndDecoded(): void
// '/api/sessions/{id}/steps/{seq}' vs '/api/sessions/s%201/steps/4' → ['id' => 's 1', 'seq' => '4']

public function testAStaticRouteWinsOverAPlaceholderRoute(): void
// '/api/sessions/{id}' and '/api/sessions/new' both registered; '/api/sessions/new' matches the static one

public function testAPlaceholderNeverSpansSegmentsOrMatchesEmpty(): void
// '/api/sessions/{id}' does not match '/api/sessions/a/b' nor '/api/sessions/'

public function testAKnownPathUnderAnotherMethodIsMethodNotAllowed(): void

/** @dataProvider badRegistrations */
public function testABadRegistrationIsRefused(string $permission, array $channels, bool $preInstall): void
// ['nonsense', [BROWSER], false], [READ, [], false], [READ, ['carrier-pigeon'], false],
// [OWN, [BROWSER, REMOTE], false], [READ, [BROWSER], true]
// each: expectException(\InvalidArgumentException::class)

public function testRoutesAreListedInRegistrationOrder(): void
```

`tests/Security/PrincipalTest.php`:

```php
/** @dataProvider permissionMatrix */
public function testEachRoleHoldsExactlyItsPermissions(?string $role, array $allowed): void
// null      → [OPEN]
// viewer    → [OPEN, READ]
// operator  → [OPEN, READ, OPERATE]
// admin     → [OPEN, READ, OPERATE, ADMINISTER]
// owner     → all five
// every permission not listed must be refused

public function testLabelsIdentifyTheActor(): void
// anonymous(BROWSER)->label() === 'anonymous'; forUser(user id 7)->label() === 'user:7'
```

- [ ] **Step 2: Run to verify failure** — `vendor/bin/phpunit --filter 'RequestTest|RouterTest|PrincipalTest'` — Expected: FAIL.

- [ ] **Step 3: Write the value objects, extend `Request`, rewrite `Router`**

- [ ] **Step 4: Keep `src/index.php` compiling**

Register `/api/env` with `Permission::OPEN` and run its handler directly from the `RouteMatch` for now. Task 11 replaces this block with the Kernel and tightens `/api/env`.

- [ ] **Step 5: Run the whole suite, then commit**

```bash
git add src/Core src/Security src/index.php tests/Core tests/Security/PrincipalTest.php
git commit -m "feat(core): add principals, permissions and a router that declares them"
```

---

### Task 6: Login sessions bound to a user

As old Task 5 ("Login sessions"), with these changes.

**Files:**
- Create: `src/Security/AuthSessions.php`, `src/Security/LoginResult.php`, `src/Storage/Contracts/AuthSessionStore.php`, `src/Storage/Sqlite/SqliteAuthSessionStore.php`, `src/Storage/Json/JsonAuthSessionStore.php`
- Modify: `src/Core/Version.php` (`SCHEMA = 3`), both migrators (step 3), `Storage` and both implementations (`authSessions()`)
- Test: `tests/Security/AuthSessionsTest.php`, both migrator tests

**Interfaces:**
- Consumes: `UserStore`, `RateLimiter`, `PasswordHasher`.
- Produces:
  - `LoginResult` with `ok`, `reason`, `token`, `retryAfter`, and `?int $userId`; `LoginResult::success(string $token, int $userId)`; `failure(string $reason, int $retryAfter = 0)`. Reasons: `not_installed`, `bad_credentials`, `locked_out`.
  - `AuthSessions::login(string $login, string $password, string $ip, string $userAgent, int $now): LoginResult`
  - `AuthSessions::validate(string $token, string $userAgent, int $now): ?User` — the user when the session is good, otherwise `null`.
  - `AuthSessions::logout(string $token): void`, `cookieHeader(string $token, bool $https, string $path, int $now): string`, `clearCookieHeader(bool $https, string $path): string`, `static hashToken(string $token): string` — as in the old plan.
  - Constants as in the old plan: `COOKIE = 'wpsm_session'`, `LIFETIME_SECONDS = 43200`, `IDLE_SECONDS = 3600`. Cookie attributes: `HttpOnly`, `SameSite=Strict`, `Secure` when HTTPS, `Path` = the install directory.
  - `AuthSessionStore` as in the old plan, with `user_id: int` added to the row shape.

Changes from the old plan:

- Migration step 3. SQLite: the old plan's `auth_sessions` table with one more column, `user_id INTEGER NOT NULL`, and no `IF NOT EXISTS`. JSON: nothing to do (`data/auth_sessions.php` is created on first write).
- `login()` order: lockout check on bucket `'login:' . $ip` → `users()->isEmpty()` gives `not_installed` → look the user up by login → verify the password → on success clear the bucket, rehash if `needsRehash()` and `users()->update()` that one user, issue the token, store the row with `user_id`.
- **Unknown and disabled logins** take the wrong-password path exactly: `PasswordHasher::hash($password)` is called once and its result discarded, so the work done matches a real verification under whichever algorithm this host uses; the attempt is counted with `hit()`; and the reason is `bad_credentials` (or `locked_out` once the limit is reached).
- `validate()` drops the `$ip` parameter the old plan passed and never used (the address is not bound, for the reason the old plan gives: mobile networks rotate it). After the old plan's expiry, idle and user-agent checks it loads the user; a missing or disabled user deletes the session and returns `null`.

- [ ] **Step 1: Write the failing test**

Start from the old plan's thirteen tests in this task, with every `login($password, …)` call becoming `login('owner', $password, …)`, set-up creating the owner through `users()->create('owner', Role::OWNER, $hasher->hash($password))`, and `validate()` assertions comparing the returned user's id. Add:

```php
public function testUnknownLoginIsIndistinguishableFromAWrongPassword(): void
// $a = login('nobody', 'whatever-long-password', ip A); $b = login('owner', 'wrong-long-password', ip B)
// assertSame($b->reason, $a->reason); both 'bad_credentials'; both tokens null;
// rateLimiter()->peek('login:A', $now) and peek('login:B', $now) show the same attempt count (1)

public function testADisabledUserCannotLogInAndLooksLikeAWrongPassword(): void

public function testASessionOfADisabledUserStopsValidating(): void
// login ok → validate returns the user → set disabledAt, users()->update() → validate returns null
// → the session row is gone from authSessions()

public function testLoginNameIsCaseInsensitive(): void          // login('OWNER', correct password) succeeds

public function testRehashTouchesOnlyTheUserWhoLoggedIn(): void
// two users with credentials forced to need a rehash; log in as the first;
// the second user's stored hash is byte-for-byte unchanged
```

Add to both migrator tests:

```php
public function testStepThreeAddsLoginSessionsWithoutTouchingUsers(): void
// migrate to 2, create a user, migrate to 3; the user is still found; authSessions() accepts a put/get
```

- [ ] **Step 2: Run to verify failure** — `vendor/bin/phpunit --filter 'AuthSessionsTest|MigratorTest'` — Expected: FAIL.

- [ ] **Step 3: Write the store contract and both implementations, migration step 3, `LoginResult`, `AuthSessions`; wire `authSessions()`; set `Version::SCHEMA = 3`**

- [ ] **Step 4: Run the whole suite, then commit**

```bash
git add src/Security src/Storage src/Core/Version.php tests
git commit -m "feat(security): add login sessions bound to a user"
```

---

### Task 7: CSRF tokens

Build old Task 6 ("CSRF tokens") exactly as written: `src/Security/Csrf.php`, `tests/Security/CsrfTest.php`, `Csrf::issue()`, `Csrf::verify()`, `Csrf::HEADER = 'X-WPSM-CSRF'`. It consumes `AuthSessions::hashToken()`, which Task 6 provides unchanged. That CSRF applies to the browser channel only is enforced in the Kernel (Task 11), not here.

- [ ] **Step 1: Write the failing test** (the old task's Step 1)
- [ ] **Step 2: Run to verify failure** — `vendor/bin/phpunit --filter CsrfTest`
- [ ] **Step 3: Write `Csrf`** (the old task's Step 3)
- [ ] **Step 4: Run and commit**

```bash
git add src/Security/Csrf.php tests/Security/CsrfTest.php
git commit -m "feat(security): add csrf tokens bound to the login session"
```

---

### Task 8: Audit log attributed to a principal

As old Task 7 ("Audit log"), with these changes.

**Files:** as the old task, plus `src/Core/Version.php` (`SCHEMA = 4`) and both migrators (step 4).

**Interfaces:**
- Consumes: `Principal` (Task 5), `GuardedJsonLines`, `SqliteConnection`.
- Produces: `AuditEvent` (`at`, `actor`, `event`, `subject`, `meta`, `ip`) with the old plan's eight event constants. `AuditLog::record(AuditEvent $e): void`, `AuditLog::recent(int $limit = 100): array` newest first. `Storage::auditLog(): AuditLog`.
  - **Replaces `AuditEvent::now()`:** `AuditEvent::by(Principal $actor, string $event, ?string $ip = null, ?string $subject = null, array $meta = [], ?int $at = null): self`, whose `actor` is `$actor->label()` and whose `at` defaults to `time()`. There is no factory that invents an actor.

Changes from the old plan:

- Migration step 4. SQLite: the old plan's `audit_log` table, no `IF NOT EXISTS`. JSON: nothing to do.
- The literal actor `'operator'` no longer exists anywhere. Conformance tests build events with `AuditEvent::by(Principal::forUser($user, Channel::BROWSER), …)` or `Principal::anonymous(Channel::BROWSER)`.

- [ ] **Step 1: Add the conformance tests** (the old task's Step 3, with events built through `AuditEvent::by`), plus:

```php
public function testAnEventCarriesItsActorsLabel(): void
// by(forUser(user id 3), LOGIN)->actor === 'user:3'; by(anonymous, LOGIN_FAILED)->actor === 'anonymous'
```

and in both migrator tests:

```php
public function testStepFourAddsTheAuditLog(): void   // migrate to 4; auditLog()->record(...) then recent(1) returns it
```

- [ ] **Step 2: Run to verify failure** — `vendor/bin/phpunit --filter 'StorageTest|MigratorTest'`
- [ ] **Step 3: Write `AuditEvent`, the contract, both implementations, migration step 4; wire `auditLog()`; set `Version::SCHEMA = 4`**
- [ ] **Step 4: Run the whole suite, then commit**

```bash
git add src/Storage src/Core/Version.php tests/Storage
git commit -m "feat(storage): add an audit log attributed to a principal"
```

---

### Task 9: Web-accessibility self-test

Build old Task 8 ("Web-accessibility self-test") exactly as written: `src/Security/WebAccessibilitySelfTest.php`, `src/Security/SelfTestResult.php`, `tests/Security/WebAccessibilitySelfTestTest.php`. No interface in it changed. The recurring trigger for the Keep-installed posture is Phase 2 work and is not built here.

- [ ] **Step 1: Write the failing test** (the old task's Step 1)
- [ ] **Step 2: Run to verify failure** — `vendor/bin/phpunit --filter WebAccessibilitySelfTestTest`
- [ ] **Step 3: Write both classes** (the old task's Step 3)
- [ ] **Step 4: Run, including the positive case** (the old task's Step 4)
- [ ] **Step 5: Commit**

```bash
git add src/Security/WebAccessibilitySelfTest.php src/Security/SelfTestResult.php tests/Security/WebAccessibilitySelfTestTest.php
git commit -m "feat(security): add the data-directory web-accessibility self-test"
```

---

### Task 10: The installer creates the owner

As old Task 9 ("The installer"), with these changes.

**Files:**
- Create: `src/Installer/Installer.php`, `src/Installer/InstallState.php`, `src/Api/InstallController.php`
- Test: `tests/Installer/InstallerTest.php`, `tests/Api/InstallControllerTest.php` (new)

**Interfaces:**
- Consumes: `Storage` (`users()`, `config()`, `auditLog()`), `KeyVault`, `PasswordHasher`, `WebAccessibilitySelfTest`, `DataDir`, `EnvironmentReport`, `Principal`.
- Produces:
  - `Installer::__construct(Storage $storage, KeyVault $vault, PasswordHasher $hasher, string $dataDir, EnvironmentReport $env)`
  - Step constants `STEP_TOKEN = 'token'`, `STEP_OWNER = 'owner'`, `STEP_API_KEY = 'api_key'`, `STEP_DONE = 'done'`.
  - `state(): InstallState`, `issueToken(): string`, `verifyToken(string $presented): bool`, `runSelfTest(string $baseUrl): SelfTestResult`, `storeApiKey(string $key, bool $acknowledgedRisk, string $baseUrl): void` — as the old plan.
  - **Replaces `setPassword()`:** `createOwner(string $login, string $password): User`. Throws `StorageException` when the password has weaknesses (all of them, joined, as the old plan did), when the login is invalid, or when a user already exists (`'This copy already has an owner.'`).
  - `finalize(string $ip): void` — additionally writes config `posture` = `emergency`, and records the `install` audit event with the owner as actor.
  - `InstallState` fields: `installed`, `step`, `hasOwner`, `hasApiKey`, `selfTest`, `encryption`, `hasWordPress`. `toArray()` keys: `installed`, `step`, `has_owner`, `has_api_key`, `self_test`, `encryption`, `has_wordpress`. `toPublicArray()` returns only `['installed' => true, 'step' => 'done']` and is what an installed copy answers.
  - `InstallController` methods: `state`, `verifyToken`, `createOwner`, `selfTest`, `apiKey`, `finish`. Its constructor takes the `Installer` only; client address and base URL come from the `Request`.

Changes from the old plan:

- Wizard order: token → owner → self-test → API key → finish. `hasOwner` is `!users()->isEmpty()`.
- `hasWordPress` is `$env->wpRoot !== null`. Nothing in the installer may depend on it being true.
- **Every mutating installer route re-verifies the install token** from the request body (`token`): `createOwner`, `apiKey`, `finish`, and `rename` (Task 12). The old plan checked it only when setting the password. `state` and `selfTest` take no token. A missing or wrong token answers `403 {"error":"bad_token"}`.
- The controller's own `already_installed` checks are removed: the Kernel (Task 11) refuses every installer mutation once installed. `state` answers `toPublicArray()` when installed and `toArray()` otherwise.
- Every `AuditEvent::now(…)` and hand-built `new AuditEvent(…, 'operator', …)` in the old task becomes `AuditEvent::by(…)` with the owner as actor (the single user in `users()->all()`).
- Where the old controller read `$this->server`, it reads the `Request`: `$request->clientIp()` and `WebAccessibilitySelfTest::baseUrlFrom($request->server)`.
- Request body for `createOwner`: `{"token", "login", "password"}`. Error codes: `weak_password` (422, message lists every weakness), `bad_login` (422), `owner_exists` (409).

- [ ] **Step 1: Write the failing tests**

`InstallerTest`: the old plan's twelve tests, with `setPassword($p)` becoming `createOwner('owner', $p)` and `hasPassword` becoming `hasOwner`. Add:

```php
public function testTheOwnerIsCreatedWithTheOwnerRole(): void
// createOwner('Marko', strong)->role === Role::OWNER; login stored as 'marko'; users()->all() has one user

public function testASecondOwnerCannotBeCreated(): void
// createOwner twice → second throws StorageException; users()->all() still has exactly one

public function testAnInvalidLoginIsRefused(): void                  // createOwner('a b', strong) throws

public function testInstallCompletesWithNoWordPressBesideIt(): void
// Installer built with Environment::probe($emptyTempDir); state()->hasWordPress === false;
// token → owner → api key (acknowledged) → finalize; state()->step === 'done'

public function testFinalizeRecordsTheEmergencyPosture(): void       // config()->get('posture') === 'emergency'

public function testTheInstallEventIsAttributedToTheOwner(): void    // auditLog()->recent(1)[0]->actor === 'user:1'
```

`InstallControllerTest` (controller called directly with built `Request` objects):

```php
/** @dataProvider tokenGuardedActions — createOwner, apiKey, finish */
public function testInstallerMutationsRequireTheToken(string $action): void
// body without 'token', and with a wrong one → 403 bad_token; nothing is stored

public function testStateIsFullBeforeInstallAndMinimalAfter(): void
// before: body has has_owner, has_api_key, encryption, has_wordpress
// after finalize: body is exactly ['installed' => true, 'step' => 'done']

public function testAWeakPasswordListsEveryWeakness(): void          // 422 weak_password
```

- [ ] **Step 2: Run to verify failure** — `vendor/bin/phpunit --filter 'InstallerTest|InstallControllerTest'`
- [ ] **Step 3: Write `InstallState`, `Installer` and `InstallController`**
- [ ] **Step 4: Run to verify it passes**
- [ ] **Step 5: Commit**

```bash
git add src/Installer src/Api/InstallController.php tests/Installer tests/Api/InstallControllerTest.php
git commit -m "feat(installer): add the first-run wizard that creates the owner"
```

---

### Task 11: The Kernel, the route table, and the auth endpoints

Replaces old Task 10 ("Middleware, auth endpoints, and the install gate"). There is no `Middleware` class.

**Files:**
- Create: `src/Api/Kernel.php`, `src/Api/Routes.php`, `src/Api/AuthController.php`
- Modify: `src/index.php`, `src/Api/EnvController.php` (no logic change; it is now behind `READ`)
- Test: `tests/Api/KernelTest.php`, `tests/Api/RoutesTest.php`, `tests/Api/AuthControllerTest.php`

**Interfaces:**
- Consumes: everything from Tasks 2–10.
- Produces:
  - `Kernel::__construct(Router $router, Storage $storage, AuthSessions $sessions, Installer $installer)`
  - `Kernel::handle(Request $request, int $now): Response`
  - `Routes::register(Router $router, Storage $storage, AuthSessions $sessions, Installer $installer, string $appDir, string $dataDir): void` — the only place routes are added. Tasks 12 and 13 add their lines here.
  - `AuthController::login`, `logout`, `me`.

`Kernel::handle` runs these checks in this order and returns at the first that fails. Every error body is `{"error": "<code>"}`.

| # | Check | Failure |
|---|---|---|
| 1 | `Router::match` | 404 or 405, using the existing `Response::notFound()` / `methodNotAllowed()` |
| 2 | Not installed and the route is not `preInstall` | 409 `not_installed`, plus `"step"` |
| 3 | Installed, the route is `preInstall`, and the method is not `GET` | 409 `already_installed` |
| 4 | Resolve the principal: `cookie(AuthSessions::COOKIE)` → `AuthSessions::validate()` → `Principal::forUser($user, Channel::BROWSER)`; otherwise `Principal::anonymous(Channel::BROWSER)` | — |
| 5 | The principal's channel is in the route's channels | 403 `channel_not_allowed` |
| 6 | The route's permission is `OPEN`, or the principal holds it | anonymous: 401 `unauthenticated`; otherwise 403 `forbidden` |
| 7 | The principal is a user on the browser channel and the method is not `GET`, `HEAD` or `OPTIONS`: `Csrf::verify` on `header(Csrf::HEADER)` | 403 `bad_csrf` |
| 8 | Set `$request->principal` and `$request->params`, call the handler | — |

The CSRF secret is config `install_id`, as in the old plan.

Route table. `RoutesTest` pins it; a route added without a row there fails the build.

| Method | Path | Handler | Permission | preInstall |
|---|---|---|---|---|
| GET | `/api/install/state` | `InstallController::state` | OPEN | yes |
| POST | `/api/install/token` | `InstallController::verifyToken` | OPEN | yes |
| POST | `/api/install/owner` | `InstallController::createOwner` | OPEN | yes |
| POST | `/api/install/self-test` | `InstallController::selfTest` | OPEN | yes |
| POST | `/api/install/api-key` | `InstallController::apiKey` | OPEN | yes |
| POST | `/api/install/finish` | `InstallController::finish` | OPEN | yes |
| POST | `/api/auth/login` | `AuthController::login` | OPEN | no |
| POST | `/api/auth/logout` | `AuthController::logout` | READ | no |
| GET | `/api/auth/me` | `AuthController::me` | READ | no |
| GET | `/api/env` | `EnvController` | READ | no |

All ten accept the browser channel only. `/api/env` moves from open to `READ`: an installed copy no longer tells anonymous visitors its version, PHP build and storage backend.

`AuthController`:

- `login` — body `{"login", "password"}`. On failure: audit `login_failed` by the anonymous principal with meta `{"reason"}` only (**the attempted login name is never recorded**: mistyped passwords end up in that field), then 401 `bad_credentials` or 429 `locked_out`, both with `retry_after`. On success: audit `login` by the user, run the self-test as the old plan did, set the session cookie, and answer `{"ok": true, "csrf", "user": {"id", "login", "role"}, "self_test"}`.
- `me` — `{"user": {"id", "login", "role"}, "csrf"}`. This is how a reloaded page recovers its CSRF token and how the UI learns whether it is logged in.
- `logout` — revokes the session, audits `logout`, clears the cookie, answers `{"ok": true}`.

`src/index.php` keeps its static-asset block unchanged and ends with: open storage, build `Installer` (with `Environment::probe()`), `AuthSessions`, `Router`, call `Routes::register`, then `(new Kernel(...))->handle($request, time())->send()`.

- [ ] **Step 1: Write the failing tests**

`KernelTest` builds a `Router` with stub routes over real storage in a temp directory:

```php
public function testUnknownPathIs404AndWrongMethodIs405(): void
public function testAnUninstalledCopyRefusesEveryNonInstallerRoute(): void    // 409 not_installed with 'step'
public function testInstallerRoutesAnswerBeforeInstall(): void
public function testInstallerMutationsAreRefusedOnceInstalled(): void         // POST preInstall route → 409 already_installed
public function testAnInstallerReadStillAnswersOnceInstalled(): void          // GET preInstall route → 200
public function testAnAnonymousRequestToAProtectedRouteIs401(): void

/** @dataProvider roleByPermission — every role × every permission, expected 200 or 403 */
public function testEachRoleReachesExactlyTheRoutesItsPermissionsAllow(string $role, string $permission, int $expected): void

public function testAMutatingRequestWithoutCsrfIs403(): void                  // bad_csrf
public function testAValidCsrfHeaderPasses(): void
public function testReadsNeedNoCsrf(): void
public function testAnAnonymousPostToAnOpenRouteNeedsNoCsrf(): void           // the login case
public function testALoggedInPostToAnOpenRouteStillNeedsCsrf(): void
public function testADisabledUsersCookieIsTreatedAsAnonymous(): void          // 401, not 200
public function testARouteThatDoesNotAcceptTheBrowserChannelIs403(): void     // channel_not_allowed
public function testTheHandlerReceivesThePrincipalAndTheParams(): void
// '/api/things/{id}' → handler sees $request->params === ['id' => '42'] and principal->userId()
```

`RoutesTest`:

```php
public function testEveryRouteMatchesTheDeclaredTable(): void
// Routes::register on a fresh Router; map Router::routes() to
// [method, pattern, permission, channels, preInstall] and assertSame against the table above,
// written out literally in the test
```

`AuthControllerTest`:

```php
public function testLoginSetsTheCookieAndReturnsCsrfAndUser(): void
public function testAFailedLoginIsAuditedWithoutTheAttemptedName(): void
// login 'my-real-password-typed-in-the-wrong-box' → recent(1)[0]: event login_failed,
// actor 'anonymous', and json_encode of the whole event does not contain that string
public function testLockoutAnswers429WithRetryAfter(): void
public function testMeReturnsTheUserAndAWorkingCsrfToken(): void
public function testLogoutRevokesTheSessionAndClearsTheCookie(): void
```

- [ ] **Step 2: Run to verify failure** — `vendor/bin/phpunit --filter 'KernelTest|RoutesTest|AuthControllerTest'`
- [ ] **Step 3: Write `Kernel`, `Routes`, `AuthController`; rewire `src/index.php`**
- [ ] **Step 4: Run the whole suite**
- [ ] **Step 5: Verify the gates end to end against a scratch tree with no WordPress**

```bash
T=$(mktemp -d) && cp -R src "$T/wpsafemode"
php -S 127.0.0.1:8126 -t "$T" >/dev/null 2>&1 &
sleep 1
B=http://127.0.0.1:8126/wpsafemode/index.php
curl -s "$B/api/install/state"
curl -s -o /dev/null -w 'env before install: %{http_code}\n' "$B/api/env"
curl -s -o /dev/null -w 'login before install: %{http_code}\n' -X POST -d '{"login":"x","password":"y"}' "$B/api/auth/login"
kill %1
```

Expected: the state body has `"installed":false` and `"has_wordpress":false`; both other requests answer **409**.

- [ ] **Step 6: Commit**

```bash
git add src/Api src/index.php tests/Api
git commit -m "feat(api): route every request through one kernel with declared permissions"
```

---

### Task 12: Self-rename, before and after install

As old Task 11 ("One-click self-rename") for `src/Installer/SelfRename.php` and `tests/Installer/SelfRenameTest.php`, unchanged. The endpoint is re-cut.

**Files:**
- Create: `src/Installer/SelfRename.php`, `src/Api/AdminController.php`
- Modify: `src/Api/InstallController.php` (`rename`), `src/Api/Routes.php`, `tests/Api/RoutesTest.php`
- Test: `tests/Installer/SelfRenameTest.php`, `tests/Api/RenameEndpointsTest.php`

**Interfaces:**
- Consumes: `SelfRename`, `AuditLog`, `Principal`.
- Produces: `SelfRename::suggest()`, `rename()`, `isSafeName()` as the old plan. Two routes:

| Method | Path | Handler | Permission | preInstall |
|---|---|---|---|---|
| POST | `/api/install/rename` | `InstallController::rename` | OPEN | yes |
| POST | `/api/admin/rename` | `AdminController::rename` | OWN | no |

Why two. The old plan's single endpoint skipped the token check once the tool was installed and sat on an open path, so after installation anyone could move the tool to a name of their choosing. Now the installer route requires the install token and is refused by the Kernel once installed; afterwards only a logged-in owner can rename, through the second route.

`InstallController::__construct` gains a second parameter, `string $appDir`, the directory to rename; `AdminController::__construct(Storage $storage, string $appDir)`. `Routes::register` already receives `$appDir` and passes it to both.

Both answer `{"ok": true, "redirect": "../<name>/"}` and record a `rename` audit event by the request's principal. After a post-install rename the session cookie's path no longer matches, so the owner signs in again at the new address; the response includes `"sign_in_again": true` so the UI can say so.

- [ ] **Step 1: Write the failing tests** — the old task's `SelfRenameTest`, plus:

```php
public function testTheInstallerRenameRequiresTheToken(): void               // no token → 403 bad_token, directory unmoved
public function testTheInstallerRenameIsRefusedOnceInstalled(): void         // through the Kernel → 409 already_installed
public function testOnlyAnOwnerCanRenameAfterInstall(): void                 // admin → 403 forbidden; anonymous → 401
public function testAnOwnerRenameIsAuditedAndAsksForANewSignIn(): void       // actor 'user:1'; body sign_in_again true
```

and add the two rows to the table in `RoutesTest`.

- [ ] **Step 2: Run to verify failure, then write `SelfRename`** (the old task's Step 2)
- [ ] **Step 3: Add both endpoints and register them**
- [ ] **Step 4: Run and commit**

```bash
git add src/Installer/SelfRename.php src/Api tests/Installer/SelfRenameTest.php tests/Api
git commit -m "feat(installer): add self-rename, token-gated before install and owner-only after"
```

---

### Task 13: Uninstall self-destruct

As old Task 12 ("Uninstall self-destruct"), with these changes.

**Files:** as the old task, plus `src/Api/Routes.php` and `tests/Api/RoutesTest.php`.

**Interfaces:**
- Consumes: `AuditLog`, `Principal`.
- Produces: `Uninstaller::run(string $appDir, string $dataDir): string` as the old plan. Route:

| Method | Path | Handler | Permission | preInstall |
|---|---|---|---|---|
| POST | `/api/uninstall` | `UninstallController` | OWN | no |

Changes: `UninstallController::__construct(Storage $storage, string $appDir, string $dataDir)` (no `$server`); the audit event is `AuditEvent::by($request->principal, AuditEvent::UNINSTALL, $request->clientIp())`; the typed confirmation (`"confirm": "DELETE"`) stays.

- [ ] **Step 1: Write the failing tests** — the old task's `UninstallerTest`, plus:

```php
public function testOnlyAnOwnerCanUninstall(): void                // through the Kernel: admin 403, anonymous 401
public function testUninstallNeedsTheTypedConfirmation(): void     // owner without confirm → 422, nothing deleted
```

and add the row to `RoutesTest`.

- [ ] **Step 2: Run to verify failure, then write the `Uninstaller`** (the old task's Step 2)
- [ ] **Step 3: Add the endpoint and register it**
- [ ] **Step 4: Run and commit**

```bash
git add src/Installer/Uninstaller.php src/Api tests/Installer/UninstallerTest.php tests/Api
git commit -m "feat(installer): add owner-only uninstall self-destruct"
```

---

### Task 14: Installer and login screens

As old Task 13 ("Installer and login UI"), with these changes.

**Files:** as the old task.

**Interfaces:**
- Consumes: `/api/install/*`, `/api/auth/login`, `/api/auth/me`, `/api/auth/logout`, `/api/env`.
- Produces: `frontend/src/api.js` exporting `get(path)`, `post(path, body)`, `setCsrf(token)`; `Install.jsx`; `Login.jsx`; the four shared components.

Changes from the old plan:

- **Installer, owner step** (was the password step): two fields, a login name and a password. Copy for the name: *"Pick a name to sign in with. You are the owner of this copy; you can add other people later."* It still shows every password weakness at once. Posts `{token, login, password}` to `install/owner`; `bad_login` and `owner_exists` each get their own plain sentence.
- **Every installer post sends the token.** The screen keeps the verified token in memory for the rest of the wizard.
- **No WordPress found.** When `has_wordpress` is false the first screen shows an info `Notice`: *"No WordPress was found next to this tool. You can still set it up; it will look again when you start."* It is not an error and blocks nothing.
- **Login:** two fields, name and password. On success call `setCsrf(body.csrf)`. The lockout and exposed-directory notices are as the old plan.
- **`app.jsx` routing:** fetch `install/state`. Not installed → `<Install/>`. Otherwise `get('auth/me')`: a 401 → `<Login/>`; a 200 → `setCsrf(body.csrf)` and the M0 environment panel, which now loads `api/env` only after sign-in.
- **After an owner rename** answering `sign_in_again`, follow `redirect` and show *"The tool has moved. Sign in again at its new address."*

- [ ] **Step 1: Write the API helper** (the old task's Step 1, unchanged)
- [ ] **Step 2: Write the shared components** (the old task's Step 2)
- [ ] **Step 3: Write the installer screen** with the changes above
- [ ] **Step 4: Write the login screen** with the changes above
- [ ] **Step 5: Route in `app.jsx`** as above
- [ ] **Step 6: Build, verify, and commit the output**

```bash
cd frontend && npm install && npm run build && cd ..
grep -o 'src="[^"]*"' src/public/index.html
```

Expected: a relative `./assets/index-<hash>.js`. Then start the scratch-tree server from Task 11 Step 5, open `http://127.0.0.1:8126/wpsafemode/` in a browser and walk the whole flow: a wrong token is refused; the owner is created; the self-test result shows before the key field; finishing lands on the login screen; signing in shows the environment panel; reloading the page stays signed in.

```bash
git add frontend/ src/public/
git commit -m "feat(frontend): add installer and login screens for named users"
```

---

## Not in this plan

Deliberately left to later milestones of Phase 1, so nobody builds them early:

- `Tool` risk classes, `ApprovalPolicy`, the redaction pass, the site mutation lock and the three verification outcomes (M3–M5).
- The `sessions` columns `mode`, `playbook_id`, `created_by`, `origin`, `policy_json` (M5, as one migration step).
- The remote protocol document (before M5's API is designed). No signature code ships in 2.0.0.
- The pack format and built-in rescue playbooks (M5).
- Team screens, second factor, the dormant lock, and the rule that the last enabled owner cannot be removed (Phase 2 and Phase 7).
- The Claude API refresh in amendment §13.4 (M5).
- The uninstall screen (spec §5.8). M1 ships the `/api/uninstall` endpoint but no screen for it, so the only way to remove the tool from the browser is missing. It is the first task of M2, and the 2.0.0 release is blocked until it exists (R42).

## Self-review

Run against the amendment and the old plan after writing.

**Spec coverage.** Amendment §18's M1 re-cut table: "new, first" → Tasks 2, 3, 4; Task 5 login sessions → Task 6; CSRF → Tasks 7 and 11 (row 7 of the Kernel table); audit actor → Task 8; self-test → Task 9; installer creates the owner and completes with no `wp-config.php` → Task 10; principal resolution, per-route permission and channel, path parameters, request headers → Tasks 5 and 11; old Tasks 11–13 → Tasks 12–14. §7 "2.0.0 ships Emergency only, the installer does not ask" → Task 10. §8.3 "owner routes are browser-only" → Task 5's registration rule. §16 "a store is built in the phase that first uses it" → only `UserStore`, `AuthSessionStore` and `AuditLog` are added here; `StepLog`, `ApprovalStore`, `SnapshotIndex` and the site lock wait for the milestones that use them.

**Type consistency.** `AuthSessions::validate()` returns `?User` in Task 6 and is consumed as such by the Kernel in Task 11. `AuditEvent::by()` takes a `Principal` in Task 8 and is called that way in Tasks 10–13. `Installer::createOwner()` in Task 10 matches the `/api/install/owner` route in Task 11 and the screen in Task 14. Schema versions rise one per task: 2 (Task 3), 3 (Task 6), 4 (Task 8).

**Review Focus.** Each of the five lines names a test: Task 6 `testUnknownLoginIsIndistinguishableFromAWrongPassword`; Task 6 `testASessionOfADisabledUserStopsValidating` and Task 11 `testADisabledUsersCookieIsTreatedAsAnonymous`; Task 2 `testAFailedStepRollsBackItsOwnWrites` and `testAStepThatRanButWasNotRecordedRunsAgainSafely`; Task 5 `testABadRegistrationIsRefused` and Task 11 `testEveryRouteMatchesTheDeclaredTable`; Task 10 `testASecondOwnerCannotBeCreated` and `testInstallerMutationsRequireTheToken`.
