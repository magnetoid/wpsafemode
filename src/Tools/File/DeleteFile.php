<?php
/**
 * File deletion tool.
 *
 * @package WPSM
 */

declare( strict_types = 1 );

namespace WPSM\Tools\File;

use WPSM\Tools\Tool;

defined( 'ABSPATH' ) || exit;

/**
 * Deletes a file, after snapshotting it so the delete is reversible.
 */
final class DeleteFile extends Tool {

	public function name(): string {
		return 'delete_file';
	}

	public function title(): string {
		return __( 'Delete file', 'wp-safe-mode-ai' );
	}

	public function description(): string {
		return 'Delete a file inside the WordPress content area. '
			. 'The file is snapshotted first, so the deletion can be reverted from the change history. '
			. 'Prefer write_file when you mean to replace contents — deleting and recreating loses file permissions.';
	}

	/**
	 * @return array<string, mixed>
	 */
	public function input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'path'   => [
					'type'        => 'string',
					'description' => 'File path relative to wp-content.',
				],
				'reason' => [
					'type'        => 'string',
					'description' => 'One sentence on why, shown to the person approving it.',
				],
			],
			'required'   => [ 'path' ],
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
		$real     = $this->plugin->sandbox()->resolve_for_write( $this->required_string( $input, 'path' ) );
		$relative = $this->plugin->sandbox()->relative( $real );
		$exists   = is_file( $real );

		return [
			'kind'    => 'file_delete',
			'path'    => $relative,
			'exists'  => $exists,
			'bytes'   => $exists ? (int) filesize( $real ) : 0,
			'reason'  => $this->optional_string( $input, 'reason' ),
			'blocked' => ! $exists,
		];
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>
	 */
	public function run( array $input ): array {
		return $this->plugin->writer()->delete( $this->required_string( $input, 'path' ) );
	}

	/**
	 * @param array<string, mixed> $input
	 */
	public function summarise( array $input ): string {
		/* translators: %s: file path */
		return sprintf( __( 'Delete %s', 'wp-safe-mode-ai' ), $this->optional_string( $input, 'path' ) );
	}
}
