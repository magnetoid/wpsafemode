<?php

declare(strict_types=1);

namespace WPSafeMode\Storage\Json;

use WPSafeMode\Security\DataDir;
use WPSafeMode\Security\GuardedJson;
use WPSafeMode\Storage\Contracts\StorageException;

/**
 * Versioned schema steps for the JSON backend. The whole run holds one
 * exclusive lock, so concurrent requests migrate one at a time. A JSON write
 * cannot be rolled back, so every step must be safe to run twice: the version
 * is recorded only after its step returns, and a crash in between simply
 * re-runs the step.
 */
final class JsonMigrator
{
    /**
     * Step N produces schema version N. Later milestones append steps here.
     * A step must use only the data directory it is given, never JsonStorage:
     * the migration lock is not re-entrant.
     *
     * @return array<int, callable(string): void>
     */
    public static function steps(): array
    {
        return [
            // Files are created on first write; nothing to do.
            1 => static function (string $dataDir): void {
            },

            // The single credential is dropped, not migrated: nothing was
            // released and no installer existed to create one. Safe to run twice.
            2 => static function (string $dataDir): void {
                $auth = $dataDir . '/auth.php';

                if (is_file($auth) && !@unlink($auth)) {
                    throw new StorageException('Cannot remove the old credential file.');
                }
            },

            // data/auth_sessions.php is created on first write; nothing to do.
            3 => static function (string $dataDir): void {
            },
        ];
    }

    /**
     * @param array<int, callable(string): void>|null $steps Test seam; null means steps().
     */
    public static function migrate(string $dataDir, ?array $steps = null): void
    {
        $steps ??= self::steps();

        if ($steps === []) {
            return;
        }

        ksort($steps);
        $target = (int) max(array_keys($steps));

        DataDir::ensure($dataDir);

        $current = self::currentVersion($dataDir);
        self::refuseNewer($current, $target);

        if ($current >= $target) {
            return;
        }

        GuardedJson::withExclusiveLock($dataDir . '/migrate', static function () use ($dataDir, $steps, $target): void {
            // Re-read under the lock: a request that waited here may find the
            // work already done.
            $current = self::currentVersion($dataDir);
            self::refuseNewer($current, $target);

            foreach ($steps as $version => $step) {
                if ($version <= $current) {
                    continue;
                }

                try {
                    $step($dataDir);
                } catch (\Throwable $e) {
                    throw new StorageException(
                        sprintf('Migration to schema version %d failed: %s', $version, $e->getMessage()),
                        0,
                        $e
                    );
                }

                GuardedJson::write($dataDir . '/meta.php', ['schema_version' => $version]);
            }
        });
    }

    public static function currentVersion(string $dataDir): int
    {
        $meta = GuardedJson::read($dataDir . '/meta.php');

        if (!array_key_exists('schema_version', $meta)) {
            return 0;
        }

        if (!is_int($meta['schema_version']) || $meta['schema_version'] < 0) {
            throw new StorageException('The stored schema version is not an integer.');
        }

        return $meta['schema_version'];
    }

    private static function refuseNewer(int $stored, int $target): void
    {
        if ($stored > $target) {
            throw new StorageException(sprintf(
                'This data directory was written by a newer version of WP Safe Mode '
                . '(schema %d; this copy understands up to %d). Upload the newer version again.',
                $stored,
                $target
            ));
        }
    }
}
