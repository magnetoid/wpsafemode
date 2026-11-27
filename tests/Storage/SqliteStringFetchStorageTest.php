<?php

declare(strict_types=1);

namespace WPSafeMode\Tests\Storage;

use PDO;

/**
 * The whole SQLite conformance suite again, with every INTEGER column read
 * back as a digit string, the way pdo_sqlite returns them before PHP 8.1.
 * This host runs a later PHP, so without this the hydrators' string branch
 * (users, login sessions, audit rows, rate-limit state) never runs here.
 */
final class SqliteStringFetchStorageTest extends SqliteStorageTest
{
    protected function attributes(): array
    {
        return [PDO::ATTR_STRINGIFY_FETCHES => true];
    }

    public function testIntegerColumnsReallyComeBackAsStrings(): void
    {
        $pdo = $this->connection($this->dataDir)->pdo();
        $pdo->exec('CREATE TABLE probe (n INTEGER)');
        $pdo->exec('INSERT INTO probe VALUES (42)');

        self::assertSame('42', $pdo->query('SELECT n FROM probe')->fetchColumn());
    }
}
