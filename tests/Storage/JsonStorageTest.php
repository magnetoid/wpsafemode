<?php

declare(strict_types=1);

namespace WPSafeMode\Tests\Storage;

use WPSafeMode\Storage\Contracts\ConfigStore;
use WPSafeMode\Storage\Contracts\MessageLog;
use WPSafeMode\Storage\Contracts\SessionRepo;
use WPSafeMode\Storage\Json\JsonConfigStore;
use WPSafeMode\Storage\Json\JsonMessageLog;
use WPSafeMode\Storage\Json\JsonSessionRepo;

final class JsonStorageTest extends StorageConformanceTest
{
    protected function makeConfigStore(string $dataDir): ConfigStore
    {
        return new JsonConfigStore($dataDir . '/config.php');
    }

    protected function makeSessionRepo(string $dataDir): SessionRepo
    {
        return new JsonSessionRepo($dataDir . '/sessions');
    }

    protected function makeMessageLog(string $dataDir): MessageLog
    {
        return new JsonMessageLog($dataDir . '/sessions');
    }

    protected function assertNoSessionStorageLeftBehind(string $dataDir, string $id): void
    {
        self::assertDirectoryDoesNotExist($dataDir . '/sessions/' . $id);
    }
}
