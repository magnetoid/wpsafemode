<?php
/**
 * Minimal MCP server over HTTP.
 *
 * @package WPSM
 */

declare( strict_types = 1 );

namespace WPSM\Rest;

use WPSM\Plugin;
use WPSM\Tools\Tool;

defined( 'ABSPATH' ) || exit;

/**
 * A small JSON-RPC handler implementing the MCP methods that matter.
 *
 * This is the fallback path. The preferred route is the Abilities API: this
 * plugin registers its tools with wp_register_ability(), and the official
 * WordPress/mcp-adapter plugin exposes them — no protocol code of ours in the
 * way. That needs WordPress 6.9+ and the adapter installed, so this covers
 * sites that have neither.
 *
 * Deliberately not built on a PHP MCP SDK: those are all pre-1.0, and the four
 * methods below are the entire surface a tool server needs. A dependency that
 * has to be committed into the plugin folder is not worth it for this much
 * code.
 */
final class McpController {

	private const PROTOCOL_VERSION = '2025-06-18';

	public function __construct( private readonly Plugin $plugin ) {}

	public function handle( \WP_REST_Request $request ): \WP_REST_Response {
		$body = $request->get_json_params();

		if ( ! is_array( $body ) ) {
			return $this->error( null, -32700, __( 'Parse error.', 'wp-safe-mode-ai' ) );
		}

		// Batches are a valid JSON-RPC shape and MCP clients do send them.
		if ( array_is_list( $body ) ) {
			$responses = [];

			foreach ( $body as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}

				$response = $this->dispatch( $item );

				if ( null !== $response ) {
					$responses[] = $response;
				}
			}

			return new \WP_REST_Response( $responses );
		}

		$response = $this->dispatch( $body );

		// Notifications get no reply; 202 is the correct acknowledgement.
		return null === $response
			? new \WP_REST_Response( null, 202 )
			: new \WP_REST_Response( $response );
	}

	/**
	 * @param array<string, mixed> $message
	 * @return array<string, mixed>|null Null for notifications.
	 */
	private function dispatch( array $message ): ?array {
		$method = (string) ( $message['method'] ?? '' );
		$id     = $message['id'] ?? null;

		// No id means a notification: act on it, answer nothing.
		if ( null === $id ) {
			return null;
		}

		return match ( $method ) {
			'initialize' => $this->result( $id, $this->initialize() ),
			'ping'       => $this->result( $id, new \stdClass() ),
			'tools/list' => $this->result( $id, [ 'tools' => $this->tool_list() ] ),
			'tools/call' => $this->call( $id, (array) ( $message['params'] ?? [] ) ),
			default      => $this->error(
				$id,
				-32601,
				sprintf(
					/* translators: %s: JSON-RPC method name */
					__( 'Method %s is not supported by this server.', 'wp-safe-mode-ai' ),
					$method
				)
			)->get_data(),
		};
	}

	/**
	 * @return array<string, mixed>
	 */
	private function initialize(): array {
		return [
			'protocolVersion' => self::PROTOCOL_VERSION,
			'capabilities'    => [
				'tools' => new \stdClass(),
			],
			'serverInfo'      => [
				'name'    => 'wp-safe-mode-ai',
				'version' => \WPSM\VERSION,
				'title'   => get_bloginfo( 'name' ) . ' — WP Safe Mode AI Toolkit',
			],
			'instructions'    => 'Tools operate on one WordPress site. Read-only tools run immediately. '
				. 'Tools that change the site return a proposal id instead of executing — a human approves it in '
				. 'the site admin, at Tools → Safe Mode AI. Nothing you call here can change the site on its own.',
		];
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function tool_list(): array {
		$out = [];

		foreach ( $this->exposed_tools() as $tool ) {
			$description = $tool->description();

			// Be explicit at the protocol boundary: a client that does not know
			// about the approval queue will otherwise read a proposal id as a
			// completed action.
			if ( $tool->mutates() ) {
				$description .= ' NOTE: this does not execute immediately — it queues a proposal for a human to '
					. 'approve in the WordPress admin, and returns the proposal id.';
			}

			$out[] = [
				'name'        => $tool->name(),
				'title'       => $tool->title(),
				'description' => $description,
				'inputSchema' => $tool->input_schema(),
			];
		}

		return $out;
	}

	/**
	 * @param array<string, mixed> $params
	 * @return array<string, mixed>
	 */
	private function call( mixed $id, array $params ): array {
		$name = (string) ( $params['name'] ?? '' );
		$args = is_array( $params['arguments'] ?? null ) ? $params['arguments'] : [];

		$exposed = $this->exposed_tools();

		if ( ! isset( $exposed[ $name ] ) ) {
			return $this->result(
				$id,
				[
					'isError' => true,
					'content' => [
						[
							'type' => 'text',
							'text' => sprintf(
								/* translators: %s: tool name */
								__( 'The tool %s is not available over MCP on this site.', 'wp-safe-mode-ai' ),
								$name
							),
						],
					],
				]
			);
		}

		$outcome = $this->plugin->tools()->dispatch( $name, $args );

		if ( ! ( $outcome['ok'] ?? false ) ) {
			return $this->result(
				$id,
				[
					'isError' => true,
					'content' => [
						[
							'type' => 'text',
							'text' => (string) ( $outcome['error'] ?? __( 'The tool failed.', 'wp-safe-mode-ai' ) ),
						],
					],
				]
			);
		}

		if ( ! empty( $outcome['needs_approval'] ) ) {
			return $this->result(
				$id,
				[
					'content' => [
						[
							'type' => 'text',
							'text' => sprintf(
								/* translators: 1: proposal id, 2: JSON preview of the change */
								__( "Queued for approval as %1\$s. It has not been applied. A site administrator must approve it at Tools → Safe Mode AI.\n\nWhat it would do:\n%2\$s", 'wp-safe-mode-ai' ),
								(string) $outcome['proposal_id'],
								(string) wp_json_encode( $outcome['preview'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES )
							),
						],
					],
				]
			);
		}

		return $this->result(
			$id,
			[
				'content' => [
					[
						'type' => 'text',
						'text' => (string) wp_json_encode( $outcome['result'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ),
					],
				],
			]
		);
	}

	/**
	 * Tools this site is willing to expose over MCP.
	 *
	 * Read-only by default. An MCP endpoint is reachable by anything holding
	 * the credential, which is a wider audience than the admin screen, so
	 * write access is opt-in per site.
	 *
	 * @return array<string, Tool>
	 */
	private function exposed_tools(): array {
		$expose_writes = (bool) $this->plugin->settings()->get( 'mcp_expose_writes', false );
		$exposed       = [];

		foreach ( $this->plugin->tools()->all() as $name => $tool ) {
			if ( $tool->mcp_public() || $expose_writes ) {
				$exposed[ $name ] = $tool;
			}
		}

		return $exposed;
	}

	/**
	 * @param array<string, mixed>|\stdClass $result
	 * @return array<string, mixed>
	 */
	private function result( mixed $id, mixed $result ): array {
		return [
			'jsonrpc' => '2.0',
			'id'      => $id,
			'result'  => $result,
		];
	}

	private function error( mixed $id, int $code, string $message ): \WP_REST_Response {
		return new \WP_REST_Response(
			[
				'jsonrpc' => '2.0',
				'id'      => $id,
				'error'   => [
					'code'    => $code,
					'message' => $message,
				],
			]
		);
	}
}
