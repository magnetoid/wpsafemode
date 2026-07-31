<?php
/**
 * Enter safe mode.
 *
 * @package WPSM
 */

declare( strict_types = 1 );

namespace WPSM\Tools\SafeMode;

use WPSM\SafeMode\Profile;
use WPSM\Tools\Tool;

defined( 'ABSPATH' ) || exit;

/**
 * Opens a safe-mode session for the current browser.
 *
 * Mutating, because it changes what the operator sees and because starting one
 * without them realising would be genuinely disorienting — but note that it
 * never changes what a visitor sees.
 */
final class Start extends Tool {

	public function name(): string {
		return 'safe_mode_start';
	}

	public function title(): string {
		return __( 'Enter safe mode', 'wp-safe-mode-ai' );
	}

	public function description(): string {
		return 'Enter safe mode for this browser session only. Logged-out visitors and other users continue to see '
			. 'the site exactly as it is — nothing about the live site changes. '
			. 'Specify which plugins stay active to bisect a conflict: pass an empty list to disable everything, '
			. 'or a subset to narrow down the culprit. '
			. 'The toolkit itself always stays active regardless of what you pass.';
	}

	/**
	 * @return array<string, mixed>
	 */
	public function input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'plugins' => [
					'type'        => 'array',
					'items'       => [ 'type' => 'string' ],
					'description' => 'Plugin files to keep active, as returned by safe_mode_status (for example "akismet/akismet.php"). Empty array disables all of them.',
				],
				'theme'   => [
					'type'        => 'string',
					'description' => 'Stylesheet to switch to for this session. Omit to keep the current theme.',
				],
				'minutes' => [
					'type'        => 'integer',
					'description' => 'How long the session lasts, up to 480. Defaults to 60.',
				],
			],
			'required'   => [],
		];
	}

	public function mutates(): bool {
		return true;
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>
	 */
	public function preview( array $input ): array {
		$profile = $this->build_profile( $input );

		// live(), not current(): if a session is already open, current() returns
		// the overlay's view and "will disable" would be computed against the
		// wrong baseline.
		$live = Profile::live();

		$disabled = array_values( array_diff( $live->plugins, $profile->plugins ) );
		$ready    = (bool) ( $this->plugin->dropins()->status()['session_overlay']['healthy'] ?? false );

		return [
			'kind'             => 'safe_mode_start',
			'profile'          => $profile->to_array(),
			'currently_active' => $live->to_array(),
			'will_disable'     => $disabled,
			'theme_change'     => $profile->theme !== $live->theme
				? [ 'from' => $live->theme, 'to' => $profile->theme ]
				: null,
			'minutes'          => $this->optional_int( $input, 'minutes', 60 ),
			'affects_visitors' => false,
			'blocked'          => ! $ready,
			'error'            => $ready
				? null
				: __( 'The session overlay drop-in is not installed or has been modified. Reinstall it from the toolkit settings before using safe mode.', 'wp-safe-mode-ai' ),
		];
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>
	 */
	public function run( array $input ): array {
		$minutes = max( 1, min( 480, $this->optional_int( $input, 'minutes', 60 ) ) );

		return $this->plugin->sessions()->start( $this->build_profile( $input ), $minutes * 60 );
	}

	/**
	 * @param array<string, mixed> $input
	 */
	private function build_profile( array $input ): Profile {
		// A missing "plugins" key means "leave them alone"; an explicitly empty
		// array means "turn them all off". Those are different intents and
		// collapsing them would make the tool unpredictable.
		if ( ! array_key_exists( 'plugins', $input ) ) {
			return Profile::from_array(
				[
					'plugins' => Profile::live()->plugins,
					'theme'   => $this->optional_string( $input, 'theme' ),
				]
			);
		}

		return Profile::from_array(
			[
				'plugins' => (array) ( $input['plugins'] ?? [] ),
				'theme'   => $this->optional_string( $input, 'theme' ),
			]
		);
	}

	/**
	 * @param array<string, mixed> $input
	 */
	public function summarise( array $input ): string {
		return __( 'Enter safe mode', 'wp-safe-mode-ai' );
	}
}
