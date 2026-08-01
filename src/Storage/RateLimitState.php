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

    /**
     * "Assume the worst": already at the limit and locked, for exactly as
     * long as a real lockout would run from here.
     *
     * Added in review round 1 (IMPORTANT 1 and 2): before this, a stored
     * value neither backend could have written itself — a JSON bucket entry
     * that is not an array, or a garbage locked_until in either backend —
     * was read as a fresh, unlocked bucket in JSON and as whatever an
     * (int) cast of the garbage happened to produce in SQLite, arbitrarily
     * locked or not depending on the garbage. Both are the wrong direction
     * for a security counter: a value that cannot be trusted to mean
     * anything else must fail closed, not fail open.
     */
    public static function alreadyAtLimit(int $now, int $maxAttempts, int $lockoutSeconds): self
    {
        return new self($maxAttempts, $now, $now + $lockoutSeconds);
    }

    /**
     * Parses a raw stored row/entry — the same three-field shape
     * (attempts, window_start, locked_until) both backends persist — into a
     * state, or into alreadyAtLimit() if the row cannot be trusted to mean
     * anything else.
     *
     * This lives here, once, rather than as similar-but-separate validation
     * duplicated in SqliteRateLimiter and JsonRateLimiter, for the same
     * reason afterExpiry() does: a second copy of "what counts as corrupt"
     * is exactly the kind of drift that let the two backends disagree in
     * the first place (review round 1, IMPORTANT 2) — SQLite trusted
     * anything an (int) cast would accept, JSON only tolerated missing keys.
     * One implementation is the only way the conformance suite's malformed-
     * value test can hold both backends to it identically.
     *
     * @param array<string, mixed> $row
     */
    public static function fromRawRow(array $row, int $now, int $maxAttempts, int $lockoutSeconds): self
    {
        if (!self::isPlausible($row)) {
            return self::alreadyAtLimit($now, $maxAttempts, $lockoutSeconds);
        }

        return new self(
            (int) $row['attempts'],
            (int) $row['window_start'],
            $row['locked_until'] === null ? null : (int) $row['locked_until']
        );
    }

    /**
     * True only for a row shaped exactly as hit() itself writes: all three
     * fields present, attempts/window_start numeric, and locked_until
     * either null or numeric. Anything else — a missing field, or a value
     * that is present but not a number — did not come from this class's own
     * writes and cannot be trusted to mean what it looks like it means.
     *
     * @param array<string, mixed> $row
     */
    private static function isPlausible(array $row): bool
    {
        if (
            !array_key_exists('attempts', $row)
            || !array_key_exists('window_start', $row)
            || !array_key_exists('locked_until', $row)
        ) {
            return false;
        }

        if (!is_numeric($row['attempts']) || !is_numeric($row['window_start'])) {
            return false;
        }

        return $row['locked_until'] === null || is_numeric($row['locked_until']);
    }
}
