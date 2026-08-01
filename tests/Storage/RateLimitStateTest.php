<?php

declare(strict_types=1);

namespace WPSafeMode\Tests\Storage;

use PHPUnit\Framework\TestCase;
use WPSafeMode\Storage\RateLimitState;

/**
 * Direct unit coverage of RateLimitState, added in review round 1
 * (IMPORTANT 3).
 *
 * The conformance suite only ever exercises this class through two real
 * backends whose interface constants happen to have WINDOW_SECONDS ===
 * LOCKOUT_SECONDS (both 900) — so a bug that confused which one belongs in
 * which role would be numerically invisible to every conformance test.
 * afterExpiry() takes $windowSeconds as a free parameter, decoupled from any
 * specific constant, so it can — and, per review round 1, must — be driven
 * directly with a window and a lock duration that differ from each other.
 */
final class RateLimitStateTest extends TestCase
{
    public function testAfterExpiryLeavesAStillLockedStateUnchanged(): void
    {
        $state = new RateLimitState(5, 1000, 1300); // locked until 1300

        $result = $state->afterExpiry(1200, 5000); // still locked (1300 > 1200)

        self::assertSame($state, $result, 'a still-locked state must be returned as-is, not rebuilt');
    }

    public function testAfterExpiryResetsOnceTheWholeWindowHasElapsed(): void
    {
        $state = new RateLimitState(3, 1000, null); // never locked, mid-window

        $result = $state->afterExpiry(1200, 200); // exactly at the 200-second window boundary

        self::assertSame(0, $result->attempts);
        self::assertSame(1200, $result->windowStart);
        self::assertNull($result->lockedUntil);
    }

    /**
     * Branch 3 — review round 1 (IMPORTANT 3) found this reachable today,
     * via the free $windowSeconds parameter, but exercised by nothing: a
     * lock that has already expired, while the window it was set inside is
     * still open, must not leave the pre-lock attempt count in place, or one
     * lockout would effectively become permanent (every next hit would
     * immediately re-trigger it, forever).
     *
     * Deliberately uses a lock duration (100s) far shorter than the window
     * (1000s), unlike the real interface constants where both happen to be
     * 900 — see testSwappingWindowAndLockoutDurationsIsObservable for why
     * that distinction matters.
     */
    public function testAfterExpiryResetsWhenAnExpiredLockOutlivesItsOwnDurationButNotTheWindow(): void
    {
        $state = new RateLimitState(5, 1000, 1100); // locked 1000..1100 (a 100-second lockout)

        $result = $state->afterExpiry(1150, 1000); // lock long expired (1100 < 1150); window (1000..2000) still open

        self::assertSame(0, $result->attempts);
        self::assertSame(1150, $result->windowStart);
        self::assertNull($result->lockedUntil);
    }

    public function testAfterExpiryLeavesANeverLockedMidWindowStateUnchanged(): void
    {
        $state = new RateLimitState(2, 1000, null);

        $result = $state->afterExpiry(1150, 1000);

        self::assertSame($state, $result);
    }

    /**
     * Review round 1, IMPORTANT 3's second point: WINDOW_SECONDS and
     * LOCKOUT_SECONDS are both 900 on the real interface, so any test built
     * from those constants cannot tell correct code from a bug that
     * confused the two — every assertion is numerically identical either
     * way. This test uses the SAME pair of numbers (100 and 1000) in the TWO
     * possible role assignments and confirms the outcome actually reverses
     * between them, proving the underlying mechanism treats "window" and
     * "lockout" as genuinely distinct roles rather than interchangeable
     * durations — a distinction the real 900/900 constants can never
     * exercise on their own.
     */
    public function testSwappingWindowAndLockoutDurationsIsObservable(): void
    {
        $windowStart = 1000;
        $now = 1150; // 150 seconds after windowStart

        // "lockout" (100s) shorter than "window" (1000s): by $now the lock
        // has expired but the window has not — the bucket must come back
        // unlocked.
        $shortLockout = new RateLimitState(5, $windowStart, $windowStart + 100);
        $afterShortLockout = $shortLockout->afterExpiry($now, 1000);
        self::assertFalse(
            $afterShortLockout->isLocked($now),
            'a 100-second lockout must have expired 150 seconds later'
        );
        // isLocked() alone is a weaker check than it looks: it compares the
        // ORIGINAL lockedUntil against $now regardless of whether a reset
        // actually ran, so a mutant that skipped the reset entirely would
        // still report "unlocked" here (1100 < 1150 either way). Pinning
        // attempts too catches that: only a real reset brings it back to 0.
        self::assertSame(
            0,
            $afterShortLockout->attempts,
            'the expired lock must not leave the pre-lock attempt count in place'
        );

        // The same two numbers, swapped: "lockout" (1000s) now longer than
        // "window" (100s). By the SAME $now, the lock has NOT expired — the
        // bucket must still be locked.
        $longLockout = new RateLimitState(5, $windowStart, $windowStart + 1000);
        $afterLongLockout = $longLockout->afterExpiry($now, 100);
        self::assertTrue(
            $afterLongLockout->isLocked($now),
            'a 1000-second lockout must still be in force 150 seconds later'
        );
    }

    public function testAlreadyAtLimitIsLockedForExactlyTheGivenLockoutFromNow(): void
    {
        $state = RateLimitState::alreadyAtLimit(1000, 5, 300);

        self::assertSame(5, $state->attempts);
        self::assertSame(1000, $state->windowStart);
        self::assertTrue($state->isLocked(1000));
        self::assertSame(300, $state->retryAfter(1000));
        self::assertFalse($state->isLocked(1301));
    }

    public function testFromRawRowParsesAWellShapedRow(): void
    {
        $state = RateLimitState::fromRawRow(
            ['attempts' => 3, 'window_start' => 500, 'locked_until' => null],
            1000,
            5,
            300
        );

        self::assertSame(3, $state->attempts);
        self::assertSame(500, $state->windowStart);
        self::assertNull($state->lockedUntil);
        self::assertFalse($state->isLocked(1000));
    }

    public function testFromRawRowFailsClosedWhenAFieldIsMissing(): void
    {
        $state = RateLimitState::fromRawRow(
            ['attempts' => 3, 'window_start' => 500], // locked_until missing entirely
            1000,
            5,
            300
        );

        self::assertSame(5, $state->attempts);
        self::assertTrue($state->isLocked(1000));
    }

    public function testFromRawRowFailsClosedWhenAFieldIsNotNumeric(): void
    {
        $state = RateLimitState::fromRawRow(
            ['attempts' => 3, 'window_start' => 500, 'locked_until' => 'not-a-number'],
            1000,
            5,
            300
        );

        self::assertSame(5, $state->attempts);
        self::assertTrue($state->isLocked(1000));
    }
}
