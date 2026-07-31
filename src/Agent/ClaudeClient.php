<?php
/**
 * Claude Messages API client.
 *
 * @package WPSM
 */

declare( strict_types = 1 );

namespace WPSM\Agent;

use WPSM\Audit\Log;
use WPSM\Support\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Talks to the Claude Messages API over WordPress' own HTTP layer.
 *
 * WHY NOT THE OFFICIAL PHP SDK
 * ----------------------------
 * anthropic-ai/sdk is PSR-18 based and needs an HTTP client bundled alongside
 * it — in practice Guzzle plus a PSR-7 implementation. This plugin ships as a
 * drop-in folder, so those would have to be committed into vendor/, and a
 * committed Guzzle is the single most common cause of fatal class collisions
 * between WordPress plugins: two plugins, two Guzzle majors, one autoloader,
 * one white screen. PHP-Scoper would solve it properly and is the right answer
 * if this is ever distributed, but it adds a build step the drop-in promise
 * does not need yet.
 *
 * Writing against wp_remote_post() instead means zero runtime dependencies,
 * and the site's proxy settings, TLS configuration and HTTP filters apply for
 * free. The surface used here is small — one endpoint, one streaming variant —
 * and the class is deliberately narrow so swapping in the scoped SDK later is a
 * single-file change.
 */
final class ClaudeClient {

	private const ENDPOINT    = 'https://api.anthropic.com/v1/messages';
	private const API_VERSION = '2023-06-01';

	/**
	 * Opting into server-side fallbacks by default: Claude Opus 5 runs safety
	 * classifiers that occasionally decline benign requests, and a toolkit that
	 * edits files and reads databases sits close enough to that line to trip
	 * them. "default" routes by refusal category rather than pinning a model.
	 */
	private const BETA_FALLBACK = 'server-side-fallback-2026-07-01';

	private const MAX_ATTEMPTS = 3;

	public function __construct(
		private readonly Settings $settings,
		private readonly Log $log
	) {}

	public function is_configured(): bool {
		return $this->settings->has_api_key();
	}

	/**
	 * Send one turn and wait for the complete response.
	 *
	 * @param array<string, mixed> $params Merged over the defaults.
	 *
	 * @return array<string, mixed> The decoded Message object.
	 *
	 * @throws ClaudeError
	 */
	public function messages( array $params ): array {
		$body     = $this->build_body( $params, false );
		$attempt  = 0;
		$last_err = null;

		while ( $attempt < self::MAX_ATTEMPTS ) {
			++$attempt;

			$response = wp_remote_post(
				self::ENDPOINT,
				[
					'headers' => $this->headers(),
					'body'    => (string) wp_json_encode( $body ),
					// Agentic turns at high effort are slow. This still has to
					// sit under the host's max_execution_time, which is why the
					// interactive path streams instead.
					'timeout' => 180,
				]
			);

			if ( is_wp_error( $response ) ) {
				$last_err = new ClaudeError(
					sprintf(
						/* translators: %s: transport error message */
						__( 'Could not reach the Claude API: %s', 'wp-safe-mode-ai' ),
						$response->get_error_message()
					),
					0
				);

				// A transport failure is worth one more go; a refused
				// connection usually is not, but we cannot tell them apart
				// here and the backoff keeps the cost low.
				$this->backoff( $attempt );
				continue;
			}

			$status  = (int) wp_remote_retrieve_response_code( $response );
			$decoded = json_decode( (string) wp_remote_retrieve_body( $response ), true );

			if ( 200 === $status && is_array( $decoded ) ) {
				return $decoded;
			}

			$error = $this->to_error( $status, is_array( $decoded ) ? $decoded : [], $response );

			if ( ! $error->is_retryable() ) {
				throw $error;
			}

			$last_err = $error;
			$this->backoff( $attempt, $error->retry_after );
		}

		throw $last_err ?? new ClaudeError( __( 'The Claude API did not respond.', 'wp-safe-mode-ai' ), 0 );
	}

