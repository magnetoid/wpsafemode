<?php
/**
 * Admin screen registration.
 *
 * @package WPSM
 */

declare( strict_types = 1 );

namespace WPSM\Admin;

use WPSM\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Mounts the toolkit UI in wp-admin and warns about anything that would make
 * the safety chain weaker than it looks.
 */
final class Screen {

	public const SLUG = 'wp-safe-mode-ai';

	public function __construct( private readonly Plugin $plugin ) {}

	public function register(): void {
		add_action( 'admin_menu', [ $this, 'add_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
		add_action( 'admin_notices', [ $this, 'notices' ] );
		add_action( 'admin_bar_menu', [ $this, 'admin_bar' ], 100 );
	}

	public function add_menu(): void {
		add_management_page(
			__( 'Safe Mode AI Toolkit', 'wp-safe-mode-ai' ),
			__( 'Safe Mode AI', 'wp-safe-mode-ai' ),
			\WPSM\CAP,
			self::SLUG,
			[ $this, 'render' ]
		);
	}

	public function render(): void {
		echo '<div class="wrap"><div id="wpsm-root"></div></div>';
	}

	public function enqueue( string $hook ): void {
		if ( 'tools_page_' . self::SLUG !== $hook ) {
			return;
		}

		$asset_file = WPSM_DIR . 'admin/build/index.asset.php';

		// Built assets are committed so the plugin folder stays drop-in. If
		// they are missing, say so rather than rendering an empty page.
		if ( ! is_readable( $asset_file ) ) {
			add_action(
				'admin_notices',
				static function (): void {
					printf(
						'<div class="notice notice-error"><p>%s</p></div>',
						esc_html__( 'The toolkit interface has not been built. Run "npm install && npm run build" in the plugin directory.', 'wp-safe-mode-ai' )
					);
				}
			);
			return;
		}

		$asset = require $asset_file;

		wp_enqueue_script(
			'wpsm-admin',
			WPSM_URL . 'admin/build/index.js',
			$asset['dependencies'] ?? [],
			$asset['version'] ?? \WPSM\VERSION,
			true
		);

		// wp-scripts names the stylesheet after the entry point it was imported
		// from, so index.js yields style-index.css, not index.css.
		wp_enqueue_style(
			'wpsm-admin',
			WPSM_URL . 'admin/build/style-index.css',
			[],
			$asset['version'] ?? \WPSM\VERSION
		);

		// RTL locales get the mirrored build automatically.
		wp_style_add_data( 'wpsm-admin', 'rtl', 'replace' );

		wp_add_inline_script(
			'wpsm-admin',
			'window.WPSM_BOOTSTRAP = ' . wp_json_encode(
				[
					'restUrl'   => esc_url_raw( rest_url( \WPSM\REST_NS ) ),
					'nonce'     => wp_create_nonce( 'wp_rest' ),
					'version'   => \WPSM\VERSION,
					'siteName'  => get_bloginfo( 'name' ),
					'homeUrl'   => home_url( '/' ),
					'adminUrl'  => admin_url(),
					'canManage' => current_user_can( \WPSM\CAP ),
				]
			) . ';',
			'before'
		);

		wp_set_script_translations( 'wpsm-admin', 'wp-safe-mode-ai' );
	}

	/**
	 * Surface the two conditions that silently weaken the safety chain:
	 * a missing or tampered drop-in, and a foreign fatal-error handler.
	 */
	public function notices(): void {
		if ( ! current_user_can( \WPSM\CAP ) ) {
			return;
		}

		$status = $this->plugin->dropins()->status();

		foreach (
			[
				'session_overlay' => __( 'Safe mode cannot be used until this is fixed.', 'wp-safe-mode-ai' ),
				'fatal_handler'   => __( 'Automatic rollback after a fatal error is unavailable until this is fixed. Every other safety step still applies.', 'wp-safe-mode-ai' ),
			] as $key => $consequence
		) {
			$state = $status[ $key ];

			if ( $state['healthy'] ) {
				continue;
			}

			$reason = match ( true ) {
				! $state['present'] => __( 'the file is missing', 'wp-safe-mode-ai' ),
				! $state['ours']    => __( 'another plugin owns that file', 'wp-safe-mode-ai' ),
				default             => __( 'the file has been modified since it was installed', 'wp-safe-mode-ai' ),
			};

			printf(
				'<div class="notice notice-warning"><p><strong>%s</strong> %s — %s %s</p></div>',
				esc_html__( 'WP Safe Mode AI:', 'wp-safe-mode-ai' ),
				esc_html( basename( (string) $state['path'] ) ),
				esc_html( $reason ),
				esc_html( $consequence )
			);
		}
	}

	/**
	 * A persistent indicator while safe mode is on.
	 *
	 * Forgetting you are in safe mode and concluding the site is broken (or
	 * fixed) is the obvious failure mode here, so it gets a permanent spot in
	 * the admin bar rather than a dismissible notice.
	 */
	public function admin_bar( \WP_Admin_Bar $bar ): void {
		$status = $this->plugin->sessions()->status();

		if ( ! $status['active'] ) {
			return;
		}

		$minutes = (int) ceil( ( $status['seconds_remaining'] ?? 0 ) / 60 );

		$bar->add_node(
			[
				'id'    => 'wpsm-safe-mode',
				'title' => sprintf(
					/* translators: %d: minutes until the session expires */
					__( 'Safe Mode active — %dm left', 'wp-safe-mode-ai' ),
					$minutes
				),
				'href'  => admin_url( 'tools.php?page=' . self::SLUG ),
				'meta'  => [
					'title' => __( 'Only you see the site this way. Visitors see it normally.', 'wp-safe-mode-ai' ),
				],
			]
		);
	}
}
