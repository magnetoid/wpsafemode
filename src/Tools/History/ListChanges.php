<?php
/**
 * Change history tool.
 *
 * @package WPSM
 */

declare( strict_types = 1 );

namespace WPSM\Tools\History;

use WPSM\Snapshots\Journal;
use WPSM\Tools\Tool;

defined( 'ABSPATH' ) || exit;

/**
 * Lists journal entries so the agent can see what it — or anyone else — has
 * already changed, and find the change id needed to revert something.
 */
final class ListChanges extends Tool {

	public function name(): string {
		return 'list_changes';
	}

	public function title(): string {
		return __( 'List changes', 'wp-safe-mode-ai' );
	}

	public function description(): string {
		return 'List changes the toolkit has applied, newest first, with their change ids. '
			. 'Status is one of: pending (applied, not yet verified), committed (applied and the site responded), '
			. 'reverted (rolled back on request), auto_reverted (rolled back automatically after a fatal error), '
			. 'failed (never applied). '
			. 'Use the change id with revert_change to roll one back.';
	}

	/**
	 * @return array<string, mixed>
	 */
	public function input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'status' => [
					'type'        => 'string',
					'enum'        => [ 'pending', 'committed', 'reverted', 'auto_reverted', 'failed' ],
					'description' => 'Filter by status. Omit for all.',
				],
				'limit'  => [
					'type'        => 'integer',
					'description' => 'How many to return, up to 200. Defaults to 25.',
				],
			],
			'required'   => [],
		];
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>
	 */
	public function run( array $input ): array {
		$entries = $this->plugin->journal()->list(
			[
				'status' => $this->optional_string( $input, 'status' ),
				'limit'  => $this->optional_int( $input, 'limit', 25 ),
			]
		);

		// Trim the payload: full meta blobs are large and mostly uninteresting
		// to a reader deciding what to revert.
		return [
			'changes' => array_map(
				static fn( array $row ): array => [
					'change_id'   => $row['change_id'],
					'kind'        => $row['kind'],
					'op'          => $row['op'],
					'target'      => $row['target'],
					'status'      => $row['status'],
					'created_at'  => $row['created_at'],
					'resolved_at' => $row['resolved_at'],
					'reverted_by' => $row['meta']['reverted_by'] ?? null,
					'verified'    => $row['meta']['verified'] ?? null,
				],
				$entries
			),
			'pending_count' => count( $this->plugin->journal()->list( [ 'status' => Journal::PENDING, 'limit' => 200 ] ) ),
		];
	}
}
