<?php
/**
 * REST API surface.
 *
 * @package WPSM
 */

declare( strict_types = 1 );

namespace WPSM\Rest;

use WPSM\Agent\ClaudeError;
use WPSM\Agent\SystemPrompt;
use WPSM\Plugin;
use WPSM\SafeMode\Profile;
use WPSM\Support\Capabilities;
use WPSM\Tools\Proposals;

defined( 'ABSPATH' ) || exit;

/**
 * Everything the admin UI and external clients talk to.
 *
 * Most of the surface is one generic route — POST /tools/{name} — because the
 * tool registry is already the authoritative list of what can be done and what
 * needs approval. Adding a tool should not mean adding a route, and it
 * certainly should not mean adding a second place where permissions are
 * checked.
 */
final class Controller {

	public function __construct( private readonly Plugin $plugin ) {}

	public function register_routes(): void {
		$ns   = \WPSM\REST_NS;
		$auth = [ Capabilities::class, 'rest_check' ];

		register_rest_route(
			$ns,
			'/status',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'status' ],
				'permission_callback' => $auth,
			]
		);

		register_rest_route(
			$ns,
			'/tools',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'tools' ],
				'permission_callback' => $auth,
			]
		);

		register_rest_route(
			$ns,
			'/tools/(?P<name>[a-z0-9_]+)',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'run_tool' ],
				'permission_callback' => $auth,
				'args'                => [
					'name' => [
						'required'          => true,
						'validate_callback' => static fn( $value ): bool => is_string( $value ),
					],
				],
			]
		);

		register_rest_route(
			$ns,
			'/proposals',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'list_proposals' ],
				'permission_callback' => $auth,
			]
		);

		register_rest_route(
			$ns,
			'/proposals/(?P<id>[A-Za-z0-9_]+)/approve',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'approve_proposal' ],
				'permission_callback' => $auth,
			]
		);

		register_rest_route(
			$ns,
			'/proposals/(?P<id>[A-Za-z0-9_]+)/reject',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'reject_proposal' ],
				'permission_callback' => $auth,
			]
		);

		register_rest_route(
			$ns,
			'/journal',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'journal' ],
				'permission_callback' => $auth,
			]
		);

		register_rest_route(
			$ns,
			'/audit',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'audit' ],
				'permission_callback' => $auth,
			]
		);

		register_rest_route(
			$ns,
			'/settings',
			[
				[
					'methods'             => 'GET',
					'callback'            => [ $this, 'get_settings' ],
					'permission_callback' => $auth,
				],
				[
					'methods'             => 'POST',
					'callback'            => [ $this, 'update_settings' ],
					'permission_callback' => $auth,
				],
			]
		);

		register_rest_route(
			$ns,
			'/dropins/install',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'install_dropins' ],
				'permission_callback' => $auth,
			]
		);

		register_rest_route(
			$ns,
			'/agent/turn',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'agent_turn' ],
				'permission_callback' => $auth,
			]
		);

		register_rest_route(
			$ns,
			'/agent/stream',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'agent_stream' ],
				'permission_callback' => $auth,
			]
		);

		register_rest_route(
			$ns,
			'/mcp',
			[
				'methods'             => 'POST',
				'callback'            => [ new McpController( $this->plugin ), 'handle' ],
				'permission_callback' => $auth,
			]
		);
	}

	// ------------------------------------------------------------------
	// Status and catalogue
	// ------------------------------------------------------------------

	public function status( \WP_REST_Request $request ): \WP_REST_Response {
		$settings = $this->plugin->settings();

		return new \WP_REST_Response(
			[
				'version'     => \WPSM\VERSION,
				'site'        => $this->plugin->tools()->dispatch( 'site_info', [] )['result'] ?? [],
				'safe_mode'   => $this->plugin->sessions()->status(),
				'dropins'     => $this->plugin->dropins()->status(),
				'agent'       => [
					'configured' => $settings->has_api_key(),
					'key_hint'   => $settings->api_key_hint(),
					'external'   => $settings->api_key_is_external(),
					'model'      => $settings->get( 'model' ),
					'effort'     => $settings->get( 'effort' ),
				],
				'proposals'   => count( $this->plugin->proposals()->list( [ 'status' => Proposals::PENDING ] ) ),
				'profiles'    => [
					// live() rather than current(): with a session open,
					// current() describes the overlay, and the picker needs the
					// real baseline to offer sensible choices.
					'live'    => Profile::live()->to_array(),
					'session' => Profile::current()->to_array(),
					'minimal' => Profile::minimal()->to_array(),
				],
			]
		);
	}

	public function tools( \WP_REST_Request $request ): \WP_REST_Response {
		return new \WP_REST_Response( [ 'tools' => $this->plugin->tools()->catalogue() ] );
	}

	public function run_tool( \WP_REST_Request $request ): \WP_REST_Response {
		$name  = (string) $request->get_param( 'name' );
		$input = $request->get_json_params();

		$result = $this->plugin->tools()->dispatch( $name, is_array( $input ) ? $input : [] );

		return new \WP_REST_Response( $result, ( $result['ok'] ?? false ) ? 200 : 400 );
	}

	// ------------------------------------------------------------------
	// Approval queue
	// ------------------------------------------------------------------

	public function list_proposals( \WP_REST_Request $request ): \WP_REST_Response {
		$status = (string) ( $request->get_param( 'status' ) ?: Proposals::PENDING );

		return new \WP_REST_Response(
			[ 'proposals' => $this->plugin->proposals()->list( [ 'status' => $status ] ) ]
		);
	}

	public function approve_proposal( \WP_REST_Request $request ): \WP_REST_Response {
		$result = $this->plugin->proposals()->approve( (string) $request->get_param( 'id' ) );

		return new \WP_REST_Response( $result, ( $result['ok'] ?? false ) ? 200 : 400 );
	}

	public function reject_proposal( \WP_REST_Request $request ): \WP_REST_Response {
		$body = $request->get_json_params();
		$ok   = $this->plugin->proposals()->reject(
			(string) $request->get_param( 'id' ),
			is_array( $body ) ? (string) ( $body['reason'] ?? '' ) : ''
		);

		return new \WP_REST_Response( [ 'ok' => $ok ], $ok ? 200 : 400 );
	}

	// ------------------------------------------------------------------
	// History
	// ------------------------------------------------------------------

	public function journal( \WP_REST_Request $request ): \WP_REST_Response {
		return new \WP_REST_Response(
			[
				'changes' => $this->plugin->journal()->list(
					[
						'status' => (string) ( $request->get_param( 'status' ) ?: '' ),
						'limit'  => (int) ( $request->get_param( 'limit' ) ?: 50 ),
						'offset' => (int) ( $request->get_param( 'offset' ) ?: 0 ),
					]
				),
			]
		);
	}

	public function audit( \WP_REST_Request $request ): \WP_REST_Response {
		return new \WP_REST_Response(
			[
				'entries' => $this->plugin->log()->list(
					[
						'event'  => (string) ( $request->get_param( 'event' ) ?: '' ),
						'limit'  => (int) ( $request->get_param( 'limit' ) ?: 100 ),
						'offset' => (int) ( $request->get_param( 'offset' ) ?: 0 ),
					]
				),
			]
		);
	}

	// ------------------------------------------------------------------
	// Settings and drop-ins
	// ------------------------------------------------------------------

	public function get_settings( \WP_REST_Request $request ): \WP_REST_Response {
		$settings = $this->plugin->settings();

		return new \WP_REST_Response(
			$settings->all() + [
				'api_key_set'      => $settings->has_api_key(),
				'api_key_hint'     => $settings->api_key_hint(),
				'api_key_external' => $settings->api_key_is_external(),
			]
		);
	}

	public function update_settings( \WP_REST_Request $request ): \WP_REST_Response {
		$body = $request->get_json_params();
		$body = is_array( $body ) ? $body : [];

		if ( array_key_exists( 'api_key', $body ) ) {
			$this->plugin->settings()->set_api_key( (string) $body['api_key'] );
			$this->plugin->log()->record( 'settings.api_key_changed' );
			unset( $body['api_key'] );
		}

		$this->plugin->settings()->update( $body );
		$this->plugin->log()->record( 'settings.updated', '', [ 'keys' => array_keys( $body ) ] );

		return $this->get_settings( $request );
	}

	public function install_dropins( \WP_REST_Request $request ): \WP_REST_Response {
		return new \WP_REST_Response(
			[
				'install' => $this->plugin->dropins()->install(),
				'status'  => $this->plugin->dropins()->status(),
			]
		);
	}

	// ------------------------------------------------------------------
	// Agent
	// ------------------------------------------------------------------

	/**
	 * One Claude turn.
	 *
	 * The conversation lives in the browser and is sent up each time. That is
	 * deliberate: a multi-turn agent loop inside one PHP request would hit
	 * max_execution_time on most hosts, and keeping each request to a single
	 * API call means the approval UI has a natural place to sit between a tool
	 * call and its execution.
	 */
	public function agent_turn( \WP_REST_Request $request ): \WP_REST_Response {
		$body     = $request->get_json_params();
		$messages = is_array( $body ) ? ( $body['messages'] ?? [] ) : [];

		if ( ! is_array( $messages ) || [] === $messages ) {
			return new \WP_REST_Response(
				[ 'error' => __( 'No messages were sent.', 'wp-safe-mode-ai' ) ],
				400
			);
		}

		try {
			$message = $this->plugin->claude()->messages(
				[
					'system'   => ( new SystemPrompt( $this->plugin ) )->build(),
					'messages' => $messages,
					'tools'    => $this->plugin->tools()->claude_schemas(),
				]
			);
		} catch ( ClaudeError $e ) {
			return new \WP_REST_Response(
				[
					'error'       => $e->getMessage(),
					'status'      => $e->status,
					'retryable'   => $e->is_retryable(),
					'retry_after' => $e->retry_after,
				],
				502
			);
		}

		// A refusal is a successful HTTP response with no usable content. The
		// client has to be told explicitly, or it will render an empty turn.
		if ( 'refusal' === ( $message['stop_reason'] ?? '' ) ) {
			$this->plugin->log()->record(
				'claude.refusal',
				'',
				[ 'category' => $message['stop_details']['category'] ?? null ]
			);
		}

		return new \WP_REST_Response( [ 'message' => $message ] );
	}

	/**
	 * Same as agent_turn, relayed as Server-Sent Events.
	 *
	 * Bypasses WP_REST_Response entirely — this writes to the output buffer and
	 * exits, because the whole point is that bytes reach the browser before the
	 * turn is finished.
	 */
	public function agent_stream( \WP_REST_Request $request ): void {
		$body     = $request->get_json_params();
		$messages = is_array( $body ) ? ( $body['messages'] ?? [] ) : [];

		nocache_headers();
		header( 'Content-Type: text/event-stream; charset=utf-8' );
		header( 'Cache-Control: no-cache, no-store, must-revalidate' );
		// nginx and several managed hosts buffer proxied responses by default,
		// which turns a stream back into a long pause. This asks them not to.
		header( 'X-Accel-Buffering: no' );

		while ( ob_get_level() > 0 ) {
			ob_end_flush();
		}

		try {
			$this->plugin->claude()->stream(
				[
					'system'   => ( new SystemPrompt( $this->plugin ) )->build(),
					'messages' => is_array( $messages ) ? $messages : [],
					'tools'    => $this->plugin->tools()->claude_schemas(),
				],
				static function ( string $chunk ): void {
					echo $chunk; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- raw SSE relay.
					flush();
				}
			);
		} catch ( ClaudeError $e ) {
			// Emit the failure as an SSE event so the client sees it on the
			// same channel rather than as a truncated stream.
			printf(
				"event: wpsm_error\ndata: %s\n\n",
				(string) wp_json_encode( [ 'error' => $e->getMessage(), 'status' => $e->status ] )
			);
			flush();
		}

		exit;
	}
}
