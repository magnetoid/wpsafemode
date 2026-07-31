<?php
/**
 * Sandboxed reads and directory listings.
 *
 * @package WPSM
 */

declare( strict_types = 1 );

namespace WPSM\Files;

defined( 'ABSPATH' ) || exit;

/**
 * Read side of the file tools.
 *
 * Reads are capped: an agent that pulls a 40 MB minified bundle into context
 * has burned the budget for the task it was actually asked to do, so oversized
 * files are refused with a clear message rather than silently truncated.
 */
final class Reader {

	private const MAX_BYTES = 1048576; // 1 MiB.

	public function __construct( private readonly Sandbox $sandbox ) {}

	/**
	 * @return array{path: string, relative: string, bytes: int, sha256: string, lines: int, contents: string}
	 *
	 * @throws SandboxViolation
	 * @throws \RuntimeException
	 */
	public function read( string $path ): array {
		$real = $this->sandbox->resolve_for_read( $path );

		if ( ! is_file( $real ) ) {
			throw new \RuntimeException(
				sprintf(
					/* translators: %s: file path */
					__( '%s does not exist.', 'wp-safe-mode-ai' ),
					$this->sandbox->relative( $real )
				)
			);
		}

		$size = (int) filesize( $real );

		if ( $size > self::MAX_BYTES ) {
			throw new \RuntimeException(
				sprintf(
					/* translators: 1: file path, 2: file size, 3: size limit */
					__( '%1$s is %2$s, which is over the %3$s read limit. Read a narrower target instead.', 'wp-safe-mode-ai' ),
					$this->sandbox->relative( $real ),
					size_format( $size ),
					size_format( self::MAX_BYTES )
				)
			);
		}

		$contents = file_get_contents( $real );

		if ( false === $contents ) {
			throw new \RuntimeException(
				sprintf(
					/* translators: %s: file path */
					__( 'Could not read %s.', 'wp-safe-mode-ai' ),
					$this->sandbox->relative( $real )
				)
			);
		}

		return [
			'path'     => $real,
			'relative' => $this->sandbox->relative( $real ),
			'bytes'    => $size,
			'sha256'   => hash( 'sha256', $contents ),
			'lines'    => substr_count( $contents, "\n" ) + 1,
			'contents' => $contents,
		];
	}

	/**
	 * List one directory level.
	 *
	 * @return array{path: string, relative: string, entries: array<int, array<string, mixed>>}
	 *
	 * @throws SandboxViolation
	 * @throws \RuntimeException
	 */
	public function listing( string $path = '' ): array {
		// An empty path means "show me the roots" rather than an error, so the
		// UI has a sensible entry point.
		if ( '' === trim( $path ) ) {
			return $this->roots_listing();
		}

		$real = $this->sandbox->resolve( $path );

		if ( ! is_dir( $real ) ) {
			throw new \RuntimeException(
				sprintf(
					/* translators: %s: directory path */
					__( '%s is not a directory.', 'wp-safe-mode-ai' ),
					$this->sandbox->relative( $real )
				)
			);
		}

		$entries = [];

		foreach ( new \DirectoryIterator( $real ) as $item ) {
			if ( $item->isDot() ) {
				continue;
			}

			$entries[] = [
				'name'     => $item->getFilename(),
				'relative' => $this->sandbox->relative( $item->getPathname() ),
				'type'     => $item->isDir() ? 'dir' : 'file',
				'bytes'    => $item->isFile() ? $item->getSize() : null,
				'modified' => $item->getMTime(),
				'editable' => $item->isFile() && $this->is_editable( $item->getPathname() ),
			];
		}

		usort(
			$entries,
			static function ( array $a, array $b ): int {
				if ( $a['type'] !== $b['type'] ) {
					return 'dir' === $a['type'] ? -1 : 1;
				}
				return strcasecmp( $a['name'], $b['name'] );
			}
		);

		return [
			'path'     => $real,
			'relative' => $this->sandbox->relative( $real ),
			'entries'  => $entries,
		];
	}

	/**
	 * @return array{path: string, relative: string, entries: array<int, array<string, mixed>>}
	 */
	private function roots_listing(): array {
		$entries = [];

		foreach ( $this->sandbox->roots() as $label => $root ) {
			$entries[] = [
				'name'     => $label,
				'relative' => $this->sandbox->relative( $root ),
				'type'     => 'dir',
				'bytes'    => null,
				'modified' => (int) ( filemtime( $root ) ?: 0 ),
				'editable' => false,
			];
		}

		return [
			'path'     => '',
			'relative' => '',
			'entries'  => $entries,
		];
	}

	private function is_editable( string $absolute ): bool {
		try {
			$this->sandbox->resolve_for_write( $absolute );
			return true;
		} catch ( SandboxViolation ) {
			return false;
		}
	}
}
