<?php

declare(strict_types=1);

namespace WPSafeMode\Storage\Json;

if (!defined('WPSM_ENTRY')) {
    http_response_code(404);
    exit;
}

use WPSafeMode\Storage\Contracts\ConfigStore;

final class JsonConfigStore implements ConfigStore
{
    private JsonFile $file;

    public function __construct(string $path)
    {
        $this->file = new JsonFile($path);
    }

    public function get(string $key, ?string $default = null): ?string
    {
        $data = $this->file->read();

        // array_key_exists, not isset() — isset() reports false for a stored
        // empty string and would conflate it with an absent key.
        return array_key_exists($key, $data) ? (string) $data[$key] : $default;
    }

    public function set(string $key, string $value): void
    {
        $data = $this->file->read();
        $data[$key] = $value;
        $this->file->write($data);
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->file->read());
    }

    public function delete(string $key): void
    {
        $data = $this->file->read();

        if (!array_key_exists($key, $data)) {
            return;
        }

        unset($data[$key]);
        $this->file->write($data);
    }

    public function all(): array
    {
        $data = $this->file->read();
        ksort($data);

        $out = [];
        foreach ($data as $key => $value) {
            $out[(string) $key] = (string) $value;
        }

        return $out;
    }
}
