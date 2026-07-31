<?php
/**
 * Site orientation tool.
 *
 * @package WPSM
 */

declare( strict_types = 1 );

namespace WPSM\Tools\Site;

use WPSM\SafeMode\Profile;
use WPSM\Tools\Tool;

defined( 'ABSPATH' ) || exit;

/**
 * The first thing an agent should call: what am I working on, what can I touch,
 * and which safety mechanisms are actually available here?
 *
 * Environment varies enormously between WordPress hosts. Whether `php -l` can
 * run, whether the site can reach itself over HTTP, whether file edits are
 * blocked by a constant — these change what the agent should attempt, so they
 * are reported up front rather than discovered through failures.
 */
final class SiteInfo extends Tool {

	public function name(): string {
		return 'site_info';
	}

	public function title(): string {
		return __( 'Site information', 'wp-safe-mode-ai' );
	}

	public function description(): string {
		return 'Report the WordPress and PHP versions, the active theme and plugins, which directories are '
			. 'writable, and which safety mechanisms are available on this host. '
			. 'Call this before making changes: it tells you whether syntax checking, post-write verification and '
			. 'automatic rollback are actually working here, and whether file edits are permitted at all.';
	}

	/**
	 * @return array<string, mixed>
	 */
	public function input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => new \stdClass(),
			'required'   => [],
		];
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>
	 */
	public function run( array $input ): array {
		global $wpdb;

		$dropins  = $this->plugin->dropins()->status();
		$theme    = wp_get_theme();
		$lint     = $this->plugin->lint()->check( '<?php echo 1;', 'probe.php' );
		$settings = $this->plugin->settings();
		$live     = Profile::live();

		$roots = [];
		foreach ( $this->plugin->sandbox()->roots() as $label => $path ) {
			$roots[ $label ] = [
				'path'     => $this->plugin->sandbox()->relative( $path ),
				'writable' => is_writable( $path ),
			];
		}

		return [
			'site'     => [
				'name'        => get_bloginfo( 'name' ),
				'url'         => home_url(),
				'wp_version'  => get_bloginfo( 'version' ),
				'php_version' => PHP_VERSION,
				'db_version'  => $wpdb->db_version(),
				'multisite'   => is_multisite(),
				'locale'      => get_locale(),
			],
			'theme'    => [
				'name'       => $theme->get( 'Name' ),
				'stylesheet' => get_stylesheet(),
				'template'   => get_template(),
				'is_child'   => get_stylesheet() !== get_template(),
				// Inside a safe-mode session the values above describe the
				// overlay, not the site. Report the real one alongside, so the
				// agent is never reasoning about a theme visitors cannot see.
				'live'       => $live->theme,
			],
			'plugins'  => [
				'active_for_visitors' => $live->plugins,
				'active_this_session' => array_values( (array) get_option( 'active_plugins', [] ) ),
				'count'               => count( $live->plugins ),
			],
			'sandbox'  => [
				'roots'              => $roots,
				'file_edits_allowed' => $this->file_edits_allowed(),
			],
			'safety'   => [
				// Which lint strategy is live matters: the tokenizer fallback
				// is slightly less strict than the binary, and the agent should
				// know which guarantee it is actually getting.
				'lint_strategy'       => $lint['strategy'],
				'lint_available'      => 'none' !== $lint['strategy'],
				'verify_probe'        => $settings->get( 'verify_probe', true ),
				'auto_revert_ready'   => $dropins['fatal_handler']['healthy'] ?? false,
				'safe_mode_ready'     => $dropins['session_overlay']['healthy'] ?? false,
				'opcache_invalidate'  => function_exists( 'opcache_invalidate' ),
			],
			'toolkit'  => [
				'version'         => \WPSM\VERSION,
				'snapshot_bytes'  => $this->plugin->store()->usage_bytes(),
				'pending_changes' => count( $this->plugin->journal()->pending() ),
			],
		];
	}

	/**
	 * @return array{allowed: bool, reason: string|null}
	 */
	private function file_edits_allowed(): array {
		try {
			$this->plugin->sandbox()->assert_file_edits_permitted();

			return [ 'allowed' => true, 'reason' => null ];
		} catch ( \RuntimeException $e ) {
			return [ 'allowed' => false, 'reason' => $e->getMessage() ];
		}
	}
}
