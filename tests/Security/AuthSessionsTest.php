<?php

declare(strict_types=1);

namespace WPSafeMode\Tests\Security;

use PHPUnit\Framework\TestCase;
use WPSafeMode\Security\AuthSessions;
use WPSafeMode\Security\PasswordHasher;
use WPSafeMode\Security\Role;
use WPSafeMode\Storage\Contracts\RateLimiter;
use WPSafeMode\Storage\Contracts\Storage;
use WPSafeMode\Storage\Contracts\StorageException;
use WPSafeMode\Storage\Credential;
use WPSafeMode\Storage\StorageFactory;
use WPSafeMode\Storage\User;

final class AuthSessionsTest extends TestCase
{
    private const PASSWORD = 'correct horse battery staple';

    private string $dir;
    private Storage $storage;
    private AuthSessions $auth;
    private User $owner;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/wpsm-auth-' . bin2hex(random_bytes(6));
        mkdir($this->dir, 0700, true);

        $this->storage = StorageFactory::create($this->dir, 'json');
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
        $stored = (string) file_get_contents($this->dir . '/auth_sessions.php');

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
        $empty = sys_get_temp_dir() . '/wpsm-auth-empty-' . bin2hex(random_bytes(6));
        mkdir($empty, 0700, true);

        $storage = StorageFactory::create($empty, 'json');
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
        $path = $this->dir . '/auth_sessions.php';
        $hash = AuthSessions::hashToken($token);

        $rows = \WPSafeMode\Security\GuardedJson::read($path);
        $rows[$hash]['user_id'] = 'one';
        \WPSafeMode\Security\GuardedJson::write($path, $rows);

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
}
