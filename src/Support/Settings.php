<?php
/**
 * Plugin settings, including credential storage.
 *
 * @package WPSM
 */

declare( strict_types = 1 );

namespace WPSM\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Typed access to the settings option, plus encryption for the API key.
 *
 * The key is encrypted at rest with a secret derived from the site's own salts.
 * That does not defend against an attacker who can already read wp-config.php —
 * nothing stored on the same box does — but it does mean a leaked database
 * dump, a misconfigured backup, or a SQL injection in some other plugin does
 * not hand over a working Anthropic key.
 */
final class Settings {

	public const OPTION = 'wpsm_settings';

	private const DEFAULTS = [
		'model'                => 'claude-opus-5',
		'effort'               => 'high',
		'max_tokens'           => 16000,
		'verify_probe'         => true,
		'allow_uploads_writes' => false,
		'mcp_expose_writes'    => false,
		'auto_approve'         => [],
		'system_prompt_extra'  => '',
	];

	/**
	 * @return array<string, mixed>
	 */
	public function all(): array {
		$stored = get_option( self::OPTION, [] );
		$stored = is_array( $stored ) ? $stored : [];

		// The key never travels with the rest of the settings.
		unset( $stored['api_key_cipher'] );

		return array_merge( self::DEFAULTS, $stored );
	}

	public function get( string $key, mixed $default = null ): mixed {
		$all = $this->all();

		return $all[ $key ] ?? $default ?? ( self::DEFAULTS[ $key ] ?? null );
	}

	/**
	 * @param array<string, mixed> $values
	 */
	public function update( array $values ): void {
		$stored = get_option( self::OPTION, [] );
		$stored = is_array( $stored ) ? $stored : [];

		// Only known keys, so a malformed request cannot inject settings the
		// plugin never reads but something else might.
		foreach ( $values as $key => $value ) {
			if ( array_key_exists( $key, self::DEFAULTS ) ) {
				$stored[ $key ] = $value;
			}
		}

		update_option( self::OPTION, $stored, false );
	}

	// ------------------------------------------------------------------
	// API key
	// ------------------------------------------------------------------

	public function has_api_key(): bool {
		return '' !== $this->api_key();
	}

	/**
	 * Last four characters, for showing which key is configured without
	 * showing the key.
	 */
	public function api_key_hint(): string {
		$key = $this->api_key();

		return '' === $key ? '' : '…' . substr( $key, -4 );
	}

	public function api_key(): string {
		// An environment variable wins over stored settings: it keeps the key
		// out of the database entirely, which is the better arrangement where
		// the host allows it.
		$env = getenv( 'ANTHROPIC_API_KEY' );

		if ( is_string( $env ) && '' !== $env ) {
			return $env;
		}

		if ( defined( 'WPSM_ANTHROPIC_API_KEY' ) && is_string( WPSM_ANTHROPIC_API_KEY ) ) {
			return WPSM_ANTHROPIC_API_KEY;
		}

		$stored = get_option( self::OPTION, [] );
		$cipher = is_array( $stored ) ? ( $stored['api_key_cipher'] ?? '' ) : '';

		if ( ! is_string( $cipher ) || '' === $cipher ) {
			return '';
		}

		return $this->decrypt( $cipher );
	}

	public function set_api_key( string $key ): void {
		$stored = get_option( self::OPTION, [] );
		$stored = is_array( $stored ) ? $stored : [];

		if ( '' === trim( $key ) ) {
			unset( $stored['api_key_cipher'] );
		} else {
			$stored['api_key_cipher'] = $this->encrypt( trim( $key ) );
		}

		update_option( self::OPTION, $stored, false );
	}

	/**
	 * Whether the key is supplied by the environment rather than stored here.
	 */
	public function api_key_is_external(): bool {
		$env = getenv( 'ANTHROPIC_API_KEY' );

		return ( is_string( $env ) && '' !== $env )
			|| ( defined( 'WPSM_ANTHROPIC_API_KEY' ) && is_string( WPSM_ANTHROPIC_API_KEY ) );
	}

	// ------------------------------------------------------------------
	// Encryption
	// ------------------------------------------------------------------

	private function encrypt( string $plaintext ): string {
		$key = $this->derive_key();

		if ( function_exists( 'sodium_crypto_secretbox' ) ) {
			$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );

			return 'sodium:' . base64_encode( $nonce . sodium_crypto_secretbox( $plaintext, $nonce, $key ) );
		}

		$iv  = random_bytes( 12 );
		$tag = '';
		$ct  = openssl_encrypt( $plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag );

		if ( false === $ct ) {
			throw new \RuntimeException( __( 'Could not encrypt the API key on this host.', 'wp-safe-mode-ai' ) );
		}

		return 'openssl:' . base64_encode( $iv . $tag . $ct );
	}

	private function decrypt( string $stored ): string {
		$key = $this->derive_key();

		if ( str_starts_with( $stored, 'sodium:' ) ) {
			if ( ! function_exists( 'sodium_crypto_secretbox_open' ) ) {
				return '';
			}

			$raw   = base64_decode( substr( $stored, 7 ), true );
			$nonce = substr( (string) $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			$ct    = substr( (string) $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );

			$plain = sodium_crypto_secretbox_open( $ct, $nonce, $key );

			return is_string( $plain ) ? $plain : '';
		}

		if ( str_starts_with( $stored, 'openssl:' ) ) {
			$raw = (string) base64_decode( substr( $stored, 8 ), true );
			$iv  = substr( $raw, 0, 12 );
			$tag = substr( $raw, 12, 16 );
			$ct  = substr( $raw, 28 );

			$plain = openssl_decrypt( $ct, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag );

			return is_string( $plain ) ? $plain : '';
		}

		return '';
	}

	/**
	 * 32-byte key derived from the site's auth salts.
	 *
	 * Rotating the salts invalidates the stored key, which is the correct
	 * behaviour: rotating salts is what you do after a compromise, and the
	 * stored credential should not survive it.
	 */
	private function derive_key(): string {
		$material = ( defined( 'AUTH_KEY' ) ? AUTH_KEY : '' )
			. ( defined( 'SECURE_AUTH_SALT' ) ? SECURE_AUTH_SALT : '' )
			. 'wpsm-credential-v1';

		return hash( 'sha256', $material, true );
	}
}
