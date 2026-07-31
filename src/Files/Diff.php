<?php
/**
 * Unified diff rendering.
 *
 * @package WPSM
 */

declare( strict_types = 1 );

namespace WPSM\Files;

defined( 'ABSPATH' ) || exit;

/**
 * Produces a unified diff for the approval UI and for the agent to read back.
 *
 * Built on core's bundled Text_Diff rather than a hand-rolled LCS: the naive
 * dynamic-programming approach needs an n×m matrix, which is fine on a snippet
 * and catastrophic on a 20,000-line file. Text_Diff uses a memory-sane
 * algorithm and is already on disk.
 */
final class Diff {

	private const CONTEXT = 3;

	/**
	 * @return array{
	 *   unified: string,
	 *   added: int,
	 *   removed: int,
	 *   hunks: int,
	 *   truncated: bool
	 * }
	 */
	public function unified( string $before, string $after, string $label = 'file' ): array {
		if ( $before === $after ) {
			return [
				'unified'   => '',
				'added'     => 0,
				'removed'   => 0,
				'hunks'     => 0,
				'truncated' => false,
			];
		}

		require_once ABSPATH . WPINC . '/wp-diff.php';

		$old_lines = explode( "\n", $this->normalise( $before ) );
		$new_lines = explode( "\n", $this->normalise( $after ) );

		$diff = new \Text_Diff( 'auto', [ $old_lines, $new_lines ] );

		$flat = $this->flatten( $diff->getDiff() );
		$hunks = $this->group( $flat );

		$added   = 0;
		$removed = 0;

		foreach ( $flat as $entry ) {
			if ( '+' === $entry['type'] ) {
				++$added;
			} elseif ( '-' === $entry['type'] ) {
				++$removed;
			}
		}

		$out = [
			'--- a/' . $label,
			'+++ b/' . $label,
		];

		$truncated = false;
		$budget    = 2000; // Lines of rendered diff, so a huge rewrite cannot flood the UI or the model context.

		foreach ( $hunks as $hunk ) {
			if ( count( $out ) > $budget ) {
				$truncated = true;
				$out[]     = sprintf(
					/* translators: %d: number of remaining hunks */
					__( '... diff truncated; %d further hunks not shown', 'wp-safe-mode-ai' ),
					count( $hunks ) - $hunk['ordinal']
				);
				break;
			}

			$out[] = sprintf(
				'@@ -%d,%d +%d,%d @@',
				$hunk['old_start'],
				$hunk['old_count'],
				$hunk['new_start'],
				$hunk['new_count']
			);

			foreach ( $hunk['lines'] as $entry ) {
				$out[] = $entry['type'] . $entry['line'];
			}
		}

		return [
			'unified'   => implode( "\n", $out ),
			'added'     => $added,
			'removed'   => $removed,
			'hunks'     => count( $hunks ),
			'truncated' => $truncated,
		];
	}

	/**
	 * Turn Text_Diff ops into a flat, line-numbered stream.
	 *
	 * @param array<int, object> $ops
	 * @return array<int, array{type: string, line: string, old: int|null, new: int|null}>
	 */
	private function flatten( array $ops ): array {
		$flat = [];
		$old  = 1;
		$new  = 1;

		foreach ( $ops as $op ) {
			$class = strtolower( get_class( $op ) );

			if ( str_contains( $class, 'copy' ) ) {
				foreach ( (array) $op->orig as $line ) {
					$flat[] = [ 'type' => ' ', 'line' => (string) $line, 'old' => $old++, 'new' => $new++ ];
				}
				continue;
			}

			if ( str_contains( $class, 'delete' ) ) {
				foreach ( (array) $op->orig as $line ) {
					$flat[] = [ 'type' => '-', 'line' => (string) $line, 'old' => $old++, 'new' => null ];
				}
				continue;
			}

			if ( str_contains( $class, 'add' ) ) {
				foreach ( (array) $op->final as $line ) {
					$flat[] = [ 'type' => '+', 'line' => (string) $line, 'old' => null, 'new' => $new++ ];
				}
				continue;
			}

			// change: deletions first, then additions, matching unified format.
			foreach ( (array) $op->orig as $line ) {
				$flat[] = [ 'type' => '-', 'line' => (string) $line, 'old' => $old++, 'new' => null ];
			}
			foreach ( (array) $op->final as $line ) {
				$flat[] = [ 'type' => '+', 'line' => (string) $line, 'old' => null, 'new' => $new++ ];
			}
		}

		return $flat;
	}

	/**
	 * Group changed lines into hunks with surrounding context.
	 *
	 * @param array<int, array{type: string, line: string, old: int|null, new: int|null}> $flat
	 * @return array<int, array{ordinal: int, old_start: int, old_count: int, new_start: int, new_count: int, lines: array<int, array<string, mixed>>}>
	 */
	private function group( array $flat ): array {
		$changed = [];

		foreach ( $flat as $index => $entry ) {
			if ( ' ' !== $entry['type'] ) {
				$changed[] = $index;
			}
		}

		if ( [] === $changed ) {
			return [];
		}

		$ranges = [];
		$start  = max( 0, $changed[0] - self::CONTEXT );
		$end    = min( count( $flat ) - 1, $changed[0] + self::CONTEXT );

		foreach ( array_slice( $changed, 1 ) as $index ) {
			if ( $index - self::CONTEXT <= $end + 1 ) {
				$end = min( count( $flat ) - 1, $index + self::CONTEXT );
				continue;
			}

			$ranges[] = [ $start, $end ];
			$start    = max( 0, $index - self::CONTEXT );
			$end      = min( count( $flat ) - 1, $index + self::CONTEXT );
		}

		$ranges[] = [ $start, $end ];

		$hunks   = [];
		$ordinal = 0;

		foreach ( $ranges as [$from, $to] ) {
			$lines     = array_slice( $flat, $from, $to - $from + 1 );
			$old_start = null;
			$new_start = null;
			$old_count = 0;
			$new_count = 0;

			foreach ( $lines as $entry ) {
				if ( null !== $entry['old'] ) {
					$old_start ??= $entry['old'];
					++$old_count;
				}
				if ( null !== $entry['new'] ) {
					$new_start ??= $entry['new'];
					++$new_count;
				}
			}

			$hunks[] = [
				'ordinal'   => ++$ordinal,
				'old_start' => $old_start ?? 0,
				'old_count' => $old_count,
				'new_start' => $new_start ?? 0,
				'new_count' => $new_count,
				'lines'     => $lines,
			];
		}

		return $hunks;
	}

	private function normalise( string $text ): string {
		return str_replace( [ "\r\n", "\r" ], "\n", $text );
	}
}
