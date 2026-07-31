<?php
/**
 * Standalone test bootstrap.
 *
 * The security-critical parts of this plugin — path containment, syntax
 * checking, snapshot integrity — depend on very little of WordPress. Stubbing
 * that little means they can be tested with nothing but PHP and PHPUnit,
 * without Docker, a database, or a WordPress install.
 *
 * That matters more than it sounds: these are exactly the tests you want to be
 * able to run on every change, and a test suite that needs a container is a
 * test suite that gets run less often.
 *
 * Anything needing $wpdb (the journal, the audit log, proposals) is covered by
 * the wp-env integration suite instead.
 *
 * @package WPSM
 */

declare( strict_types = 1 );

define( 'WPSM_TESTING', true );

$root    = dirname( __DIR__ );
$sandbox = sys_get_temp_dir() . '/wpsm-tests-' . bin2hex( random_bytes( 6 ) );

// A throwaway WordPress-shaped directory tree for the sandbox tests to resolve
// against. Real directories, because the whole point of Sandbox is that it
// resolves against the real filesystem rather than trusting strings.
mkdir( $sandbox . '/wp-content/themes/demo-theme', 0777, true );
mkdir( $sandbox . '/wp-content/plugins/demo-plugin', 0777, true );
mkdir( $sandbox . '/wp-content/mu-plugins', 0777, true );
mkdir( $sandbox . '/wp-content/uploads', 0777, true );
mkdir( $sandbox . '/outside', 0777, true );

file_put_contents( $sandbox . '/wp-config.php', "<?php\n// pretend config\n" );
file_put_contents( $sandbox . '/wp-content/themes/demo-theme/functions.php', "<?php\n\nadd_action( 'init', 'demo' );\n" );
file_put_contents( $sandbox . '/wp-content/themes/demo-theme/style.css', "/* demo */\n" );
file_put_contents( $sandbox . '/wp-content/.htaccess', "deny from all\n" );
file_put_contents( $sandbox . '/outside/secret.php', "<?php\n// should never be reachable\n" );

define( 'WPSM_TEST_ROOT', $sandbox );
define( 'ABSPATH', $sandbox . '/' );
define( 'WP_CONTENT_DIR', $sandbox . '/wp-content' );
define( 'WP_PLUGIN_DIR', $sandbox . '/wp-content/plugins' );
define( 'WPMU_PLUGIN_DIR', $sandbox . '/wp-content/mu-plugins' );
define( 'WPSM_DIR', $root . '/' );
define( 'WPSM_FILE', $root . '/wp-safe-mode-ai.php' );
define( 'FS_CHMOD_FILE', 0644 );

// ---------------------------------------------------------------------------
// Minimal WordPress surface
// ---------------------------------------------------------------------------

$GLOBALS['wpsm_test_options'] = [];

function get_theme_root(): string {
	return WP_CONTENT_DIR . '/themes';
}

/**
 * Overridable so a test can point the storage root somewhere awkward — a path
 * containing a quote or a backslash — and confirm the generated drop-in still
 * parses. Store is final by design, so the seam has to be here.
 */
$GLOBALS['wpsm_test_uploads_basedir'] = null;

function wp_upload_dir( $time = null, $create = true ): array {
	$basedir = $GLOBALS['wpsm_test_uploads_basedir'] ?? WP_CONTENT_DIR . '/uploads';

	return [
		'basedir' => $basedir,
		'baseurl' => 'http://example.test/wp-content/uploads',
	];
}

function get_option( string $name, $default = false ) {
	return $GLOBALS['wpsm_test_options'][ $name ] ?? $default;
}

function update_option( string $name, $value, $autoload = null ): bool {
	$GLOBALS['wpsm_test_options'][ $name ] = $value;
	return true;
}

function delete_option( string $name ): bool {
	unset( $GLOBALS['wpsm_test_options'][ $name ] );
	return true;
}

function apply_filters( string $hook, $value, ...$args ) {
	return $value;
}

function do_action( string $hook, ...$args ): void {}

function add_action( string $hook, $callback, int $priority = 10, int $args = 1 ): bool {
	return true;
}

function add_filter( string $hook, $callback, int $priority = 10, int $args = 1 ): bool {
	return true;
}

function wp_mkdir_p( string $target ): bool {
	return is_dir( $target ) || mkdir( $target, 0777, true );
}

function wp_json_encode( $data, int $flags = 0, int $depth = 512 ) {
	return json_encode( $data, $flags, $depth );
}

function wp_generate_password( int $length = 12, bool $special = true, bool $extra = false ): string {
	$chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
	$out   = '';

	for ( $i = 0; $i < $length; $i++ ) {
		$out .= $chars[ random_int( 0, strlen( $chars ) - 1 ) ];
	}

	return $out;
}

function size_format( $bytes, int $decimals = 0 ): string {
	return round( (int) $bytes / 1024, $decimals ) . ' KB';
}

function __( string $text, string $domain = 'default' ): string {
	return $text;
}

function esc_html__( string $text, string $domain = 'default' ): string {
	return $text;
}

function current_time( string $type, $gmt = 0 ) {
	return gmdate( 'Y-m-d H:i:s' );
}

function get_current_user_id(): int {
	return 1;
}

// ---------------------------------------------------------------------------
// Autoload the plugin's own classes
// ---------------------------------------------------------------------------

spl_autoload_register(
	static function ( string $class ) use ( $root ): void {
		$prefix = 'WPSM\\';

		if ( 0 !== strncmp( $prefix, $class, strlen( $prefix ) ) ) {
			return;
		}

		$path = $root . '/src/' . str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) ) . '.php';

		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);

// Constants the plugin file would normally define.
if ( ! defined( 'WPSM\\VERSION' ) ) {
	define( 'WPSM\\VERSION', '0.1.0-test' );
	define( 'WPSM\\CAP', 'wpsm_manage' );
	define( 'WPSM\\SESSION_COOKIE', 'wp-wpsm-session' );
	define( 'WPSM\\REST_NS', 'wpsm/v1' );
}

register_shutdown_function(
	static function () use ( $sandbox ): void {
		// Clean up the throwaway tree. Guarded on the prefix so a bug here
		// cannot reach anything real.
		if ( str_starts_with( $sandbox, sys_get_temp_dir() ) && is_dir( $sandbox ) ) {
			$items = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $sandbox, FilesystemIterator::SKIP_DOTS ),
				RecursiveIteratorIterator::CHILD_FIRST
			);

			foreach ( $items as $item ) {
				$item->isDir() ? @rmdir( $item->getPathname() ) : @unlink( $item->getPathname() );
			}

			@rmdir( $sandbox );
		}
	}
);
