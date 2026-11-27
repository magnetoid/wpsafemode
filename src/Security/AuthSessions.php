<?php

declare(strict_types=1);

namespace WPSafeMode\Security;

use WPSafeMode\Storage\Contracts\Storage;
use WPSafeMode\Storage\User;

/**
 * Login sessions in our own storage, each bound to the user who logged in.
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

    public function login(string $login, string $password, string $ip, string $userAgent, int $now): LoginResult
    {
        // The same placeholder Request::clientIp() uses, so the bucket and
        // the stored row agree when no address is known.
        $ip = $ip === '' ? 'unknown' : $ip;
        $bucket = 'login:' . $ip;
        $limiter = $this->storage->rateLimiter();

        $state = $limiter->peek($bucket, $now);

        if ($state->isLocked($now)) {
            // Checked before the password so a correct guess during lockout is
            // still refused; otherwise the lockout means nothing.
            return LoginResult::failure('locked_out', $state->retryAfter($now));
        }

        $users = $this->storage->users();

        if ($users->isEmpty()) {
            // Not counted: no user exists, so no password can be right.
            return LoginResult::failure('not_installed');
        }

        if (strpos($password, "\0") !== false) {
            // bcrypt refuses a NUL byte (hash() throws) while verify() and
            // argon2id do not, so such a password must never reach either: it
            // takes the counted failure path below for every login, real or
            // not, and can neither enumerate logins nor go uncounted.
            $user = null;
            $verified = false;
        } elseif (($user = $users->findByLogin($login)) !== null && $user->isEnabled()) {
            $verified = $this->hasher->verify($password, $user->credential);
        } else {
            // An unknown or disabled login does the same work as a real
            // verification under this host's algorithm, and is counted and
            // reported exactly like a wrong password, so neither the response
            // nor its timing says whether the login exists.
            $this->hasher->hash($password);
            $verified = false;
        }

        if ($user === null || !$verified) {
            $after = $limiter->hit($bucket, $now);

            return LoginResult::failure(
                $after->isLocked($now) ? 'locked_out' : 'bad_credentials',
                $after->retryAfter($now)
            );
        }

        $limiter->clear($bucket);

        // The only moment the plaintext exists, so it is the only moment an
        // upgrade from bcrypt to argon2id can happen (spec 5.1). A new User,
        // so the loaded one is not mutated, and only this user is written.
        if ($this->hasher->needsRehash($user->credential)) {
            $users->update(new User(
                $user->id,
                $user->login,
                $user->role,
                $this->hasher->hash($password),
                $user->disabledAt,
            ));
        }

        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');

        $sessions = $this->storage->authSessions();
        $sessions->purgeExpired($now);
        $sessions->put(self::hashToken($token), [
            'user_id'      => $user->id,
            'created_at'   => $now,
            'last_seen_at' => $now,
            'expires_at'   => $now + self::LIFETIME_SECONDS,
            'ip'           => $ip,
            'ua_hash'      => self::hashAgent($userAgent),
        ]);

        return LoginResult::success($token, $user->id);
    }

    /** The user the session belongs to when it is good, otherwise null. */
    public function validate(string $token, string $userAgent, int $now): ?User
    {
        if ($token === '') {
            return null;
        }

        $store = $this->storage->authSessions();
        $hash = self::hashToken($token);
        $row = $store->get($hash);

        if ($row === null) {
            return null;
        }

        if ($row['expires_at'] <= $now || $now - $row['last_seen_at'] > self::IDLE_SECONDS) {
            $store->delete($hash);

            return null;
        }

        // The user agent is bound but the IP is not: mobile networks and
        // CGNAT rotate addresses mid-session, and logging someone out of a
        // rescue because their phone changed tower is worse than the marginal
        // protection. The IP is recorded for the audit log either way.
        if (!hash_equals($row['ua_hash'], self::hashAgent($userAgent))) {
            $store->delete($hash);

            return null;
        }

        $user = $this->storage->users()->find($row['user_id']);

        if ($user === null || !$user->isEnabled()) {
            $store->delete($hash);

            return null;
        }

        $store->touch($hash, $now);

        return $user;
    }

    public function logout(string $token): void
    {
        $this->storage->authSessions()->delete(self::hashToken($token));
    }

    public function cookieHeader(string $token, bool $https, string $path, int $now): string
    {
        if (preg_match('/^[A-Za-z0-9_-]+\z/', $token) !== 1) {
            throw new \InvalidArgumentException('The session token has characters a cookie value must not carry.');
        }

        $parts = [
            self::COOKIE . '=' . $token,
            'Path=' . self::cookiePath($path),
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
            'Path=' . self::cookiePath($path),
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

    /**
     * '' (a root install) becomes '/', because an empty Path attribute makes
     * browsers fall back to the request's directory. Anything that could add
     * or override a cookie attribute is refused.
     */
    private static function cookiePath(string $path): string
    {
        if ($path === '') {
            return '/';
        }

        if ($path[0] !== '/' || preg_match('/[;,\s\x00-\x1F\x7F]/', $path) === 1) {
            throw new \InvalidArgumentException('The cookie path is not a plain absolute path.');
        }

        return $path;
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
