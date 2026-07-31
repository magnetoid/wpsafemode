<?php
/**
 * Claude API failure.
 *
 * @package WPSM
 */

declare( strict_types = 1 );

namespace WPSM\Agent;

defined( 'ABSPATH' ) || exit;

/**
 * Carries enough detail for the caller to decide whether retrying is sensible,
 * with a message already written for a human to read.
 */
final class ClaudeError extends \RuntimeException {

	public function __construct(
		string $message,
		public readonly int $status,
		public readonly ?int $retry_after = null,
		public readonly string $api_type = ''
	) {
		parent::__construct( $message, $status );
	}

	/**
	 * Rate limits and server errors are worth another attempt. A rejected key
	 * or a malformed request will fail identically forever.
	 */
	public function is_retryable(): bool {
		return 429 === $this->status || $this->status >= 500 || 0 === $this->status;
	}
}
