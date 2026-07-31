<?php
/**
 * Path containment and write policy.
 *
 * @package WPSM
 */

declare( strict_types = 1 );

namespace WPSM\Files;

defined( 'ABSPATH' ) || exit;

/**
 * Every path the toolkit touches passes through here first.
 *
 * The threat model is a model-supplied path: it may contain traversal
 * sequences, point at a symlink aimed outside the site, name a file whose
 * parent directories do not exist yet, or target WordPress' own configuration.
 * Resolution is therefore done against the real filesystem (so symlinks are
 * followed and then checked), not by string manipulation alone.
 */
final class Sandbox {

	/** Files that are never readable or writable, matched on basename. */
	private const DENIED_BASENAMES = [
		'wp-config.php',
		'wp-config-sample.php',
		'.htaccess',
		'.htpasswd',
		'.user.ini',
		'php.ini',
		'web.config',
		'.env',
	];

	/** Extensions the toolkit will write. Anything else is refused. */
	private const WRITABLE_EXTENSIONS = [
		'php', 'js', 'jsx', 'ts', 'tsx', 'mjs', 'cjs',
		'css', 'scss', 'sass', 'less',
		'json', 'txt', 'md', 'html', 'htm', 'xml', 'svg',
		'po', 'pot',
	];

	/** @var array<string, string>|null Cached realpath'd roots keyed by label. */
	private ?array $roots = null;

	/**
	 * Allowed roots, keyed by a short label used in the UI.
	 *
	 * @return array<string, string>
	 */
	public function roots(): array {
		if ( null !== $this->roots ) {
			return $this->roots;
		}

		$candidates = [
			'themes'     => get_theme_root(),
			'plugins'    => WP_PLUGIN_DIR,
			'mu-plugins' => WPMU_PLUGIN_DIR,
		];

		if ( $this->uploads_writable_enabled() ) {
			$uploads = wp_upload_dir( null, false );
			if ( ! empty( $uploads['basedir'] ) ) {
				$candidates['uploads'] = $uploads['basedir'];
			}
		}

		/**
		 * Filter the roots the toolkit may operate inside.
		 *
		 * Adding a root here widens the blast radius of every tool. Paths are
		 * realpath'd afterwards, so a filter cannot smuggle in a traversal.
		 *
		 * @param array<string, string> $candidates Label => absolute path.
		 */
		$candidates = (array) apply_filters( 'wpsm_sandbox_roots', $candidates );

		$resolved = [];
		foreach ( $candidates as $label => $path ) {
			$real = realpath( (string) $path );
			if ( false !== $real ) {
				$resolved[ (string) $label ] = $real;
			}
		}

		return $this->roots = $resolved;
	}

	/**
	 * Turn a caller-supplied path into a canonical absolute path inside a root.
	 *
	 * Accepts either an absolute path or one relative to wp-content.
	 *
	 * @throws SandboxViolation If the path escapes the sandbox.
	 */
	public function resolve( string $path ): string {
		$path = trim( $path );

		if ( '' === $path ) {
			throw new SandboxViolation( __( 'No path was given.', 'wp-safe-mode-ai' ) );
		}

		if ( str_contains( $path, "\0" ) ) {
			throw new SandboxViolation( __( 'Path contains a null byte.', 'wp-safe-mode-ai' ) );
		}

		// Wrappers (phar://, file://, http://) are never legitimate here and
		// phar:// in particular is an object-injection vector.
		if ( preg_match( '#^[a-z][a-z0-9+.-]*://#i', $path ) ) {
			throw new SandboxViolation( __( 'Stream wrappers are not allowed in paths.', 'wp-safe-mode-ai' ) );
		}

		$absolute = $this->to_absolute( $path );
		$real     = $this->realpath_deep( $absolute );

		if ( ! $this->inside_a_root( $real ) ) {
			throw new SandboxViolation(
				sprintf(
					/* translators: %s: file path */
					__( '%s is outside the directories this toolkit is allowed to touch.', 'wp-safe-mode-ai' ),
					$this->relative( $real )
				)
			);
		}

		return $real;
	}

	/**
	 * Resolve for reading, and refuse denied files.
	 *
	 * @throws SandboxViolation
	 */
	public function resolve_for_read( string $path ): string {
		$real = $this->resolve( $path );
		$this->assert_not_denied( $real );

		return $real;
	}

	/**
	 * Resolve for writing: denied-file check, extension allowlist, and the
	 * WordPress-level file-modification constants.
	 *
	 * @throws SandboxViolation
	 */
	public function resolve_for_write( string $path ): string {
		$this->assert_file_edits_permitted();

		$real = $this->resolve( $path );
		$this->assert_not_denied( $real );

		$extension = strtolower( pathinfo( $real, PATHINFO_EXTENSION ) );

		if ( ! in_array( $extension, self::WRITABLE_EXTENSIONS, true ) ) {
			throw new SandboxViolation(
				sprintf(
					/* translators: 1: file extension, 2: comma-separated list of allowed extensions */
					__( 'Refusing to write .%1$s files. Allowed: %2$s.', 'wp-safe-mode-ai' ),
					$extension ?: '(none)',
					implode( ', ', self::WRITABLE_EXTENSIONS )
				)
			);
		}

		return $real;
	}

	/**
	 * Present an absolute path relative to wp-content for display.
	 */
	public function relative( string $absolute ): string {
		$content = realpath( WP_CONTENT_DIR );

		if ( false !== $content && str_starts_with( $absolute, $content . DIRECTORY_SEPARATOR ) ) {
			return ltrim( substr( $absolute, strlen( $content ) ), '/\\' );
		}

		return $absolute;
	}

