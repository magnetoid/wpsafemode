<?php
/**
 * Install and remove the safe-mode drop-ins.
 *
 * @package WPSM
 */

declare( strict_types = 1 );

namespace WPSM\SafeMode;

use WPSM\Audit\Log;
use WPSM\Snapshots\Store;

defined( 'ABSPATH' ) || exit;

/**
 * Manages the two files the toolkit places outside its own directory:
 *
 *   wp-content/mu-plugins/wpsm-safe-mode.php   session overlay
 *   wp-content/fatal-error-handler.php         auto-revert on fatal
 *
 * Both are core-sanctioned extension points, and both are places another
 * plugin may already have claimed. Nothing here overwrites a file it does not
 * recognise as its own — a collision is reported to the user to resolve, not
 * silently won.
 */
final class Dropins {

	public const OPTION_STATE = 'wpsm_dropin_state';

	/** Marker proving a file on disk is ours. */
	private const SIGNATURE = 'WP Safe Mode AI';

	public function __construct(
		private readonly Store $store,
		private readonly Log $log
	) {}

	/**
	 * Install or refresh both drop-ins.
	 *
	 * @return array{
	 *   installed: array<int, string>,
	 *   skipped: array<int, array{file: string, reason: string}>,
	 *   errors: array<int, string>
	 * }
	 */
	public function install(): array {
		$result = [
			'installed' => [],
			'skipped'   => [],
			'errors'    => [],
		];

		$this->store->ensure();
		$state = [];

		// --- Session overlay -------------------------------------------------
		$mu_target = $this->mu_plugin_path();

		if ( ! wp_mkdir_p( dirname( $mu_target ) ) ) {
			$result['errors'][] = sprintf(
				/* translators: %s: directory path */
				__( 'Could not create %s.', 'wp-safe-mode-ai' ),
				dirname( $mu_target )
			);
		} elseif ( $this->is_foreign( $mu_target ) ) {
			$result['skipped'][] = [
				'file'   => $mu_target,
				'reason' => __( 'A different file already occupies this path. Remove it, or rename it, then reinstall.', 'wp-safe-mode-ai' ),
			];
		} else {
			$source = file_get_contents( WPSM_DIR . 'mu-loader/wpsm-safe-mode.php' );

			if ( false === $source || false === file_put_contents( $mu_target, $source, LOCK_EX ) ) {
				/* translators: %s: file path */
				$result['errors'][] = sprintf( __( 'Could not write %s.', 'wp-safe-mode-ai' ), $mu_target );
			} else {
				$result['installed'][]  = $mu_target;
				$state['session_overlay'] = $this->fingerprint( $mu_target );
			}
		}

		// --- Fatal error handler ---------------------------------------------
		$fatal_target = $this->fatal_handler_path();

		if ( $this->is_foreign( $fatal_target ) ) {
			$result['skipped'][] = [
				'file'   => $fatal_target,
				'reason' => __( 'Another plugin already owns the fatal-error-handler drop-in. Auto-revert is unavailable until that is resolved; every other safety step still applies.', 'wp-safe-mode-ai' ),
			];
		} else {
			$generated = $this->render_fatal_handler();

			if ( null === $generated ) {
				$result['errors'][] = __( 'Could not read the fatal-error handler template.', 'wp-safe-mode-ai' );
			} else {
				if ( false === file_put_contents( $fatal_target, $generated, LOCK_EX ) ) {
					/* translators: %s: file path */
					$result['errors'][] = sprintf( __( 'Could not write %s.', 'wp-safe-mode-ai' ), $fatal_target );
				} else {
					$result['installed'][]   = $fatal_target;
					$state['fatal_handler'] = $this->fingerprint( $fatal_target );
				}
			}
		}

		update_option( self::OPTION_STATE, $state, false );
		$this->log->record( 'dropins.installed', '', $result );

		return $result;
	}

