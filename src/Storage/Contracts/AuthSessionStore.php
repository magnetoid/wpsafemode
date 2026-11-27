<?php

declare(strict_types=1);

namespace WPSafeMode\Storage\Contracts;

if (!defined('WPSM_ENTRY')) {
    http_response_code(404);
    exit;
}

/**
 * Login session records, keyed by the SHA-256 of the token. The raw token
 * exists only in the user's cookie: a stolen data directory must not yield
 * usable sessions.
 *
 * A stored record that cannot be read back intact throws StorageException;
 * it is never read as "no such session" and never repaired.
 */
interface AuthSessionStore
{
    /** @param array{user_id:int,created_at:int,last_seen_at:int,expires_at:int,ip:string,ua_hash:string} $row */
    public function put(string $tokenHash, array $row): void;

    /**
     * @return array{user_id:int,created_at:int,last_seen_at:int,expires_at:int,ip:string,ua_hash:string}|null
     * @throws StorageException if the stored record is malformed
     */
    public function get(string $tokenHash): ?array;

    public function touch(string $tokenHash, int $now): void;

    public function delete(string $tokenHash): void;

    /** Removes every record whose expires_at has passed. */
    public function purgeExpired(int $now): void;
}
