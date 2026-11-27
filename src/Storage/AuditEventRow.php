<?php

declare(strict_types=1);

namespace WPSafeMode\Storage;

use WPSafeMode\Storage\Contracts\StorageException;

/**
 * The one definition of what a stored audit record must look like, shared by
 * both backends so they cannot disagree about what is corrupt.
 */
final class AuditEventRow
{
    /**
     * Accepts a canonical decimal string for `at`: pdo_sqlite returns INTEGER
     * columns as strings before PHP 8.1. SQLite hands meta over as JSON text
     * ($meta is the decoded value either way, so callers decode first).
     *
     * @param array<mixed> $row
     * @param mixed $meta already-decoded meta
     * @throws StorageException
     */
    public static function hydrate(array $row, $meta): AuditEvent
    {
        $at = $row['at'] ?? null;

        if (is_string($at) && preg_match('/^(0|-?[1-9]\d*)$/', $at) === 1) {
            $at = (int) $at;
        }

        if (!is_int($at)) {
            throw new StorageException('Corrupt audit record: at is not an integer.');
        }

        foreach (['actor', 'event'] as $key) {
            if (!isset($row[$key]) || !is_string($row[$key]) || $row[$key] === '') {
                throw new StorageException('Corrupt audit record: ' . $key . ' is not a non-empty string.');
            }
        }

        foreach (['subject', 'ip'] as $key) {
            if (isset($row[$key]) && !is_string($row[$key])) {
                throw new StorageException('Corrupt audit record: ' . $key . ' is not a string or null.');
            }
        }

        if (!is_array($meta)) {
            throw new StorageException('Corrupt audit record: meta is not an object.');
        }

        /** @var array<string, mixed> $meta */
        return new AuditEvent($at, $row['actor'], $row['event'], $row['subject'] ?? null, $meta, $row['ip'] ?? null);
    }
}
