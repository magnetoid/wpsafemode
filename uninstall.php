<?php
/**
 * Uninstall routine.
 *
 * Deactivation is reversible and leaves data alone. Uninstall is the point at
 * which someone has said they are done, so this removes everything: tables,
 * options, the capability, and the snapshot store.
 *
 * The drop-ins are removed on deactivation, which always runs before uninstall,
 * but they are checked again here in case a site was force-deleted.
 *
 * @package WPSM
 */

declare( strict_types = 1 );

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

// ---------------------------------------------------------------------------
// Drop-ins
// ---------------------------------------------------------------------------

foreach (
	[
		rtrim( WPMU_PLUGIN_DIR, '/\\' ) . '/wpsm-safe-mode.php',
		rtrim( WP_CONTENT_DIR, '/\\' ) . '/fatal-error-handler.php',
	] as $dropin
) {
	if ( ! file_exists( $dropin ) ) {
		continue;
	}

	// Only ever remove files we recognise as ours.
	$head = (string) file_get_contents( $dropin, false, null, 0, 2048 );

	if ( str_contains( $head, 'WP Safe Mode AI' ) ) {
		@unlink( $dropin );
	}
}

// ---------------------------------------------------------------------------
// Snapshot store
// ---------------------------------------------------------------------------

$dir_name = get_option( 'wpsm_storage_dir' );

if ( is_string( $dir_name ) && preg_match( '/^wpsm-[a-zA-Z0-9]{12}$/', $dir_name ) ) {
	$uploads = wp_upload_dir( null, false );
	$root    = rtrim( (string) ( $uploads['basedir'] ?? '' ), '/\\' ) . '/' . $dir_name;
	$real    = realpath( $root );

	// Belt and braces: only recurse into a path that both matches the expected
	// name pattern and resolves inside uploads.
	$uploads_real = realpath( (string) ( $uploads['basedir'] ?? '' ) );

	if ( false !== $real && false !== $uploads_real && str_starts_with( $real, $uploads_real . DIRECTORY_SEPARATOR ) ) {
		$items = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $real, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $items as $item ) {
			$item->isDir() ? @rmdir( $item->getPathname() ) : @unlink( $item->getPathname() );
		}

		@rmdir( $real );
	}
}

// ---------------------------------------------------------------------------
// Database
// ---------------------------------------------------------------------------

foreach ( [ 'wpsm_journal', 'wpsm_audit', 'wpsm_proposals', 'wpsm_sites' ] as $suffix ) {
	$table = $wpdb->prefix . $suffix;
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- fixed table names, uninstall only.
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
}

foreach (
	[
		'wpsm_settings',
		'wpsm_storage_dir',
		'wpsm_session_secret',
		'wpsm_safe_sessions',
		'wpsm_dropin_state',
		'wpsm_db_version',
	] as $option
) {
	delete_option( $option );
}

delete_transient( 'wpsm_reconcile_lock' );

// ---------------------------------------------------------------------------
// Capability
// ---------------------------------------------------------------------------

foreach ( wp_roles()->roles as $slug => $_ ) {
	$role = get_role( $slug );

	if ( $role instanceof WP_Role && $role->has_cap( 'wpsm_manage' ) ) {
		$role->remove_cap( 'wpsm_manage' );
	}
}
