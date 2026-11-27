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
