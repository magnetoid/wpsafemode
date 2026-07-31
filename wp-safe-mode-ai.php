<?php
/**
 * Plugin Name:       WP Safe Mode AI Toolkit
 * Plugin URI:        https://github.com/magnetoid/wp-safe-mode-ai
 * Description:       AI-driven WordPress file and database editing behind a session-scoped safe mode, with snapshot, lint, diff, approval, verify and auto-revert-on-fatal.
 * Version:           0.1.0
 * Requires at least: 6.0
 * Requires PHP:      8.1
 * Author:            magnetoid
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-safe-mode-ai
 *
 * @package WPSM
 */

declare( strict_types = 1 );

namespace WPSM;

defined( 'ABSPATH' ) || exit;

const VERSION = '0.1.0';

/**
 * 8.1 rather than 8.0: readonly properties and array_is_list() are both used
 * throughout, and both landed in 8.1. PHP 8.0 has been end-of-life since
 * November 2023, so this is not a meaningful restriction in practice.
 */
const MIN_PHP = '8.1';
const MIN_WP  = '6.0';
const REST_NS     = 'wpsm/v1';
const CAP         = 'wpsm_manage';
const SESSION_COOKIE = 'wp-wpsm-session';

define( 'WPSM_FILE', __FILE__ );
define( 'WPSM_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPSM_URL', plugin_dir_url( __FILE__ ) );

/**
 * Environment guard. Runs before anything else is loaded so an unsupported
 * host gets an admin notice instead of a fatal.
 */
function environment_error(): ?string {
	if ( version_compare( PHP_VERSION, MIN_PHP, '<' ) ) {
		return sprintf(
			/* translators: 1: required PHP version, 2: current PHP version */
			__( 'WP Safe Mode AI Toolkit requires PHP %1$s or newer. This site runs PHP %2$s.', 'wp-safe-mode-ai' ),
			MIN_PHP,
			PHP_VERSION
		);
	}

	if ( version_compare( get_bloginfo( 'version' ), MIN_WP, '<' ) ) {
		return sprintf(
			/* translators: 1: required WordPress version, 2: current WordPress version */
			__( 'WP Safe Mode AI Toolkit requires WordPress %1$s or newer. This site runs %2$s.', 'wp-safe-mode-ai' ),
			MIN_WP,
			get_bloginfo( 'version' )
		);
	}

	return null;
}

/**
 * PSR-4 autoloader for WPSM\ => src/.
 *
 * Hand-rolled rather than Composer's so the plugin folder stays drop-in even
 * if vendor/ was never built.
 */
spl_autoload_register(
	static function ( string $class ): void {
		$prefix = __NAMESPACE__ . '\\';
		if ( 0 !== strncmp( $prefix, $class, strlen( $prefix ) ) ) {
			return;
		}

		$relative = substr( $class, strlen( $prefix ) );
		$path     = WPSM_DIR . 'src/' . str_replace( '\\', '/', $relative ) . '.php';

		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);

// Composer autoload is optional — used only when third-party deps are vendored.
if ( is_readable( WPSM_DIR . 'vendor/autoload.php' ) ) {
	require_once WPSM_DIR . 'vendor/autoload.php';
}

add_action(
	'plugins_loaded',
	static function (): void {
		$error = environment_error();

		if ( null !== $error ) {
			add_action(
				'admin_notices',
				static function () use ( $error ): void {
					printf(
						'<div class="notice notice-error"><p>%s</p></div>',
						esc_html( $error )
					);
				}
			);
			return;
		}

		Plugin::instance()->boot();
	}
);

register_activation_hook(
	__FILE__,
	static function (): void {
		if ( null !== environment_error() ) {
			return;
		}
		Install::activate();
	}
);

register_deactivation_hook(
	__FILE__,
	static function (): void {
		Install::deactivate();
	}
);
