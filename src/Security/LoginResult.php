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
        public ?int $userId = null,
    ) {
    }

    public static function success(string $token, int $userId): self
    {
        return new self(true, null, $token, 0, $userId);
    }

    public static function failure(string $reason, int $retryAfter = 0): self
    {
        return new self(false, $reason, null, $retryAfter);
    }
}
