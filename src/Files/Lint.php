<?php
/**
 * Syntax validation for PHP before it is written to disk.
 *
 * @package WPSM
 */

declare( strict_types = 1 );

namespace WPSM\Files;

defined( 'ABSPATH' ) || exit;

/**
 * The last gate before bytes hit the filesystem.
 *
 * Two strategies, in order of preference:
 *
 *  1. `php -l` in a subprocess. Authoritative, catches everything the runtime
 *     would catch, and cannot execute the code being checked.
 *  2. token_get_all() with TOKEN_PARSE. Available everywhere, needs no
 *     subprocess, and throws ParseError on invalid syntax. It is slightly less
 *     strict than `php -l` on a handful of compile-time errors, which is why it
 *     is the fallback rather than the default.
 *
 * Shared hosts frequently disable proc_open, so strategy 2 is not hypothetical.
 */
final class Lint {

	/**
	 * @return array{ok: bool, strategy: string, message: string, line: int|null}
	 */
	public function check( string $source, string $filename = 'buffer.php' ): array {
		if ( ! $this->looks_like_php( $filename ) ) {
			return [
				'ok'       => true,
				'strategy' => 'skipped',
				'message'  => __( 'Not a PHP file; no syntax check needed.', 'wp-safe-mode-ai' ),
				'line'     => null,
			];
		}

		if ( $this->can_use_subprocess() ) {
			$result = $this->check_with_binary( $source );

			if ( null !== $result ) {
				return $result;
			}
		}

		return $this->check_with_tokenizer( $source );
	}

	private function looks_like_php( string $filename ): bool {
		return 'php' === strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
	}

	private function can_use_subprocess(): bool {
		if ( ! function_exists( 'proc_open' ) ) {
			return false;
		}

		$disabled = array_map( 'trim', explode( ',', (string) ini_get( 'disable_functions' ) ) );

		return ! in_array( 'proc_open', $disabled, true );
	}

	/**
	 * @return array{ok: bool, strategy: string, message: string, line: int|null}|null
	 *         Null when the subprocess could not be run at all, so the caller
	 *         falls through to the tokenizer rather than treating an
	 *         environment problem as a syntax error.
	 */
	private function check_with_binary( string $source ): ?array {
		$binary = $this->php_binary();

		if ( null === $binary ) {
			return null;
		}

		$descriptors = [
			0 => [ 'pipe', 'r' ],
			1 => [ 'pipe', 'w' ],
			2 => [ 'pipe', 'w' ],
		];

		// -n ignores php.ini so a host-level auto_prepend_file cannot pollute
		// the check; -d display_errors=1 makes sure the message reaches stdout.
		$process = @proc_open(
			escapeshellarg( $binary ) . ' -n -d display_errors=1 -d error_reporting=E_ALL -l',
			$descriptors,
			$pipes
		);

		if ( ! is_resource( $process ) ) {
			return null;
		}

		fwrite( $pipes[0], $source );
		fclose( $pipes[0] );

		$stdout = (string) stream_get_contents( $pipes[1] );
		$stderr = (string) stream_get_contents( $pipes[2] );
		fclose( $pipes[1] );
		fclose( $pipes[2] );

		$exit = proc_close( $process );

		if ( 0 === $exit ) {
			return [
				'ok'       => true,
				'strategy' => 'php -l',
				'message'  => __( 'No syntax errors detected.', 'wp-safe-mode-ai' ),
				'line'     => null,
			];
		}

		$output = trim( $stdout . "\n" . $stderr );

		return [
			'ok'       => false,
			'strategy' => 'php -l',
			'message'  => $this->tidy_message( $output ),
			'line'     => $this->extract_line( $output ),
		];
	}

	/**
	 * @return array{ok: bool, strategy: string, message: string, line: int|null}
	 */
	private function check_with_tokenizer( string $source ): array {
		if ( ! function_exists( 'token_get_all' ) ) {
			// Without a tokenizer we cannot vouch for the source, and the
			// safety chain does not write anything it cannot vouch for.
			return [
				'ok'       => false,
				'strategy' => 'none',
				'message'  => __( 'No syntax checker is available on this host (proc_open and the tokenizer extension are both unavailable), so the write was refused.', 'wp-safe-mode-ai' ),
				'line'     => null,
			];
		}

		try {
			token_get_all( $source, TOKEN_PARSE );
		} catch ( \ParseError $e ) {
			return [
				'ok'       => false,
				'strategy' => 'token_get_all',
				'message'  => $e->getMessage(),
				'line'     => $e->getLine(),
			];
		} catch ( \Throwable $e ) {
			return [
				'ok'       => false,
				'strategy' => 'token_get_all',
				'message'  => $e->getMessage(),
				'line'     => null,
			];
		}

		return [
			'ok'       => true,
			'strategy' => 'token_get_all',
			'message'  => __( 'No syntax errors detected.', 'wp-safe-mode-ai' ),
			'line'     => null,
		];
	}

	private function php_binary(): ?string {
		if ( defined( 'PHP_BINARY' ) && '' !== PHP_BINARY && is_executable( PHP_BINARY ) ) {
			// PHP_BINARY is php-fpm under FPM, which has no -l. Prefer a CLI
			// sibling when one is sitting next to it.
			$base = basename( PHP_BINARY );

			if ( ! str_contains( $base, 'fpm' ) ) {
				return PHP_BINARY;
			}

			$sibling = dirname( PHP_BINARY ) . '/php';
			if ( is_executable( $sibling ) ) {
				return $sibling;
			}
		}

		foreach ( [ '/usr/local/bin/php', '/usr/bin/php', '/opt/homebrew/bin/php' ] as $candidate ) {
			if ( is_executable( $candidate ) ) {
				return $candidate;
			}
		}

		return null;
	}

	private function tidy_message( string $output ): string {
		// `php -l` reports against the stdin stream; that path is noise to the
		// reader, who knows which file they were editing.
		$output = preg_replace( '/ in (Standard input code|-) on line (\d+)/', ' on line $2', $output ) ?? $output;
		$output = preg_replace( '/^Errors parsing.*$/m', '', $output ) ?? $output;

		return trim( $output );
	}

	private function extract_line( string $output ): ?int {
		if ( preg_match( '/on line (\d+)/', $output, $matches ) ) {
			return (int) $matches[1];
		}

		return null;
	}
}
