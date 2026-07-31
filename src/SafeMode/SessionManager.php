<?php
/**
 * Safe-mode session lifecycle.
 *
 * @package WPSM
 */

declare( strict_types = 1 );

namespace WPSM\SafeMode;

use WPSM\Audit\Log;

defined( 'ABSPATH' ) || exit;

/**
 * Issues, validates and revokes the cookie that puts one browser session into
 * safe mode while every other visitor sees the site untouched.
 *
 * Trust is split deliberately. The mu-plugin runs before the auth cookie has
 * been parsed, so all it can check is that the session cookie carries a valid
 * HMAC and has not expired — enough to decide which plugins to load. This class
 * then re-checks on `init`, when the current user is actually known, and
 * revokes the session if the holder does not have the capability. The window
 * between the two grants a different *view* of the site, never a different
 * permission.
 */
final class SessionManager {

	public const OPTION_SESSIONS = 'wpsm_safe_sessions';
	public const OPTION_SECRET   = 'wpsm_session_secret';

	private const DEFAULT_TTL = 3600;
	private const MAX_TTL     = 28800;

	public function __construct( private readonly Log $log ) {}

	/**
	 * Open a session for the current user and set the cookie.
	 *
	 * @return array{id: string, expires: int, profile: array<string, mixed>}
	 *
	 * @throws \RuntimeException If the current user may not do this.
	 */
	public function start( Profile $profile, int $ttl = self::DEFAULT_TTL ): array {
		if ( ! current_user_can( \WPSM\CAP ) ) {
			throw new \RuntimeException( __( 'You do not have permission to enter safe mode.', 'wp-safe-mode-ai' ) );
		}

		$ttl     = max( 60, min( self::MAX_TTL, $ttl ) );
		$id      = bin2hex( random_bytes( 16 ) );
		$expires = time() + $ttl;

		$sessions        = $this->sessions();
		$sessions[ $id ] = [
			'id'      => $id,
			'user_id' => get_current_user_id(),
			'profile' => $profile->to_array(),
			'created' => time(),
			'expires' => $expires,
		];

		$this->put_sessions( $this->gc( $sessions ) );
		$this->set_cookie( $id, $expires );

		$this->log->record(
			'safemode.started',
			$id,
			[
				'expires' => $expires,
				'profile' => $profile->to_array(),
			]
		);

		return [
			'id'      => $id,
			'expires' => $expires,
			'profile' => $profile->to_array(),
		];
	}

	/**
	 * Close the current session and clear the cookie.
	 */
	public function stop(): void {
		$id = $this->cookie_session_id();

		if ( null !== $id ) {
			$sessions = $this->sessions();
			unset( $sessions[ $id ] );
			$this->put_sessions( $sessions );
			$this->log->record( 'safemode.stopped', $id );
		}

		$this->clear_cookie();
	}

	/**
	 * The active, fully validated session for this request, if any.
	 *
	 * @return array<string, mixed>|null
	 */
	public function current(): ?array {
		$id = $this->cookie_session_id();

		if ( null === $id ) {
			return null;
		}

		$session = $this->sessions()[ $id ] ?? null;

		if ( ! is_array( $session ) || ( $session['expires'] ?? 0 ) < time() ) {
			return null;
		}

		return $session;
	}

	/**
	 * Late capability check, hooked on `init`.
	 *
	 * The mu-plugin could only verify the signature. Now that the user is
	 * resolved, confirm the session belongs to them and that they still hold
	 * the capability — a demoted or logged-out user loses safe mode here.
	 */
	public function verify_current_session(): void {
		$id = $this->cookie_session_id();

		if ( null === $id ) {
			return;
		}

		$session = $this->sessions()[ $id ] ?? null;

		if ( ! is_array( $session ) ) {
			$this->clear_cookie();
			return;
		}

		if ( ( $session['expires'] ?? 0 ) < time() ) {
			$this->stop();
			return;
		}

		$owner = (int) ( $session['user_id'] ?? 0 );

		if ( get_current_user_id() !== $owner || ! user_can( $owner, \WPSM\CAP ) ) {
			$this->log->record(
				'safemode.revoked',
				$id,
				[
					'reason'       => 'capability_check_failed',
					'session_user' => $owner,
					'request_user' => get_current_user_id(),
				]
			);
			$this->stop();
		}
	}

