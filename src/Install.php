<?php
/**
 * Activation, upgrade and deactivation.
 *
 * @package WPSM
 */

declare( strict_types = 1 );

namespace WPSM;

use WPSM\Audit\Log;
use WPSM\Snapshots\Journal;

defined( 'ABSPATH' ) || exit;

/**
 * Everything that has to happen once, rather than on every request.
 *
 * Deactivation is deliberately thorough about the drop-ins — leaving a
 * fatal-error handler behind that points at a snapshot store belonging to a
 * disabled plugin would be a genuinely nasty thing to do to a site.
 */
final class Install {

	public const OPTION_DB_VERSION = 'wpsm_db_version';
	public const DB_VERSION        = '1';

	public static function activate(): void {
		self::create_tables();
		Support\Capabilities::grant();

		$plugin = Plugin::instance();
		$plugin->store()->ensure();

		// Seed the session signing key now so the mu-plugin never has to.
		$plugin->sessions()->secret();

		$plugin->dropins()->install();

		update_option( self::OPTION_DB_VERSION, self::DB_VERSION, false );

		( new Log() )->record( 'plugin.activated', VERSION );
	}

	public static function deactivate(): void {
		$plugin = Plugin::instance();

		// Any change still in flight is unverified by definition. Roll it back
		// while we still have the machinery loaded to do so — after
		// deactivation the fatal handler is gone and nothing else will.
		foreach ( $plugin->journal()->pending() as $entry ) {
			$plugin->restore()->revert( (string) $entry['change_id'], true );
		}

		$plugin->sessions()->rotate_secret();
		$plugin->dropins()->remove();

		( new Log() )->record( 'plugin.deactivated', VERSION );
	}

	/**
	 * Run pending schema work after an update.
	 */
	public static function maybe_upgrade(): void {
		if ( get_option( self::OPTION_DB_VERSION ) === self::DB_VERSION ) {
			return;
		}

		self::create_tables();
		Support\Capabilities::grant();
		Plugin::instance()->dropins()->install();

		update_option( self::OPTION_DB_VERSION, self::DB_VERSION, false );
	}

	private static function create_tables(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$journal = Journal::table();
		$audit   = Log::table();
		$props   = $wpdb->prefix . 'wpsm_proposals';
		$sites   = $wpdb->prefix . 'wpsm_sites';

		// dbDelta is fussy: two spaces after PRIMARY KEY, KEY names present,
		// lowercase types. Formatting here is load-bearing, not stylistic.
		dbDelta(
			"CREATE TABLE {$journal} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				change_id varchar(32) NOT NULL,
				actor_id bigint(20) unsigned NOT NULL DEFAULT 0,
				kind varchar(20) NOT NULL,
				op varchar(30) NOT NULL,
				target text NOT NULL,
				status varchar(20) NOT NULL,
				snapshot_id varchar(32) DEFAULT NULL,
				inverse longtext DEFAULT NULL,
				meta longtext DEFAULT NULL,
				created_at datetime NOT NULL,
				resolved_at datetime DEFAULT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY change_id (change_id),
				KEY status (status),
				KEY created_at (created_at)
			) {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$audit} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				ts datetime NOT NULL,
				actor_id bigint(20) unsigned NOT NULL DEFAULT 0,
				event varchar(60) NOT NULL,
				subject varchar(500) DEFAULT '',
				detail longtext DEFAULT NULL,
				ip varchar(45) DEFAULT '',
				PRIMARY KEY  (id),
				KEY event (event),
				KEY ts (ts)
			) {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$props} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				proposal_id varchar(32) NOT NULL,
				actor_id bigint(20) unsigned NOT NULL DEFAULT 0,
				tool varchar(60) NOT NULL,
				payload longtext NOT NULL,
				preview longtext DEFAULT NULL,
				status varchar(20) NOT NULL,
				result longtext DEFAULT NULL,
				created_at datetime NOT NULL,
				expires_at datetime NOT NULL,
				resolved_at datetime DEFAULT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY proposal_id (proposal_id),
				KEY status (status),
				KEY expires_at (expires_at)
			) {$charset};"
		);

		// Multi-site awareness lands with the schema even though only the local
		// row is wired in this milestone; retrofitting a site column across
		// every table later is the expensive version of this decision.
		dbDelta(
			"CREATE TABLE {$sites} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				name varchar(191) NOT NULL,
				url varchar(500) NOT NULL,
				key_id varchar(32) NOT NULL,
				secret text NOT NULL,
				is_local tinyint(1) NOT NULL DEFAULT 0,
				last_seen datetime DEFAULT NULL,
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY key_id (key_id),
				KEY is_local (is_local)
			) {$charset};"
		);
	}
}
