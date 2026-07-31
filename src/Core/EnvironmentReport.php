<?php

declare(strict_types=1);

namespace WPSafeMode\Core;

final class EnvironmentReport
{
    public function __construct(
        public string $phpVersion,
        public bool $phpSatisfied,
        public bool $hasPdoSqlite,
        public bool $hasMysqli,
        public bool $hasOpenssl,
        public bool $hasSodium,
        public bool $hasExec,
        public int $freeDiskBytes,
        public ?string $wpRoot,
    ) {
    }

    /**
     * Browser-safe projection. Deliberately omits wpRoot: absolute server
     * paths must never reach the client (spec 5.7).
     *
     * @return array<string, bool|int|string>
     */
    public function toArray(): array
    {
        return [
            'php_version'     => $this->phpVersion,
            'php_satisfied'   => $this->phpSatisfied,
            'has_pdo_sqlite'  => $this->hasPdoSqlite,
            'has_mysqli'      => $this->hasMysqli,
            'has_openssl'     => $this->hasOpenssl,
            'has_sodium'      => $this->hasSodium,
            'has_exec'        => $this->hasExec,
            'free_disk_bytes' => $this->freeDiskBytes,
            'wp_detected'     => $this->wpRoot !== null,
        ];
    }
}
