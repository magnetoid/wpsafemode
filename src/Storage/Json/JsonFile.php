<?php

declare(strict_types=1);

namespace WPSafeMode\Storage\Json;

use JsonException;
use WPSafeMode\Storage\Contracts\StorageException;

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
        if (!is_file($this->path)) {
            return [];
        }

        $raw = file_get_contents($this->path);

        if ($raw === false || $raw === '') {
            return [];
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new StorageException('Corrupt JSON store: ' . $this->path, 0, $e);
        }

        return is_array($decoded) ? $decoded : [];
    }

    /** @param array<string, mixed> $data */
    public function write(array $data): void
    {
        $dir = dirname($this->path);

        if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new StorageException('Cannot create data directory: ' . $dir);
        }

        try {
            $json = json_encode(
                $data,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            );
        } catch (JsonException $e) {
            throw new StorageException('Cannot encode store: ' . $this->path, 0, $e);
        }

        $tmp = $this->path . '.tmp.' . bin2hex(random_bytes(4));

        if (file_put_contents($tmp, $json, LOCK_EX) === false) {
            throw new StorageException('Cannot write store: ' . $tmp);
        }

        @chmod($tmp, 0600);

        if (!rename($tmp, $this->path)) {
            @unlink($tmp);
            throw new StorageException('Cannot commit store: ' . $this->path);
        }
    }
}
