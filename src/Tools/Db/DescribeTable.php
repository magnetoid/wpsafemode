<?php
/**
 * Table schema tool.
 *
 * @package WPSM
 */

declare( strict_types = 1 );

namespace WPSM\Tools\Db;

use WPSM\Tools\Tool;

defined( 'ABSPATH' ) || exit;

/**
 * Returns column definitions for one table.
 */
final class DescribeTable extends Tool {

	public function name(): string {
		return 'db_describe_table';
	}

	public function title(): string {
		return __( 'Describe table', 'wp-safe-mode-ai' );
	}

	public function description(): string {
		return 'Return the column definitions for one table: name, type, nullability, key and default. '
			. 'Columns marked redacted are never returned by any query — password and activation-key columns '
			. 'come back as a placeholder.';
	}

	/**
	 * @return array<string, mixed>
	 */
	public function input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'table' => [
					'type'        => 'string',
					'description' => 'Full table name including the prefix, for example "wp_posts".',
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
		$table = $this->required_string( $input, 'table' );

		return [
			'table'   => $table,
			'columns' => $this->plugin->db()->describe( $table ),
		];
	}

	/**
	 * @param array<string, mixed> $input
	 */
	public function summarise( array $input ): string {
		/* translators: %s: table name */
		return sprintf( __( 'Describe %s', 'wp-safe-mode-ai' ), $this->optional_string( $input, 'table' ) );
	}
}
