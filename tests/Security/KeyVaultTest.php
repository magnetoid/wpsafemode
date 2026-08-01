<?php

declare(strict_types=1);

namespace WPSafeMode\Tests\Security;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use WPSafeMode\Security\DataDir;
use WPSafeMode\Security\GuardedJson;
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

    /**
     * The vault's own derived encryption key, fetched by reflection so a
     * test can construct a genuine envelope for a specific backend
     * regardless of which algorithm this host actually runs encrypt()
     * through — decrypt() dispatches on the envelope's own tag, not on
     * algorithm(), so both branches are reachable on any host once an
     * envelope names one.
     */
    private static function derivedKey(KeyVault $vault): string
    {
        /** @var string $key */
        $key = self::reflectPrivateMethod(KeyVault::class, 'key')->invoke($vault);

        return $key;
    }

    /**
     * setAccessible(true) is required to invoke a private method at all on
     * the PHP 8.0 floor this ships to; it has had no effect since PHP 8.1
     * and PHP 8.5 (this host) now deprecates calling it. Guarding the call
     * keeps every reflection use-site in this file correct on 8.0 without
     * printing that deprecation notice on every run here.
     */
    private static function reflectPrivateMethod(string $class, string $method): ReflectionMethod
    {
        $reflected = new ReflectionMethod($class, $method);

        if (PHP_VERSION_ID < 80100) {
            $reflected->setAccessible(true);
        }

        return $reflected;
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
        // decrypt() throws StorageException for six different reasons;
        // pinning the message, not just the class, proves this is
        // specifically the auth-failure path and not some other one.
        $this->expectExceptionMessage('wrong key or tampered value');
        $vault->decrypt(implode('.', $parts));
    }

    public function testAnEnvelopeFromAnotherInstallIsRejected(): void
    {
        $other = sys_get_temp_dir() . '/wpsm-vault-other-' . bin2hex(random_bytes(6));
        mkdir($other, 0700, true);

        $envelope = (new KeyVault($other))->encrypt('sk-ant-secret');

        $this->removeRecursively($other);

        $this->expectException(StorageException::class);
        $this->expectExceptionMessage('wrong key or tampered value');
        (new KeyVault($this->dir))->decrypt($envelope);
    }

    public function testMalformedEnvelopeIsRejected(): void
    {
        $this->expectException(StorageException::class);
        (new KeyVault($this->dir))->decrypt('not-an-envelope');
    }

    /**
     * Task 2 review round 2, item (d): this test and two others below call
     * openssl_encrypt()/openssl_decrypt() directly to construct a specific
     * envelope, with no guard. On a sodium-present/openssl-absent host —
     * which KeyVault::encrypt() and isAvailable() both explicitly support —
     * they previously fataled with "Call to undefined function" instead of
     * skipping.
     */
    private function skipUnlessOpensslIsAvailable(): void
    {
        if (!extension_loaded('openssl')) {
            self::markTestSkipped('This test requires the openssl extension.');
        }
    }

    // --- review round 1: IMPORTANT 1 — the GCM tag/IV length is never pinned ---

    public function testGenuineShortGcmTagPrefixIsRejectedRatherThanAuthenticating(): void
    {
        $this->skipUnlessOpensslIsAvailable();

        $vault = new KeyVault($this->dir);
        $key = self::derivedKey($vault);

        $iv = random_bytes(12);
        $realTag = '';
        $cipher = openssl_encrypt('sk-ant-secret', 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $realTag);
        self::assertNotFalse($cipher);

        // openssl_decrypt() has no tag-length parameter of its own — it
        // trusts strlen($tag) and hands exactly that many bytes to
        // EVP_CTRL_AEAD_SET_TAG, which OpenSSL accepts for any length from
        // 1 to 16 and verifies only that many bytes. This is a GENUINE
        // one-byte prefix of the real tag, not a random byte: verified
        // empirically, openssl_decrypt() returns the correct plaintext
        // given only the first byte of its own tag, i.e. a forged envelope
        // with a 1-byte tag authenticates with probability 1/256 per
        // attempt instead of 2^-128.
        $oneByteTag = substr($realTag, 0, 1);

        $envelope = implode('.', [
            'v1',
            'openssl',
            base64_encode($iv),
            base64_encode($oneByteTag),
            base64_encode($cipher),
        ]);

        $this->expectException(StorageException::class);
        $vault->decrypt($envelope);
    }

    public function testZeroLengthIvIsRejectedWithoutEmittingAPhpWarning(): void
    {
        $this->skipUnlessOpensslIsAvailable();

        $vault = new KeyVault($this->dir);
        $key = self::derivedKey($vault);

        $tag = '';
        $cipher = openssl_encrypt('sk-ant-secret', 'aes-256-gcm', $key, OPENSSL_RAW_DATA, random_bytes(12), $tag);
        self::assertNotFalse($cipher);

        $envelope = implode('.', [
            'v1',
            'openssl',
            base64_encode(''),
            base64_encode($tag),
            base64_encode($cipher),
        ]);

        // Verified empirically: passing a zero-length IV straight to
        // openssl_decrypt() emits "Warning: openssl_decrypt(): Setting of IV
        // length for AEAD mode failed" before returning false, and src/
        // never disables display_errors, so a real request leaks that raw
        // warning into whatever it is producing (an HTTP response, a log
        // line). Asserting only expectException(StorageException::class)
        // here is NOT sufficient to catch a regression: PHPUnit's own error
        // handler converts the warning straight into a thrown exception,
        // which decrypt()'s existing catch (Throwable) then wraps as a
        // StorageException anyway, on BOTH the fixed and unfixed code —
        // that catch block was written for SodiumException, not as warning
        // suppression, and its incidentally catching this too would hide a
        // regression here. A scoped handler that records rather than
        // throws stands in for how a real request actually behaves.
        $warnings = [];
        set_error_handler(
            static function (int $errno, string $errstr) use (&$warnings): bool {
                $warnings[] = $errstr;

                return true;
            },
            E_WARNING
        );

        try {
            $thrown = null;

            try {
                $vault->decrypt($envelope);
            } catch (StorageException $e) {
                $thrown = $e;
            }
        } finally {
            restore_error_handler();
        }

        self::assertSame(
            [],
            $warnings,
            'a wrong-length IV must be rejected before it ever reaches openssl_decrypt(), not caught after the fact'
        );
        self::assertInstanceOf(StorageException::class, $thrown);
    }

    // --- review round 1: IMPORTANT 2 — an existing-but-unusable secret must never be silently replaced ---

    public function testExistingButEmptyKeyVaultFileThrowsRatherThanBeingSilentlyReplaced(): void
    {
        // GuardedJson::read() returns [] for a guard-only file with an
        // empty payload, exactly as it does for a genuinely missing file.
        // The file EXISTS here, though, so treating the two the same and
        // minting a replacement secret would silently and permanently
        // orphan any API key already encrypted under whatever this file
        // used to hold.
        file_put_contents($this->dir . '/keyvault.php', DataDir::GUARD);

        $this->expectException(StorageException::class);
        (new KeyVault($this->dir))->encrypt('x');
    }

    public function testWrongLengthStoredSecretThrows(): void
    {
        GuardedJson::write($this->dir . '/keyvault.php', [
            'secret' => base64_encode(random_bytes(10)),
            'created_at' => time(),
        ]);

        $this->expectException(StorageException::class);
        (new KeyVault($this->dir))->encrypt('x');
    }

    // --- review round 1: IMPORTANT 3 — concurrent first run must converge on one secret ---

    public function testConcurrentFirstRunConvergesOnASingleMasterSecretInsteadOfOrphaningAnEnvelope(): void
    {
        // Instance 1 completes an entire first run exactly as a real
        // first-ever request would: mint a secret, persist it, then
        // encrypt and hand back an envelope.
        $first = new KeyVault($this->dir);
        $envelope = $first->encrypt('sk-ant-secret');

        // Instance 2 simulates a second, concurrent request whose own
        // is_file() check had already returned false before instance 1's
        // write landed. Reflection invokes the create step directly —
        // exactly what masterSecret() would do if instance 2's check had
        // raced ahead of instance 1's write — bypassing the is_file()
        // guard that a real second request would only have passed
        // *before* the race resolved.
        $second = new KeyVault($this->dir);
        self::reflectPrivateMethod(KeyVault::class, 'createMasterSecret')
            ->invoke($second, $this->dir . '/keyvault.php');

        // Whichever secret is actually on disk after that is what every
        // caller must converge on: a THIRD, entirely fresh instance —
        // reading only from disk, caching nothing from either instance
        // above — must still be able to decrypt instance 1's envelope. On
        // unfixed code, instance 2's create attempt silently renames over
        // instance 1's secret, and this assertion fails with "wrong key or
        // tampered value" — the message for an attack, not for a lost race.
        $third = new KeyVault($this->dir);
        self::assertSame('sk-ant-secret', $third->decrypt($envelope));
    }

    // --- review round 1: "also worth closing" gaps ---

    public function testAnOpensslEnvelopeProducedElsewhereDecryptsOnThisHostRegardlessOfLocalAlgorithm(): void
    {
        // Not one of the two tests item (d) named, but the same underlying
        // issue: this envelope is 'openssl'-tagged, so decrypt() reaches its
        // openssl branch — and calls the real openssl_decrypt() — regardless
        // of which algorithm THIS host would have picked for encrypt(). On a
        // sodium-present/openssl-absent host that would fatal too.
        $this->skipUnlessOpensslIsAvailable();

        // Fixed fixture, generated once (see task-2-report.md): a 32-byte
        // master secret and a genuine OpenSSL envelope produced from it
        // under a sodium-disabled interpreter. The class docblock's whole
        // promise is that a host which gains or loses sodium can still
        // read what the other backend wrote, but encrypt() only ever
        // exercises whichever algorithm THIS host has — neither of the
        // suite's two runs (sodium available, sodium disabled) exercises
        // both branches of decrypt() without a fixture like this.
        GuardedJson::write($this->dir . '/keyvault.php', [
            'secret' => base64_encode(str_repeat("\x42", 32)),
            'created_at' => time(),
        ]);

        $envelope = 'v1.openssl.AQEBAQEBAQEBAQEB.ZjmGcd4Ym3Kc7mmWodbrJg==.2UR4OyY1z/xaogNgvg2XfUD/pLFx';

        self::assertSame('sk-ant-fixture-secret', (new KeyVault($this->dir))->decrypt($envelope));
    }

    public function testAlgorithmReportsSodiumOrOpenssl(): void
    {
        self::assertContains((new KeyVault($this->dir))->algorithm(), ['sodium', 'openssl']);
    }

    public function testIsAvailableReportsTrueOnThisHost(): void
    {
        // Every environment this suite runs in has openssl, sodium, or
        // both (Task 2's report records exactly this host has both), so
        // this is not vacuous here — Task 12 shows the user isAvailable()
        // to decide whether to offer encryption at rest at all.
        self::assertTrue(KeyVault::isAvailable());
    }

    public function testASodiumTaggedEnvelopeWithTheWrongSegmentCountIsRejected(): void
    {
        $vault = new KeyVault($this->dir);

        // 5 segments total (v1, sodium, a, b, c) where sodium expects 4.
        $envelope = 'v1.sodium.' . base64_encode('a') . '.' . base64_encode('b') . '.' . base64_encode('c');

        $this->expectException(StorageException::class);
        $vault->decrypt($envelope);
    }

    public function testAnOpensslTaggedEnvelopeWithTheWrongSegmentCountIsRejected(): void
    {
        $vault = new KeyVault($this->dir);

        // 4 segments total (v1, openssl, a, b) where openssl expects 5.
        $envelope = 'v1.openssl.' . base64_encode('a') . '.' . base64_encode('b');

        $this->expectException(StorageException::class);
        $vault->decrypt($envelope);
    }
}
