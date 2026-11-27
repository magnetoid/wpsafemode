<?php

declare(strict_types=1);

namespace WPSafeMode\Storage\Json;

if (!defined('WPSM_ENTRY')) {
    http_response_code(404);
    exit;
}

use WPSafeMode\Security\GuardedJson;
use WPSafeMode\Storage\AuthSessionRow;
use WPSafeMode\Storage\Contracts\AuthSessionStore;
use WPSafeMode\Storage\Contracts\StorageException;

/**
 * data/auth_sessions.php holds {"<token hash>": row}. A missing file is an
 * empty store. Every modification holds the file's exclusive lock across its
 * read and its write, so concurrent logins cannot lose each other's sessions.
 */
final class JsonAuthSessionStore implements AuthSessionStore
{
    public function __construct(private string $path)
    {
    }

    public function put(string $tokenHash, array $row): void
    {
        $row = AuthSessionRow::validate($row);

        GuardedJson::withExclusiveLock($this->path, function () use ($tokenHash, $row): void {
            $all = $this->load();
            $all[$tokenHash] = $row;
            GuardedJson::write($this->path, $all);
        });
    }

    public function get(string $tokenHash): ?array
    {
        return $this->load()[$tokenHash] ?? null;
    }

    public function touch(string $tokenHash, int $now): void
    {
        GuardedJson::withExclusiveLock($this->path, function () use ($tokenHash, $now): void {
            $all = $this->load();

            if (!isset($all[$tokenHash])) {
                return;
            }

            $all[$tokenHash]['last_seen_at'] = $now;
            GuardedJson::write($this->path, $all);
        });
    }

    public function delete(string $tokenHash): void
    {
        GuardedJson::withExclusiveLock($this->path, function () use ($tokenHash): void {
            $all = $this->load();

            if (!isset($all[$tokenHash])) {
                return;
            }

            unset($all[$tokenHash]);
            GuardedJson::write($this->path, $all);
        });
    }

    public function purgeExpired(int $now): void
    {
        GuardedJson::withExclusiveLock($this->path, function () use ($now): void {
            $all = $this->load();
            $kept = array_filter($all, static fn (array $row): bool => $row['expires_at'] > $now);

            if (count($kept) !== count($all)) {
                GuardedJson::write($this->path, $kept);
            }
        });
    }

    /**
     * Validates the whole file as it parses it.
     *
     * @return array<string, array{user_id:int,created_at:int,last_seen_at:int,expires_at:int,ip:string,ua_hash:string}>
     * @throws StorageException
     */
    private function load(): array
    {
        $all = [];

        foreach (GuardedJson::read($this->path) as $key => $row) {
            if (!is_array($row)) {
                throw new StorageException('Corrupt login sessions file: a record is not an object.');
            }

            if ((string) $key === '') {
                throw new StorageException('Corrupt login sessions file: a record has an empty token hash.');
            }

            $all[(string) $key] = AuthSessionRow::validate($row);
        }

        return $all;
    }
}
