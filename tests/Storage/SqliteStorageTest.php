<?php

declare(strict_types=1);

namespace WPSafeMode\Tests\Storage;

use WPSafeMode\Storage\Contracts\ConfigStore;
use WPSafeMode\Storage\Sqlite\SqliteConfigStore;
use WPSafeMode\Storage\Sqlite\SqliteConnection;

final class SqliteStorageTest extends StorageConformanceTest
{
    protected function makeConfigStore(string $dataDir): ConfigStore
    {
        return new SqliteConfigStore(new SqliteConnection($dataDir . '/wpsm.sqlite'));
    }
}
