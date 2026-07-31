<?php
/**
 * Rollback tool.
 *
 * @package WPSM
 */

declare( strict_types = 1 );

namespace WPSM\Tools\History;

use WPSM\Tools\Tool;

defined( 'ABSPATH' ) || exit;

/**
 * Restores the files captured before a change was applied.
 */
final class RevertChange extends Tool {

	public function name(): string {
		return 'revert_change';
	}

	public function title(): string {
		return __( 'Revert change', 'wp-safe-mode-ai' );
	}

	public function description(): string {
		return 'Roll a change back to its snapshot, restoring the files exactly as they were before it was applied. '
			. 'Files the change created are deleted. '
			. 'Get the change id from list_changes.';
	}

	/**
	 * @return array<string, mixed>
	 */
	public function input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'change_id' => [
					'type'        => 'string',
					'description' => 'The change id from list_changes, for example "chg_a1b2c3...".',
				],
			],
			'required'   => [ 'change_id' ],
		];
	}

	public function mutates(): bool {
		return true;
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>
	 */
	public function preview( array $input ): array {
		$change_id = $this->required_string( $input, 'change_id' );
		$entry     = $this->plugin->journal()->get( $change_id );
		$manifest  = $this->plugin->restore()->manifest( $change_id );

		if ( null === $entry ) {
			return [
				'kind'    => 'revert',
				'blocked' => true,
				/* translators: %s: change identifier */
				'error'   => sprintf( __( 'No change called %s.', 'wp-safe-mode-ai' ), $change_id ),
			];
		}

		$files = [];

		foreach ( $manifest['files'] ?? [] as $file ) {
			$files[] = [
				'path'   => $this->plugin->sandbox()->relative( (string) $file['path'] ),
				'action' => empty( $file['existed'] )
					? __( 'delete (this change created it)', 'wp-safe-mode-ai' )
					: __( 'restore previous contents', 'wp-safe-mode-ai' ),
				'bytes'  => $file['bytes'] ?? 0,
			];
		}

		return [
			'kind'      => 'revert',
			'change_id' => $change_id,
			'target'    => $entry['target'],
			'status'    => $entry['status'],
			'files'     => $files,
			'blocked'   => null === $manifest,
			'error'     => null === $manifest
				? __( 'The snapshot for this change is no longer on disk, so it cannot be rolled back.', 'wp-safe-mode-ai' )
				: null,
		];
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>
	 */
	public function run( array $input ): array {
		return $this->plugin->restore()->revert( $this->required_string( $input, 'change_id' ), false );
	}

	/**
	 * @param array<string, mixed> $input
	 */
	public function summarise( array $input ): string {
		/* translators: %s: change identifier */
		return sprintf( __( 'Revert %s', 'wp-safe-mode-ai' ), $this->optional_string( $input, 'change_id' ) );
	}
}
