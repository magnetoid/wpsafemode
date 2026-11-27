<?php

declare(strict_types=1);

namespace WPSafeMode\Storage\Contracts;

if (!defined('WPSM_ENTRY')) {
    http_response_code(404);
    exit;
}

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
