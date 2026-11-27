<?php

declare(strict_types=1);

namespace WPSafeMode\Tests\Installer;

use PHPUnit\Framework\TestCase;
use WPSafeMode\Core\Environment;
use WPSafeMode\Installer\Installer;
use WPSafeMode\Security\KeyVault;
use WPSafeMode\Security\PasswordHasher;
use WPSafeMode\Security\Role;
use WPSafeMode\Storage\Contracts\Storage;
use WPSafeMode\Storage\Contracts\StorageException;
use WPSafeMode\Storage\StorageFactory;
use WPSafeMode\Tests\Support\FailingStorage;
use WPSafeMode\Tests\Support\FakeFetcher;

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

    /** The self-test never leaves the process: every request fails, so it is inconclusive. */
    private function makeInstaller(string $envDir, ?\WPSafeMode\Security\SelfTestFetcher $fetcher = null): Installer
    {
        return new Installer(
            StorageFactory::create($this->dir, 'json'),
            new KeyVault($this->dir),
            new PasswordHasher(),
            $this->dir,
            Environment::probe($envDir),
            $fetcher ?? FakeFetcher::unreachable()
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
        // Acknowledged, so the self-test cannot be the reason for the refusal.
        try {
            $this->installer->storeApiKey('sk-ant-key', true, self::UNREACHABLE);
            self::fail('The API key was stored with no owner');
        } catch (StorageException $e) {
            self::assertStringContainsString('owner', $e->getMessage());
            self::assertFalse($this->installer->state()->hasApiKey);
        }
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

        // The real transport, against the real server.
        $installer = new Installer(
            StorageFactory::create($this->dir, 'json'),
            new KeyVault($this->dir),
            new PasswordHasher(),
            $this->dir,
            Environment::probe($this->dir)
        );
        $installer->createOwner('owner', self::STRONG);

        try {
            $installer->storeApiKey('sk-ant-key', false, $server);
            self::fail('The API key was stored while the data directory was web-readable');
        } catch (StorageException $e) {
            self::assertStringContainsString('readable over the web', $e->getMessage());
        }

        $installer->storeApiKey('sk-ant-key', true, $server);
        self::assertTrue($installer->state()->hasApiKey);
    }

    public function testAServedDataDirectoryBlocksTheKeyUntilAcknowledgedAndIsAudited(): void
    {
        $installer = $this->makeInstaller($this->dir, FakeFetcher::serving($this->dir));
        $installer->createOwner('owner', self::STRONG);

        try {
            $installer->storeApiKey('sk-ant-key', false, self::UNREACHABLE);
            self::fail('The API key was stored while the data directory was web-readable');
        } catch (StorageException $e) {
            self::assertSame(Installer::ERR_EXPOSED, $e->getCode());
            self::assertStringContainsString('readable over the web', $e->getMessage());
        }

        $installer->storeApiKey('sk-ant-key', true, self::UNREACHABLE);
        self::assertTrue($installer->state()->hasApiKey);

        $row = StorageFactory::create($this->dir, 'json')->auditLog()->recent(1)[0];
        self::assertSame('reachable', $row->meta['outcome'] ?? null);
        self::assertTrue($row->meta['acknowledged_risk'] ?? null);
    }

    public function testARefusedDataDirectoryLetsTheKeyInWithoutAnAcknowledgement(): void
    {
        $installer = $this->makeInstaller($this->dir, FakeFetcher::refusing());
        $installer->createOwner('owner', self::STRONG);

        $installer->storeApiKey('sk-ant-key', false, self::UNREACHABLE);

        self::assertTrue($installer->state()->hasApiKey);
        $row = StorageFactory::create($this->dir, 'json')->auditLog()->recent(1)[0];
        self::assertSame('not_reachable', $row->meta['outcome'] ?? null);
        self::assertFalse($row->meta['acknowledged_risk'] ?? null);
    }

    public function testNoBaseUrlBlocksTheKeyWithoutAProbe(): void
    {
        $fetcher = FakeFetcher::refusing();
        $installer = $this->makeInstaller($this->dir, $fetcher);
        $installer->createOwner('owner', self::STRONG);

        try {
            $installer->storeApiKey('sk-ant-key', false, null);
            self::fail('The API key was stored with no address to check');
        } catch (StorageException $e) {
            self::assertSame(Installer::ERR_EXPOSED, $e->getCode());
        }

        self::assertSame([], $fetcher->requested);
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

    public function testAReissuedTokenOpensNothingOnAnInstalledCopy(): void
    {
        $old = $this->installer->issueToken();
        $this->installer->createOwner('owner', self::STRONG);
        $this->installer->storeApiKey('sk-ant-key', true, self::UNREACHABLE);
        $this->installer->finalize('1.2.3.4');

        // Put the token back by hand, as a failed unlink or a leftover copy would.
        \WPSafeMode\Security\GuardedJson::write($this->dir . '/install-token.php', ['token' => $old, 'created_at' => time()]);

        self::assertFalse($this->installer->verifyToken($old));

        foreach ([
            fn () => $this->installer->issueToken(),
            fn () => $this->installer->storeApiKey('sk-other', true, self::UNREACHABLE),
            fn () => $this->installer->finalize('5.6.7.8'),
        ] as $call) {
            try {
                $call();
                self::fail('An installer action ran on an installed copy');
            } catch (StorageException $e) {
                self::assertStringContainsString('already installed', $e->getMessage());
            }
        }

        $installs = array_filter(
            StorageFactory::create($this->dir, 'json')->auditLog()->recent(),
            static fn ($e): bool => $e->event === 'install'
        );
        self::assertCount(1, $installs);
    }

    private function installerOver(Storage $storage, string $dir): Installer
    {
        return new Installer($storage, new KeyVault($dir), new PasswordHasher(), $dir, Environment::probe($dir), FakeFetcher::unreachable());
    }

    public function testAFailedInstalledAtWriteLeavesACopyThatCanStillBeFinished(): void
    {
        $failing = FailingStorage::onKey(StorageFactory::create($this->dir, 'json'), 'installed_at');
        $installer = $this->installerOver($failing, $this->dir);
        $installer->createOwner('owner', self::STRONG);

        try {
            $installer->finalize('1.2.3.4');
            self::fail('finalize() reported success although installed_at was not written');
        } catch (StorageException $e) {
            self::assertSame(FailingStorage::MESSAGE, $e->getMessage());
        }

        self::assertFalse($this->installer->state()->installed);

        $this->installer->finalize('1.2.3.4');

        self::assertTrue($this->installer->state()->installed);
        self::assertNotSame('', (string) StorageFactory::create($this->dir, 'json')->config()->get('install_id'));
    }

    /**
     * A request can die at any write. Whichever one it is, the copy is
     * either installed with its CSRF secret and posture in place, or not
     * installed and finishable: never installed and unable to sign anyone in.
     */
    public function testACrashAtAnyFinalizeWriteNeverLeavesAnInstalledCopyWithoutItsSecret(): void
    {
        for ($crashAt = 1; $crashAt <= 3; $crashAt++) {
            $dir = $this->dir . '/crash-' . $crashAt;
            mkdir($dir, 0700, true);
            $real = StorageFactory::create($dir, 'json');
            $failing = new FailingStorage($real, static fn (string $key, int $write): bool => $write === $crashAt);
            $installer = $this->installerOver($failing, $dir);
            $installer->createOwner('owner', self::STRONG);

            try {
                $installer->finalize('1.2.3.4');
                self::fail('finalize() survived a failed write ' . $crashAt);
            } catch (StorageException $e) {
                // The crash.
            }

            $config = $real->config();

            if ($config->get('installed_at') !== null) {
                self::assertNotSame('', (string) $config->get('install_id'), 'installed without a CSRF secret after write ' . $crashAt);
                self::assertSame('emergency', $config->get('posture'), 'installed without a posture after write ' . $crashAt);
                continue;
            }

            $retry = $this->installerOver($real, $dir);
            $retry->finalize('1.2.3.4');
            self::assertTrue($retry->state()->installed, 'not finishable after write ' . $crashAt);
            self::assertNotSame('', (string) $config->get('install_id'));
        }
    }

    public function testInstalledAtIsTheLastConfigWriteOfFinalize(): void
    {
        $recorder = new FailingStorage(StorageFactory::create($this->dir, 'json'), static fn (): bool => false);
        $installer = $this->installerOver($recorder, $this->dir);
        $installer->createOwner('owner', self::STRONG);
        $installer->finalize('1.2.3.4');

        self::assertSame('installed_at', $recorder->written[count($recorder->written) - 1]);
        self::assertContains('install_id', $recorder->written);
        self::assertContains('posture', $recorder->written);
    }

    public function testEveryUserFacingRefusalIsCoded(): void
    {
        $refusals = [
            'no owner for the key'   => [fn () => $this->installer->storeApiKey('sk-ant-key', true, self::UNREACHABLE), Installer::ERR_NO_OWNER],
            'no owner to finish'     => [fn () => $this->installer->finalize('1.2.3.4'), Installer::ERR_NO_OWNER],
        ];

        foreach ($refusals as $name => [$call, $code]) {
            try {
                $call();
                self::fail($name . ' was not refused');
            } catch (StorageException $e) {
                self::assertSame($code, $e->getCode(), $name);
            }
        }

        $this->installer->createOwner('owner', self::STRONG);

        $refusals = [
            'empty key'          => [fn () => $this->installer->storeApiKey('  ', true, self::UNREACHABLE), Installer::ERR_EMPTY_KEY],
            'unacknowledged key' => [fn () => $this->installer->storeApiKey('sk-ant-key', false, self::UNREACHABLE), Installer::ERR_EXPOSED],
        ];

        foreach ($refusals as $name => [$call, $code]) {
            try {
                $call();
                self::fail($name . ' was not refused');
            } catch (StorageException $e) {
                self::assertSame($code, $e->getCode(), $name);
                self::assertStringNotContainsString($this->dir, $e->getMessage(), $name);
            }
        }

        $this->installer->finalize('1.2.3.4');

        try {
            $this->installer->finalize('1.2.3.4');
            self::fail('A second finalize was not refused');
        } catch (StorageException $e) {
            self::assertSame(Installer::ERR_ALREADY_INSTALLED, $e->getCode());
        }
    }

    public function testTheKeysAuditRowRecordsTheAcknowledgementAndTheOutcome(): void
    {
        $this->installer->createOwner('owner', self::STRONG);
        $this->installer->storeApiKey('sk-ant-key', true, self::UNREACHABLE);

        $rows = array_values(array_filter(
            StorageFactory::create($this->dir, 'json')->auditLog()->recent(),
            static fn ($e): bool => $e->event === 'self_test'
        ));

        self::assertCount(1, $rows);
        self::assertTrue($rows[0]->meta['acknowledged_risk'] ?? null);
        self::assertSame('inconclusive', $rows[0]->meta['outcome'] ?? null);
        self::assertFalse($rows[0]->meta['reachable'] ?? null);
        self::assertTrue($rows[0]->meta['inconclusive'] ?? null);
    }

    public function testAnOwnerPasswordWithANulByteIsRefusedAsAWeakness(): void
    {
        try {
            $this->installer->createOwner('owner', "correct horse\0battery staple");
            self::fail('A password no login could ever accept was stored');
        } catch (StorageException $e) {
            self::assertSame(Installer::ERR_WEAK_PASSWORD, $e->getCode());
            self::assertStringContainsString('NUL', $e->getMessage());
        }

        self::assertFalse($this->installer->state()->hasOwner);
    }
}
