<?php

declare(strict_types=1);

namespace WPSafeMode\Storage;

use WPSafeMode\Storage\Contracts\ConfigStore;
use WPSafeMode\Storage\Contracts\MessageLog;
use WPSafeMode\Storage\Contracts\SessionRepo;
use WPSafeMode\Storage\Contracts\Storage;
use WPSafeMode\Storage\Json\JsonConfigStore;
use WPSafeMode\Storage\Json\JsonMessageLog;
use WPSafeMode\Storage\Json\JsonMigrator;
use WPSafeMode\Storage\Json\JsonSessionRepo;

final class JsonStorage implements Storage
{
    private bool $migrated = false;

    public function __construct(private string $dataDir)
    {
    }

    /** Runs the migrations once per instance, before the first store is handed out. */
    private function migrated(): string
    {
        if (!$this->migrated) {
            JsonMigrator::migrate($this->dataDir);
            $this->migrated = true;
        }

        return $this->dataDir;
    }

    public function config(): ConfigStore
    {
        return new JsonConfigStore($this->migrated() . '/config.php');
    }

    public function sessions(): SessionRepo
    {
        return new JsonSessionRepo($this->migrated() . '/sessions');
    }

    public function messages(): MessageLog
    {
        return new JsonMessageLog($this->migrated() . '/sessions');
    }

    public function backend(): string
    {
        return 'json';
    }
}
