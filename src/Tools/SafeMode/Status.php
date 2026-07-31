<?php
/**
 * Safe mode status tool.
 *
 * @package WPSM
 */

declare( strict_types = 1 );

namespace WPSM\Tools\SafeMode;

use WPSM\SafeMode\Profile;
use WPSM\Tools\Tool;

defined( 'ABSPATH' ) || exit;

/**
 * Reports whether safe mode is on, what it is showing, and whether the
 * drop-ins that make it work are healthy.
 */
final class Status extends Tool {

	public function name(): string {
		return 'safe_mode_status';
	}

	public function title(): string {
		return __( 'Safe mode status', 'wp-safe-mode-ai' );
	}

	public function description(): string {
		return 'Report whether safe mode is currently active for this session, which plugins and theme it is '
			. 'showing, and whether the two drop-in files that implement it are installed and intact. '
			. 'Check this before assuming a change you made is or is not visible to real visitors.';
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
		$status  = $this->plugin->sessions()->status();
		$dropins = $this->plugin->dropins()->status();

		$available = [];
		foreach ( Profile::installed_plugins() as $file => $data ) {
			$available[] = [
				'file' => $file,
				'name' => $data['Name'] ?? $file,
			];
		}

		return [
			'session'           => $status,
			'dropins'           => $dropins,
			'auto_revert_ready' => $dropins['fatal_handler']['healthy'] ?? false,
			'overlay_ready'     => $dropins['session_overlay']['healthy'] ?? false,
			// What visitors get, regardless of any overlay this session has on.
			'live_state'        => Profile::live()->to_array(),
			// What this request itself sees — identical to live_state unless a
			// session is open.
			'session_state'     => Profile::current()->to_array(),
			'available_plugins' => $available,
		];
	}
}
