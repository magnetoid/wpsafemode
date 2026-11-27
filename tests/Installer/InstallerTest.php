<?php

declare(strict_types=1);

namespace WPSafeMode\Tests\Installer;

use PHPUnit\Framework\TestCase;
use WPSafeMode\Core\Environment;
use WPSafeMode\Installer\Installer;
use WPSafeMode\Security\KeyVault;
use WPSafeMode\Security\PasswordHasher;
use WPSafeMode\Security\Role;
use WPSafeMode\Storage\Contracts\StorageException;
use WPSafeMode\Storage\StorageFactory;

final class InstallerTest extends TestCase
{
    private const UNREACHABLE = 'http://127.0.0.1:1/wpsm';
    private const STRONG = 'correct horse battery staple';

    private string $dir;
    private Installer $installer;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/wpsm-install-' . bin2hex(random_bytes(6));
        mkdir($this->dir, 0700, true);

        $this->installer = $this->makeInstaller($this->dir);
    }

    protected function tearDown(): void
    {
        $this->removeRecursively($this->dir);
    }

    private function makeInstaller(string $envDir): Installer
    {
        return new Installer(
            StorageFactory::create($this->dir, 'json'),
            new KeyVault($this->dir),
            new PasswordHasher(),
            $this->dir,
            Environment::probe($envDir)
        );
    }

    private function removeRecursively(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $full = $path . '/' . $entry;
            is_dir($full) ? $this->removeRecursively($full) : unlink($full);
        }
        rmdir($path);
    }

    public function testAFreshCopyIsNotInstalled(): void
    {
        $state = $this->installer->state();

        self::assertFalse($state->installed);
        self::assertFalse($state->hasOwner);
        self::assertFalse($state->hasApiKey);
    }

    public function testTheInstallTokenIsWrittenToTheDataDirectoryAndVerifies(): void
    {
        $token = $this->installer->issueToken();

        self::assertNotSame('', $token);
        self::assertFileExists($this->dir . '/install-token.php');
        self::assertTrue($this->installer->verifyToken($token));
        self::assertFalse($this->installer->verifyToken('wrong-token'));
    }

    public function testTheTokenIsStableAcrossCalls(): void
    {
        // Re-issuing on every page load would make the printed token useless.
        self::assertSame($this->installer->issueToken(), $this->installer->issueToken());
    }

    public function testCreatingTheOwnerRequiresAStrongPassword(): void
    {
        $this->expectException(StorageException::class);
        $this->installer->createOwner('owner', 'short');
    }

    public function testCreatingTheOwnerMakesTheCopyHaveOne(): void
    {
        $this->installer->createOwner('owner', self::STRONG);

        self::assertTrue($this->installer->state()->hasOwner);
    }

    public function testTheApiKeyIsRefusedUntilAnOwnerExists(): void
    {
        $this->expectException(StorageException::class);
        $this->installer->storeApiKey('sk-ant-key', false, self::UNREACHABLE);
    }

    public function testTheApiKeyIsStoredEncryptedAndNeverInPlaintext(): void
    {
        $this->installer->createOwner('owner', self::STRONG);
        $this->installer->storeApiKey('sk-ant-api03-secret', true, self::UNREACHABLE);

        $onDisk = (string) file_get_contents($this->dir . '/config.php');

        self::assertStringNotContainsString('sk-ant-api03-secret', $onDisk);
        self::assertTrue($this->installer->state()->hasApiKey);
    }

    public function testTheStoredApiKeyDecryptsBack(): void
    {
        $this->installer->createOwner('owner', self::STRONG);
        $this->installer->storeApiKey('sk-ant-api03-secret', true, self::UNREACHABLE);

        $stored = StorageFactory::create($this->dir, 'json')->config()->get('api_key');

        self::assertNotNull($stored);
        self::assertSame('sk-ant-api03-secret', (new KeyVault($this->dir))->decrypt($stored));
    }

    public function testAnUndecidableSelfTestBlocksTheApiKeyUntilAcknowledged(): void
    {
        $this->installer->createOwner('owner', self::STRONG);

        try {
            $this->installer->storeApiKey('sk-ant-key', false, self::UNREACHABLE);
            self::fail('The API key was stored although the self-test could not decide');
        } catch (StorageException $e) {
            self::assertFalse($this->installer->state()->hasApiKey);
        }

        // Acknowledged, the user may proceed: it is their site and their risk.
        $this->installer->storeApiKey('sk-ant-key', true, self::UNREACHABLE);
        self::assertTrue($this->installer->state()->hasApiKey);
    }

    public function testAnExposedDataDirectoryBlocksTheApiKeyUntilAcknowledged(): void
    {
        $server = getenv('WPSM_SELFTEST_URL');

        if ($server === false || $server === '') {
            self::markTestSkipped('Set WPSM_SELFTEST_URL to a base URL serving the data dir.');
        }

        $this->installer->createOwner('owner', self::STRONG);

        try {
            $this->installer->storeApiKey('sk-ant-key', false, $server);
            self::fail('The API key was stored while the data directory was web-readable');
        } catch (StorageException $e) {
            self::assertStringContainsString('readable over the web', $e->getMessage());
        }

        $this->installer->storeApiKey('sk-ant-key', true, $server);
        self::assertTrue($this->installer->state()->hasApiKey);
    }

    public function testFinalizeMarksTheInstallCompleteAndRecordsIt(): void
    {
        $this->installer->createOwner('owner', self::STRONG);
        $this->installer->storeApiKey('sk-ant-key', true, self::UNREACHABLE);
        $this->installer->finalize('1.2.3.4');

        self::assertTrue($this->installer->state()->installed);
        self::assertNotSame(
            [],
            array_filter(
                StorageFactory::create($this->dir, 'json')->auditLog()->recent(),
                static fn ($e): bool => $e->event === 'install'
            )
        );
    }

    public function testFinalizeIsRefusedWithoutAnOwner(): void
    {
        $this->expectException(StorageException::class);
        $this->installer->finalize('1.2.3.4');
    }

    public function testTheInstallTokenFileIsRemovedOnceInstalled(): void
    {
        $this->installer->issueToken();
        $this->installer->createOwner('owner', self::STRONG);
        $this->installer->storeApiKey('sk-ant-key', true, self::UNREACHABLE);
        $this->installer->finalize('1.2.3.4');

        // It has done its job, and a token lying around is one more thing to leak.
        self::assertFileDoesNotExist($this->dir . '/install-token.php');
    }

    public function testTheOwnerIsCreatedWithTheOwnerRole(): void
    {
        $user = $this->installer->createOwner('Marko', self::STRONG);

        self::assertSame(Role::OWNER, $user->role);
        self::assertSame('marko', $user->login);
        self::assertCount(1, StorageFactory::create($this->dir, 'json')->users()->all());
    }

    public function testASecondOwnerCannotBeCreated(): void
    {
        $this->installer->createOwner('owner', self::STRONG);

        try {
            $this->installer->createOwner('second', self::STRONG);
            self::fail('A second owner was created');
        } catch (StorageException $e) {
            self::assertSame(Installer::ERR_OWNER_EXISTS, $e->getCode());
        }

        self::assertCount(1, StorageFactory::create($this->dir, 'json')->users()->all());
    }

    public function testAnInvalidLoginIsRefused(): void
    {
        try {
            $this->installer->createOwner('a b', self::STRONG);
            self::fail('An invalid login was accepted');
        } catch (StorageException $e) {
            self::assertSame(Installer::ERR_BAD_LOGIN, $e->getCode());
        }

        self::assertFalse($this->installer->state()->hasOwner);
    }

    public function testInstallCompletesWithNoWordPressBesideIt(): void
    {
        $empty = sys_get_temp_dir() . '/wpsm-nowp-' . bin2hex(random_bytes(6));
        mkdir($empty, 0700, true);

        try {
            $installer = $this->makeInstaller($empty);
            self::assertFalse($installer->state()->hasWordPress);

            $token = $installer->issueToken();
            self::assertTrue($installer->verifyToken($token));
            $installer->createOwner('owner', self::STRONG);
            $installer->storeApiKey('sk-ant-key', true, self::UNREACHABLE);
            $installer->finalize('1.2.3.4');

            self::assertSame('done', $installer->state()->step);
        } finally {
            rmdir($empty);
        }
    }

    public function testFinalizeRecordsTheEmergencyPosture(): void
    {
        $this->installer->createOwner('owner', self::STRONG);
        $this->installer->finalize('1.2.3.4');

        self::assertSame('emergency', StorageFactory::create($this->dir, 'json')->config()->get('posture'));
    }

    public function testTheInstallEventIsAttributedToTheOwner(): void
    {
        $this->installer->createOwner('owner', self::STRONG);
        $this->installer->finalize('1.2.3.4');

        $recent = StorageFactory::create($this->dir, 'json')->auditLog()->recent(1);

        self::assertSame('user:1', $recent[0]->actor);
    }
}
