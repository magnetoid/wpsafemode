<?php
/**
 * Append-only audit trail.
 *
 * @package WPSM
 */

declare( strict_types = 1 );

namespace WPSM\Audit;

defined( 'ABSPATH' ) || exit;

/**
 * Records every security-relevant action: proposals raised and resolved,
 * changes applied and reverted, safe-mode sessions opened, drop-ins installed,
 * pairing secrets rotated.
 *
 * Nothing in this class ever updates or deletes a row. Retention is enforced
 * by age only, and by a scheduled task rather than inline.
 */
final class Log {

	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'wpsm_audit';
	}

	/**
	 * @param array<string, mixed> $detail
	 */
	public function record( string $event, string $subject = '', array $detail = [] ): void {
		global $wpdb;

		$wpdb->insert(
			self::table(),
			[
				'ts'       => current_time( 'mysql', true ),
				'actor_id' => get_current_user_id(),
				'event'    => $event,
				'subject'  => mb_substr( $subject, 0, 500 ),
				'detail'   => (string) wp_json_encode( $this->redact( $detail ) ),
				'ip'       => $this->client_ip(),
			],
			[ '%s', '%d', '%s', '%s', '%s', '%s' ]
		);
	}

	/**
	 * @param array{event?: string, limit?: int, offset?: int} $args
	 * @return array<int, array<string, mixed>>
	 */
	public function list( array $args = [] ): array {
		global $wpdb;

		$table  = self::table();
		$where  = [ '1=1' ];
		$params = [];

		if ( ! empty( $args['event'] ) ) {
			$where[]  = 'event LIKE %s';
			$params[] = $wpdb->esc_like( (string) $args['event'] ) . '%';
		}

		$params[] = min( 500, max( 1, (int) ( $args['limit'] ?? 100 ) ) );
		$params[] = max( 0, (int) ( $args['offset'] ?? 0 ) );

		$sql = 'SELECT * FROM ' . $table
			. ' WHERE ' . implode( ' AND ', $where )
			. ' ORDER BY id DESC LIMIT %d OFFSET %d';

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- assembled from a fixed clause list.
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, ...$params ), ARRAY_A );

		if ( ! is_array( $rows ) ) {
			return [];
		}

		foreach ( $rows as &$row ) {
			$row['detail'] = json_decode( (string) $row['detail'], true ) ?: [];
		}

		return $rows;
	}

	/**
	 * Strip anything that looks like a credential before it reaches storage.
	 *
	 * The audit log is read by the admin UI and exported for review, so it must
	 * never become the place a leaked API key ends up.
	 *
	 * @param array<string, mixed> $detail
	 * @return array<string, mixed>
	 */
	private function redact( array $detail ): array {
		$sensitive = [ 'api_key', 'apikey', 'secret', 'token', 'password', 'authorization', 'signature' ];

		foreach ( $detail as $key => $value ) {
			$needle = strtolower( (string) $key );

			foreach ( $sensitive as $marker ) {
				if ( str_contains( $needle, $marker ) ) {
					$detail[ $key ] = '[redacted]';
					continue 2;
				}
			}

			if ( is_array( $value ) ) {
				$detail[ $key ] = $this->redact( $value );
			}
		}

		return $detail;
	}

	private function client_ip(): string {
		$raw = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '';
		$ip  = filter_var( wp_unslash( $raw ), FILTER_VALIDATE_IP );

		return is_string( $ip ) ? $ip : '';
	}
}
