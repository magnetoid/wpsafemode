<?php
/**
 * Expose the toolkit through the WordPress Abilities API.
 *
 * @package WPSM
 */

declare( strict_types = 1 );

namespace WPSM\Abilities;

use WPSM\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Registers every tool as a WordPress ability.
 *
 * This is the preferred MCP path. The Abilities API landed in WordPress 6.9 and
 * the official WordPress/mcp-adapter plugin turns registered abilities into MCP
 * tools — which means an AI client can drive this toolkit without the plugin
 * implementing any MCP protocol code at all.
 *
 * Sites without the Abilities API fall back to Rest\McpController. Everything
 * here is guarded so older WordPress versions simply skip it.
 */
final class Register {

	private const NAMESPACE = 'wpsm';
	private const CATEGORY  = 'wpsm-safe-mode';

	public function __construct( private readonly Plugin $plugin ) {}

	public function register(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		$this->register_category();

		$expose_writes = (bool) $this->plugin->settings()->get( 'mcp_expose_writes', false );

		foreach ( $this->plugin->tools()->all() as $name => $tool ) {
			$public = $tool->mcp_public() || $expose_writes;

			wp_register_ability(
				self::NAMESPACE . '/' . str_replace( '_', '-', $name ),
				[
					'label'               => $tool->title(),
					'description'         => $tool->description(),
					'category'            => self::CATEGORY,
					'input_schema'        => $tool->input_schema(),
					'output_schema'       => [
						'type'        => 'object',
						'description' => 'Tool result, or a proposal id when the call needs human approval.',
					],
					'permission_callback' => static fn(): bool => current_user_can( \WPSM\CAP ),
					'execute_callback'    => function ( $input ) use ( $name ) {
						// Routed through the registry rather than the tool
						// directly, so abilities inherit the approval gate and
						// the audit log exactly as every other caller does.
						return $this->plugin->tools()->dispatch( $name, is_array( $input ) ? $input : [] );
					},
					'meta'                => [
						'mcp' => [
							'public' => $public,
						],
					],
				]
			);
		}
	}

	/**
	 * Categories must exist before abilities that reference them, which is why
	 * the categories hook fires first. Registering from inside the abilities
	 * hook works too as long as it happens before the ability itself.
	 */
	private function register_category(): void {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}

		wp_register_ability_category(
			self::CATEGORY,
			[
				'label'       => __( 'Safe Mode AI Toolkit', 'wp-safe-mode-ai' ),
				'description' => __( 'Inspect and change this site behind a snapshot, lint, approval and auto-revert chain.', 'wp-safe-mode-ai' ),
			]
		);
	}
}
