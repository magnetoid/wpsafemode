<?php

declare(strict_types=1);

namespace WPSafeMode\Storage\Json;

use WPSafeMode\Security\GuardedJson;

/**
 * Read-modify-write for a small mutable JSON file.
 *
 * Writes go to a temporary file and are moved into place with rename(), which
 * is atomic within a filesystem. A crashed request therefore leaves either the
 * previous complete file or the new complete file, never a truncated one.
 */
final class JsonFile
{
    public function __construct(private string $path)
    {
    }

    public function path(): string
    {
        return $this->path;
    }

    /** @return array<string, mixed> */
    public function read(): array
    {
        return GuardedJson::read($this->path);
    }

    /** @param array<string, mixed> $data */
    public function write(array $data): void
    {
        GuardedJson::write($this->path, $data);
    }
}
