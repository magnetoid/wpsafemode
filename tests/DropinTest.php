<?php
/**
 * The generated fatal-error handler must itself be valid PHP.
 *
 * @package WPSM
 */

declare( strict_types = 1 );

namespace WPSM\Tests;

use PHPUnit\Framework\TestCase;
use WPSM\Audit\Log;
use WPSM\SafeMode\Dropins;
use WPSM\Snapshots\Store;

/**
 * This is the one place the plugin generates executable PHP, and it generates
 * the file whose entire job is catching fatal errors. A quoting mistake here
 * would mean the recovery mechanism is itself a parse error — failing in
 * precisely the way it exists to prevent, and silently, because a broken
 * drop-in is skipped rather than reported.
 */
final class DropinTest extends TestCase {

	private Dropins $dropins;

	protected function setUp(): void {
		$this->dropins = new Dropins( new Store(), new Log() );
	}

	public function test_generated_handler_is_valid_php(): void {
		$generated = $this->dropins->render_fatal_handler();

		$this->assertIsString( $generated );
		$this->assertSame( [], $this->parse_errors( $generated ) );
	}

	public function test_generated_handler_has_no_placeholders_left(): void {
		$generated = (string) $this->dropins->render_fatal_handler();

		$this->assertStringNotContainsString( '{{WPSM_', $generated );
	}

	public function test_generated_handler_points_at_the_real_storage_paths(): void {
		$store     = new Store();
		$generated = (string) $this->dropins->render_fatal_handler();

		// The handler can only find the snapshots if these are exact. It has no
		// fallback: a wrong path means no rollback and no error.
		$this->assertStringContainsString( $store->pending_file(), $generated );
		$this->assertStringContainsString( $store->root() . '/snapshots', $generated );
	}

	/**
	 * @dataProvider awkward_directory_names
	 */
	public function test_generated_handler_survives_an_awkward_storage_path( string $segment ): void {
		// The storage root is built from wp_upload_dir(), so a site living under
		// a path containing a quote or a backslash puts those characters
		// straight into a generated PHP string literal. Anchored under the temp
		// directory because Store provisions the path it is given.
		$basedir  = sys_get_temp_dir() . '/wpsm-awkward-' . bin2hex( random_bytes( 4 ) ) . '/' . $segment;
		$previous = $GLOBALS['wpsm_test_uploads_basedir'];

		$GLOBALS['wpsm_test_uploads_basedir'] = $basedir;
		// A fresh storage directory name per case, so Store does not reuse the
		// one cached in options from a previous assertion.
		delete_option( 'wpsm_storage_dir' );

		try {
			$generated = ( new Dropins( new Store(), new Log() ) )->render_fatal_handler();

			$this->assertIsString( $generated );
			$this->assertSame(
				[],
				$this->parse_errors( $generated ),
				"A storage path containing {$segment} broke out of the generated string literal."
			);
		} finally {
			$GLOBALS['wpsm_test_uploads_basedir'] = $previous;
			delete_option( 'wpsm_storage_dir' );
			$this->remove_tree( dirname( $basedir ) );
		}
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function awkward_directory_names(): array {
		return [
			'single quote' => [ "o'brien" ],
			'backslash'    => [ 'back\\slash' ],
			'dollar sign'  => [ '$HOME' ],
			'double quote' => [ 'say"what' ],
			'newline'      => [ "two\nlines" ],
		];
	}

	private function remove_tree( string $dir ): void {
		if ( ! str_starts_with( $dir, sys_get_temp_dir() ) || ! is_dir( $dir ) ) {
			return;
		}

		$items = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $items as $item ) {
			$item->isDir() ? @rmdir( $item->getPathname() ) : @unlink( $item->getPathname() );
		}

		@rmdir( $dir );
	}

	public function test_session_overlay_template_is_valid_php(): void {
		// Copied verbatim rather than generated, but it loads before every
		// other plugin — a parse error here takes the whole site down.
		$source = file_get_contents( WPSM_DIR . 'mu-loader/wpsm-safe-mode.php' );

		$this->assertIsString( $source );
		$this->assertSame( [], $this->parse_errors( $source ) );
	}

	public function test_both_dropins_carry_the_ownership_signature(): void {
		// Removal and collision detection both key off this string. Without it
		// the plugin would refuse to clean up after itself on deactivation.
		foreach ( [ 'wpsm-safe-mode.php', 'fatal-error-handler.php' ] as $name ) {
			$head = (string) file_get_contents( WPSM_DIR . 'mu-loader/' . $name, false, null, 0, 2048 );

			$this->assertStringContainsString( 'WP Safe Mode AI', $head, "{$name} is missing its ownership marker." );
		}
	}

	/**
	 * Parse a string of PHP without executing it.
	 *
	 * @return array<int, string>
	 */
	private function parse_errors( string $source ): array {
		try {
			token_get_all( $source, TOKEN_PARSE );
		} catch ( \ParseError $e ) {
			return [ $e->getMessage() . ' on line ' . $e->getLine() ];
		}

		return [];
	}
}
