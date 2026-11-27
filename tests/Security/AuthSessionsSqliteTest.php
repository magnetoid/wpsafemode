<?php

declare(strict_types=1);

namespace WPSafeMode\Tests\Security;

use PDO;
use WPSafeMode\Storage\StorageFactory;

/** Every AuthSessionsTest case again, on the SQLite backend. */
final class AuthSessionsSqliteTest extends AuthSessionsTest
{
    protected function backend(): string
    {
        return StorageFactory::BACKEND_SQLITE;
    }

    protected function sessionsFile(): string
    {
        return $this->dir . '/wpsm.sqlite';
    }

    /** Stored as a BLOB, so column affinity cannot turn the text back into an integer. */
    protected function corruptSession(string $tokenHash, string $field, string $value): void
    {
        $pdo = new PDO('sqlite:' . $this->sessionsFile(), null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $stmt = $pdo->prepare('UPDATE auth_sessions SET ' . $field . ' = CAST(:v AS BLOB) WHERE token_hash = :t');
        $stmt->execute([':v' => $value, ':t' => $tokenHash]);
        self::assertSame(1, $stmt->rowCount(), 'No stored session to corrupt.');
    }

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('Needs pdo_sqlite.');
        }

        parent::setUp();
    }
}
