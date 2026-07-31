<?php
/**
 * On-disk storage for snapshots and the pending-change mirror.
 *
 * @package WPSM
 */

declare( strict_types = 1 );

namespace WPSM\Snapshots;

defined( 'ABSPATH' ) || exit;

/**
 * Owns the plugin's private storage directory.
 *
 * Lives under uploads/ so it is writable on hosts where wp-content is not,
 * with a randomised suffix and hard deny rules so snapshot contents — which
 * may include pre-edit copies of any theme or plugin file — are never
 * web-readable.
 */
final class Store {

	private const DIR_OPTION = 'wpsm_storage_dir';

	private ?string $root = null;

	/**
	 * Absolute path to the storage root, without trailing slash.
	 *
	 * Creates the directory and its guards on first call.
	 */
	public function root(): string {
		if ( null !== $this->root ) {
			return $this->root;
		}

		$uploads = wp_upload_dir( null, false );
		$basedir = $uploads['basedir'] ?? WP_CONTENT_DIR . '/uploads';

		$name = get_option( self::DIR_OPTION );
		if ( ! is_string( $name ) || '' === $name || ! preg_match( '/^wpsm-[a-zA-Z0-9]{12}$/', $name ) ) {
			$name = 'wpsm-' . wp_generate_password( 12, false, false );
			update_option( self::DIR_OPTION, $name, true );
		}

		$this->root = rtrim( $basedir, '/\\' ) . '/' . $name;
		$this->ensure();

		return $this->root;
	}

	/**
	 * Create the storage tree and its web-access guards. Idempotent.
	 */
	public function ensure(): void {
		$root = $this->root ?? $this->root();

		foreach ( [ $root, $root . '/snapshots' ] as $dir ) {
			if ( ! is_dir( $dir ) ) {
				wp_mkdir_p( $dir );
			}
		}

		// Deny direct HTTP access. Written defensively for Apache, IIS and
		// "just don't index me" on nginx (where server config is the real
		// control and we cannot influence it from here).
		$guards = [
			'.htaccess'  => "Require all denied\n<IfModule !mod_authz_core.c>\nOrder allow,deny\nDeny from all\n</IfModule>\n",
			'index.php'  => "<?php\n// Silence is golden.\n",
			'web.config' => "<configuration><system.webServer><authorization>"
				. "<deny users=\"*\" /></authorization></system.webServer></configuration>\n",
		];

		foreach ( $guards as $file => $contents ) {
			$path = $root . '/' . $file;
			if ( ! file_exists( $path ) ) {
				file_put_contents( $path, $contents, LOCK_EX );
			}
		}
	}

	/**
	 * Directory holding the snapshot payload for one change.
	 */
	public function snapshot_dir( string $change_id, bool $create = false ): string {
		$dir = $this->root() . '/snapshots/' . $this->sanitize_id( $change_id );

		if ( $create && ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}

		return $dir;
	}

	/**
	 * Path to the pending-change mirror.
	 *
	 * This file is the fatal-error handler's only input. The handler runs on
	 * shutdown from a drop-in that loads before WordPress is bootstrapped, so
	 * it cannot use $wpdb, the options API, or this plugin's autoloader —
	 * everything it needs has to be readable with plain file I/O.
	 */
	public function pending_file(): string {
		return $this->root() . '/pending.json';
	}

	/**
	 * Path to the queue the fatal-error handler writes its findings to.
	 *
	 * The handler cannot reliably reach the database, so anything it does is
	 * reported here and folded into the journal and audit log on the next
	 * healthy request.
	 */
	public function reports_file(): string {
		return $this->root() . '/recovery-reports.json';
	}

	/**
	 * Read and clear the recovery report queue.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function take_reports(): array {
		$path = $this->reports_file();

		if ( ! is_readable( $path ) ) {
			return [];
		}

		$raw  = (string) file_get_contents( $path );
		$data = json_decode( $raw, true );

		// Truncate rather than delete: the handler appends without checking for
		// the file's existence, and an unlinked path racing an append loses the
		// report entirely.
		file_put_contents( $path, '[]', LOCK_EX );

		return is_array( $data ) ? $data : [];
	}

	/**
	 * @return array<string, array<string, mixed>> Keyed by change id.
	 */
	public function read_pending(): array {
		$path = $this->pending_file();

		if ( ! is_readable( $path ) ) {
			return [];
		}

		$raw = file_get_contents( $path );
		if ( false === $raw || '' === $raw ) {
			return [];
		}

		$data = json_decode( $raw, true );

		return is_array( $data ) ? $data : [];
	}

	/**
	 * @param array<string, array<string, mixed>> $pending Keyed by change id.
	 */
	public function write_pending( array $pending ): bool {
		$json = wp_json_encode( $pending, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );

		if ( false === $json ) {
			return false;
		}

		return false !== file_put_contents( $this->pending_file(), $json, LOCK_EX );
	}

	/**
	 * Recursively delete a snapshot payload.
	 */
	public function forget( string $change_id ): void {
		$dir = $this->snapshot_dir( $change_id );

		// Containment check: never recurse outside our own snapshots tree.
		$real = realpath( $dir );
		$base = realpath( $this->root() . '/snapshots' );

		if ( false === $real || false === $base || ! str_starts_with( $real, $base . DIRECTORY_SEPARATOR ) ) {
			return;
		}

		$this->rmdir_recursive( $real );
	}

	/**
	 * Total bytes held in the snapshots tree.
	 */
	public function usage_bytes(): int {
		$base = $this->root() . '/snapshots';

		if ( ! is_dir( $base ) ) {
			return 0;
		}

		$total    = 0;
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $base, \FilesystemIterator::SKIP_DOTS )
		);

		foreach ( $iterator as $file ) {
			/** @var \SplFileInfo $file */
			if ( $file->isFile() ) {
				$total += $file->getSize();
			}
		}

		return $total;
	}

	private function rmdir_recursive( string $dir ): void {
		$items = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $items as $item ) {
			/** @var \SplFileInfo $item */
			if ( $item->isDir() ) {
				@rmdir( $item->getPathname() );
			} else {
				@unlink( $item->getPathname() );
			}
		}

		@rmdir( $dir );
	}

	private function sanitize_id( string $id ): string {
		$clean = preg_replace( '/[^a-zA-Z0-9_-]/', '', $id );

		if ( ! is_string( $clean ) || '' === $clean ) {
			throw new \InvalidArgumentException( 'Refusing to build a snapshot path from an empty identifier.' );
		}

		return $clean;
	}
}
