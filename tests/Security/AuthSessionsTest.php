<?php

declare(strict_types=1);

namespace WPSafeMode\Tests\Security;

use PHPUnit\Framework\TestCase;
use WPSafeMode\Security\AuthSessions;
use WPSafeMode\Security\GuardedJson;
use WPSafeMode\Security\PasswordHasher;
use WPSafeMode\Security\Role;
use WPSafeMode\Storage\Contracts\RateLimiter;
use WPSafeMode\Storage\Contracts\Storage;
use WPSafeMode\Storage\Contracts\StorageException;
use WPSafeMode\Storage\Credential;
use WPSafeMode\Storage\StorageFactory;
use WPSafeMode\Storage\User;

/**
 * Runs on the JSON backend; AuthSessionsSqliteTest runs every test again on
 * SQLite, which is what most hosts get.
 */
class AuthSessionsTest extends TestCase
{
    protected const PASSWORD = 'correct horse battery staple';

    protected string $dir;
    protected Storage $storage;
    protected AuthSessions $auth;
    protected User $owner;

    protected function backend(): string
    {
        return StorageFactory::BACKEND_JSON;
    }

    /** The file that holds the login sessions. */
    protected function sessionsFile(): string
    {
        return $this->dir . '/auth_sessions.php';
    }

    /** Overwrites one field of a stored login session behind the store's back. */
    protected function corruptSession(string $tokenHash, string $field, string $value): void
    {
        $rows = GuardedJson::read($this->sessionsFile());
        $rows[$tokenHash][$field] = $value;
        GuardedJson::write($this->sessionsFile(), $rows);
    }

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/wpsm-auth-' . bin2hex(random_bytes(6));
        mkdir($this->dir, 0700, true);

        $this->storage = StorageFactory::create($this->dir, $this->backend());
        $this->owner = $this->storage->users()->create('owner', Role::OWNER, (new PasswordHasher())->hash(self::PASSWORD));

        $this->auth = new AuthSessions($this->storage, new PasswordHasher());
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

    private function loginToken(int $now = 1000): string
    {
        return (string) $this->auth->login('owner', self::PASSWORD, '1.2.3.4', 'UA', $now)->token;
    }

    public function testCorrectPasswordIssuesATokenAndNamesTheUser(): void
    {
        $result = $this->auth->login('owner', self::PASSWORD, '1.2.3.4', 'UA', 1000);

        self::assertTrue($result->ok);
        self::assertNotSame('', (string) $result->token);
        self::assertSame($this->owner->id, $result->userId);
    }

    public function testWrongPasswordIsRejectedWithoutAToken(): void
    {
        $result = $this->auth->login('owner', 'wrong', '1.2.3.4', 'UA', 1000);

        self::assertFalse($result->ok);
        self::assertNull($result->token);
        self::assertNull($result->userId);
        self::assertSame('bad_credentials', $result->reason);
    }

    public function testAnIssuedTokenValidatesToTheUser(): void
    {
        $user = $this->auth->validate($this->loginToken(), 'UA', 1010);

        self::assertNotNull($user);
        self::assertSame($this->owner->id, $user->id);
    }

    public function testAnUnknownTokenDoesNotValidate(): void
    {
        self::assertNull($this->auth->validate('made-up-token', 'UA', 1000));
    }

    public function testTheRawTokenIsNeverStored(): void
    {
        $token = $this->loginToken();

        // A stolen data directory must not yield usable session tokens.
        $stored = (string) file_get_contents($this->sessionsFile());

        self::assertStringNotContainsString($token, $stored);
        self::assertStringContainsString(AuthSessions::hashToken($token), $stored);
    }

    public function testAnExpiredTokenDoesNotValidate(): void
    {
        $token = $this->loginToken();

        self::assertNull($this->auth->validate($token, 'UA', 1000 + AuthSessions::LIFETIME_SECONDS + 1));
    }

    public function testAnIdleTokenDoesNotValidate(): void
    {
        $token = $this->loginToken();

        self::assertNull($this->auth->validate($token, 'UA', 1000 + AuthSessions::IDLE_SECONDS + 1));
    }

    public function testActivityKeepsASessionFromGoingIdle(): void
    {
        $token = $this->loginToken();

        self::assertNotNull($this->auth->validate($token, 'UA', 1000 + AuthSessions::IDLE_SECONDS - 10));
        self::assertNotNull($this->auth->validate($token, 'UA', 1000 + 2 * AuthSessions::IDLE_SECONDS - 20));
    }

