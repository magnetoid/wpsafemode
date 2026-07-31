<?php
/**
 * The file write safety chain.
 *
 * @package WPSM
 */

declare( strict_types = 1 );

namespace WPSM\Files;

use WPSM\Audit\Log;
use WPSM\Snapshots\FileSnapshot;
use WPSM\Snapshots\Journal;
use WPSM\Snapshots\Store;

defined( 'ABSPATH' ) || exit;

/**
 * Applies a file change through the full chain:
 *
 *   resolve → concurrency check → lint → snapshot → journal(pending)
 *     → write → opcache invalidate → verify → commit | revert
 *
 * No step is optional and no caller writes files any other way. If any stage
 * before the write fails, nothing has touched the disk. If verification fails,
 * the change is rolled back before this method returns.
 */
final class Writer {

	public function __construct(
		private readonly Sandbox $sandbox,
		private readonly Lint $lint,
		private readonly Store $store,
		private readonly Journal $journal,
		private readonly Log $log
	) {}

	/**
	 * @param array{expected_sha256?: string|null} $options
	 *
	 * @return array{
	 *   ok: bool,
	 *   change_id: string|null,
	 *   relative: string,
	 *   created: bool,
	 *   verified: bool,
	 *   lint: array<string, mixed>,
	 *   probe: array<string, mixed>|null,
	 *   error: string|null
	 * }
	 *
	 * @throws SandboxViolation
	 */
	public function write( string $path, string $contents, array $options = [] ): array {
		$real     = $this->sandbox->resolve_for_write( $path );
		$relative = $this->sandbox->relative( $real );
		$existed  = is_file( $real );

		$result = [
			'ok'        => false,
			'change_id' => null,
			'relative'  => $relative,
			'created'   => ! $existed,
			'verified'  => false,
			'lint'      => [],
			'probe'     => null,
			'error'     => null,
		];

		// Optimistic concurrency. An agent reads a file, thinks for a while,
		// then writes it back; if something else changed it in between we must
		// not silently discard that.
		$expected = $options['expected_sha256'] ?? null;

		if ( is_string( $expected ) && '' !== $expected ) {
			$current = $existed ? (string) file_get_contents( $real ) : '';
			$actual  = hash( 'sha256', $current );

			if ( ! hash_equals( $expected, $actual ) ) {
				$result['error'] = sprintf(
					/* translators: %s: file path */
					__( '%s changed on disk since it was read. Re-read the file and reapply the edit.', 'wp-safe-mode-ai' ),
					$relative
				);
				$this->log->record( 'file.write_conflict', $relative, [ 'expected' => $expected, 'actual' => $actual ] );

				return $result;
			}
		}

		// Lint before anything is written. A syntax error caught here costs
		// nothing; the same error on disk takes the site down.
		$lint           = $this->lint->check( $contents, $real );
		$result['lint'] = $lint;

		if ( ! $lint['ok'] ) {
			$result['error'] = sprintf(
				/* translators: 1: file path, 2: syntax error message */
				__( 'Refused to write %1$s: %2$s', 'wp-safe-mode-ai' ),
				$relative,
				$lint['message']
			);
			$this->log->record( 'file.lint_rejected', $relative, $lint );

			return $result;
		}

		// Reserve the id first so the snapshot is written straight to its final
		// location. Snapshot before the journal row, so a snapshot failure
		// aborts cleanly without leaving an orphaned pending entry.
		$change_id           = $this->journal->reserve_id();
		$result['change_id'] = $change_id;

		try {
			$snapshot = ( new FileSnapshot( $this->store ) )->capture( $change_id, [ $real ] );
		} catch ( \RuntimeException $e ) {
			$result['change_id'] = null;
			$result['error']     = $e->getMessage();
			$this->log->record( 'file.snapshot_failed', $relative, [ 'error' => $e->getMessage() ] );

			return $result;
		}

		$this->journal->open(
			[
				'change_id' => $change_id,
				'kind'      => 'file',
				'op'        => $existed ? 'write' : 'create',
				'target'    => $relative,
				'files'     => $snapshot['files'],
				'meta'      => [
					'lint'         => $lint,
					'bytes_before' => $existed ? (int) filesize( $real ) : 0,
					'bytes_after'  => strlen( $contents ),
				],
			]
		);

		if ( ! $this->put( $real, $contents ) ) {
			$this->journal->fail( $change_id, 'write_failed' );
			$result['error'] = sprintf(
				/* translators: %s: file path */
				__( 'Could not write %s. Check filesystem permissions.', 'wp-safe-mode-ai' ),
				$relative
			);

			return $result;
		}

		if ( function_exists( 'opcache_invalidate' ) && str_ends_with( $real, '.php' ) ) {
			@opcache_invalidate( $real, true );
		}

		$probe           = $this->probe();
		$result['probe'] = $probe;

		if ( 'broken' === $probe['status'] ) {
			$this->log->record( 'file.probe_failed', $relative, $probe );

			/**
			 * Fires when a post-write probe finds the site broken.
			 *
			 * Rollback is requested through an action rather than a direct call
			 * because Journal needs to trigger one too, and Journal → Restore
			 * would be circular (Restore depends on Journal). Writer uses the
			 * same route so there is one path into rollback, not two.
			 *
			 * Plugin::boot() registers the listener on every request — see
			 * Restore::register().
			 *
			 * @param string $change_id Change to roll back.
			 */
			do_action( 'wpsm_revert_stale_change', $change_id );

			$result['error'] = sprintf(
				/* translators: 1: file path, 2: HTTP status or error */
				__( 'Wrote %1$s but the site stopped responding correctly (%2$s), so the change was rolled back.', 'wp-safe-mode-ai' ),
				$relative,
				$probe['detail']
			);

			return $result;
		}

		$verified = 'ok' === $probe['status'];

		// An inconclusive probe (loopback blocked, DNS refused) is common on
		// locked-down hosts and is not evidence of breakage. Commit, but keep
		// the change in the fatal-handler mirror so a fatal in the next few
		// minutes still triggers an automatic rollback.
		$this->journal->commit( $change_id, [ 'probe' => $probe, 'verified' => $verified ], $verified );

		$result['ok']       = true;
		$result['verified'] = $verified;

		$this->log->record(
			'file.written',
			$relative,
			[
				'change_id' => $change_id,
				'created'   => ! $existed,
				'verified'  => $verified,
				'bytes'     => strlen( $contents ),
			]
		);

		return $result;
	}

