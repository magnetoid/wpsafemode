<?php

declare(strict_types=1);

namespace WPSafeMode\Storage;

use WPSafeMode\Storage\Contracts\ConfigStore;
use WPSafeMode\Storage\Contracts\MessageLog;
use WPSafeMode\Storage\Contracts\RateLimiter;
use WPSafeMode\Storage\Contracts\SessionRepo;
use WPSafeMode\Storage\Contracts\Storage;
use WPSafeMode\Storage\Contracts\UserStore;
use WPSafeMode\Storage\Sqlite\SqliteConfigStore;
use WPSafeMode\Storage\Sqlite\SqliteConnection;
use WPSafeMode\Storage\Sqlite\SqliteMessageLog;
use WPSafeMode\Storage\Sqlite\SqliteRateLimiter;
use WPSafeMode\Storage\Sqlite\SqliteSessionRepo;
use WPSafeMode\Storage\Sqlite\SqliteUserStore;

final class SqliteStorage implements Storage
{
    private SqliteConnection $connection;

    public function __construct(string $dataDir, string $filename = 'wpsm.sqlite')
    {
        $this->connection = new SqliteConnection($dataDir . '/' . $filename);
    }

    public function config(): ConfigStore
    {
        return new SqliteConfigStore($this->connection);
    }

    public function sessions(): SessionRepo
    {
        return new SqliteSessionRepo($this->connection);
    }

    public function messages(): MessageLog
    {
        return new SqliteMessageLog($this->connection);
    }

    public function users(): UserStore
    {
        return new SqliteUserStore($this->connection);
    }

    public function rateLimiter(): RateLimiter
    {
        return new SqliteRateLimiter($this->connection);
    }

    public function backend(): string
    {
        return 'sqlite';
    }
}
