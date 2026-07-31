<?php
/**
 * Read-only database inspection.
 *
 * @package WPSM
 */

declare( strict_types = 1 );

namespace WPSM\Db;

defined( 'ABSPATH' ) || exit;

/**
 * Gives the agent enough database context to reason about a site without
 * granting it any ability to change one.
 *
 * Every query is parsed before it runs. The guard is intentionally strict and
 * refuses anything it does not positively recognise, rather than trying to
 * enumerate what is dangerous — the list of things that can hurt you in SQL is
 * longer than the list of things this needs to support.
 */
final class Browser {

	/** Statements that may run. Everything else is refused. */
	private const ALLOWED_VERBS = [ 'select', 'show', 'describe', 'desc', 'explain' ];

	/**
	 * Constructs that are read-only in the strict sense but still off limits:
	 * they touch the filesystem, exfiltrate, or tie up the server.
	 */
	private const FORBIDDEN = [
		'into outfile',
		'into dumpfile',
		'load_file',
		'load data',
		'benchmark(',
		'sleep(',
		'get_lock(',
		'sys_exec',
	];

	/** Never returned, whatever the query asks for. */
	private const REDACTED_COLUMNS = [ 'user_pass', 'user_activation_key' ];

	private const DEFAULT_LIMIT = 100;
	private const MAX_LIMIT     = 1000;

	/**
	 * Every table in the database with its size.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function tables(): array {
		global $wpdb;

		$rows = $wpdb->get_results( 'SHOW TABLE STATUS', ARRAY_A );

		if ( ! is_array( $rows ) ) {
			return [];
		}

		$tables = [];

		foreach ( $rows as $row ) {
			$tables[] = [
				'name'        => (string) $row['Name'],
				'rows'        => (int) ( $row['Rows'] ?? 0 ),
				'engine'      => (string) ( $row['Engine'] ?? '' ),
				'bytes'       => (int) ( $row['Data_length'] ?? 0 ) + (int) ( $row['Index_length'] ?? 0 ),
				'collation'   => (string) ( $row['Collation'] ?? '' ),
				'is_wp_core'  => $this->is_core_table( (string) $row['Name'] ),
			];
		}

		usort( $tables, static fn( array $a, array $b ): int => strcmp( $a['name'], $b['name'] ) );

		return $tables;
	}

	/**
	 * Column definitions for one table.
	 *
	 * @return array<int, array<string, mixed>>
	 *
	 * @throws \InvalidArgumentException If the table does not exist.
	 */
	public function describe( string $table ): array {
		global $wpdb;

		$table = $this->assert_table_exists( $table );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- validated against SHOW TABLES above.
		$rows = $wpdb->get_results( "DESCRIBE `{$table}`", ARRAY_A );

		if ( ! is_array( $rows ) ) {
			return [];
		}

		return array_map(
			static fn( array $row ): array => [
				'field'    => (string) $row['Field'],
				'type'     => (string) $row['Type'],
				'null'     => 'YES' === ( $row['Null'] ?? '' ),
				'key'      => (string) ( $row['Key'] ?? '' ),
				'default'  => $row['Default'],
				'extra'    => (string) ( $row['Extra'] ?? '' ),
				'redacted' => in_array( strtolower( (string) $row['Field'] ), self::REDACTED_COLUMNS, true ),
			],
			$rows
		);
	}

	/**
	 * Paginated rows from one table, without needing the caller to write SQL.
	 *
	 * @return array{table: string, total: int, limit: int, offset: int, columns: array<int, string>, rows: array<int, array<string, mixed>>}
	 *
	 * @throws \InvalidArgumentException
	 */
	public function preview( string $table, int $limit = self::DEFAULT_LIMIT, int $offset = 0 ): array {
		global $wpdb;

		$table  = $this->assert_table_exists( $table );
		$limit  = max( 1, min( self::MAX_LIMIT, $limit ) );
		$offset = max( 0, $offset );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- validated against SHOW TABLES above.
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" );

		$rows = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- validated table name; bounds are prepared.
			$wpdb->prepare( "SELECT * FROM `{$table}` LIMIT %d OFFSET %d", $limit, $offset ),
			ARRAY_A
		);

		$rows = is_array( $rows ) ? array_map( [ $this, 'redact_row' ], $rows ) : [];