	/**
	 * Stream one turn, invoking a callback for each raw SSE chunk.
	 *
	 * Used by the REST proxy to relay events straight through to the browser,
	 * so the operator sees text as it is generated rather than after a
	 * multi-minute pause. Needs the curl extension; wp_remote_post() buffers
	 * the whole body and cannot do this.
	 *
	 * @param array<string, mixed> $params
	 * @param callable(string):void $on_chunk
	 *
	 * @throws ClaudeError
	 */
	public function stream( array $params, callable $on_chunk ): void {
		if ( ! function_exists( 'curl_init' ) ) {
			throw new ClaudeError(
				__( 'Streaming needs the PHP curl extension, which is not available on this host. Turn streaming off in the toolkit settings.', 'wp-safe-mode-ai' ),
				0
			);
		}

		$body = $this->build_body( $params, true );

		$headers = [];
		foreach ( $this->headers() as $name => $value ) {
			$headers[] = $name . ': ' . $value;
		}

		$handle = curl_init( self::ENDPOINT );

		if ( false === $handle ) {
			throw new ClaudeError( __( 'Could not initialise the HTTP client.', 'wp-safe-mode-ai' ), 0 );
		}

		$error_body = '';

		curl_setopt_array(
			$handle,
			[
				CURLOPT_POST           => true,
				CURLOPT_HTTPHEADER     => $headers,
				CURLOPT_POSTFIELDS     => (string) wp_json_encode( $body ),
				CURLOPT_RETURNTRANSFER => false,
				CURLOPT_TIMEOUT        => 900,
				CURLOPT_WRITEFUNCTION  => static function ( $ch, string $chunk ) use ( $on_chunk, &$error_body ): int {
					$status = (int) curl_getinfo( $ch, CURLINFO_RESPONSE_CODE );

					// A non-200 body is a JSON error object, not an event
					// stream. Collect it rather than relaying it as if it were
					// content the browser can render.
					if ( $status >= 400 ) {
						$error_body .= $chunk;
					} else {
						$on_chunk( $chunk );
					}

					return strlen( $chunk );
				},
			]
		);

		$ok     = curl_exec( $handle );
		$status = (int) curl_getinfo( $handle, CURLINFO_RESPONSE_CODE );
		$err    = curl_error( $handle );

		curl_close( $handle );

		if ( false === $ok && '' !== $err ) {
			throw new ClaudeError(
				sprintf(
					/* translators: %s: transport error message */
					__( 'The connection to the Claude API failed: %s', 'wp-safe-mode-ai' ),
					$err
				),
				0
			);
		}

		if ( $status >= 400 ) {
			$decoded = json_decode( $error_body, true );

			throw $this->to_error( $status, is_array( $decoded ) ? $decoded : [], null );
		}
	}

	// ------------------------------------------------------------------
	// Request construction
	// ------------------------------------------------------------------

	/**
	 * @param array<string, mixed> $params
	 * @return array<string, mixed>
	 */
	private function build_body( array $params, bool $stream ): array {
		$body = array_merge(
			[
				'model'         => (string) $this->settings->get( 'model', 'claude-opus-5' ),
				'max_tokens'    => $stream ? 64000 : (int) $this->settings->get( 'max_tokens', 16000 ),
				// Adaptive is the default on Opus 5; stating it explicitly keeps
				// the request honest if the configured model is changed to one
				// where it is not.
				'thinking'      => [ 'type' => 'adaptive' ],
				'output_config' => [ 'effort' => (string) $this->settings->get( 'effort', 'high' ) ],
				'fallbacks'     => 'default',
			],
			$params
		);

		if ( $stream ) {
			$body['stream'] = true;
		}

		return $body;
	}

	/**
	 * @return array<string, string>
	 */
	private function headers(): array {
		$key = $this->settings->api_key();

		if ( '' === $key ) {
			throw new ClaudeError(
				__( 'No Anthropic API key is configured. Add one in the toolkit settings, or set ANTHROPIC_API_KEY in the environment.', 'wp-safe-mode-ai' ),
				0
			);
		}

		return [
			'x-api-key'         => $key,
			'anthropic-version' => self::API_VERSION,
			'anthropic-beta'    => self::BETA_FALLBACK,
			'content-type'      => 'application/json',
			'user-agent'        => 'wp-safe-mode-ai/' . \WPSM\VERSION . '; ' . home_url(),
		];
	}

	// ------------------------------------------------------------------
	// Errors
	// ------------------------------------------------------------------

	/**
	 * @param array<string, mixed> $decoded
	 * @param array<string, mixed>|null $response
	 */
	private function to_error( int $status, array $decoded, ?array $response ): ClaudeError {
		$api_message = $decoded['error']['message'] ?? '';
		$api_type    = $decoded['error']['type'] ?? '';

		$message = match ( true ) {
			401 === $status => __( 'The Anthropic API rejected the key. Check it in the toolkit settings.', 'wp-safe-mode-ai' ),
			403 === $status => __( 'The Anthropic API key does not have access to this model.', 'wp-safe-mode-ai' ),
			404 === $status => __( 'The Anthropic API rejected the model name. Check the model in the toolkit settings.', 'wp-safe-mode-ai' ),
			429 === $status => __( 'Rate limited by the Anthropic API.', 'wp-safe-mode-ai' ),
			$status >= 500  => __( 'The Anthropic API is unavailable right now.', 'wp-safe-mode-ai' ),
			default         => sprintf(
				/* translators: %s: message from the API */
				__( 'The Anthropic API rejected the request: %s', 'wp-safe-mode-ai' ),
				'' !== $api_message ? $api_message : __( 'no detail given', 'wp-safe-mode-ai' )
			),
		};

		$retry_after = null;

		if ( null !== $response ) {
			$header = wp_remote_retrieve_header( $response, 'retry-after' );

			if ( is_string( $header ) && ctype_digit( $header ) ) {
				$retry_after = (int) $header;
			}
		}

		$this->log->record(
			'claude.error',
			(string) $status,
			[
				'type'    => $api_type,
				'message' => $api_message,
			]
		);

		return new ClaudeError( $message, $status, $retry_after, (string) $api_type );
	}

	private function backoff( int $attempt, ?int $retry_after = null ): void {
		$seconds = $retry_after ?? min( 8, 2 ** $attempt );

		// Bounded so a request cannot sit here past the host's execution limit
		// with nothing to show for it.
		sleep( max( 1, min( 10, $seconds ) ) );
	}
}
