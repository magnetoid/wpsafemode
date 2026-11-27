<?php

declare(strict_types=1);

namespace WPSafeMode\Tests\Security;

use PHPUnit\Framework\TestCase;
use WPSafeMode\Security\PasswordHasher;
use WPSafeMode\Storage\Credential;

final class PasswordHasherTest extends TestCase
{
    public function testHashesAndVerifies(): void
    {
        $hasher = new PasswordHasher();
        $credential = $hasher->hash('correct horse battery staple');

        self::assertTrue($hasher->verify('correct horse battery staple', $credential));
        self::assertFalse($hasher->verify('wrong password', $credential));
    }

    public function testRecordsWhichAlgorithmProducedTheHash(): void
    {
        $credential = (new PasswordHasher())->hash('pw');

        self::assertContains($credential->algo, ['argon2id', 'bcrypt']);
        self::assertSame(defined('PASSWORD_ARGON2ID') ? 'argon2id' : 'bcrypt', $credential->algo);
    }

    public function testTheHashIsNeverThePlaintext(): void
    {
        $credential = (new PasswordHasher())->hash('plaintext-password');

        self::assertStringNotContainsString('plaintext-password', $credential->hash);
    }

    public function testABcryptHashOnAnArgonCapableHostNeedsRehash(): void
    {
        if (!defined('PASSWORD_ARGON2ID')) {
            self::markTestSkipped('This build has no argon2id, so there is nothing to upgrade to.');
        }

        $bcrypt = new Credential(password_hash('pw', PASSWORD_BCRYPT, ['cost' => 12]), 'bcrypt', 1, 1);

        self::assertTrue((new PasswordHasher())->needsRehash($bcrypt));
    }

    public function testAFreshHashDoesNotNeedRehash(): void
    {
        $hasher = new PasswordHasher();

        self::assertFalse($hasher->needsRehash($hasher->hash('pw')));
    }

    public function testVerifyingAgainstAnEmptyHashFails(): void
    {
        self::assertFalse((new PasswordHasher())->verify('pw', new Credential('', 'bcrypt', 1, 1)));
    }

    public function testWeakPasswordsAreRejectedBeforeHashing(): void
    {
        self::assertNotSame([], PasswordHasher::weaknesses('short'), 'too short must be rejected');
        self::assertNotSame([], PasswordHasher::weaknesses('password123'), 'too short must be rejected');

        // 12+ characters so length alone cannot explain a rejection here —
        // these two isolate the forbidden-substring rule and the
        // repeated-character rule respectively. Without them, deleting
        // either rule from PasswordHasher leaves this test green.
        self::assertNotSame(
            [],
            PasswordHasher::weaknesses('wordpress-admin-1'),
            'a 12+ character password containing a forbidden substring must still be rejected'
        );
        self::assertNotSame(
            [],
            PasswordHasher::weaknesses(str_repeat('a', 12)),
            'a 12+ character single-repeated-character password must still be rejected'
        );

        self::assertSame([], PasswordHasher::weaknesses('correct horse battery staple'));
    }

    public function testANulByteIsAWeaknessBecauseNoLoginCouldAcceptIt(): void
    {
        $weaknesses = PasswordHasher::weaknesses("correct horse\0battery staple");

        self::assertCount(1, $weaknesses);
        self::assertStringContainsString('NUL', $weaknesses[0]);
    }
}
