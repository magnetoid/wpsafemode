<?php
/**
 * File write tool.
 *
 * @package WPSM
 */

declare( strict_types = 1 );

namespace WPSM\Tools\File;

use WPSM\Tools\Tool;

defined( 'ABSPATH' ) || exit;

/**
 * The tool the whole safety chain exists for.
 *
 * Mutating, so a call to this never writes anything directly — it produces a
 * proposal carrying the diff and the lint verdict, and a human approves it.
 * The write itself goes through Files\Writer, which snapshots, lints again,
 * writes, probes the site, and rolls back if the probe fails.
 */
final class WriteFile extends Tool {

	public function name(): string {
		return 'write_file';
	}

	public function title(): string {
		return __( 'Write file', 'wp-safe-mode-ai' );
	}

	public function description(): string {
		return 'Write the full contents of a file inside the WordPress content area, creating it if needed. '
			. 'PHP is syntax-checked before anything is written and the write is refused if it does not parse. '
			. 'The previous contents are snapshotted first, and the change is rolled back automatically if the site '
			. 'stops responding afterwards. '
			. 'Always read_file first and pass its sha256 back as expected_sha256 so a concurrent edit cannot be lost. '
			. 'Supply the complete file, not a fragment or a diff.';
	}

	/**
	 * @return array<string, mixed>
	 */
	public function input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'path'            => [
					'type'        => 'string',
					'description' => 'File path relative to wp-content.',
				],
				'contents'        => [
					'type'        => 'string',
					'description' => 'The complete new contents of the file.',
				],
				'expected_sha256' => [
					'type'        => 'string',
					'description' => 'The sha256 returned by read_file. Omit only when creating a new file.',
				],
				'reason'          => [
					'type'        => 'string',
					'description' => 'One sentence on why this change is being made, shown to the person approving it.',
				],
			],
			'required'   => [ 'path', 'contents' ],
		];
	}

	public function mutates(): bool {
		return true;
	}

	/**
	 * Build the approval payload: the diff, the lint result, and what happens
	 * to the file.
	 *
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>
	 */
	public function preview( array $input ): array {
		$path     = $this->required_string( $input, 'path' );
		$contents = (string) ( $input['contents'] ?? '' );

		// resolve_for_write applies the full write policy, so an unapprovable
		// path fails here rather than after someone has clicked approve.
		$real     = $this->plugin->sandbox()->resolve_for_write( $path );
		$relative = $this->plugin->sandbox()->relative( $real );
		$exists   = is_file( $real );
		$before   = $exists ? (string) file_get_contents( $real ) : '';

		$lint = $this->plugin->lint()->check( $contents, $real );
		$diff = $this->plugin->diff()->unified( $before, $contents, $relative );

		return [
			'kind'         => 'file_write',
			'path'         => $relative,
			'creating'     => ! $exists,
			'reason'       => $this->optional_string( $input, 'reason' ),
			'bytes_before' => strlen( $before ),
			'bytes_after'  => strlen( $contents ),
			'sha256_before' => $exists ? hash( 'sha256', $before ) : null,
			'lint'         => $lint,
			'diff'         => $diff,
			// Surfaced so the UI can warn before approval rather than failing
			// after it.
			'blocked'      => ! $lint['ok'],
		];
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>
	 */
	public function run( array $input ): array {
		$expected = $this->optional_string( $input, 'expected_sha256' );

		return $this->plugin->writer()->write(
			$this->required_string( $input, 'path' ),
			(string) ( $input['contents'] ?? '' ),
			[ 'expected_sha256' => '' !== $expected ? $expected : null ]
		);
	}

	/**
	 * @param array<string, mixed> $input
	 */
	public function summarise( array $input ): string {
		/* translators: %s: file path */
		return sprintf( __( 'Write %s', 'wp-safe-mode-ai' ), $this->optional_string( $input, 'path' ) );
	}
}
