<?php

declare(strict_types=1);

namespace WPSafeMode\Tests\Storage;

use WPSafeMode\Security\GuardedJson;
use WPSafeMode\Storage\Contracts\AuthStore;
use WPSafeMode\Storage\Contracts\ConfigStore;
use WPSafeMode\Storage\Contracts\MessageLog;
use WPSafeMode\Storage\Contracts\SessionRepo;
use WPSafeMode\Storage\Contracts\StorageException;
use WPSafeMode\Storage\Json\JsonAuthStore;
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

    protected function makeAuthStore(string $dataDir): AuthStore
    {
        return new JsonAuthStore($dataDir . '/auth.php');
    }

    protected function assertNoSessionStorageLeftBehind(string $dataDir, string $id): void
    {
        self::assertDirectoryDoesNotExist($dataDir . '/sessions/' . $id);
    }

    /**
     * JSON-backend-specific, not part of the shared conformance suite: SQLite
     * has no equivalent state, since a NOT NULL column makes a row that
     * exists-but-lacks-a-password_hash impossible.
     *
     * GuardedJson::read() returns [] both for a file that was never written
     * and for one whose content parses but is not a credential (empty
     * payload, or valid JSON that isn't shaped like a row). If isConfigured()
     * or current() treated that [] the same way for both cases, a corrupted
     * — but PRESENT — auth.php would look identical to a fresh install, and
     * whatever first-run flow that unlocks (Task 8) would silently overwrite
     * it. is_file() is checked separately from the parsed result precisely so
     * "present but unreadable" and "never written" stay distinguishable.
     */
    public function testCorruptCredentialFileIsNotTreatedAsUnconfigured(): void
    {
        $path = $this->dataDir . '/auth.php';
        GuardedJson::write($path, ['unexpected' => 'shape']);

        $store = $this->makeAuthStore($this->dataDir);

        self::assertTrue(
            $store->isConfigured(),
            'A present-but-unparseable credential file must not read as a fresh install.'
        );

        $this->expectException(StorageException::class);
        $store->current();
    }

    /**
     * A row with password_hash present but one of the other three fields
     * missing still has isset($row['password_hash']) === true, so a check
     * that only looked at that one key would parse this "successfully" —
     * Credential::fromRow() would read the missing key as null and hand
     * back a Credential with a real hash and a bogus (0 or '') timestamp,
     * silently, instead of throwing. current() must require every field the
     * row is supposed to carry, not just the one isConfigured() cares about.
     */
    public function testCorruptCredentialFileMissingAnyFieldStillThrows(): void
    {
        $path = $this->dataDir . '/auth.php';
        GuardedJson::write($path, [
            'password_hash' => '$argon2id$hash',
            'hash_algo'     => 'argon2id',
            // created_at and password_changed_at deliberately omitted.
        ]);

        $store = $this->makeAuthStore($this->dataDir);

        self::assertTrue($store->isConfigured());

        $this->expectException(StorageException::class);
        $store->current();
    }
}