		return [
			'table'   => $table,
			'total'   => $total,
			'limit'   => $limit,
			'offset'  => $offset,
			'columns' => [] === $rows ? [] : array_keys( $rows[0] ),
			'rows'    => $rows,
		];
	}

	/**
	 * Run a guarded read-only query.
	 *
	 * @return array{sql: string, rows: array<int, array<string, mixed>>, count: int, truncated: bool}
	 *
	 * @throws \InvalidArgumentException If the statement is not permitted.
	 */
	public function query( string $sql, int $limit = self::DEFAULT_LIMIT ): array {
		global $wpdb;

		$limit    = max( 1, min( self::MAX_LIMIT, $limit ) );
		$prepared = $this->guard( $sql, $limit );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- statement is validated by guard() and read-only.
		$rows = $wpdb->get_results( $prepared, ARRAY_A );

		if ( ! is_array( $rows ) ) {
			$error = $wpdb->last_error;

			throw new \InvalidArgumentException(
				'' !== $error
					? $error
					: __( 'The query returned no result set.', 'wp-safe-mode-ai' )
			);
		}

		$rows = array_map( [ $this, 'redact_row' ], $rows );

		return [
			'sql'       => $prepared,
			'rows'      => $rows,
			'count'     => count( $rows ),
			'truncated' => count( $rows ) >= $limit,
		];
	}

	// ------------------------------------------------------------------
	// Guard
	// ------------------------------------------------------------------

	/**
	 * Validate a statement and return it with a LIMIT applied.
	 *
	 * @throws \InvalidArgumentException
	 */
	private function guard( string $sql, int $limit ): string {
		$sql = trim( $sql );

		if ( '' === $sql ) {
			throw new \InvalidArgumentException( __( 'No query was given.', 'wp-safe-mode-ai' ) );
		}

		// Strip comments before analysis. They are valid SQL, but leaving them
		// in means the verb check can be fooled by `/*x*/DELETE`.
		$stripped = $this->strip_comments( $sql );
		$stripped = trim( $stripped );

		// One statement only. A trailing semicolon is fine; a second statement
		// after it is not.
		$body = rtrim( $stripped, "; \t\n\r" );

		if ( str_contains( $body, ';' ) ) {
			throw new \InvalidArgumentException(
				__( 'Only one statement at a time. Remove the semicolon and everything after it.', 'wp-safe-mode-ai' )
			);
		}

		$verb = strtolower( (string) preg_replace( '/^\W*(\w+).*$/s', '$1', $body ) );

		if ( ! in_array( $verb, self::ALLOWED_VERBS, true ) ) {
			throw new \InvalidArgumentException(
				sprintf(
					/* translators: 1: the SQL verb used, 2: comma-separated list of allowed verbs */
					__( '%1$s statements are not available. This tool is read-only and accepts: %2$s.', 'wp-safe-mode-ai' ),
					strtoupper( $verb ?: '?' ),
					strtoupper( implode( ', ', self::ALLOWED_VERBS ) )
				)
			);
		}

		$lowered = strtolower( $body );

		foreach ( self::FORBIDDEN as $needle ) {
			if ( str_contains( $lowered, $needle ) ) {
				throw new \InvalidArgumentException(
					sprintf(
						/* translators: %s: the forbidden SQL construct */
						__( 'The query uses %s, which is not permitted even in a read-only statement.', 'wp-safe-mode-ai' ),
						strtoupper( trim( $needle, '(' ) )
					)
				);
			}
		}

		// SHOW/DESCRIBE/EXPLAIN return bounded result sets and reject LIMIT in
		// several of their forms, so only SELECT gets one appended.
		if ( 'select' === $verb && ! preg_match( '/\blimit\s+\d/i', $body ) ) {
			$body .= ' LIMIT ' . $limit;
		}

		return $body;
	}

	private function strip_comments( string $sql ): string {
		$sql = (string) preg_replace( '#/\*.*?\*/#s', ' ', $sql );
		$sql = (string) preg_replace( '/--[^\n]*/', ' ', $sql );
		$sql = (string) preg_replace( '/#[^\n]*/', ' ', $sql );

		return $sql;
	}

	/**
	 * @throws \InvalidArgumentException
	 */
	private function assert_table_exists( string $table ): string {
		global $wpdb;

		// Backtick-quoting is not enough on its own — verify the name against
		// the live schema so nothing that is not a real table ever reaches a
		// query string.
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );

		if ( ! is_string( $found ) || '' === $found ) {
			throw new \InvalidArgumentException(
				sprintf(
					/* translators: %s: table name */
					__( 'There is no table called %s.', 'wp-safe-mode-ai' ),
					$table
				)
			);
		}

		return $found;
	}

	/**
	 * @param array<string, mixed> $row
	 * @return array<string, mixed>
	 */
	private function redact_row( array $row ): array {
		foreach ( $row as $column => $value ) {
			if ( in_array( strtolower( (string) $column ), self::REDACTED_COLUMNS, true ) ) {
				$row[ $column ] = null === $value ? null : '[redacted]';
			}
		}

		return $row;
	}

	private function is_core_table( string $name ): bool {
		global $wpdb;

		$core = [
			'posts', 'postmeta', 'options', 'users', 'usermeta', 'terms',
			'termmeta', 'term_taxonomy', 'term_relationships', 'comments',
			'commentmeta', 'links',
		];

		foreach ( $core as $suffix ) {
			if ( $wpdb->prefix . $suffix === $name ) {
				return true;
			}
		}

		return false;
	}
}
