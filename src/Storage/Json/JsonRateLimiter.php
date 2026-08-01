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
        // The read (peek) and the write below must happen as one guarded
        // sequence, not two independent GuardedJson calls: read()/write()
        // alone give no exclusion at all across that gap (write()'s LOCK_EX
        // is on a freshly-named temp file, never on $this->path), so two
        // concurrent hit() calls could both peek() the same stale count,
        // both compute the same "+1", and the later write() would simply
        // discard the earlier one — the silent lost-update review round 1
        // found, for the one component whose entire job is counting
        // attempts correctly.
        return GuardedJson::withExclusiveLock($this->path, function () use ($bucket, $now): RateLimitState {
            $state = $this->peek($bucket, $now);

            $attempts = $state->attempts + 1;
            $windowStart = $state->attempts === 0 ? $now : $state->windowStart;
            $lockedUntil = $state->lockedUntil;

            if ($attempts >= self::MAX_ATTEMPTS && !$state->isLocked($now)) {
                $lockedUntil = $now + self::LOCKOUT_SECONDS;
            }

            $all = GuardedJson::read($this->path);
            $all = $this->pruneExpiredBuckets($all, $now);
            $all[$bucket] = ['window_start' => $windowStart, 'attempts' => $attempts, 'locked_until' => $lockedUntil];
            GuardedJson::write($this->path, $all);

            return new RateLimitState($attempts, $windowStart, $lockedUntil);
        });
    }

    /**
     * Drops buckets whose window AND lock have both fully elapsed (review
     * round 2, IMPORTANT E), so rate_limit.php does not grow without bound
     * under a flood across many distinct buckets — exactly the load this
     * limiter exists to survive. clear() only runs on a SUCCESSFUL login, so
     * a bucket that is simply never revisited (an attacker moving on, or
     * scanning many IPs/usernames once each) would otherwise sit here
     * forever, and every later hit()'s read-whole-file/write-whole-file
     * critical section — now serialised behind one lock for every bucket —
     * pays the cost of all of them.
     *
     * A row this cannot confidently parse is left alone, not dropped:
     * fromRawRow()'s fail-closed alreadyAtLimit() reports such a row as
     * locked, so it never satisfies the "not locked" half of the condition
     * below and survives the sweep — the same fail-closed direction as
     * reading it (review round 1, IMPORTANT 1/2).
     *
     * @param array<string, mixed> $all
     * @return array<string, mixed>
     */
    private function pruneExpiredBuckets(array $all, int $now): array
    {
        foreach ($all as $candidateBucket => $row) {
            if (!is_array($row)) {
                continue;
            }

            $state = RateLimitState::fromRawRow($row, $now, self::MAX_ATTEMPTS, self::LOCKOUT_SECONDS);

            if (!$state->isLocked($now) && $now - $state->windowStart >= self::WINDOW_SECONDS) {
                unset($all[$candidateBucket]);
            }
        }

        return $all;
    }

    public function peek(string $bucket, int $now): RateLimitState
    {
        $all = GuardedJson::read($this->path);

        if (!array_key_exists($bucket, $all)) {
            return new RateLimitState(0, $now, null);
        }

        if (!is_array($all[$bucket])) {
            // IMPORTANT 1: a bucket entry that EXISTS but is not even an
            // array cannot have been written by hit() itself, which only
            // ever stores an array here. Reading it as "0 attempts,
            // unlocked" — the previous behaviour — is the most permissive
            // interpretation possible for a security counter; fail closed
            // instead, exactly as fromRawRow() does for a malformed-but-array
            // entry below.
            return RateLimitState::alreadyAtLimit($now, self::MAX_ATTEMPTS, self::LOCKOUT_SECONDS)
                ->afterExpiry($now, self::WINDOW_SECONDS);
        }

        // fromRawRow(), not bare (int) casts of whatever keys happen to be
        // present: review round 1 (IMPORTANT 2) found that defaulting a
        // missing/garbage field to 0 or now collapsed a malformed entry to a
        // clean, unlocked slate instead of failing closed, and that SQLite
        // disagreed with this backend on the same kind of value. One shared
        // parse (on the value object) is what keeps the two backends unable
        // to drift on what counts as corrupt.
        return RateLimitState::fromRawRow($all[$bucket], $now, self::MAX_ATTEMPTS, self::LOCKOUT_SECONDS)
            ->afterExpiry($now, self::WINDOW_SECONDS);
    }

    public function clear(string $bucket): void
    {
        // Same read-modify-write shape as hit(), and the same fix: without
        // the lock, a clear() racing a concurrent hit() on a DIFFERENT
        // bucket could read the file before that hit()'s write and then
        // overwrite the whole file afterwards, silently discarding it.
        GuardedJson::withExclusiveLock($this->path, function () use ($bucket): void {
            $all = GuardedJson::read($this->path);

            if (!array_key_exists($bucket, $all)) {
                return;
            }

            unset($all[$bucket]);
            GuardedJson::write($this->path, $all);
        });
    }
}
