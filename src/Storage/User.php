<?php

declare(strict_types=1);

namespace WPSafeMode\Storage;

if (!defined('WPSM_ENTRY')) {
    http_response_code(404);
    exit;
}

use WPSafeMode\Security\Role;
use WPSafeMode\Storage\Contracts\StorageException;

final class User
{
    public const LOGIN_PATTERN = '/^[a-z0-9][a-z0-9._@-]{2,63}\z/';

    public function __construct(
        public int $id,
        public string $login,
        public string $role,
        public Credential $credential,
        public ?int $disabledAt = null,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->disabledAt === null;
    }

    public static function normalizeLogin(string $login): string
    {
        return strtolower(trim($login));
    }

    public static function isValidLogin(string $normalized): bool
    {
        return preg_match(self::LOGIN_PATTERN, $normalized) === 1;
    }

    /**
     * Hydrates a stored row, refusing anything that is not exactly what a
     * store would have written. Credential::fromRow() casts leniently, so a
     * row with a missing or mistyped field would otherwise become a user with
     * silently wrong metadata; and a store that "repairs" a corrupt record
     * could hand out a role or an enabled state nobody granted.
     *
     * @param array<mixed> $row
     * @throws StorageException on any missing, mistyped or out-of-set field
     */
    public static function fromRow(array $row): self
    {
        foreach (['id', 'created_at', 'password_changed_at'] as $key) {
            if (!isset($row[$key]) || !is_int($row[$key])) {
                throw new StorageException('Corrupt user record: ' . $key . ' is not an integer.');
            }
        }

        if (!array_key_exists('disabled_at', $row) || ($row['disabled_at'] !== null && !is_int($row['disabled_at']))) {
            throw new StorageException('Corrupt user record: disabled_at is neither an integer nor null.');
        }

        foreach (['login', 'role', 'password_hash', 'hash_algo'] as $key) {
            if (!isset($row[$key]) || !is_string($row[$key]) || $row[$key] === '') {
                throw new StorageException('Corrupt user record: ' . $key . ' is not a non-empty string.');
            }
        }

        if (!Role::isValid($row['role'])) {
            throw new StorageException('Corrupt user record: unknown role.');
        }

        if ($row['login'] !== self::normalizeLogin($row['login']) || !self::isValidLogin($row['login'])) {
            throw new StorageException('Corrupt user record: invalid login.');
        }

        return new self(
            $row['id'],
            $row['login'],
            $row['role'],
            Credential::fromRow($row),
            $row['disabled_at'],
        );
    }
}
