<?php
/**
 * Guarded read-only query tool.
 *
 * @package WPSM
 */

declare( strict_types = 1 );

namespace WPSM\Tools\Db;

use WPSM\Tools\Tool;

defined( 'ABSPATH' ) || exit;

/**
 * Runs a read-only SQL statement through the guard in Db\Browser.
 *
 * Read-only is enforced by parsing, not by hoping. Anything that is not a
 * SELECT, SHOW, DESCRIBE or EXPLAIN is refused, as is anything touching the
 * filesystem or able to stall the server.
 */
final class Query extends Tool {

	public function name(): string {
		return 'db_query';
	}

	public function title(): string {
		return __( 'Run a read-only query', 'wp-safe-mode-ai' );
	}

	public function description(): string {
		return 'Run one read-only SQL statement. Only SELECT, SHOW, DESCRIBE and EXPLAIN are accepted; '
			. 'anything that would modify data is refused, as are multiple statements in one call. '
			. 'A LIMIT is added automatically if you do not supply one. '
			. 'Password and activation-key columns are always returned redacted. '
			. 'This tool cannot change anything — writing to the database is not available in this version.';
	}

	/**
	 * @return array<string, mixed>
	 */
	public function input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'sql'   => [
					'type'        => 'string',
					'description' => 'One SELECT, SHOW, DESCRIBE or EXPLAIN statement. No trailing semicolon needed.',
				],
				'limit' => [
					'type'        => 'integer',
					'description' => 'Maximum rows, up to 1000. Defaults to 100. Ignored if the statement already has a LIMIT.',
				],
			],
			'required'   => [ 'sql' ],
		];
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>
	 */
	public function run( array $input ): array {
		return $this->plugin->db()->query(
			$this->required_string( $input, 'sql' ),
			$this->optional_int( $input, 'limit', 100 )
		);
	}

	/**
	 * @param array<string, mixed> $input
	 */
	public function summarise( array $input ): string {
		$sql = $this->optional_string( $input, 'sql' );

		return sprintf(
			/* translators: %s: the SQL statement, truncated */
			__( 'Query: %s', 'wp-safe-mode-ai' ),
			mb_strimwidth( $sql, 0, 80, '…' )
		);
	}
}
