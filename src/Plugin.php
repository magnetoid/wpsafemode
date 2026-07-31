<?php
/**
 * Plugin container and boot sequence.
 *
 * @package WPSM
 */

declare( strict_types = 1 );

namespace WPSM;

use WPSM\Abilities\Register as AbilitiesRegister;
use WPSM\Admin\Screen;
use WPSM\Agent\ClaudeClient;
use WPSM\Audit\Log;
use WPSM\Db\Browser;
use WPSM\Files\Diff;
use WPSM\Files\Lint;
use WPSM\Files\Reader;
use WPSM\Files\Sandbox;
use WPSM\Files\Writer;
use WPSM\Rest\Controller;
use WPSM\SafeMode\Dropins;
use WPSM\SafeMode\SessionManager;
use WPSM\Snapshots\Journal;
use WPSM\Snapshots\Restore;
use WPSM\Snapshots\Store;
use WPSM\Support\Settings;
use WPSM\Tools\Proposals;
use WPSM\Tools\Registry as ToolRegistry;

defined( 'ABSPATH' ) || exit;

/**
 * Service locator.
 *
 * Deliberately tiny: this plugin has a fixed, known set of services and no
 * need for a DI container. Services are lazily constructed and memoised.
 */
final class Plugin {

	private static ?self $instance = null;

	/** @var array<string, object> */
	private array $services = [];

	private bool $booted = false;

	private function __construct() {}

	public static function instance(): self {
		return self::$instance ??= new self();
	}

	public function boot(): void {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		load_plugin_textdomain( 'wp-safe-mode-ai', false, dirname( plugin_basename( WPSM_FILE ) ) . '/languages' );

		// Must happen on every request, not lazily: Writer and Journal request
		// a rollback by firing an action, and if nothing has registered the
		// listener the rollback silently does not happen.
		$this->restore()->register();

		// Late verification of the safe-mode session. The mu-plugin can only
		// check the HMAC and expiry (it runs before the auth cookie is parsed),
		// so we confirm capability here and revoke if it does not hold up.
		add_action( 'init', [ $this->sessions(), 'verify_current_session' ], 1 );

		// Fold anything the fatal-error handler did on a previous request into
		// the journal, then reconcile changes stranded in "pending".
		add_action( 'init', [ $this->journal(), 'drain_recovery_reports' ], 4 );
		add_action( 'init', [ $this->journal(), 'reconcile_stale_pending' ], 5 );

		add_action( 'admin_init', [ Install::class, 'maybe_upgrade' ] );

		add_action( 'rest_api_init', [ new Controller( $this ), 'register_routes' ] );
		add_action( 'wp_abilities_api_init', [ new AbilitiesRegister( $this ), 'register' ] );

		if ( is_admin() ) {
			( new Screen( $this ) )->register();
		}
	}

	// ---------------------------------------------------------------------
	// Services
	// ---------------------------------------------------------------------

	public function store(): Store {
		return $this->services['store'] ??= new Store();
	}

	public function journal(): Journal {
		return $this->services['journal'] ??= new Journal( $this->store(), $this->log() );
	}

	public function restore(): Restore {
		return $this->services['restore'] ??= new Restore( $this->store(), $this->journal(), $this->log() );
	}

	public function log(): Log {
		return $this->services['log'] ??= new Log();
	}

	public function sandbox(): Sandbox {
		return $this->services['sandbox'] ??= new Sandbox();
	}

	public function lint(): Lint {
		return $this->services['lint'] ??= new Lint();
	}

	public function reader(): Reader {
		return $this->services['reader'] ??= new Reader( $this->sandbox() );
	}

	public function writer(): Writer {
		return $this->services['writer'] ??= new Writer(
			$this->sandbox(),
			$this->lint(),
			$this->store(),
			$this->journal(),
			$this->log()
		);
	}

	public function diff(): Diff {
		return $this->services['diff'] ??= new Diff();
	}

	public function sessions(): SessionManager {
		return $this->services['sessions'] ??= new SessionManager( $this->log() );
	}

	public function dropins(): Dropins {
		return $this->services['dropins'] ??= new Dropins( $this->store(), $this->log() );
	}

	public function db(): Browser {
		return $this->services['db'] ??= new Browser();
	}

	public function tools(): ToolRegistry {
		return $this->services['tools'] ??= ToolRegistry::build( $this );
	}

	public function proposals(): Proposals {
		return $this->services['proposals'] ??= new Proposals( $this );
	}

	public function claude(): ClaudeClient {
		return $this->services['claude'] ??= new ClaudeClient( $this->settings(), $this->log() );
	}

	public function settings(): Settings {
		return $this->services['settings'] ??= new Settings();
	}
}
