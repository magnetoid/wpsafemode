<?php
/**
 * The toolkit's own capability.
 *
 * @package WPSM
 */

declare( strict_types = 1 );

namespace WPSM\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Gates every route and tool on `wpsm_manage` rather than `manage_options`.
 *
 * A distinct capability means access can be revoked from one administrator
 * without demoting them, and granted to a dedicated automation user that holds
 * nothing else. Given what the toolkit can do to a site, "every administrator,
 * always" is not a reasonable default to bake in.
 */
final class Capabilities {

	/**
	 * Grant to administrators on activation.
	 *
	 * Only administrators, and only on the role — never on a user object, so
	 * revoking is a single role edit.
	 */
	public static function grant(): void {
		$role = get_role( 'administrator' );

		if ( $role instanceof \WP_Role && ! $role->has_cap( \WPSM\CAP ) ) {
			$role->add_cap( \WPSM\CAP );
		}
	}

	/**
	 * Remove from every role. Called on uninstall, not deactivation.
	 */
	public static function revoke(): void {
		foreach ( wp_roles()->roles as $slug => $_ ) {
			$role = get_role( $slug );

			if ( $role instanceof \WP_Role && $role->has_cap( \WPSM\CAP ) ) {
				$role->remove_cap( \WPSM\CAP );
			}
		}
	}

	/**
	 * Permission callback shared by every REST route.
	 *
	 * Returns WP_Error rather than false so the client gets a message it can
	 * show, instead of a bare 401 with no explanation.
	 */
	public static function rest_check(): bool|\WP_Error {
		if ( current_user_can( \WPSM\CAP ) ) {
			return true;
		}

		return new \WP_Error(
			'wpsm_forbidden',
			__( 'You do not have permission to use the Safe Mode AI Toolkit.', 'wp-safe-mode-ai' ),
			[ 'status' => is_user_logged_in() ? 403 : 401 ]
		);
	}
}
