<?php

declare(strict_types=1);

namespace WPSafeMode\Tests\Security;

use PHPUnit\Framework\TestCase;
use WPSafeMode\Security\KeyVault;
use WPSafeMode\Storage\Contracts\StorageException;

final class KeyVaultTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/wpsm-vault-' . bin2hex(random_bytes(6));
        mkdir($this->dir, 0700, true);
    }

    protected function tearDown(): void
    {
        $this->removeRecursively($this->dir);
    }

    /**
     * scandir(), not glob('*'): every write in this suite goes through
     * DataDir::ensure() (via GuardedJson::write()), which drops a
     * leading-dot .htaccess that glob('*') does not match. Left behind, it
     * makes the final rmdir() fail with "Directory not empty" — a warning
     * that fails the suite outright under phpunit.xml.dist's
     * failOnWarning="true", even though every assertion in the test passed.
     */
    private function removeRecursively(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $full = $dir . '/' . $entry;
            is_dir($full) ? $this->removeRecursively($full) : unlink($full);
        }

        rmdir($dir);
    }

    public function testRoundTripsASecret(): void
    {
        $vault = new KeyVault($this->dir);
        $key = 'sk-ant-api03-' . str_repeat('x', 80);

        self::assertSame($key, $vault->decrypt($vault->encrypt($key)));
    }

    public function testCiphertextDoesNotContainThePlaintext(): void
    {
        $vault = new KeyVault($this->dir);

        self::assertStringNotContainsString('sk-ant-secret', $vault->encrypt('sk-ant-secret'));
    }

    public function testEncryptingTwiceProducesDifferentCiphertext(): void
    {
        // A fixed nonce would leak equality of secrets across config writes.
        $vault = new KeyVault($this->dir);

        self::assertNotSame($vault->encrypt('same'), $vault->encrypt('same'));
    }

    public function testASecondInstanceDecryptsWhatTheFirstWrote(): void
    {
        $envelope = (new KeyVault($this->dir))->encrypt('persisted');

        self::assertSame('persisted', (new KeyVault($this->dir))->decrypt($envelope));
    }

    public function testMasterSecretIsWrittenOwnerReadableOnly(): void
    {
        (new KeyVault($this->dir))->encrypt('x');

        self::assertFileExists($this->dir . '/keyvault.php');
        self::assertSame('0600', substr(sprintf('%o', fileperms($this->dir . '/keyvault.php')), -4));
    }

    public function testTamperedCiphertextIsRejectedRatherThanReturningGarbage(): void
    {
        $vault = new KeyVault($this->dir);
        $envelope = $vault->encrypt('sk-ant-secret');

        // Flip the last byte of the ciphertext segment.
        $parts = explode('.', $envelope);
        $raw = base64_decode(array_pop($parts), true);
        $raw[strlen($raw) - 1] = $raw[strlen($raw) - 1] === 'A' ? 'B' : 'A';
        $parts[] = base64_encode($raw);

        $this->expectException(StorageException::class);
        $vault->decrypt(implode('.', $parts));
    }

    public function testAnEnvelopeFromAnotherInstallIsRejected(): void
    {
        $other = sys_get_temp_dir() . '/wpsm-vault-other-' . bin2hex(random_bytes(6));
        mkdir($other, 0700, true);

        $envelope = (new KeyVault($other))->encrypt('sk-ant-secret');

        $this->removeRecursively($other);

        $this->expectException(StorageException::class);
        (new KeyVault($this->dir))->decrypt($envelope);
    }

    public function testMalformedEnvelopeIsRejected(): void
    {
        $this->expectException(StorageException::class);
        (new KeyVault($this->dir))->decrypt('not-an-envelope');
    }
}
