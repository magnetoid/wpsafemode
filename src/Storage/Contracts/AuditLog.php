<?php

declare(strict_types=1);

namespace WPSafeMode\Storage\Contracts;

use WPSafeMode\Storage\AuditEvent;

/**
 * Append-only, like MessageLog. Non-agent events only: install, login, config
 * change, restore, uninstall (spec 7.1).
 */
interface AuditLog
{
    public function record(AuditEvent $event): void;

    /**
     * Newest first; events sharing a timestamp come back in reverse insertion
     * order. A limit below 1 returns nothing.
     *
     * @return AuditEvent[]
     * @throws StorageException when a stored row is malformed
     */
    public function recent(int $limit = 100): array;
}
