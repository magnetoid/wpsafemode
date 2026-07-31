<?php
/**
 * Roll a change back to its snapshot.
 *
 * @package WPSM
 */

declare( strict_types = 1 );

namespace WPSM\Snapshots;

use WPSM\Audit\Log;

defined( 'ABSPATH' ) || exit;

/**
 * Restores the bytes captured before a change was applied.
 *
 * NOTE: the fatal-error-handler drop-in reimplements the core of this in plain
 * procedural PHP. That duplication is deliberate — the drop-in runs before
 * WordPress is bootstrapped and cannot load this class. If the manifest format
 * changes here, change mu-loader/fatal-error-handler.php to match.
 */
final class Restore {

	public function __construct(
		private readonly Store $store,
		private readonly Journal $journal,
		private readonly Log $log
	) {}

	/**
	 * Listen for rollback requests.
	 *
	 * Writer and Journal ask for a rollback by firing `wpsm_revert_stale_change`
	 * rather than depending on this class, which keeps the dependency graph
	 * acyclic. That indirection only works if something has registered the
	 * listener, so Plugin::boot() calls this on every request — a rollback that
	 * silently does nothing because nobody was listening is the worst possible
	 * failure here.
	 */
	public function register(): void {
		add_action( 'wpsm_revert_stale_change', [ $this, 'revert_stale' ] );
	}

	/**
	 * Roll back a change.
	 *
	 * @return array{restored: array<int, string>, deleted: array<int, string>, errors: array<int, string>}
	 */
	public function revert( string $change_id, bool $automatic = false ): array {
		$result = [
			'restored' => [],
			'deleted'  => [],
			'errors'   => [],
		];

		$manifest = $this->manifest( $change_id );

		if ( null === $manifest ) {
			$result['errors'][] = sprintf(
				/* translators: %s: change identifier */
				__( 'No snapshot manifest found for change %s.', 'wp-safe-mode-ai' ),
				$change_id
			);

			return $result;
		}

		$dir = $this->store->snapshot_dir( $change_id );

		foreach ( $manifest['files'] as $file ) {
			$path = (string) $file['path'];

			// The file we created did not exist before — removing it is the
			// correct inverse.
			if ( empty( $file['existed'] ) ) {
				if ( file_exists( $path ) && ! @unlink( $path ) ) {
					/* translators: %s: file path */
					$result['errors'][] = sprintf( __( 'Could not delete %s.', 'wp-safe-mode-ai' ), $path );
				} else {
					$result['deleted'][] = $path;
				}
				continue;
			}

			$blob = $dir . '/files/' . (int) $file['index'] . '.bin';

			if ( ! is_readable( $blob ) ) {
				/* translators: %s: file path */
				$result['errors'][] = sprintf( __( 'Snapshot payload missing for %s.', 'wp-safe-mode-ai' ), $path );
				continue;
			}

			$bytes = file_get_contents( $blob );

			if ( false === $bytes ) {
				/* translators: %s: file path */
				$result['errors'][] = sprintf( __( 'Snapshot payload unreadable for %s.', 'wp-safe-mode-ai' ), $path );
				continue;
			}

			// Integrity check before we put anything back on disk. A corrupted
			// snapshot is worse than no restore at all.
			if ( ! empty( $file['sha256'] ) && hash( 'sha256', $bytes ) !== $file['sha256'] ) {
				/* translators: %s: file path */
				$result['errors'][] = sprintf( __( 'Snapshot for %s failed its checksum; refusing to restore it.', 'wp-safe-mode-ai' ), $path );
				continue;
			}

			$dirname = dirname( $path );
			if ( ! is_dir( $dirname ) ) {
				wp_mkdir_p( $dirname );
			}

			if ( false === file_put_contents( $path, $bytes, LOCK_EX ) ) {
				/* translators: %s: file path */
				$result['errors'][] = sprintf( __( 'Could not write %s.', 'wp-safe-mode-ai' ), $path );
				continue;
			}

			if ( ! empty( $file['mode'] ) ) {
				@chmod( $path, (int) $file['mode'] );
			}
			if ( ! empty( $file['mtime'] ) ) {
				@touch( $path, (int) $file['mtime'] );
			}

			$result['restored'][] = $path;

			// Opcache will happily keep serving the broken file we just
			// replaced. Invalidate explicitly.
			if ( function_exists( 'opcache_invalidate' ) && str_ends_with( $path, '.php' ) ) {
				@opcache_invalidate( $path, true );
			}
		}

		$this->journal->mark_reverted( $change_id, $automatic, $result );

		if ( [] === $result['errors'] ) {
			$this->store->forget( $change_id );
		}

		return $result;
	}

	/**
	 * Callback for the stale-pending reconciliation hook.
	 */
	public function revert_stale( string $change_id ): void {
		$this->log->record( 'change.stale_revert', $change_id );
		$this->revert( $change_id, true );
	}

	/**
	 * @return array{change_id: string, created_at: int, files: array<int, array<string, mixed>>}|null
	 */
	public function manifest( string $change_id ): ?array {
		$path = $this->store->snapshot_dir( $change_id ) . '/manifest.json';

		if ( ! is_readable( $path ) ) {
			return null;
		}

		$data = json_decode( (string) file_get_contents( $path ), true );

		if ( ! is_array( $data ) || ! isset( $data['files'] ) || ! is_array( $data['files'] ) ) {
			return null;
		}

		return $data;
	}
}
