<?php
/**
 * Directory listing tool.
 *
 * @package WPSM
 */

declare( strict_types = 1 );

namespace WPSM\Tools\File;

use WPSM\Tools\Tool;

defined( 'ABSPATH' ) || exit;

/**
 * Lists one directory level inside the sandbox.
 */
final class ListFiles extends Tool {

	public function name(): string {
		return 'list_files';
	}

	public function title(): string {
		return __( 'List files', 'wp-safe-mode-ai' );
	}

	public function description(): string {
		return 'List the contents of one directory inside the WordPress content area. '
			. 'Call this with an empty path to see the roots you can work in (themes, plugins, mu-plugins). '
			. 'Paths are relative to wp-content, for example "themes/twentytwentyfive". '
			. 'Each entry reports whether it is editable — a file that is not editable will be refused by write_file.';
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
					'description' => 'Directory relative to wp-content. Empty string lists the available roots.',
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
		return $this->plugin->reader()->listing( $this->optional_string( $input, 'path' ) );
	}

	/**
	 * @param array<string, mixed> $input
	 */
	public function summarise( array $input ): string {
		$path = $this->optional_string( $input, 'path' );

		return sprintf(
			/* translators: %s: directory path */
			__( 'List %s', 'wp-safe-mode-ai' ),
			'' === $path ? __( 'content roots', 'wp-safe-mode-ai' ) : $path
		);
	}
}
