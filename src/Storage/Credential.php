<?php

declare(strict_types=1);

namespace WPSafeMode\Storage;

if (!defined('WPSM_ENTRY')) {
    http_response_code(404);
    exit;
}

/**
 * The single operator credential. Records which algorithm produced the hash so
 * a later login can transparently rehash when the host gains libargon2
 * (spec 5.1).
 */
final class Credential
{
    public function __construct(
        public string $hash,
        public string $algo,
        public int $createdAt,
        public int $changedAt,
    ) {
    }

    /** @return array<string, int|string> */
    public function toRow(): array
    {
        return [
            'password_hash'       => $this->hash,
            'hash_algo'           => $this->algo,
            'created_at'          => $this->createdAt,
            'password_changed_at' => $this->changedAt,
        ];
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            hash:      (string) $row['password_hash'],
            algo:      (string) $row['hash_algo'],
            createdAt: (int) $row['created_at'],
            changedAt: (int) $row['password_changed_at'],
        );
    }
}
