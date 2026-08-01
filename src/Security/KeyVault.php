<?php

declare(strict_types=1);

namespace WPSafeMode\Security;

use Throwable;
use WPSafeMode\Storage\Contracts\StorageException;

/**
 * Encrypts the Anthropic API key at rest.
 *
 * libsodium's crypto_secretbox when the build has it, OpenSSL aes-256-gcm
 * otherwise. Both are authenticated, so a tampered envelope fails loudly
 * instead of decrypting to garbage that later reaches the API as a malformed
 * key. The envelope records which was used, so a host that gains sodium later
 * can still read what OpenSSL wrote.
 *
 * Envelope format, all segments base64:
 *   v1.sodium.{nonce}.{ciphertext}
 *   v1.openssl.{iv}.{tag}.{ciphertext}
 *
 * Honest limitation, stated in spec 5.5 and shown to the user by the installer:
 * the master secret lives beside the data it protects, so an attacker who can
 * read the data directory can decrypt the key. This defends against partial
 * disclosure — a leaked backup, a directory listing, a misconfigured data/ on
 * nginx — not against full filesystem read.
 */
final class KeyVault
{
    private const SECRET_FILE = 'keyvault.php';
    private const SECRET_BYTES = 32;
    private const CONTEXT = 'wpsm:config:v1';

    private ?string $key = null;

    public function __construct(private string $dataDir)
    {
    }

    public static function isAvailable(): bool
    {
        return function_exists('sodium_crypto_secretbox')
            || (extension_loaded('openssl') && in_array('aes-256-gcm', openssl_get_cipher_methods(), true));
    }

    /** Either 'sodium' or 'openssl'. */
    public function algorithm(): string
    {
        return function_exists('sodium_crypto_secretbox') ? 'sodium' : 'openssl';
    }

    public function encrypt(string $plaintext): string
    {
        $key = $this->key();

        if ($this->algorithm() === 'sodium') {
            $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

            return implode('.', [
                'v1',
                'sodium',
                base64_encode($nonce),
                base64_encode(sodium_crypto_secretbox($plaintext, $nonce, $key)),
            ]);
        }

        if (!extension_loaded('openssl')) {
            throw new StorageException('No encryption extension is available: install sodium or openssl.');
        }

        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);

        if ($cipher === false) {
            throw new StorageException('Encryption failed.');
        }

        return implode('.', [
            'v1',
            'openssl',
            base64_encode($iv),
            base64_encode($tag),
            base64_encode($cipher),
        ]);
    }

    public function decrypt(string $envelope): string
    {
        $parts = explode('.', $envelope);

        if (count($parts) < 4 || $parts[0] !== 'v1') {
            throw new StorageException('Unrecognised encrypted value.');
        }

        $key = $this->key();

        try {
            if ($parts[1] === 'sodium' && count($parts) === 4) {
                $plain = sodium_crypto_secretbox_open(
                    self::decode($parts[3]),
                    self::decode($parts[2]),
                    $key
                );

                if ($plain === false) {
                    throw new StorageException('Could not decrypt: wrong key or tampered value.');
                }

                return $plain;
            }

            if ($parts[1] === 'openssl' && count($parts) === 5) {
                $plain = openssl_decrypt(
                    self::decode($parts[4]),
                    'aes-256-gcm',
                    $key,
                    OPENSSL_RAW_DATA,
                    self::decode($parts[2]),
                    self::decode($parts[3])
                );

                if ($plain === false) {
                    throw new StorageException('Could not decrypt: wrong key or tampered value.');
                }

                return $plain;
            }
        } catch (StorageException $e) {
            throw $e;
        } catch (Throwable $e) {
            // sodium throws SodiumException on a malformed nonce or key length.
            throw new StorageException('Could not decrypt: malformed value.', 0, $e);
        }

        throw new StorageException('Unrecognised encrypted value.');
    }

    /**
     * The 32-byte encryption key, derived from the stored master secret so the
     * secret itself is never used directly and can serve other contexts later.
     */
    private function key(): string
    {
        if ($this->key !== null) {
            return $this->key;
        }

        return $this->key = hash_hkdf('sha256', $this->masterSecret(), self::SECRET_BYTES, self::CONTEXT);
    }

    private function masterSecret(): string
    {
        $path = $this->dataDir . '/' . self::SECRET_FILE;
        $stored = GuardedJson::read($path);

        if (isset($stored['secret']) && is_string($stored['secret'])) {
            $secret = base64_decode($stored['secret'], true);

            if ($secret !== false && strlen($secret) === self::SECRET_BYTES) {
                return $secret;
            }

            throw new StorageException('The key vault is corrupt; the stored master secret is unusable.');
        }

        DataDir::ensure($this->dataDir);

        $secret = random_bytes(self::SECRET_BYTES);
        GuardedJson::write($path, ['secret' => base64_encode($secret), 'created_at' => time()]);
        @chmod($path, 0600);

        return $secret;
    }

    private static function decode(string $segment): string
    {
        $raw = base64_decode($segment, true);

        if ($raw === false) {
            throw new StorageException('Unrecognised encrypted value.');
        }

        return $raw;
    }
}
