<?php

declare(strict_types=1);

namespace WPSafeMode\Tests\Storage;

use WPSafeMode\Storage\Contracts\ConfigStore;
use WPSafeMode\Storage\Contracts\SessionRepo;
use WPSafeMode\Storage\Json\JsonConfigStore;
use WPSafeMode\Storage\Json\JsonSessionRepo;

final class JsonStorageTest extends StorageConformanceTest
{
    protected function makeConfigStore(string $dataDir): ConfigStore
    {
        return new JsonConfigStore($dataDir . '/config.json');
    }

    protected function makeSessionRepo(string $dataDir): SessionRepo
    {
        return new JsonSessionRepo($dataDir . '/sessions');
    }
}
