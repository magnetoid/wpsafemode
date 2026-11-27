<?php

declare(strict_types=1);

namespace WPSafeMode\Storage\Contracts;

if (!defined('WPSM_ENTRY')) {
    http_response_code(404);
    exit;
}

use WPSafeMode\Storage\Session;

interface SessionRepo
{
    /** @throws StorageException if the id already exists. */
    public function create(Session $session): void;

    public function find(string $id): ?Session;

    /** @throws StorageException if the id does not exist. */
    public function update(Session $session): void;

    /** @return Session[] newest first */
    public function list(int $limit = 50): array;

    public function delete(string $id): void;
}
