<?php
/**
 * File read tool.
 *
 * @package WPSM
 */

declare( strict_types = 1 );

namespace WPSM\Tools\File;

use WPSM\Tools\Tool;

defined( 'ABSPATH' ) || exit;

/**
 * Reads one file and returns its contents plus a content hash.
 *
 * The hash matters: write_file accepts it back as expected_sha256, which is how
 * an edit built from a stale read gets rejected instead of silently clobbering
 * whatever changed in between.
 */
final class ReadFile extends Tool {

	public function name(): string {
		return 'read_file';
	}

	public function title(): string {
		return __( 'Read file', 'wp-safe-mode-ai' );
	}

	public function description(): string {
		return 'Read a file inside the WordPress content area. '
			. 'Returns the contents and a sha256 hash. '
			. 'Pass that hash back as expected_sha256 when you call write_file, so the write is rejected '
			. 'if anything else changed the file while you were working on it.';
	}

	/**
	 * @return array<string, mixed>
	 */
	public function input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'path' => [
					'type'        => 'string',
					'description' => 'File path relative to wp-content, for example "themes/my-theme/functions.php".',
				],
			],
			'required'   => [ 'path' ],
		];
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>
	 */
	public function run( array $input ): array {
		return $this->plugin->reader()->read( $this->required_string( $input, 'path' ) );
	}

	/**
	 * @param array<string, mixed> $input
	 */
	public function summarise( array $input ): string {
		/* translators: %s: file path */
		return sprintf( __( 'Read %s', 'wp-safe-mode-ai' ), $this->optional_string( $input, 'path' ) );
	}
}
