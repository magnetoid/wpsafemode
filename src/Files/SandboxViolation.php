<?php
/**
 * Raised when a path or operation is refused by the sandbox.
 *
 * @package WPSM
 */

declare( strict_types = 1 );

namespace WPSM\Files;

defined( 'ABSPATH' ) || exit;

/**
 * Distinct from a generic RuntimeException so callers can surface these to the
 * user verbatim: the message is always safe to display and never contains an
 * unresolved caller-supplied path.
 */
final class SandboxViolation extends \RuntimeException {}