	/**
	 * Summary for the admin UI.
	 *
	 * @return array{active: bool, id: string|null, expires: int|null, profile: array<string, mixed>|null, seconds_remaining: int|null}
	 */
	public function status(): array {
		$session = $this->current();

		if ( null === $session ) {
			return [
				'active'            => false,
				'id'                => null,
				'expires'           => null,
				'profile'           => null,
				'seconds_remaining' => null,
			];
		}

		return [
			'active'            => true,
			'id'                => (string) $session['id'],
			'expires'           => (int) $session['expires'],
			'profile'           => (array) $session['profile'],
			'seconds_remaining' => max( 0, (int) $session['expires'] - time() ),
		];
	}

	/**
	 * HMAC key for session cookies. Generated on first use.
	 */
	public function secret(): string {
		$secret = get_option( self::OPTION_SECRET );

		if ( ! is_string( $secret ) || strlen( $secret ) < 64 ) {
			$secret = bin2hex( random_bytes( 32 ) );
			update_option( self::OPTION_SECRET, $secret, false );
		}

		return $secret;
	}

	/**
	 * Invalidate every session by rotating the signing key.
	 */
	public function rotate_secret(): void {
		update_option( self::OPTION_SECRET, bin2hex( random_bytes( 32 ) ), false );
		update_option( self::OPTION_SESSIONS, [], true );
		$this->clear_cookie();
		$this->log->record( 'safemode.secret_rotated' );
	}

	// ------------------------------------------------------------------
	// Cookie handling
	// ------------------------------------------------------------------

	/**
	 * Parse and verify the cookie, returning the session id it names.
	 *
	 * Mirrors the logic in mu-loader/wpsm-safe-mode.php. Both must agree, so
	 * change them together.
	 */
	public function cookie_session_id(): ?string {
		$raw = $_COOKIE[ \WPSM\SESSION_COOKIE ] ?? null;

		if ( ! is_string( $raw ) || '' === $raw ) {
			return null;
		}

		$parts = explode( '.', $raw );

		if ( 3 !== count( $parts ) ) {
			return null;
		}

		[ $id, $expires, $signature ] = $parts;

		if ( ! preg_match( '/^[a-f0-9]{32}$/', $id ) || ! ctype_digit( $expires ) ) {
			return null;
		}

		if ( (int) $expires < time() ) {
			return null;
		}

		$expected = hash_hmac( 'sha256', $id . '|' . $expires, $this->secret() );

		if ( ! hash_equals( $expected, $signature ) ) {
			return null;
		}

		return $id;
	}

	private function set_cookie( string $id, int $expires ): void {
		$signature = hash_hmac( 'sha256', $id . '|' . $expires, $this->secret() );
		$value     = $id . '.' . $expires . '.' . $signature;

		$options = [
			'expires'  => $expires,
			'path'     => defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/',
			'domain'   => defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '',
			'secure'   => is_ssl(),
			'httponly' => true,
			// Lax rather than Strict: clicking through from wp-admin to the
			// front end is a top-level navigation and must carry the cookie,
			// otherwise the preview silently shows the live site.
			'samesite' => 'Lax',
		];

		if ( ! headers_sent() ) {
			setcookie( \WPSM\SESSION_COOKIE, $value, $options );
		}

		// Make it visible to the rest of this request too.
		$_COOKIE[ \WPSM\SESSION_COOKIE ] = $value;
	}

	private function clear_cookie(): void {
		if ( ! headers_sent() ) {
			setcookie(
				\WPSM\SESSION_COOKIE,
				'',
				[
					'expires'  => time() - 3600,
					'path'     => defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/',
					'domain'   => defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '',
					'secure'   => is_ssl(),
					'httponly' => true,
					'samesite' => 'Lax',
				]
			);
		}

		unset( $_COOKIE[ \WPSM\SESSION_COOKIE ] );
	}

	// ------------------------------------------------------------------
	// Storage
	// ------------------------------------------------------------------

	/**
	 * @return array<string, array<string, mixed>>
	 */
	private function sessions(): array {
		$sessions = get_option( self::OPTION_SESSIONS, [] );

		return is_array( $sessions ) ? $sessions : [];
	}

	/**
	 * @param array<string, array<string, mixed>> $sessions
	 */
	private function put_sessions( array $sessions ): void {
		// Autoloaded: the mu-plugin reads this on every request, and pulling it
		// from the already-loaded alloptions cache costs nothing extra.
		update_option( self::OPTION_SESSIONS, $sessions, true );
	}

	/**
	 * @param array<string, array<string, mixed>> $sessions
	 * @return array<string, array<string, mixed>>
	 */
	private function gc( array $sessions ): array {
		$now = time();

		foreach ( $sessions as $id => $session ) {
			if ( ! is_array( $session ) || (int) ( $session['expires'] ?? 0 ) < $now ) {
				unset( $sessions[ $id ] );
			}
		}

		return $sessions;
	}
}
