<?php
/**
 * Base class for every tool the agent can call.
 *
 * @package WPSM
 */

declare( strict_types = 1 );

namespace WPSM\Tools;

use WPSM\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * One tool, one class.
 *
 * The same object backs all three front doors — the built-in agent, the MCP
 * surface, and the REST API — so there is exactly one place where a capability
 * is defined and exactly one place where it is guarded. Adding a tool that is
 * safe over REST but dangerous over MCP is not something this shape lets you
 * do by accident.
 */
abstract class Tool {

	public function __construct( protected readonly Plugin $plugin ) {}

	/** Machine name, as sent to the model. Lower snake case. */
	abstract public function name(): string;

	/** Short human label for the UI. */
	abstract public function title(): string;

	/** Description the model reads to decide when to call this. */
	abstract public function description(): string;

	/**
	 * JSON Schema for the tool input.
	 *
	 * @return array<string, mixed>
	 */
	abstract public function input_schema(): array;

	/**
	 * Execute.
	 *
	 * @param array<string, mixed> $input Validated against input_schema().
	 * @return array<string, mixed>
	 */
	abstract public function run( array $input ): array;

	/**
	 * Whether calling this can change the site.
	 *
	 * Mutating tools do not execute when the model calls them. They return a
	 * proposal that a human approves first.
	 */
	public function mutates(): bool {
		return false;
	}

	/**
	 * Describe what run() would do, without doing it.
	 *
	 * Only meaningful for mutating tools. The return value is what the approval
	 * UI renders, so it should be specific: a diff, a row count, the exact
	 * paths involved.
	 *
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>
	 */
	public function preview( array $input ): array {
		return [];
	}

	/**
	 * Whether this tool may be exposed over MCP to an external client.
	 *
	 * Defaults to read-only. Mutating tools are opt-in per site, because an
	 * MCP endpoint is reachable by anything holding the credential, and that is
	 * a wider audience than the admin screen.
	 */
	public function mcp_public(): bool {
		return ! $this->mutates();
	}

	/**
	 * One-line summary of a call, for the audit log and the approval list.
	 *
	 * @param array<string, mixed> $input
	 */
	public function summarise( array $input ): string {
		return $this->title();
	}

	/**
	 * Shape for the Claude Messages API `tools` parameter.
	 *
	 * @return array<string, mixed>
	 */
	final public function to_claude_schema(): array {
		return [
			'name'         => $this->name(),
			'description'  => $this->description(),
			'input_schema' => $this->input_schema(),
		];
	}

	// ------------------------------------------------------------------
	// Helpers for subclasses
	// ------------------------------------------------------------------

	/**
	 * Read a required string from the input.
	 *
	 * @param array<string, mixed> $input
	 *
	 * @throws \InvalidArgumentException
	 */
	protected function required_string( array $input, string $key ): string {
		$value = $input[ $key ] ?? null;

		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			throw new \InvalidArgumentException(
				sprintf(
					/* translators: 1: parameter name, 2: tool name */
					__( 'The %1$s parameter is required by %2$s.', 'wp-safe-mode-ai' ),
					$key,
					$this->name()
				)
			);
		}

		return $value;
	}

	/**
	 * @param array<string, mixed> $input
	 */
	protected function optional_string( array $input, string $key, string $default = '' ): string {
		$value = $input[ $key ] ?? null;

		return is_string( $value ) ? $value : $default;
	}

	/**
	 * @param array<string, mixed> $input
	 */
	protected function optional_int( array $input, string $key, int $default ): int {
		$value = $input[ $key ] ?? null;

		return is_numeric( $value ) ? (int) $value : $default;
	}

	/**
	 * @param array<string, mixed> $input
	 */
	protected function optional_bool( array $input, string $key, bool $default = false ): bool {
		$value = $input[ $key ] ?? null;

		if ( is_bool( $value ) ) {
			return $value;
		}

		if ( is_string( $value ) ) {
			return in_array( strtolower( $value ), [ '1', 'true', 'yes', 'on' ], true );
		}

		return $default;
	}
}
