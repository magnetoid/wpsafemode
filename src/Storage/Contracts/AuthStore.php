<?php

declare(strict_types=1);

namespace WPSafeMode\Storage\Contracts;

use WPSafeMode\Storage\Credential;

/**
 * Exactly one credential, or none. There is no user table: this is a
 * single-operator tool (spec 5.1).
 */
interface AuthStore
{
    public function isConfigured(): bool;

    /** Replaces any existing credential. */
    public function store(Credential $credential): void;

    /**
     * @throws StorageException if a credential was stored but the record
     *     cannot be read back intact. A backend must never treat that as
     *     "no credential yet" — doing so would make a corrupted store look
     *     identical to a fresh install.
     */
    public function current(): ?Credential;
}
