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
}
