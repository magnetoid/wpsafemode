<?php

declare(strict_types=1);

namespace WPSafeMode\Tests\Storage;

use WPSafeMode\Storage\Contracts\ConfigStore;
use WPSafeMode\Storage\Json\JsonConfigStore;

final class JsonStorageTest extends StorageConformanceTest
{
    protected function makeConfigStore(string $dataDir): ConfigStore
    {
        return new JsonConfigStore($dataDir . '/config.json');
    }
}
