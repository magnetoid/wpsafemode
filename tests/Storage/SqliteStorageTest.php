<?php

declare(strict_types=1);

namespace WPSafeMode\Tests\Storage;

use WPSafeMode\Storage\Contracts\AuthStore;
use WPSafeMode\Storage\Contracts\ConfigStore;
use WPSafeMode\Storage\Contracts\MessageLog;
use WPSafeMode\Storage\Contracts\SessionRepo;
use WPSafeMode\Storage\Sqlite\SqliteAuthStore;
use WPSafeMode\Storage\Sqlite\SqliteConfigStore;
use WPSafeMode\Storage\Sqlite\SqliteConnection;
use WPSafeMode\Storage\Sqlite\SqliteMessageLog;
use WPSafeMode\Storage\Sqlite\SqliteSessionRepo;

final class SqliteStorageTest extends StorageConformanceTest
{
    protected function makeConfigStore(string $dataDir): ConfigStore
    {
        return new SqliteConfigStore(new SqliteConnection($dataDir . '/wpsm.sqlite'));
    }

    protected function makeSessionRepo(string $dataDir): SessionRepo
    {
        return new SqliteSessionRepo(new SqliteConnection($dataDir . '/wpsm.sqlite'));
    }

    protected function makeMessageLog(string $dataDir): MessageLog
    {
        return new SqliteMessageLog(new SqliteConnection($dataDir . '/wpsm.sqlite'));
    }

    protected function makeAuthStore(string $dataDir): AuthStore
    {
        return new SqliteAuthStore(new SqliteConnection($dataDir . '/wpsm.sqlite'));
    }

    protected function assertNoSessionStorageLeftBehind(string $dataDir, string $id): void
    {
        // No-op: SQLite storage has no per-session directory. delete()
        // removing the row is already covered by find() returning null.
    }
}
