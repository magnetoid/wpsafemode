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