    public function testAChangedUserAgentInvalidatesTheSession(): void
    {
        $token = $this->loginToken();

        self::assertNull($this->auth->validate($token, 'A Different UA', 1010));
        self::assertNull($this->storage->authSessions()->get(AuthSessions::hashToken($token)));
    }

    public function testLogoutRevokesTheToken(): void
    {
        $token = $this->loginToken();
        $this->auth->logout($token);

        self::assertNull($this->auth->validate($token, 'UA', 1010));
    }

    public function testRepeatedFailuresLockTheAccountAndReportRetryAfter(): void
    {
        for ($i = 0; $i < RateLimiter::MAX_ATTEMPTS; $i++) {
            $this->auth->login('owner', 'wrong', '1.2.3.4', 'UA', 1000);
        }

        $result = $this->auth->login('owner', self::PASSWORD, '1.2.3.4', 'UA', 1000);

        // Correct password, still refused: otherwise the lockout is trivially
        // bypassed by guessing until you get it right.
        self::assertFalse($result->ok);
        self::assertSame('locked_out', $result->reason);
        self::assertGreaterThan(0, $result->retryAfter);
    }

    public function testASuccessfulLoginClearsTheLockoutCounter(): void
    {
        $this->auth->login('owner', 'wrong', '1.2.3.4', 'UA', 1000);
        $this->auth->login('owner', 'wrong', '1.2.3.4', 'UA', 1000);
        $this->auth->login('owner', self::PASSWORD, '1.2.3.4', 'UA', 1000);

        for ($i = 0; $i < RateLimiter::MAX_ATTEMPTS - 1; $i++) {
            $this->auth->login('owner', 'wrong', '1.2.3.4', 'UA', 1000);
        }

        self::assertTrue($this->auth->login('owner', self::PASSWORD, '1.2.3.4', 'UA', 1000)->ok);
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

    public function testLoginOnAnInstallWithNoUsersFails(): void
    {
        $empty = $this->dir . '/empty';
        mkdir($empty, 0700, true);

        $storage = StorageFactory::create($empty, $this->backend());
        $auth = new AuthSessions($storage, new PasswordHasher());
        $result = $auth->login('anything', 'whatever-long-password', '1.2.3.4', 'UA', 1000);

        self::assertFalse($result->ok);
        self::assertSame('not_installed', $result->reason);
        // No user exists, so no password can be right: nothing is counted.
        self::assertSame(0, $storage->rateLimiter()->peek('login:1.2.3.4', 1000)->attempts);

        $this->removeRecursively($empty);
    }

    public function testUnknownLoginIsIndistinguishableFromAWrongPassword(): void
    {
        $a = $this->auth->login('nobody', 'whatever-long-password', '10.0.0.1', 'UA', 1000);
        $b = $this->auth->login('owner', 'wrong-long-password', '10.0.0.2', 'UA', 1000);

        self::assertSame($b->reason, $a->reason);
        self::assertSame('bad_credentials', $a->reason);
        self::assertNull($a->token);
        self::assertNull($b->token);

        $limiter = $this->storage->rateLimiter();
        self::assertSame(1, $limiter->peek('login:10.0.0.1', 1000)->attempts);
        self::assertSame(1, $limiter->peek('login:10.0.0.2', 1000)->attempts);
    }

    public function testADisabledUserCannotLogInAndLooksLikeAWrongPassword(): void
    {
        $this->owner->disabledAt = 500;
        $this->storage->users()->update($this->owner);

        $disabled = $this->auth->login('owner', self::PASSWORD, '10.0.0.1', 'UA', 1000);
        $wrong = $this->auth->login('nobody', self::PASSWORD, '10.0.0.2', 'UA', 1000);

        self::assertFalse($disabled->ok);
        self::assertSame('bad_credentials', $disabled->reason);
        self::assertSame($wrong->reason, $disabled->reason);
        self::assertNull($disabled->token);
        self::assertSame(1, $this->storage->rateLimiter()->peek('login:10.0.0.1', 1000)->attempts);
    }

    public function testASessionOfADisabledUserStopsValidating(): void
    {
        $token = $this->loginToken();
        self::assertNotNull($this->auth->validate($token, 'UA', 1010));

        $this->owner->disabledAt = 1020;
        $this->storage->users()->update($this->owner);

        self::assertNull($this->auth->validate($token, 'UA', 1030));
        self::assertNull($this->storage->authSessions()->get(AuthSessions::hashToken($token)));
    }

    public function testLoginNameIsCaseInsensitive(): void
    {
        self::assertTrue($this->auth->login('OWNER', self::PASSWORD, '1.2.3.4', 'UA', 1000)->ok);
    }

    public function testRehashTouchesOnlyTheUserWhoLoggedIn(): void
    {
        $hasher = new PasswordHasher();
        $weak = static function (string $pw): Credential {
            return new Credential(
                (string) password_hash($pw, PASSWORD_BCRYPT, ['cost' => 4]),
                'bcrypt',
                100,
                100
            );
        };

        $first = $this->storage->users()->create('first-user', Role::ADMIN, $weak(self::PASSWORD));
        $second = $this->storage->users()->create('second-user', Role::ADMIN, $weak('another long password'));
        self::assertTrue($hasher->needsRehash($first->credential));
        self::assertTrue($hasher->needsRehash($second->credential));

        $result = $this->auth->login('first-user', self::PASSWORD, '1.2.3.4', 'UA', 1000);

        self::assertTrue($result->ok);
        $users = $this->storage->users();
        $reloadedFirst = $users->find($first->id);
        $reloadedSecond = $users->find($second->id);
        self::assertNotNull($reloadedFirst);
        self::assertNotNull($reloadedSecond);
        self::assertNotSame($first->credential->hash, $reloadedFirst->credential->hash);
        self::assertTrue($hasher->verify(self::PASSWORD, $reloadedFirst->credential));
        self::assertFalse($hasher->needsRehash($reloadedFirst->credential));
        self::assertSame(Role::ADMIN, $reloadedFirst->role);
        self::assertSame($second->credential->hash, $reloadedSecond->credential->hash);
        self::assertSame($second->credential->algo, $reloadedSecond->credential->algo);
    }

    public function testAMalformedSessionRowFailsClosed(): void
    {
        $token = $this->loginToken();
        $this->corruptSession(AuthSessions::hashToken($token), 'user_id', 'one');

        $this->expectException(StorageException::class);

        $this->auth->validate($token, 'UA', 1010);
    }

    public function testASqliteSessionRowWithATextUserIdFailsClosed(): void
    {
        $dir = $this->dir . '/sqlite';
        mkdir($dir, 0700, true);
        $storage = StorageFactory::create($dir, 'sqlite');
        $storage->users()->create('owner', Role::OWNER, (new PasswordHasher())->hash(self::PASSWORD));
        $auth = new AuthSessions($storage, new PasswordHasher());

        $token = (string) $auth->login('owner', self::PASSWORD, '1.2.3.4', 'UA', 1000)->token;
        self::assertNotNull($auth->validate($token, 'UA', 1010));

        $pdo = new \PDO('sqlite:' . $dir . '/wpsm.sqlite', null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        $pdo->exec("UPDATE auth_sessions SET user_id = 'one'");
        unset($pdo);

        $this->expectException(StorageException::class);

        $auth->validate($token, 'UA', 1020);
    }

    public function testANulBytePasswordIsCountedIdenticallyForRealAndUnknownLogins(): void
    {
        $real = $this->auth->login('owner', "pass\0word-long-enough", '10.0.0.1', 'UA', 1000);
        $unknown = $this->auth->login('nobody', "pass\0word-long-enough", '10.0.0.2', 'UA', 1000);

        foreach ([$real, $unknown] as $result) {
            self::assertFalse($result->ok);
            self::assertSame('bad_credentials', $result->reason);
            self::assertNull($result->token);
        }

        $limiter = $this->storage->rateLimiter();
        self::assertSame(1, $limiter->peek('login:10.0.0.1', 1000)->attempts);
        self::assertSame(1, $limiter->peek('login:10.0.0.2', 1000)->attempts);
    }

    public function testAnEmptyIpIsRecordedAsUnknown(): void
    {
        $token = (string) $this->auth->login('owner', self::PASSWORD, '', 'UA', 1000)->token;

        $row = $this->storage->authSessions()->get(AuthSessions::hashToken($token));
        self::assertNotNull($row);
        self::assertSame('unknown', $row['ip']);

        $this->auth->login('owner', 'wrong', '', 'UA', 1000);
        self::assertSame(1, $this->storage->rateLimiter()->peek('login:unknown', 1000)->attempts);
    }

    public function testASessionRowWithAnEmptyIpFailsClosed(): void
    {
        $token = $this->loginToken();
        $this->corruptSession(AuthSessions::hashToken($token), 'ip', '');

        try {
            $this->auth->validate($token, 'UA', 1010);
            self::fail('Expected StorageException for an empty ip');
        } catch (StorageException $e) {
            self::assertStringContainsString('ip', $e->getMessage());
        }
    }

    /** JSON only by nature: SQLite reads one row by its key and never sees another. */
    public function testAJsonSessionsFileWithAnEmptyTokenHashFailsClosed(): void
    {
        $dir = $this->dir . '/json';
        mkdir($dir, 0700, true);
        $storage = StorageFactory::create($dir, StorageFactory::BACKEND_JSON);
        $storage->users()->create('owner', Role::OWNER, (new PasswordHasher())->hash(self::PASSWORD));
        $auth = new AuthSessions($storage, new PasswordHasher());
        $token = (string) $auth->login('owner', self::PASSWORD, '1.2.3.4', 'UA', 1000)->token;

        $rows = GuardedJson::read($dir . '/auth_sessions.php');
        $rows[''] = $rows[AuthSessions::hashToken($token)];
        GuardedJson::write($dir . '/auth_sessions.php', $rows);

        $this->expectException(StorageException::class);
        $auth->validate($token, 'UA', 1010);
    }

    public function testARootInstallCookieUsesPathSlash(): void
    {
        self::assertStringContainsString('Path=/;', $this->auth->cookieHeader('tok', false, '', 1000));
        self::assertStringContainsString('Path=/;', $this->auth->clearCookieHeader(false, ''));
    }

    private function throwsInvalidArgument(callable $fn): bool
    {
        try {
            $fn();
        } catch (\InvalidArgumentException $e) {
            return true;
        }

        return false;
    }

    /** @return array<string, array{string}> */
    public function relativePaths(): array
    {
        return [
            'attribute injection' => [';Domain=evil.example'],
            'relative'            => ['wpsm'],
            'CR LF first'         => ["\r\nSet-Cookie: x=y"],
        ];
    }

    /** @dataProvider relativePaths */
    public function testCookieHeadersRefuseAPathThatIsNotAbsolute(string $path): void
    {
        self::assertTrue($this->throwsInvalidArgument(fn () => $this->auth->cookieHeader('tok', false, $path, 1000)));
        self::assertTrue($this->throwsInvalidArgument(fn () => $this->auth->clearCookieHeader(false, $path)));
    }

    /** @return array<string, array{string, string}> base path => the Path attribute it must become */
    public function pathsThatNeedEncoding(): array
    {
        return [
            'space'        => ['/wp safe mode', '/wp%20safe%20mode'],
            'parentheses'  => ['/wpsafemode (1)', '/wpsafemode%20%281%29'],
            'nested'       => ['/sites/wp safe mode', '/sites/wp%20safe%20mode'],
            'second path'  => ['/ok; Path=/', '/ok%3B%20Path%3D/'],
            'CR LF'        => ["/ok\r\nSet-Cookie: x=y", '/ok%0D%0ASet-Cookie%3A%20x%3Dy'],
            'comma'        => ['/a,b', '/a%2Cb'],
            'NUL'          => ["/a\0b", '/a%00b'],
        ];
    }

    /**
     * The Path is the percent-encoded install directory, so a folder named
     * "wp safe mode" can sign in, and nothing in the name can add or end a
     * cookie attribute: only unreserved characters, % and / remain.
     *
     * @dataProvider pathsThatNeedEncoding
     */
    public function testTheCookiePathIsThePercentEncodedBasePath(string $base, string $path): void
    {
        foreach ([
            $this->auth->cookieHeader('tok', false, $base, 1000),
            $this->auth->clearCookieHeader(false, $base),
        ] as $header) {
            self::assertStringContainsString('; Path=' . $path . ';', $header);
            self::assertSame(1, preg_match('/; Path=([^;]*);/', $header, $m));
            self::assertSame(1, preg_match('#^/[A-Za-z0-9._~%/-]*\z#', $m[1]));
            self::assertSame(1, substr_count($header, 'Path='));
            self::assertStringNotContainsString("\r", $header);
            self::assertStringNotContainsString("\n", $header);
        }
    }

    public function testCookieHeaderRefusesATokenThatIsNotUrlSafeBase64(): void
    {
        foreach (['', 'a;b', "a\nb", 'a b', "tok\n"] as $token) {
            self::assertTrue(
                $this->throwsInvalidArgument(fn () => $this->auth->cookieHeader($token, false, '/', 1000)),
                'accepted token ' . json_encode($token)
            );
        }
    }

    public function testClearCookieCarriesTheSameAttributes(): void
    {
        $header = $this->auth->clearCookieHeader(true, '/wpsm-abc');

        foreach (['Path=/wpsm-abc', 'Max-Age=0', 'HttpOnly', 'SameSite=Strict', 'Secure'] as $attr) {
            self::assertStringContainsString($attr, $header);
        }
    }
}
