<?php
/**
 * Dry-run an edit without proposing it.
 *
 * @package WPSM
 */

declare( strict_types = 1 );

namespace WPSM\Tools\File;

use WPSM\Tools\Tool;

defined( 'ABSPATH' ) || exit;

/**
 * Shows what a write would do, without queueing anything for approval.
 *
 * Exists so the agent can check its own work — lint a candidate edit, look at
 * the diff, and revise — without putting a proposal in front of a human for
 * every intermediate attempt.
 */
final class PreviewEdit extends Tool {

	public function name(): string {
		return 'preview_edit';
	}

	public function title(): string {
		return __( 'Preview edit', 'wp-safe-mode-ai' );
	}

	public function description(): string {
		return 'Dry-run a file edit: returns the unified diff and the PHP syntax check result without changing '
			. 'anything and without asking anyone to approve it. '
			. 'Use this to check your own work before calling write_file — especially to confirm an edit parses, '
			. 'and to confirm the diff only touches what you intended.';
	}

	/**
	 * @return array<string, mixed>
	 */
	public function input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'path'     => [
					'type'        => 'string',
					'description' => 'File path relative to wp-content.',
				],
				'contents' => [
					'type'        => 'string',
					'description' => 'The complete candidate contents.',
				],
			],
			'required'   => [ 'path', 'contents' ],
		];
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>
	 */
	public function run( array $input ): array {
		$path     = $this->required_string( $input, 'path' );
		$contents = (string) ( $input['contents'] ?? '' );

		$real     = $this->plugin->sandbox()->resolve_for_write( $path );
		$relative = $this->plugin->sandbox()->relative( $real );
		$before   = is_file( $real ) ? (string) file_get_contents( $real ) : '';

		return [
			'path'     => $relative,
			'creating' => ! is_file( $real ),
			'lint'     => $this->plugin->lint()->check( $contents, $real ),
			'diff'     => $this->plugin->diff()->unified( $before, $contents, $relative ),
		];
	}

	/**
	 * @param array<string, mixed> $input
	 */
	public function summarise( array $input ): string {
		/* translators: %s: file path */
		return sprintf( __( 'Preview edit to %s', 'wp-safe-mode-ai' ), $this->optional_string( $input, 'path' ) );
	}
}
