<?php

declare(strict_types=1);

namespace WPSafeMode\Storage;

if (!defined('WPSM_ENTRY')) {
    http_response_code(404);
    exit;
}

use WPSafeMode\Storage\Contracts\StorageException;

/**
 * The one definition of what a stored login-session record must look like,
 * shared by both backends so they cannot disagree about what is corrupt.
 */
final class AuthSessionRow
{
    private const INT_KEYS = ['user_id', 'created_at', 'last_seen_at', 'expires_at'];

    /**
     * Accepts canonical decimal strings as the integers they are: pdo_sqlite
     * returns INTEGER columns as strings before PHP 8.1. Anything else that
     * is not exactly an int is refused.
     *
     * @param array<mixed> $row
     * @return array{user_id:int,created_at:int,last_seen_at:int,expires_at:int,ip:string,ua_hash:string}
     * @throws StorageException
     */
    public static function validate(array $row): array
    {
        foreach (self::INT_KEYS as $key) {
            $value = $row[$key] ?? null;

            if (is_string($value) && preg_match('/^(0|-?[1-9]\d*)\z/', $value) === 1) {
                $value = (int) $value;
            }

            if (!is_int($value)) {
                throw new StorageException('Corrupt login session record: ' . $key . ' is not an integer.');
            }

            $row[$key] = $value;
        }

        if (!isset($row['ip']) || !is_string($row['ip']) || $row['ip'] === '') {
            throw new StorageException('Corrupt login session record: ip is not a non-empty string.');
        }

        if (!isset($row['ua_hash']) || !is_string($row['ua_hash']) || $row['ua_hash'] === '') {
            throw new StorageException('Corrupt login session record: ua_hash is not a non-empty string.');
        }

        return [
            'user_id'      => $row['user_id'],
            'created_at'   => $row['created_at'],
            'last_seen_at' => $row['last_seen_at'],
            'expires_at'   => $row['expires_at'],
            'ip'           => $row['ip'],
            'ua_hash'      => $row['ua_hash'],
        ];
    }
}
