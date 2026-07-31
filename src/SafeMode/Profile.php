<?php
/**
 * A safe-mode profile: which plugins and theme a session sees.
 *
 * @package WPSM
 */

declare( strict_types = 1 );

namespace WPSM\SafeMode;

defined( 'ABSPATH' ) || exit;

/**
 * Value object describing the state a safe-mode session renders under.
 *
 * "All plugins off" is only the degenerate case. Being able to name an exact
 * subset is what turns this from a kill switch into a bisect tool: disable
 * half, reload, disable half of what remains.
 */
final class Profile {

	/**
	 * @param array<int, string> $plugins Plugin basenames to keep active.
	 * @param string|null        $theme   Stylesheet to force, or null to leave alone.
	 */
	private function __construct(
		public readonly array $plugins,
		public readonly ?string $theme
	) {}

	/**
	 * Build from untrusted input, discarding anything that is not installed.
	 *
	 * @param array{plugins?: mixed, theme?: mixed} $input
	 */
	public static function from_array( array $input ): self {
		$requested = is_array( $input['plugins'] ?? null ) ? $input['plugins'] : [];
		$installed = array_keys( self::installed_plugins() );

		$plugins = [];
		foreach ( $requested as $candidate ) {
			$candidate = (string) $candidate;

			if ( in_array( $candidate, $installed, true ) ) {
				$plugins[] = $candidate;
			}
		}

		// This plugin stays active in every profile. Turning off the thing that
		// owns the exit route is not a state we let anyone reach.
		$self = plugin_basename( WPSM_FILE );
		if ( ! in_array( $self, $plugins, true ) ) {
			$plugins[] = $self;
		}

		$theme = isset( $input['theme'] ) ? (string) $input['theme'] : '';

		if ( '' === $theme || ! wp_get_theme( $theme )->exists() ) {
			$theme = null;
		}

		return new self( array_values( array_unique( $plugins ) ), $theme );
	}

	/**
	 * The classic troubleshooting posture: everything off, default theme on.
	 */
	public static function minimal(): self {
		$default = wp_get_theme( WP_DEFAULT_THEME );

		return self::from_array(
			[
				'plugins' => [],
				'theme'   => $default->exists() ? WP_DEFAULT_THEME : null,
			]
		);
	}

	/**
	 * What this request sees, which is not the same thing as what is live.
	 *
	 * Inside a safe-mode session the mu-plugin filters `option_active_plugins`
	 * and the stylesheet, so this reflects the overlay.
	 */
	public static function current(): self {
		return self::from_array(
			[
				'plugins' => (array) get_option( 'active_plugins', [] ),
				'theme'   => get_stylesheet(),
			]
		);
	}

	/**
	 * What is genuinely active for visitors, ignoring any overlay.
	 *
	 * Read straight from the options table rather than through get_option(),
	 * because the safe-mode mu-plugin filters that and would otherwise make the
	 * overlay invisible to the code that needs to reason about it — including
	 * the picker that decides which plugins an overlay may disable.
	 */
	public static function live(): self {
		global $wpdb;

		$raw = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
				'active_plugins'
			)
		);

		$plugins = is_string( $raw ) ? maybe_unserialize( $raw ) : [];

		$stylesheet = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
				'stylesheet'
			)
		);

		return self::from_array(
			[
				'plugins' => is_array( $plugins ) ? $plugins : [],
				'theme'   => is_string( $stylesheet ) ? $stylesheet : '',
			]
		);
	}

	/**
	 * @return array{plugins: array<int, string>, theme: string|null}
	 */
	public function to_array(): array {
		return [
			'plugins' => $this->plugins,
			'theme'   => $this->theme,
		];
	}

	/**
	 * Plugins installed on the site, for the profile picker.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function installed_plugins(): array {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		return get_plugins();
	}
}