	/**
	 * Delete a file, snapshotting it first.
	 *
	 * @return array{ok: bool, change_id: string|null, relative: string, error: string|null}
	 *
	 * @throws SandboxViolation
	 */
	public function delete( string $path ): array {
		$real     = $this->sandbox->resolve_for_write( $path );
		$relative = $this->sandbox->relative( $real );

		if ( ! is_file( $real ) ) {
			return [
				'ok'        => false,
				'change_id' => null,
				'relative'  => $relative,
				/* translators: %s: file path */
				'error'     => sprintf( __( '%s does not exist.', 'wp-safe-mode-ai' ), $relative ),
			];
		}

		$change_id = $this->journal->reserve_id();
		$snapshot  = ( new FileSnapshot( $this->store ) )->capture( $change_id, [ $real ] );

		$this->journal->open(
			[
				'change_id' => $change_id,
				'kind'      => 'file',
				'op'        => 'delete',
				'target'    => $relative,
				'files'     => $snapshot['files'],
				'meta'      => [ 'bytes_before' => (int) filesize( $real ) ],
			]
		);

		if ( ! @unlink( $real ) ) {
			$this->journal->fail( $change_id, 'delete_failed' );

			return [
				'ok'        => false,
				'change_id' => $change_id,
				'relative'  => $relative,
				/* translators: %s: file path */
				'error'     => sprintf( __( 'Could not delete %s.', 'wp-safe-mode-ai' ), $relative ),
			];
		}

		$probe = $this->probe();

		if ( 'broken' === $probe['status'] ) {
			do_action( 'wpsm_revert_stale_change', $change_id );

			return [
				'ok'        => false,
				'change_id' => $change_id,
				'relative'  => $relative,
				'error'     => sprintf(
					/* translators: 1: file path, 2: HTTP status or error */
					__( 'Deleted %1$s but the site stopped responding (%2$s), so it was restored.', 'wp-safe-mode-ai' ),
					$relative,
					$probe['detail']
				),
			];
		}

		$this->journal->commit( $change_id, [ 'probe' => $probe ], 'ok' === $probe['status'] );
		$this->log->record( 'file.deleted', $relative, [ 'change_id' => $change_id ] );

		return [
			'ok'        => true,
			'change_id' => $change_id,
			'relative'  => $relative,
			'error'     => null,
		];
	}

