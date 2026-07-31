<?php
/**
 * Tool catalogue and dispatcher.
 *
 * @package WPSM
 */

declare( strict_types = 1 );

namespace WPSM\Tools;

use WPSM\Files\SandboxViolation;
use WPSM\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * The single entry point for calling a tool, whoever is asking.
 *
 * dispatch() is where the approval gate lives. A mutating tool called through
 * here does not run — it returns a proposal for a human to look at. That is
 * true whether the caller is the built-in agent, an MCP client, or a REST
 * request, because there is no other code path that reaches a tool.
 */
final class Registry {

	/** @var array<string, Tool> */
	private array $tools = [];

	private function __construct( private readonly Plugin $plugin ) {}

	public static function build( Plugin $plugin ): self {
		$registry = new self( $plugin );

		$registry->add( new Site\SiteInfo( $plugin ) );

		$registry->add( new File\ListFiles( $plugin ) );
		$registry->add( new File\ReadFile( $plugin ) );
		$registry->add( new File\PreviewEdit( $plugin ) );
		$registry->add( new File\WriteFile( $plugin ) );
		$registry->add( new File\DeleteFile( $plugin ) );

		$registry->add( new History\ListChanges( $plugin ) );
		$registry->add( new History\RevertChange( $plugin ) );

		$registry->add( new SafeMode\Status( $plugin ) );
		$registry->add( new SafeMode\Start( $plugin ) );
		$registry->add( new SafeMode\Stop( $plugin ) );

		$registry->add( new Db\ListTables( $plugin ) );
		$registry->add( new Db\DescribeTable( $plugin ) );
		$registry->add( new Db\PreviewTable( $plugin ) );
		$registry->add( new Db\Query( $plugin ) );

		/**
		 * Register additional tools.
		 *
		 * Anything added here inherits the approval gate and the audit log, so
		 * extensions cannot accidentally route around them.
		 *
		 * @param Registry $registry
		 */
		do_action( 'wpsm_register_tools', $registry );

		return $registry;
	}

	public function add( Tool $tool ): void {
		$this->tools[ $tool->name() ] = $tool;
	}

	/**
	 * @return array<string, Tool>
	 */
	public function all(): array {
		return $this->tools;
	}

	public function get( string $name ): ?Tool {
		return $this->tools[ $name ] ?? null;
	}

	/**
	 * Tool definitions for the Claude Messages API.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function claude_schemas(): array {
		return array_values(
			array_map(
				static fn( Tool $tool ): array => $tool->to_claude_schema(),
				$this->tools
			)
		);
	}

	/**
	 * Catalogue for the UI and for MCP tools/list.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function catalogue(): array {
		$out = [];

		foreach ( $this->tools as $tool ) {
			$out[] = [
				'name'        => $tool->name(),
				'title'       => $tool->title(),
				'description' => $tool->description(),
				'mutates'     => $tool->mutates(),
				'mcp_public'  => $tool->mcp_public(),
				'auto'        => $this->is_auto_approved( $tool ),
				'schema'      => $tool->input_schema(),
			];
		}

		return $out;
	}

	/**
	 * Call a tool.
	 *
	 * @param array<string, mixed> $input
	 *
	 * @return array{
	 *   ok: bool,
	 *   tool: string,
	 *   needs_approval?: bool,
	 *   proposal_id?: string,
	 *   preview?: array<string, mixed>,
	 *   result?: array<string, mixed>,
	 *   error?: string
	 * }
	 */
	public function dispatch( string $name, array $input ): array {
		$tool = $this->get( $name );

		if ( null === $tool ) {
			return [
				'ok'    => false,
				'tool'  => $name,
				/* translators: %s: tool name */
				'error' => sprintf( __( 'There is no tool called %s.', 'wp-safe-mode-ai' ), $name ),
			];
		}

		if ( ! current_user_can( \WPSM\CAP ) ) {
			return [
				'ok'    => false,
				'tool'  => $name,
				'error' => __( 'You do not have permission to run this tool.', 'wp-safe-mode-ai' ),
			];
		}

		// The approval gate. A mutating tool stops here and hands back
		// something for a person to look at.
		if ( $tool->mutates() && ! $this->is_auto_approved( $tool ) ) {
			try {
				$preview = $tool->preview( $input );
			} catch ( \Throwable $e ) {
				return $this->error( $tool, $e );
			}

			$proposal_id = $this->plugin->proposals()->create( $tool, $input, $preview );

			return [
				'ok'             => true,
				'tool'           => $name,
				'needs_approval' => true,
				'proposal_id'    => $proposal_id,
				'preview'        => $preview,
			];
		}

		return $this->execute( $tool, $input );
	}

	/**
	 * Run a tool for real, bypassing the gate.
	 *
	 * Only reached from dispatch() for non-gated tools and from
	 * Proposals::approve() once a human has said yes.
	 *
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>
	 */
	public function execute( Tool $tool, array $input ): array {
		try {
			$result = $tool->run( $input );
		} catch ( \Throwable $e ) {
			return $this->error( $tool, $e );
		}

		$this->plugin->log()->record(
			'tool.ran',
			$tool->name(),
			[ 'summary' => $tool->summarise( $input ) ]
		);

		return [
			'ok'     => true,
			'tool'   => $tool->name(),
			'result' => $result,
		];
	}

	public function is_auto_approved( Tool $tool ): bool {
		if ( ! $tool->mutates() ) {
			return true;
		}

		$settings = get_option( 'wpsm_settings', [] );
		$auto     = is_array( $settings ) && is_array( $settings['auto_approve'] ?? null )
			? $settings['auto_approve']
			: [];

		return in_array( $tool->name(), $auto, true );
	}

	/**
	 * @return array{ok: false, tool: string, error: string}
	 */
	private function error( Tool $tool, \Throwable $e ): array {
		// Sandbox refusals and bad arguments are the agent's problem to fix and
		// its message is written to be read. Anything else is ours, and the
		// detail belongs in the log rather than in a model's context window.
		$safe = $e instanceof SandboxViolation || $e instanceof \InvalidArgumentException;

		$this->plugin->log()->record(
			'tool.failed',
			$tool->name(),
			[
				'error' => $e->getMessage(),
				'class' => get_class( $e ),
			]
		);

		return [
			'ok'    => false,
			'tool'  => $tool->name(),
			'error' => $safe
				? $e->getMessage()
				: __( 'The tool failed unexpectedly. Check the toolkit audit log for details.', 'wp-safe-mode-ai' ),
		];
	}
}
