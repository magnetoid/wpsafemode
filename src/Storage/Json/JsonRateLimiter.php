<?php

declare(strict_types=1);

namespace WPSafeMode\Storage\Json;

use WPSafeMode\Security\GuardedJson;
use WPSafeMode\Storage\Contracts\RateLimiter;
use WPSafeMode\Storage\RateLimitState;

final class JsonRateLimiter implements RateLimiter
{
    public function __construct(private string $path)
    {
    }

    public function hit(string $bucket, int $now): RateLimitState
    {
        $state = $this->peek($bucket, $now);

        $attempts = $state->attempts + 1;
        $windowStart = $state->attempts === 0 ? $now : $state->windowStart;
        $lockedUntil = $state->lockedUntil;

        if ($attempts >= self::MAX_ATTEMPTS && !$state->isLocked($now)) {
            $lockedUntil = $now + self::LOCKOUT_SECONDS;
        }

        $all = GuardedJson::read($this->path);
        $all[$bucket] = ['window_start' => $windowStart, 'attempts' => $attempts, 'locked_until' => $lockedUntil];
        GuardedJson::write($this->path, $all);

        return new RateLimitState($attempts, $windowStart, $lockedUntil);
    }

    public function peek(string $bucket, int $now): RateLimitState
    {
        $all = GuardedJson::read($this->path);

        if (!isset($all[$bucket]) || !is_array($all[$bucket])) {
            return new RateLimitState(0, $now, null);
        }

        $row = $all[$bucket];

        $state = new RateLimitState(
            (int) ($row['attempts'] ?? 0),
            (int) ($row['window_start'] ?? $now),
            isset($row['locked_until']) ? (int) $row['locked_until'] : null
        );

        return $state->afterExpiry($now, self::WINDOW_SECONDS);
    }

    public function clear(string $bucket): void
    {
        $all = GuardedJson::read($this->path);

        if (!array_key_exists($bucket, $all)) {
            return;
        }

        unset($all[$bucket]);
        GuardedJson::write($this->path, $all);
    }
}
