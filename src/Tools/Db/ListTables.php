<?php
/**
 * Table listing tool.
 *
 * @package WPSM
 */

declare( strict_types = 1 );

namespace WPSM\Tools\Db;

use WPSM\Tools\Tool;

defined( 'ABSPATH' ) || exit;

/**
 * Lists every table with its row count and size.
 */
final class ListTables extends Tool {

	public function name(): string {
		return 'db_list_tables';
	}

	public function title(): string {
		return __( 'List database tables', 'wp-safe-mode-ai' );
	}

	public function description(): string {
		return 'List every table in the WordPress database with its approximate row count, size and engine. '
			. 'Row counts from the storage engine are estimates for InnoDB — use db_preview if you need an exact count.';
	}

	/**
	 * @return array<string, mixed>
	 */
	public function input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => new \stdClass(),
			'required'   => [],
		];
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>
	 */
	public function run( array $input ): array {
		global $wpdb;

		return [
			'prefix' => $wpdb->prefix,
			'tables' => $this->plugin->db()->tables(),
		];
	}
}