	/**
	 * Remove both drop-ins, but only the ones we recognise as ours.
	 *
	 * @return array<int, string> Paths removed.
	 */
	public function remove(): array {
		$removed = [];

		foreach ( [ $this->mu_plugin_path(), $this->fatal_handler_path() ] as $path ) {
			if ( ! file_exists( $path ) ) {
				continue;
			}

			if ( $this->is_foreign( $path ) ) {
				continue;
			}

			if ( @unlink( $path ) ) {
				$removed[] = $path;
			}
		}

		delete_option( self::OPTION_STATE );
		$this->log->record( 'dropins.removed', '', [ 'removed' => $removed ] );

		return $removed;
	}

	/**
	 * Current on-disk state of both drop-ins.
	 *
	 * `tampered` means the file exists, is recognisably ours, but no longer
	 * matches the hash recorded when we wrote it. That is either a partial
	 * upgrade or something editing files it should not be, and both warrant a
	 * reinstall rather than silent trust.
	 *
	 * @return array<string, array{path: string, present: bool, ours: bool, tampered: bool, healthy: bool}>
	 */
	public function status(): array {
		$state = get_option( self::OPTION_STATE, [] );
		$state = is_array( $state ) ? $state : [];

		$report = [];

		foreach (
			[
				'session_overlay' => $this->mu_plugin_path(),
				'fatal_handler'   => $this->fatal_handler_path(),
			] as $key => $path
		) {
			$present  = file_exists( $path );
			$ours     = $present && ! $this->is_foreign( $path );
			$recorded = $state[ $key ]['sha256'] ?? null;
			$tampered = $ours && is_string( $recorded ) && $this->fingerprint( $path )['sha256'] !== $recorded;

			$report[ $key ] = [
				'path'     => $path,
				'present'  => $present,
				'ours'     => $ours,
				'tampered' => $tampered,
				'healthy'  => $present && $ours && ! $tampered,
			];
		}

		return $report;
	}

	/**
	 * Produce the fatal-error handler with this site's paths compiled in.
	 *
	 * The handler runs before the database is usable, so it cannot look its
	 * storage paths up — they are substituted into the source here instead.
	 * That makes this the one place in the plugin that generates executable
	 * PHP, and a quoting mistake would produce a drop-in that is itself a
	 * parse error: the recovery mechanism failing in exactly the way it exists
	 * to prevent. Kept separate from the file I/O so it can be tested.
	 *
	 * @return string|null Null if the template cannot be read.
	 */
	public function render_fatal_handler(): ?string {
		$template = file_get_contents( WPSM_DIR . 'mu-loader/fatal-error-handler.php' );

		if ( false === $template ) {
			return null;
		}

		return strtr(
			$template,
			[
				'{{WPSM_PENDING_FILE}}' => $this->escape_for_php_string( $this->store->pending_file() ),
				'{{WPSM_SNAPSHOT_DIR}}' => $this->escape_for_php_string( $this->store->root() . '/snapshots' ),
			]
		);
	}

	public function mu_plugin_path(): string {
		return rtrim( WPMU_PLUGIN_DIR, '/\\' ) . '/wpsm-safe-mode.php';
	}

	public function fatal_handler_path(): string {
		return rtrim( WP_CONTENT_DIR, '/\\' ) . '/fatal-error-handler.php';
	}

	// ------------------------------------------------------------------
	// Internals
	// ------------------------------------------------------------------

	/**
	 * True when a file exists at this path and was not written by us.
	 */
	private function is_foreign( string $path ): bool {
		if ( ! file_exists( $path ) ) {
			return false;
		}

		$head = (string) file_get_contents( $path, false, null, 0, 2048 );

		return ! str_contains( $head, self::SIGNATURE );
	}

	/**
	 * @return array{sha256: string, bytes: int, written_at: int, version: string}
	 */
	private function fingerprint( string $path ): array {
		$bytes = (string) file_get_contents( $path );

		return [
			'sha256'     => hash( 'sha256', $bytes ),
			'bytes'      => strlen( $bytes ),
			'written_at' => time(),
			'version'    => \WPSM\VERSION,
		];
	}

	/**
	 * Make a path safe to embed inside a single-quoted PHP string literal.
	 */
	private function escape_for_php_string( string $value ): string {
		return str_replace( [ '\\', "'" ], [ '\\\\', "\\'" ], $value );
	}
}
