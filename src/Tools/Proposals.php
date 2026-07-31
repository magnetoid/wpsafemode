<?php
/**
 * Pending mutating tool calls awaiting human approval.
 *
 * @package WPSM
 */

declare( strict_types = 1 );

namespace WPSM\Tools;

use WPSM\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * The queue between "the model asked to change something" and "the change
 * happened".
 *
 * Proposals are stored rather than held in memory so the decision survives a
 * page reload, and so an approved-but-not-yet-run call has a durable record if
 * the request executing it dies partway.
 */
final class Proposals {

	public const PENDING  = 'pending';
	public const APPROVED = 'approved';
	public const REJECTED = 'rejected';
	public const EXPIRED  = 'expired';
	public const EXECUTED = 'executed';

	private const TTL = 1800;

	public function __construct( private readonly Plugin $plugin ) {}

	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'wpsm_proposals';
	}

	/**
	 * @param array<string, mixed> $input
	 * @param array<string, mixed> $preview
	 */
	public function create( Tool $tool, array $input, array $preview ): string {
		global $wpdb;

		$id  = 'prop_' . bin2hex( random_bytes( 10 ) );
		$now = time();

		$wpdb->insert(
			self::table(),
			[
				'proposal_id' => $id,
				'actor_id'    => get_current_user_id(),
				'tool'        => $tool->name(),
				'payload'     => (string) wp_json_encode( $input ),
				'preview'     => (string) wp_json_encode( $preview ),
				'status'      => self::PENDING,
				'created_at'  => gmdate( 'Y-m-d H:i:s', $now ),
				'expires_at'  => gmdate( 'Y-m-d H:i:s', $now + self::TTL ),
			],
			[ '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s' ]
		);

		$this->plugin->log()->record(
			'proposal.created',
			$id,
			[
				'tool'    => $tool->name(),
				'summary' => $tool->summarise( $input ),
			]
		);

		return $id;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function get( string $id ): ?array {
		global $wpdb;

		$table = self::table();
		$row   = $wpdb->get_row(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is not user input.
			$wpdb->prepare( "SELECT * FROM {$table} WHERE proposal_id = %s", $id ),
			ARRAY_A
		);

		return is_array( $row ) ? $this->hydrate( $row ) : null;
	}

	/**
	 * @param array{status?: string, limit?: int} $args
	 * @return array<int, array<string, mixed>>
	 */
	public function list( array $args = [] ): array {
		global $wpdb;

		$this->expire_stale();

		$table  = self::table();
		$status = $args['status'] ?? self::PENDING;
		$limit  = min( 100, max( 1, (int) ( $args['limit'] ?? 50 ) ) );

		$rows = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is not user input.
			$wpdb->prepare( "SELECT * FROM {$table} WHERE status = %s ORDER BY id DESC LIMIT %d", $status, $limit ),
			ARRAY_A
		);

		return array_map( [ $this, 'hydrate' ], is_array( $rows ) ? $rows : [] );
	}

	/**
	 * Approve and run.
	 *
	 * @return array<string, mixed>
	 */
	public function approve( string $id ): array {
		$this->expire_stale();

		$proposal = $this->get( $id );

		if ( null === $proposal ) {
			return [
				'ok'    => false,
				/* translators: %s: proposal identifier */
				'error' => sprintf( __( 'Proposal %s no longer exists.', 'wp-safe-mode-ai' ), $id ),
			];
		}

		if ( self::PENDING !== $proposal['status'] ) {
			return [
				'ok'    => false,
				'error' => sprintf(
					/* translators: 1: proposal identifier, 2: current status */
					__( 'Proposal %1$s cannot be approved because it is already %2$s.', 'wp-safe-mode-ai' ),
					$id,
					$proposal['status']
				),
			];
		}

		$tool = $this->plugin->tools()->get( (string) $proposal['tool'] );

		if ( null === $tool ) {
			$this->set_status( $id, self::REJECTED, [ 'reason' => 'tool_missing' ] );

			return [
				'ok'    => false,
				/* translators: %s: tool name */
				'error' => sprintf( __( 'The %s tool is no longer registered.', 'wp-safe-mode-ai' ), $proposal['tool'] ),
			];
		}

		// Mark approved before running, so a request that dies mid-execution
		// leaves a record that someone said yes rather than looking like it was
		// never asked.
		$this->set_status( $id, self::APPROVED );
		$this->plugin->log()->record( 'proposal.approved', $id, [ 'tool' => $tool->name() ] );

		$result = $this->plugin->tools()->execute( $tool, (array) $proposal['payload'] );

		$this->set_status( $id, self::EXECUTED, [], $result );

		return $result;
	}

	public function reject( string $id, string $reason = '' ): bool {
		$proposal = $this->get( $id );

		if ( null === $proposal || self::PENDING !== $proposal['status'] ) {
			return false;
		}

		$this->set_status( $id, self::REJECTED, [ 'reason' => $reason ] );
		$this->plugin->log()->record( 'proposal.rejected', $id, [ 'reason' => $reason ] );

		return true;
	}

	/**
	 * Time out anything left sitting.
	 *
	 * An unanswered proposal is a "no". Leaving them pending indefinitely means
	 * a stale approval could be clicked days later against a site that has
	 * moved on.
	 */
	public function expire_stale(): void {
		global $wpdb;

		$table = self::table();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is not user input.
		$wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"UPDATE {$table} SET status = %s, resolved_at = %s WHERE status = %s AND expires_at < %s",
				self::EXPIRED,
				gmdate( 'Y-m-d H:i:s' ),
				self::PENDING,
				gmdate( 'Y-m-d H:i:s' )
			)
		);
	}

	// ------------------------------------------------------------------
	// Internals
	// ------------------------------------------------------------------

	/**
	 * @param array<string, mixed> $extra
	 * @param array<string, mixed>|null $result
	 */
	private function set_status( string $id, string $status, array $extra = [], ?array $result = null ): void {
		global $wpdb;

		$data    = [
			'status'      => $status,
			'resolved_at' => gmdate( 'Y-m-d H:i:s' ),
		];
		$formats = [ '%s', '%s' ];

		if ( null !== $result || [] !== $extra ) {
			$data['result'] = (string) wp_json_encode( [ 'result' => $result ] + $extra );
			$formats[]      = '%s';
		}

		$wpdb->update( self::table(), $data, [ 'proposal_id' => $id ], $formats, [ '%s' ] );
	}

	/**
	 * @param array<string, mixed> $row
	 * @return array<string, mixed>
	 */
	private function hydrate( array $row ): array {
		$row['payload'] = json_decode( (string) ( $row['payload'] ?? '' ), true ) ?: [];
		$row['preview'] = json_decode( (string) ( $row['preview'] ?? '' ), true ) ?: [];
		$row['result']  = null !== ( $row['result'] ?? null )
			? ( json_decode( (string) $row['result'], true ) ?: null )
			: null;

		return $row;
	}
}