	// ------------------------------------------------------------------
	// Internals
	// ------------------------------------------------------------------

	/**
	 * Ask the site whether it is still alive.
	 *
	 * @return array{status: 'ok'|'broken'|'inconclusive', code: int|null, detail: string}
	 */
	private function probe(): array {
		$settings = get_option( 'wpsm_settings', [] );

		if ( is_array( $settings ) && isset( $settings['verify_probe'] ) && ! $settings['verify_probe'] ) {
			return [
				'status' => 'inconclusive',
				'code'   => null,
				'detail' => __( 'Verification probe disabled in settings.', 'wp-safe-mode-ai' ),
			];
		}

		$response = wp_remote_get(
			add_query_arg( 'wpsm_probe', (string) time(), home_url( '/' ) ),
			[
				'timeout'     => 10,
				'redirection' => 2,
				'sslverify'   => false, // Loopback to self; the certificate is frequently self-signed locally.
				'headers'     => [ 'X-WPSM-Probe' => '1' ],
			]
		);

		if ( is_wp_error( $response ) ) {
			return [
				'status' => 'inconclusive',
				'code'   => null,
				'detail' => $response->get_error_message(),
			];
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( $code >= 500 ) {
			return [
				'status' => 'broken',
				'code'   => $code,
				/* translators: %d: HTTP status code */
				'detail' => sprintf( __( 'HTTP %d', 'wp-safe-mode-ai' ), $code ),
			];
		}

		// A fatal that the recovery handler caught still renders as a 200 with
		// core's "technical difficulties" page, so status code alone is not
		// enough evidence.
		$body = (string) wp_remote_retrieve_body( $response );

		if ( str_contains( $body, 'There has been a critical error' ) ) {
			return [
				'status' => 'broken',
				'code'   => $code,
				'detail' => __( 'WordPress reported a critical error', 'wp-safe-mode-ai' ),
			];
		}

		return [
			'status' => 'ok',
			'code'   => $code,
			/* translators: %d: HTTP status code */
			'detail' => sprintf( __( 'HTTP %d', 'wp-safe-mode-ai' ), $code ),
		];
	}

	private function put( string $path, string $contents ): bool {
		$dir = dirname( $path );

		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			return false;
		}

		global $wp_filesystem;

		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		// WP_Filesystem() returns false when the host needs FTP credentials we
		// cannot prompt for inside a REST request. That is a normal outcome,
		// not an error — fall through to a direct write, which is what
		// WP_Filesystem_Direct would have done anyway.
		if ( WP_Filesystem() && $wp_filesystem instanceof \WP_Filesystem_Base ) {
			return (bool) $wp_filesystem->put_contents( $path, $contents, FS_CHMOD_FILE );
		}

		return false !== file_put_contents( $path, $contents, LOCK_EX );
	}

}
