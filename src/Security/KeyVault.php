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

    public const PURPOSE_CONFIG = 'config';
    public const PURPOSE_BRIDGE = 'bridge';
    public const PURPOSE_TOTP = 'totp';
    public const PURPOSE_REMOTE = 'remote';

    private const PURPOSES = [
        self::PURPOSE_CONFIG,
        self::PURPOSE_BRIDGE,
        self::PURPOSE_TOTP,
        self::PURPOSE_REMOTE,
    ];

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
                $iv = self::decode($parts[2]);
                $tag = self::decode($parts[3]);

                // openssl_decrypt() takes no tag-length parameter of its
                // own: it trusts strlen($tag) and hands exactly that many
                // bytes to EVP_CTRL_AEAD_SET_TAG, which OpenSSL accepts for
                // any length from 1 to 16 and then verifies only that many
                // bytes against the computed tag. Verified empirically: a
                // GENUINE one-byte prefix of a real tag authenticates and
                // returns the correct plaintext, so an envelope carrying a
                // forged 1-byte tag succeeds with probability 1/256 per
                // attempt instead of 2^-128 — the standard route to
                // recovering the GHASH subkey via repeated short-tag
                // forgery. Pinned here rather than left to the library.
                //
                // The IV is not a security boundary the same way — a
                // wrong-length IV just makes the computed tag disagree with
                // the caller's, so openssl_decrypt() returns false rather
                // than silently authenticating — but a zero-length IV
                // specifically makes it emit a raw E_WARNING ("Setting of
                // IV length for AEAD mode failed") before returning false,
                // and src/ never disables display_errors. Both lengths are
                // pinned before either can reach openssl_decrypt().
                if (strlen($tag) !== 16 || strlen($iv) !== 12) {
                    throw new StorageException('Could not decrypt: wrong key or tampered value.');
                }

                $plain = openssl_decrypt(self::decode($parts[4]), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);

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
     * A 32-byte key derived for one purpose, so the master secret is never
     * used directly and a key leaked for one purpose cannot decrypt another.
     * The purpose is checked before the master secret is touched, so an
     * unknown purpose never mints a secret file.
     */
    public function subkey(string $purpose): string
    {
        if (!in_array($purpose, self::PURPOSES, true)) {
            throw new \InvalidArgumentException('Unknown key vault purpose.');
        }

        return hash_hkdf('sha256', $this->masterSecret(), self::SECRET_BYTES, 'wpsm:' . $purpose . ':v1');
    }

    /**
     * The config subkey that encrypt() and decrypt() use. Cached, as before.
     */
    private function key(): string
    {
        if ($this->key !== null) {
            return $this->key;
        }

        return $this->key = $this->subkey(self::PURPOSE_CONFIG);
    }

    private function masterSecret(): string
    {
        $path = $this->dataDir . '/' . self::SECRET_FILE;

        if (is_file($path) && (int) @filesize($path) > 0) {
            return $this->readStoredSecret($path);
        }

        if (is_file($path)) {
            // Review round 3: a TRULY zero-length file is the crash window
            // of GuardedJson::createExclusive()'s fopen('xb') fallback
            // (used when hard links are unavailable) — claimed, but the
            // process died before a single content byte landed. Unlike
            // round 1's IMPORTANT 2 concern (an existing-but-unusable file
            // might be a corrupted, previously-working secret, and
            // replacing it would destroy an API key already encrypted
            // under it), an empty file provably never held a secret: there
            // is nothing here that replacing it could destroy. It is
            // removed and treated the same as a genuinely missing file,
            // restoring the self-healing behaviour the O_CREAT|O_EXCL
            // round removed, without reintroducing the silent re-key of a
            // corrupt-but-non-empty vault (that still throws above).
            @unlink($path);
        }

        DataDir::ensure($this->dataDir);

        return $this->createMasterSecret($path);
    }

    /**
     * The file already exists, so whatever is in it is the ONLY acceptable
     * secret. GuardedJson::read() returns [] not just for a missing file
     * but also for a zero-length file, a guard-only file with an empty
     * payload, and a JSON document that decodes to a non-array — treating
     * any of those the same as "missing" and minting a replacement would
     * silently and permanently orphan an API key already encrypted under
     * whatever this file used to hold, surfacing later as "wrong key or
     * tampered value" — the message for an attack, not for "your master
     * secret was regenerated". A file that exists but does not yield a
     * usable secret is corruption, never an invitation to overwrite it.
     */
    private function readStoredSecret(string $path): string
    {
        $stored = GuardedJson::read($path);
        $secret = isset($stored['secret']) && is_string($stored['secret'])
            ? base64_decode($stored['secret'], true)
            : false;

        if ($secret !== false && strlen($secret) === self::SECRET_BYTES) {
            return $secret;
        }

        throw new StorageException('The key vault is corrupt; the stored master secret is unusable.');
    }

    /**
     * Mints a secret and persists it exclusively. GuardedJson::write() is
     * atomic per write (temp + rename) but has no create-exclusive
     * semantics, so two requests that both reach masterSecret() and both
     * find no file yet — a double-submitted install form, a browser
     * prefetch, two open tabs — would otherwise both rename over each
     * other, and whichever lost would silently persist an envelope under a
     * secret nothing on disk still holds. createExclusive()'s
     * O_CREAT|O_EXCL means only one caller can ever win that; losing is not
     * an error here, it means a concurrent request's secret, not this
     * one's, is what every caller must converge on, so the loser just reads
     * what the winner wrote.
     */
    private function createMasterSecret(string $path): string
    {
        $secret = random_bytes(self::SECRET_BYTES);

        if (GuardedJson::createExclusive($path, ['secret' => base64_encode($secret), 'created_at' => time()])) {
            return $secret;
        }

        if (!is_file($path)) {
            // Review round 3: createExclusive() returning false here means
            // neither this call nor any concurrent one ever actually
            // created $path — a genuine write failure (both the hard-link
            // publish and its O_CREAT|O_EXCL fallback failed), not a lost
            // race. readStoredSecret() would find nothing and throw "the
            // vault is corrupt" — the wording for tampering with data that
            // exists; nothing here ever existed to tamper with.
            throw new StorageException('Cannot create the key vault master secret.');
        }

        return $this->readStoredSecret($path);
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
