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

        // Signing in refuses a NUL byte on every path (bcrypt cannot hash
        // one), so an owner created with one could never sign in.
        if (strpos($password, "\0") !== false) {
            $problems[] = 'Remove the invisible NUL character. A password cannot contain one.';
        }

        return $problems;
    }
}
