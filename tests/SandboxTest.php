<?php
/**
 * Path containment tests.
 *
 * @package WPSM
 */

declare( strict_types = 1 );

namespace WPSM\Tests;

use PHPUnit\Framework\TestCase;
use WPSM\Files\Sandbox;
use WPSM\Files\SandboxViolation;

/**
 * The sandbox is the only thing standing between a model-supplied string and
 * the filesystem, so these tests are the ones to keep honest.
 */
final class SandboxTest extends TestCase {

	private Sandbox $sandbox;

	protected function setUp(): void {
		$this->sandbox = new Sandbox();
	}

	// ------------------------------------------------------------------
	// Things that must be allowed
	// ------------------------------------------------------------------

	public function test_resolves_a_path_relative_to_wp_content(): void {
		$resolved = $this->sandbox->resolve( 'themes/demo-theme/functions.php' );

		$this->assertSame(
			realpath( WP_CONTENT_DIR . '/themes/demo-theme/functions.php' ),
			$resolved
		);
	}

	public function test_resolves_an_absolute_path_inside_a_root(): void {
		$path     = WP_CONTENT_DIR . '/themes/demo-theme/style.css';
		$resolved = $this->sandbox->resolve( $path );

		$this->assertSame( realpath( $path ), $resolved );
	}

	public function test_resolves_a_file_that_does_not_exist_yet(): void {
		$resolved = $this->sandbox->resolve_for_write( 'themes/demo-theme/new-file.php' );

		$this->assertStringEndsWith( 'themes/demo-theme/new-file.php', $resolved );
		$this->assertFileDoesNotExist( $resolved );
	}

	public function test_resolves_through_directories_that_do_not_exist_yet(): void {
		// Writing into a directory tree the change is about to create is a
		// normal case, and realpath() alone cannot do it.
		$resolved = $this->sandbox->resolve_for_write( 'themes/demo-theme/inc/deep/thing.php' );

		$this->assertStringEndsWith( 'themes/demo-theme/inc/deep/thing.php', $resolved );
	}

	public function test_relative_presents_paths_against_wp_content(): void {
		$absolute = WP_CONTENT_DIR . '/themes/demo-theme/functions.php';

		$this->assertSame(
			'themes/demo-theme/functions.php',
			$this->sandbox->relative( realpath( $absolute ) )
		);
	}

	// ------------------------------------------------------------------
	// Traversal and escape
	// ------------------------------------------------------------------

	/**
	 * @dataProvider escape_attempts
	 */
	public function test_refuses_paths_that_escape_the_sandbox( string $path ): void {
		$this->expectException( SandboxViolation::class );
		$this->sandbox->resolve( $path );
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function escape_attempts(): array {
		return [
			'parent traversal'            => [ 'themes/../../outside/secret.php' ],
			'absolute outside'            => [ WPSM_TEST_ROOT . '/outside/secret.php' ],
			'traversal to abspath'        => [ 'themes/demo-theme/../../../wp-config.php' ],
			'deep traversal'              => [ '../../../../../../etc/passwd' ],
			'absolute etc'                => [ '/etc/passwd' ],
			'traversal on a missing file' => [ 'themes/demo-theme/../../../outside/new.php' ],
		];
	}

	public function test_refuses_a_null_byte(): void {
		$this->expectException( SandboxViolation::class );
		$this->sandbox->resolve( "themes/demo-theme/functions.php\0.txt" );
	}

	public function test_refuses_stream_wrappers(): void {
		$this->expectException( SandboxViolation::class );
		$this->sandbox->resolve( 'phar://themes/demo-theme/evil.phar' );
	}

	public function test_refuses_an_empty_path(): void {
		$this->expectException( SandboxViolation::class );
		$this->sandbox->resolve( '   ' );
	}

	public function test_refuses_a_symlink_pointing_outside(): void {
		$link = WP_CONTENT_DIR . '/themes/demo-theme/escape-link.php';

		if ( ! @symlink( WPSM_TEST_ROOT . '/outside/secret.php', $link ) ) {
			$this->markTestSkipped( 'Symlinks are not available in this environment.' );
		}

		try {
			// The path looks like it is inside the theme. Resolution follows
			// the link and checks where it actually lands.
			$this->expectException( SandboxViolation::class );
			$this->sandbox->resolve( 'themes/demo-theme/escape-link.php' );
		} finally {
			@unlink( $link );
		}
	}

	// ------------------------------------------------------------------
	// Deny list
	// ------------------------------------------------------------------

	public function test_refuses_htaccess_even_inside_a_root(): void {
		$this->expectException( SandboxViolation::class );
		$this->sandbox->resolve_for_read( WP_CONTENT_DIR . '/.htaccess' );
	}

	public function test_refuses_dotfiles(): void {
		$path = WP_CONTENT_DIR . '/themes/demo-theme/.env.local';
		file_put_contents( $path, 'SECRET=1' );

		try {
			$this->expectException( SandboxViolation::class );
			$this->sandbox->resolve_for_read( $path );
		} finally {
			@unlink( $path );
		}
	}

	public function test_refuses_to_modify_the_toolkit_itself(): void {
		// An agent that can rewrite its own sandbox has no sandbox. This is
		// the check that makes the rest of the class meaningful.
		$this->expectException( SandboxViolation::class );
		$this->sandbox->assert_not_denied( realpath( WPSM_DIR . 'src/Files/Sandbox.php' ) );
	}

	public function test_refuses_to_edit_the_safe_mode_dropins(): void {
		$this->expectException( SandboxViolation::class );
		$this->sandbox->assert_not_denied( WP_CONTENT_DIR . '/mu-plugins/wpsm-safe-mode.php' );
	}

	// ------------------------------------------------------------------
	// Write policy
	// ------------------------------------------------------------------

	public function test_refuses_to_write_an_unlisted_extension(): void {
		$this->expectException( SandboxViolation::class );
		$this->sandbox->resolve_for_write( 'themes/demo-theme/payload.sh' );
	}

	public function test_refuses_to_write_a_file_with_no_extension(): void {
		$this->expectException( SandboxViolation::class );
		$this->sandbox->resolve_for_write( 'themes/demo-theme/Makefile' );
	}

	public function test_allows_writing_listed_extensions(): void {
		foreach ( [ 'php', 'js', 'css', 'json', 'md', 'svg' ] as $extension ) {
			$resolved = $this->sandbox->resolve_for_write( "themes/demo-theme/thing.{$extension}" );
			$this->assertStringEndsWith( ".{$extension}", $resolved );
		}
	}

	public function test_uploads_is_not_a_root_by_default(): void {
		// Uploads is web-served, so it stays out of reach until someone
		// deliberately opts in.
		$this->assertArrayNotHasKey( 'uploads', $this->sandbox->roots() );

		$this->expectException( SandboxViolation::class );
		$this->sandbox->resolve( 'uploads/2026/07/file.php' );
	}

	public function test_uploads_becomes_a_root_when_enabled(): void {
		update_option( 'wpsm_settings', [ 'allow_uploads_writes' => true ] );

		try {
			$sandbox = new Sandbox(); // Roots are cached per instance.
			$this->assertArrayHasKey( 'uploads', $sandbox->roots() );
		} finally {
			delete_option( 'wpsm_settings' );
		}
	}
}
