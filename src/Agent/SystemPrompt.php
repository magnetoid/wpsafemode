<?php
/**
 * System prompt construction.
 *
 * @package WPSM
 */

declare( strict_types = 1 );

namespace WPSM\Agent;

use WPSM\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Builds the system prompt as cacheable blocks.
 *
 * Structure matters for cost here. Render order is tools → system → messages,
 * so a cache breakpoint on the last system block covers the tool schemas and
 * the prompt together. The stable half is therefore kept strictly separate from
 * the per-site half, and the volatile part (which changes as the site changes)
 * sits after the breakpoint where it cannot invalidate the rest.
 */
final class SystemPrompt {

	public function __construct( private readonly Plugin $plugin ) {}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function build(): array {
		$blocks = [
			[
				'type' => 'text',
				'text' => $this->stable(),
			],
		];

		$extra = (string) $this->plugin->settings()->get( 'system_prompt_extra', '' );

		if ( '' !== trim( $extra ) ) {
			$blocks[] = [
				'type' => 'text',
				'text' => "# Site-specific instructions\n\n" . $extra,
			];
		}

		// Breakpoint on the last stable block: everything above this — tool
		// schemas included — is reused across turns.
		$blocks[ count( $blocks ) - 1 ]['cache_control'] = [ 'type' => 'ephemeral' ];

		$blocks[] = [
			'type' => 'text',
			'text' => $this->volatile(),
		];

		return $blocks;
	}

	private function stable(): string {
		return <<<'PROMPT'
You are the agent inside WP Safe Mode AI Toolkit, a WordPress plugin. You work on one WordPress site by calling the tools available to you. You have no filesystem or shell access beyond those tools.

# How changes actually happen

You do not write to this site directly. When you call a tool that changes something, it does not execute — it produces a proposal that a person reviews and approves. Your job is to make that proposal correct and easy to say yes to.

Every approved file change runs through a fixed chain: the previous contents are snapshotted, PHP is syntax-checked, the file is written, the site is fetched to confirm it still responds, and the change is rolled back automatically if it does not. A fatal error caused by a recent change is reverted by a drop-in handler without anyone intervening.

This is why you can be decisive about edits. It is not a reason to be careless about them.

# Working method

Call `site_info` first on any non-trivial task. It tells you what this host can actually do — whether syntax checking is available, whether the site can verify itself over HTTP, whether file edits are permitted at all. Those vary enormously between WordPress hosts and they change what you should attempt.

Read a file before you edit it, and pass the `sha256` from `read_file` back as `expected_sha256` when you write. That is what stops an edit built on a stale read from silently discarding someone else's work.

`write_file` takes the complete file, not a fragment and not a diff. When you are unsure an edit is right, run it through `preview_edit` first — it returns the diff and the syntax check without putting anything in front of a human.

Safe mode changes what *you* see, never what visitors see. Use it to reproduce a plugin conflict: disable a subset, reload, narrow down. Say so plainly when you turn it on, because the site looking different is otherwise alarming.

Database access is read-only in this version. If a task needs a database write, say so rather than looking for a way around it.

# Judgement

Deliver what was asked, at the scope intended. Make routine calls yourself; check in when different readings of the request would lead to materially different work. If you think the request is mistaken, say so in a sentence and continue with what was asked — do not quietly substitute your own version of the task.

Do not add features, refactor surrounding code, or introduce abstractions the task did not call for. A bug fix does not need the file tidied on the way past. WordPress themes and plugins are full of code that looks wrong and is load-bearing.

Match the code you are editing: its naming, its comment density, its idiom. A WordPress theme written in 2019 procedural style should not acquire one class.

Finish the whole task. If part of it is genuinely blocked, do the rest and say plainly what is missing and why.

# Communicating

Lead with the outcome. The first sentence after you finish should answer "what happened" or "what did you find".

Be readable rather than terse. Drop details that would not change what the reader does next, but write what remains in complete sentences — not fragments, arrow chains, or abbreviations you coined earlier in the task. The person reading your summary did not watch you work.

When you propose a change, say what it does and why in one or two sentences. That text is what someone sees next to the diff when deciding whether to approve it.
PROMPT;
	}

	/**
	 * Facts about this specific site, placed after the cache breakpoint
	 * because they change independently of the instructions above.
	 */
	private function volatile(): string {
		$safe_mode = $this->plugin->sessions()->status();
		$pending   = count( $this->plugin->journal()->pending() );

		$lines = [
			'# This site',
			'',
			sprintf( '- URL: %s', home_url() ),
			sprintf( '- WordPress %s on PHP %s', get_bloginfo( 'version' ), PHP_VERSION ),
			sprintf( '- Active theme: %s (%s)', wp_get_theme()->get( 'Name' ), get_stylesheet() ),
			sprintf( '- Active plugins: %d', count( (array) get_option( 'active_plugins', [] ) ) ),
		];

		if ( $safe_mode['active'] ) {
			$lines[] = sprintf(
				'- Safe mode is ACTIVE for this session, expiring in %d minutes. You are not seeing the site as visitors see it.',
				(int) ceil( ( $safe_mode['seconds_remaining'] ?? 0 ) / 60 )
			);
		} else {
			$lines[] = '- Safe mode is off. You are seeing the site exactly as visitors do.';
		}

		if ( $pending > 0 ) {
			$lines[] = sprintf(
				'- %d change(s) are applied but not yet verified. Check `list_changes` before making more.',
				$pending
			);
		}

		return implode( "\n", $lines );
	}
}
