<?php
/**
 * Append-only record of every change the toolkit applies.
 *
 * @package WPSM
 */

declare( strict_types = 1 );

namespace WPSM\Snapshots;

use WPSM\Audit\Log;

defined( 'ABSPATH' ) || exit;

/**
 * The journal is the source of truth for "what did we change, and can we take
 * it back". Every mutating operation opens an entry before touching anything
 * and closes it after verification.
 *
 * States:
 *   pending       applied to disk/db, not yet verified
 *   committed     applied and the site still responds
 *   reverted      rolled back on request
 *   auto_reverted rolled back by the fatal-error handler or reconciliation
 *   failed        never applied (lint rejection, write error)
 */
final class Journal {

	public const PENDING       = 'pending';
	public const COMMITTED     = 'committed';
	public const REVERTED      = 'reverted';
	public const AUTO_REVERTED = 'auto_reverted';
	public const FAILED        = 'failed';

	/**
	 * A change left pending for longer than this (seconds) is assumed to belong
	 * to a request that died before it could verify itself.
	 */
	private const STALE_AFTER = 120;

	public function __construct(
		private readonly Store $store,
		private readonly Log $log
	) {}

	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'wpsm_journal';
	}

	/**
	 * Allocate a change id before the entry exists.
	 *
	 * Callers snapshot under this id first, so the payload is already sitting
	 * at its final location by the time the row is written and a crash between
	 * the two leaves nothing to reconcile.
	 */
	public function reserve_id(): string {
		return 'chg_' . bin2hex( random_bytes( 10 ) );
	}

	/**
	 * Open a pending entry.
	 *
	 * @param array{
	 *   change_id?: string,
	 *   kind: string,
	 *   op: string,
	 *   target: string,
	 *   files?: array<int, array{path: string, snapshot: ?string, sha256: ?string, existed: bool}>,
	 *   inverse?: array<string, mixed>|null,
	 *   meta?: array<string, mixed>
	 * } $entry
	 *
	 * @return string Change id.
	 */
	public function open( array $entry ): string {
		global $wpdb;

		$change_id = $entry['change_id'] ?? $this->reserve_id();
		$now       = current_time( 'mysql', true );

		$wpdb->insert(
			self::table(),
			[
				'change_id'   => $change_id,
				'actor_id'    => get_current_user_id(),
				'kind'        => $entry['kind'],
				'op'          => $entry['op'],
				'target'      => $entry['target'],
				'status'      => self::PENDING,
				'snapshot_id' => $change_id,
				'inverse'     => isset( $entry['inverse'] ) ? (string) wp_json_encode( $entry['inverse'] ) : null,
				'meta'        => (string) wp_json_encode( $entry['meta'] ?? [] ),
				'created_at'  => $now,
			],
			[ '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ]
		);

		// Mirror to the flat file the fatal-error handler reads. Files only —
		// the handler restores bytes on disk; database rollback needs a live
		// WordPress and is handled by reconciliation instead.
		if ( ! empty( $entry['files'] ) ) {
			$pending               = $this->store->read_pending();
			$pending[ $change_id ] = [
				'change_id' => $change_id,
				'opened_at' => time(),
				'files'     => array_values( $entry['files'] ),
			];
			$this->store->write_pending( $pending );
		}

		return $change_id;
	}

	/**
	 * Mark a change applied.
	 *
	 * @param array<string, mixed> $meta     Merged into the existing meta blob.
	 * @param bool                 $verified Whether the site was confirmed healthy
	 *                                       afterwards. When it was not — usually
	 *                                       because loopback HTTP is blocked — the
	 *                                       entry stays in the fatal-handler mirror
	 *                                       so a fatal in the next few minutes still
	 *                                       triggers an automatic rollback. The
	 *                                       mirror entry ages out on its own.
	 */
	public function commit( string $change_id, array $meta = [], bool $verified = true ): void {
		$this->resolve( $change_id, self::COMMITTED, $meta );

		if ( $verified ) {
			$this->unmirror( $change_id );
		}

		$this->log->record( 'change.committed', $change_id, $meta );
	}

	/**
	 * Mark a change as never-applied.
	 *
	 * @param array<string, mixed> $meta
	 */
	public function fail( string $change_id, string $reason, array $meta = [] ): void {
		$this->resolve( $change_id, self::FAILED, $meta + [ 'reason' => $reason ] );
		$this->unmirror( $change_id );
		$this->log->record( 'change.failed', $change_id, [ 'reason' => $reason ] );
	}

	/**
	 * @param array<string, mixed> $meta
	 */
	public function mark_reverted( string $change_id, bool $automatic, array $meta = [] ): void {
		$status = $automatic ? self::AUTO_REVERTED : self::REVERTED;
		$this->resolve( $change_id, $status, $meta );
		$this->unmirror( $change_id );
		$this->log->record( 'change.' . $status, $change_id, $meta );
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function get( string $change_id ): ?array {
		global $wpdb;

		$table = self::table();
		$row   = $wpdb->get_row(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is not user input.
			$wpdb->prepare( "SELECT * FROM {$table} WHERE change_id = %s", $change_id ),
			ARRAY_A
		);

		return is_array( $row ) ? $this->hydrate( $row ) : null;
	}

	/**
	 * @param array{status?: string, kind?: string, limit?: int, offset?: int} $args
	 * @return array<int, array<string, mixed>>
	 */
	public function list( array $args = [] ): array {
		global $wpdb;

		$table  = self::table();
		$where  = [ '1=1' ];
		$params = [];

		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'status = %s';
			$params[] = $args['status'];
		}

		if ( ! empty( $args['kind'] ) ) {
			$where[]  = 'kind = %s';
			$params[] = $args['kind'];
		}

		$limit    = min( 200, max( 1, (int) ( $args['limit'] ?? 50 ) ) );
		$offset   = max( 0, (int) ( $args['offset'] ?? 0 ) );
		$params[] = $limit;
		$params[] = $offset;

		$sql = 'SELECT * FROM ' . $table
			. ' WHERE ' . implode( ' AND ', $where )
			. ' ORDER BY id DESC LIMIT %d OFFSET %d';

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- assembled from a fixed clause list.
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, ...$params ), ARRAY_A );

		return array_map( [ $this, 'hydrate' ], is_array( $rows ) ? $rows : [] );
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function pending(): array {
		return $this->list( [ 'status' => self::PENDING, 'limit' => 200 ] );
	}

	/**
	 * Resolve changes stranded in "pending" by a request that never finished.
	 *
	 * A pending file change means we wrote bytes and never confirmed the site
	 * still works. That is exactly the state we refuse to leave behind, so an
	 * unverifiable change is rolled back rather than assumed good.
	 */
	public function reconcile_stale_pending(): void {
		if ( ! is_admin() && ! wp_doing_cron() && ! ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		$mirror = $this->store->read_pending();
		if ( [] === $mirror ) {
			return;
		}

		if ( ! $this->acquire_lock() ) {
			return;
		}

		try {
			foreach ( $mirror as $change_id => $entry ) {
				$opened = (int) ( $entry['opened_at'] ?? 0 );

				if ( ( time() - $opened ) < self::STALE_AFTER ) {
					continue;
				}

				$this->log->record(
					'change.stale_detected',
					(string) $change_id,
					[ 'age_seconds' => time() - $opened ]
				);

				/*
				 * Hand off to Restore via the plugin container rather than
				 * holding a reference here — Journal must stay constructible
				 * without Restore, since Restore depends on Journal.
				 */
				do_action( 'wpsm_revert_stale_change', (string) $change_id );
			}
		} finally {
			$this->release_lock();
		}
	}

	/**
	 * Fold anything the fatal-error handler did into the journal.
	 *
	 * The handler runs on shutdown with no dependable database access, so it
	 * leaves a note on disk instead. This closes the loop: the change is marked
	 * auto_reverted and the rollback shows up in the audit log with the fatal
	 * that caused it.
	 */
	public function drain_recovery_reports(): void {
		foreach ( $this->store->take_reports() as $report ) {
			if ( ! is_array( $report ) ) {
				continue;
			}

			if ( 'handler_error' === ( $report['type'] ?? '' ) ) {
				$this->log->record( 'recovery.handler_error', '', $report );
				continue;
			}

			if ( 'auto_revert' !== ( $report['type'] ?? '' ) ) {
				continue;
			}

			$change_id = (string) ( $report['change_id'] ?? '' );

			if ( '' === $change_id ) {
				continue;
			}

			$this->resolve(
				$change_id,
				self::AUTO_REVERTED,
				[
					'reverted_by' => 'fatal_error_handler',
					'restored'    => $report['restored'] ?? [],
					'fatal'       => [
						'file'    => $report['error_file'] ?? null,
						'line'    => $report['error_line'] ?? null,
						'message' => $report['error_msg'] ?? null,
					],
				]
			);

			$this->unmirror( $change_id );
			$this->log->record( 'change.auto_reverted', $change_id, $report );
		}
	}

	/**
	 * Drop a change out of the fatal-handler mirror without touching its row.
	 */
	public function unmirror( string $change_id ): void {
		$pending = $this->store->read_pending();

		if ( ! isset( $pending[ $change_id ] ) ) {
			return;
		}

		unset( $pending[ $change_id ] );
		$this->store->write_pending( $pending );
	}

	// ------------------------------------------------------------------
	// Internals
	// ------------------------------------------------------------------

	/**
	 * @param array<string, mixed> $meta
	 */
	private function resolve( string $change_id, string $status, array $meta ): void {
		global $wpdb;

		$existing = $this->get( $change_id );
		$merged   = array_merge( is_array( $existing['meta'] ?? null ) ? $existing['meta'] : [], $meta );

		$wpdb->update(
			self::table(),
			[
				'status'      => $status,
				'meta'        => (string) wp_json_encode( $merged ),
				'resolved_at' => current_time( 'mysql', true ),
			],
			[ 'change_id' => $change_id ],
			[ '%s', '%s', '%s' ],
			[ '%s' ]
		);
	}

	/**
	 * @param array<string, mixed> $row
	 * @return array<string, mixed>
	 */
	private function hydrate( array $row ): array {
		$row['meta']    = json_decode( (string) ( $row['meta'] ?? '' ), true ) ?: [];
		$row['inverse'] = null !== ( $row['inverse'] ?? null )
			? ( json_decode( (string) $row['inverse'], true ) ?: null )
			: null;

		return $row;
	}

	private function acquire_lock(): bool {
		if ( false !== get_transient( 'wpsm_reconcile_lock' ) ) {
			return false;
		}

		set_transient( 'wpsm_reconcile_lock', 1, 60 );

		return true;
	}

	private function release_lock(): void {
		delete_transient( 'wpsm_reconcile_lock' );
	}
}
