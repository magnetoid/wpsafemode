<?php
/**
 * Capture pre-change copies of files.
 *
 * @package WPSM
 */

declare( strict_types = 1 );

namespace WPSM\Snapshots;

defined( 'ABSPATH' ) || exit;

/**
 * Writes a snapshot payload plus a manifest describing it.
 *
 * The manifest format is shared with mu-loader/fatal-error-handler.php, which
 * parses it without WordPress loaded. Keep it plain JSON with no nesting the
 * drop-in cannot walk.
 */
final class FileSnapshot {

	public function __construct( private readonly Store $store ) {}

	/**
	 * Snapshot a set of absolute paths.
	 *
	 * Paths that do not exist yet are still recorded, with existed=false, so a
	 * revert knows to delete the file the change is about to create.
	 *
	 * @param array<int, string> $paths Absolute paths.
	 *
	 * @return array{manifest: array<string, mixed>, files: array<int, array<string, mixed>>}
	 *
	 * @throws \RuntimeException If the snapshot cannot be written.
	 */
	public function capture( string $change_id, array $paths ): array {
		$dir = $this->store->snapshot_dir( $change_id, true );

		if ( ! wp_mkdir_p( $dir . '/files' ) ) {
			throw new \RuntimeException(
				sprintf(
					/* translators: %s: directory path */
					__( 'Could not create the snapshot directory at %s.', 'wp-safe-mode-ai' ),
					$dir
				)
			);
		}

		$entries = [];
		$index   = 0;

		foreach ( array_values( array_unique( $paths ) ) as $path ) {
			$exists = is_file( $path );

			$entry = [
				'index'   => $index,
				'path'    => $path,
				'existed' => $exists,
				'sha256'  => null,
				'bytes'   => 0,
				'mode'    => null,
				'mtime'   => null,
			];

			if ( $exists ) {
				$bytes = file_get_contents( $path );

				if ( false === $bytes ) {
					throw new \RuntimeException(
						sprintf(
							/* translators: %s: file path */
							__( 'Could not read %s to snapshot it. Refusing to continue.', 'wp-safe-mode-ai' ),
							$path
						)
					);
				}

				$blob = $dir . '/files/' . $index . '.bin';

				if ( false === file_put_contents( $blob, $bytes, LOCK_EX ) ) {
					throw new \RuntimeException(
						sprintf(
							/* translators: %s: file path */
							__( 'Could not store a snapshot of %s. Refusing to continue.', 'wp-safe-mode-ai' ),
							$path
						)
					);
				}

				$entry['sha256'] = hash( 'sha256', $bytes );
				$entry['bytes']  = strlen( $bytes );
				$entry['mode']   = fileperms( $path ) & 0777;
				$entry['mtime']  = filemtime( $path ) ?: null;
			}

			$entries[] = $entry;
			++$index;
		}

		$manifest = [
			'change_id'  => $change_id,
			'created_at' => time(),
			'root'       => $dir,
			'files'      => $entries,
		];

		$written = file_put_contents(
			$dir . '/manifest.json',
			(string) wp_json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ),
			LOCK_EX
		);

		if ( false === $written ) {
			throw new \RuntimeException( __( 'Could not write the snapshot manifest. Refusing to continue.', 'wp-safe-mode-ai' ) );
		}

		return [
			'manifest' => $manifest,
			'files'    => $entries,
		];
	}
}
