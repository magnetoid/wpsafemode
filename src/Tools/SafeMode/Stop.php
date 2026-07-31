<?php
/**
 * Leave safe mode.
 *
 * @package WPSM
 */

declare( strict_types = 1 );

namespace WPSM\Tools\SafeMode;

use WPSM\Tools\Tool;

defined( 'ABSPATH' ) || exit;

/**
 * Closes the safe-mode session and clears the cookie.
 */
final class Stop extends Tool {

	public function name(): string {
		return 'safe_mode_stop';
	}

	public function title(): string {
		return __( 'Leave safe mode', 'wp-safe-mode-ai' );
	}

	public function description(): string {
		return 'End the current safe-mode session and go back to seeing the site as everyone else does.';
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

	public function mutates(): bool {
		return true;
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>
	 */
	public function preview( array $input ): array {
		$status = $this->plugin->sessions()->status();

		return [
			'kind'    => 'safe_mode_stop',
			'active'  => $status['active'],
			'profile' => $status['profile'],
			'blocked' => ! $status['active'],
			'error'   => $status['active'] ? null : __( 'Safe mode is not currently active.', 'wp-safe-mode-ai' ),
		];
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>
	 */
	public function run( array $input ): array {
		$this->plugin->sessions()->stop();

		return [ 'active' => false ];
	}
}
