<?php
/**
 * Table row preview tool.
 *
 * @package WPSM
 */

declare( strict_types = 1 );

namespace WPSM\Tools\Db;

use WPSM\Tools\Tool;

defined( 'ABSPATH' ) || exit;

/**
 * Paginated rows from a table, without the caller writing SQL.
 */
final class PreviewTable extends Tool {

	public function name(): string {
		return 'db_preview_table';
	}

	public function title(): string {
		return __( 'Preview table rows', 'wp-safe-mode-ai' );
	}

	public function description(): string {
		return 'Return rows from a table with an exact total count, without writing SQL. '
			. 'Use this for a quick look at data; use db_query when you need to filter, join or aggregate.';
	}

	/**
	 * @return array<string, mixed>
	 */
	public function input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'table'  => [
					'type'        => 'string',
					'description' => 'Full table name including the prefix.',
				],
				'limit'  => [
					'type'        => 'integer',
					'description' => 'Rows to return, up to 1000. Defaults to 20.',
				],
				'offset' => [
					'type'        => 'integer',
					'description' => 'Rows to skip. Defaults to 0.',
				],
			],
			'required'   => [ 'table' ],
		];
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>
	 */
	public function run( array $input ): array {
		return $this->plugin->db()->preview(
			$this->required_string( $input, 'table' ),
			$this->optional_int( $input, 'limit', 20 ),
			$this->optional_int( $input, 'offset', 0 )
		);
	}

	/**
	 * @param array<string, mixed> $input
	 */
	public function summarise( array $input ): string {
		/* translators: %s: table name */
		return sprintf( __( 'Preview %s', 'wp-safe-mode-ai' ), $this->optional_string( $input, 'table' ) );
	}
}