	// ------------------------------------------------------------------
	// Policy checks
	// ------------------------------------------------------------------

	/**
	 * @throws SandboxViolation
	 */
	public function assert_not_denied( string $real ): void {
		$basename = basename( $real );

		if ( in_array( strtolower( $basename ), array_map( 'strtolower', self::DENIED_BASENAMES ), true ) ) {
			throw new SandboxViolation(
				sprintf(
					/* translators: %s: file name */
					__( '%s is on the permanent deny list.', 'wp-safe-mode-ai' ),
					$basename
				)
			);
		}

		// Dotfiles are denied by default: they are configuration far more often
		// than they are code, and none of them are things an editing tool
		// should be reaching for unprompted.
		if ( str_starts_with( $basename, '.' ) ) {
			throw new SandboxViolation(
				sprintf(
					/* translators: %s: file name */
					__( '%s is a dotfile; these are not editable through the toolkit.', 'wp-safe-mode-ai' ),
					$basename
				)
			);
		}

		// Self-modification guard. An agent that can rewrite the sandbox has no
		// sandbox.
		$own = realpath( WPSM_DIR );
		if ( false !== $own && ( $real === $own || str_starts_with( $real, $own . DIRECTORY_SEPARATOR ) ) ) {
			throw new SandboxViolation(
				__( 'The toolkit will not modify its own files.', 'wp-safe-mode-ai' )
			);
		}

		// Neither will it modify its own snapshots — that would make rollback
		// unreliable in exactly the situation rollback exists for.
		$storage = get_option( 'wpsm_storage_dir' );
		if ( is_string( $storage ) && '' !== $storage && str_contains( $real, '/' . $storage . '/' ) ) {
			throw new SandboxViolation(
				__( 'That path is inside the toolkit snapshot store.', 'wp-safe-mode-ai' )
			);
		}

		// The safe-mode drop-ins are managed through their own installer, which
		// tracks hashes and collisions. Editing them as ordinary files would
		// desynchronise that.
		if ( in_array( $basename, [ 'wpsm-safe-mode.php', 'fatal-error-handler.php' ], true ) ) {
			throw new SandboxViolation(
				__( 'Safe-mode drop-ins are managed by the toolkit and cannot be edited directly.', 'wp-safe-mode-ai' )
			);
		}
	}

	/**
	 * @throws SandboxViolation
	 */
	public function assert_file_edits_permitted(): void {
		if ( defined( 'WPSM_ALLOW_FILE_EDIT' ) && WPSM_ALLOW_FILE_EDIT ) {
			return;
		}

		if ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS ) {
			throw new SandboxViolation(
				__( 'DISALLOW_FILE_MODS is set in wp-config.php. Define WPSM_ALLOW_FILE_EDIT as true to let the toolkit write files anyway.', 'wp-safe-mode-ai' )
			);
		}

		if ( defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT ) {
			throw new SandboxViolation(
				__( 'DISALLOW_FILE_EDIT is set in wp-config.php. Define WPSM_ALLOW_FILE_EDIT as true to let the toolkit write files anyway.', 'wp-safe-mode-ai' )
			);
		}
	}

	// ------------------------------------------------------------------
	// Internals
	// ------------------------------------------------------------------

	private function to_absolute( string $path ): string {
		$is_absolute = str_starts_with( $path, '/' )
			|| (bool) preg_match( '#^[A-Za-z]:[\\\\/]#', $path );

		if ( $is_absolute ) {
			return $path;
		}

		return rtrim( WP_CONTENT_DIR, '/\\' ) . '/' . ltrim( $path, '/\\' );
	}

	/**
	 * realpath() that also works for files whose parents do not exist yet.
	 *
	 * Walks up to the nearest existing ancestor, canonicalises that, then
	 * re-appends the remaining segments — refusing any traversal segment on the
	 * way so the tail cannot climb back out of the resolved ancestor.
	 *
	 * @throws SandboxViolation
	 */
	private function realpath_deep( string $absolute ): string {
		$existing = realpath( $absolute );

		if ( false !== $existing ) {
			return $existing;
		}

		$tail    = [];
		$current = $absolute;

		// Bounded so a pathological input cannot spin here.
		for ( $depth = 0; $depth < 64; $depth++ ) {
			$parent = dirname( $current );

			if ( $parent === $current ) {
				break;
			}

			$segment = basename( $current );

			if ( '..' === $segment || '.' === $segment ) {
				throw new SandboxViolation( __( 'Relative path segments are not allowed.', 'wp-safe-mode-ai' ) );
			}

			array_unshift( $tail, $segment );

			$real = realpath( $parent );

			if ( false !== $real ) {
				return $real . DIRECTORY_SEPARATOR . implode( DIRECTORY_SEPARATOR, $tail );
			}

			$current = $parent;
		}

		throw new SandboxViolation(
			sprintf(
				/* translators: %s: file path */
				__( 'Could not resolve %s to a real location on disk.', 'wp-safe-mode-ai' ),
				$absolute
			)
		);
	}

	private function inside_a_root( string $real ): bool {
		foreach ( $this->roots() as $root ) {
			if ( $real === $root || str_starts_with( $real, $root . DIRECTORY_SEPARATOR ) ) {
				return true;
			}
		}

		return false;
	}

	private function uploads_writable_enabled(): bool {
		$settings = get_option( 'wpsm_settings', [] );

		return is_array( $settings ) && ! empty( $settings['allow_uploads_writes'] );
	}
}
