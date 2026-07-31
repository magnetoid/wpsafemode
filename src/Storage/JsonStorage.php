<?php

declare(strict_types=1);

namespace WPSafeMode\Storage;

use WPSafeMode\Storage\Contracts\ConfigStore;
use WPSafeMode\Storage\Contracts\MessageLog;
use WPSafeMode\Storage\Contracts\SessionRepo;
use WPSafeMode\Storage\Contracts\Storage;
use WPSafeMode\Storage\Json\JsonConfigStore;
use WPSafeMode\Storage\Json\JsonMessageLog;
use WPSafeMode\Storage\Json\JsonSessionRepo;

final class JsonStorage implements Storage
{
    public function __construct(private string $dataDir)
    {
    }

    public function config(): ConfigStore
    {
        return new JsonConfigStore($this->dataDir . '/config.json');
    }

    public function sessions(): SessionRepo
    {
        return new JsonSessionRepo($this->dataDir . '/sessions');
    }

    public function messages(): MessageLog
    {
        return new JsonMessageLog($this->dataDir . '/sessions');
    }

    public function backend(): string
    {
        return 'json';
    }
}
