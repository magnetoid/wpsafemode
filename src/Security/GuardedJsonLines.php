<?php

declare(strict_types=1);

namespace WPSafeMode\Security;

use Generator;
use JsonException;
use WPSafeMode\Storage\Contracts\StorageException;

/**
 * An append-only JSONL log behind the same die() guard.
 *
 * The guard occupies the first line and every later line is one JSON object.
 * Appending is O(1) under LOCK_EX and a crashed request truncates at most the
 * final line. Readers stream: a long session's message log is the largest
 * object in the data directory and must never be loaded whole (spec 15.2).
 */
final class GuardedJsonLines
{
    /** @param array<string, mixed> $row */
    public static function append(string $path, array $row): void
    {
        DataDir::ensure(dirname($path));

        try {
            $line = json_encode(
                $row,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG
            );
        } catch (JsonException $e) {
            throw new StorageException('Cannot encode log line: ' . basename($path), 0, $e);
        }

        // 'a+b', not 'ab': every write still lands at end-of-file regardless
        // of where the read position below leaves the pointer (POSIX
        // O_APPEND forces this on every fwrite()), but the '+' makes the
        // handle readable, which the torn-tail repair below needs.
        $handle = fopen($path, 'a+b');

        if ($handle === false) {
            throw new StorageException('Cannot open log: ' . basename($path));
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new StorageException('Cannot lock log: ' . basename($path));
            }

            // Decided from the locked handle, not a pre-fopen snapshot of
            // is_file(): fopen(..., 'a+b') itself can create the file, so
            // whether it was "new" is only trustworthy once this process
            // holds the lock. fstat()'s size — unlike ftell() on an
            // O_APPEND stream, which reports 0 unconditionally regardless of
            // the file's real size — reflects what is actually on disk, so
            // this also self-heals a zero-length file left by a lost race
            // between two first-appenders, a request that died between
            // fopen() and the old pre-lock guard write, or a bare `touch`.
            $stat = fstat($handle);
            $size = $stat === false ? 0 : $stat['size'];

            if ($size === 0) {
                // Compared against strlen(), not just !== false: both this
                // write and the line write below are well within PHP's
                // stream buffer, so a short write is not a realistic way for
                // fwrite() to fail here — but the check should mean what it
                // says rather than only catching the case that cannot happen.
                if (fwrite($handle, DataDir::GUARD) !== strlen(DataDir::GUARD)) {
                    throw new StorageException('Cannot write log guard: ' . basename($path));
                }
            } else {
                // Repair a torn tail before adding to it. A request that
                // died mid-fwrite on a previous append can leave the last
                // line without its trailing newline; writing straight onto
                // that (the previous behaviour) concatenates this call's
                // JSON onto the tail of the last one. That corrupts both,
                // silently loses this message, and — because the merge
                // collapses two log entries into one physical line —
                // undercounts every count() call afterwards, so the very
                // next append is issued the same seq again. Sealing the
                // tail first keeps every entry, valid or not, on its own
                // line, whatever comes after.
                fseek($handle, -1, SEEK_END);
                $lastByte = fgetc($handle);

                if ($lastByte !== "\n" && fwrite($handle, "\n") !== 1) {
                    throw new StorageException('Cannot repair log tail: ' . basename($path));
                }
            }

            if (fwrite($handle, $line . "\n") !== strlen($line) + 1) {
                throw new StorageException('Cannot write log line: ' . basename($path));
            }

            // fwrite() succeeding does not prove anything reached disk: the
            // guard and a typical line both fit PHP's stream buffer, so
            // fwrite() returns the full byte count regardless of whether the
            // underlying write will ever succeed. A full disk or quota
            // surfaces here, at flush — discarding this return (as an
            // earlier version of this fix did) let append() report success,
            // and JsonMessageLog::append() hand back a seq, for a message
            // that was never actually stored.
            if (fflush($handle) === false) {
                throw new StorageException('Cannot flush log: ' . basename($path));
            }

            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }

        // Unconditional: with the fstat()-based check above, a file this
        // process did not just create can still have been the one that
        // needed its guard (re)written, so "was it new" is no longer a
        // meaningful gate for this — every append reasserts 0600.
        @chmod($path, 0600);
    }

    /** @return Generator<int, array<string, mixed>> */
    public static function stream(string $path): Generator
    {
        if (!is_file($path)) {
            return;
        }

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new StorageException('Cannot read log: ' . basename($path));
        }

        try {
            // One line held back from the last iteration: whether a
            // malformed line is forgivable depends on whether anything
            // follows it, which is only knowable once the next fgets() has
            // been attempted (checking feof() immediately after reading the
            // last line is not reliable — PHP does not set the EOF flag
            // until a read is attempted past the end).
            $current = fgets($handle);

            while ($current !== false) {
                $next = fgets($handle);
                $isLastLine = $next === false;

                $line = trim($current);

                if ($line !== '' && strncmp($line, '<?php', 5) !== 0) {
                    try {
                        $row = json_decode($line, true, 512, JSON_THROW_ON_ERROR);

                        if (is_array($row)) {
                            yield $row;
                        }
                    } catch (JsonException $e) {
                        if (!$isLastLine) {
                            throw new StorageException('Corrupt log line in ' . basename($path), 0, $e);
                        }

                        // An unparseable line is only forgivable when it is
                        // the last one: that is exactly what a crashed
                        // append (a torn write with no trailing newline, or
                        // one flock() away from finishing) looks like, and
                        // the class docblock promises that costs at most the
                        // final line, not the session.
                    }
                }

                $current = $next;
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * Counts entries without decoding them: appends compute the next seq
     * number by calling this first, and a version that parsed every line's
     * JSON to do so turned each append into work proportional to every prior
     * message's content, not just its own — quadratic over a session of any
     * length. A malformed line still counts as one entry, matching stream()'s
     * tolerance for a crashed final line, so a corrupt tail cannot also make
     * appending (which needs a count to proceed) fail forever.
     */
    public static function count(string $path): int
    {
        if (!is_file($path)) {
            return 0;
        }

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new StorageException('Cannot read log: ' . basename($path));
        }

        $count = 0;

        try {
            while (($line = fgets($handle)) !== false) {
                $line = trim($line);

                if ($line !== '' && strncmp($line, '<?php', 5) !== 0) {
                    $count++;
                }
            }
        } finally {
            fclose($handle);
        }

        return $count;
    }
}
