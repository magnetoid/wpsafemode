# WP Safe Mode AI Toolkit

AI-driven WordPress file and database work, behind a safety chain that assumes the AI will eventually be wrong.

Drop the folder into `wp-content/plugins/` and activate. There is no build step, no Composer install, and no separate process to run.

---

## What it does

Ask it to change something. It reads the relevant files, proposes an edit, and shows you the exact diff. Nothing reaches the site until you approve that diff.

When you do approve it, the change goes through a fixed chain:

```
snapshot → lint → write → verify → commit
                              └─ fails? → roll back
```

And if something slips past all of that and takes the site down, a drop-in fatal-error handler restores the snapshot without you doing anything.

Alongside that, **safe mode** lets one browser session see the site with a different set of plugins or a different theme active — while every real visitor continues to see the site exactly as it is. That is what makes bisecting a plugin conflict on a production site a reasonable thing to do.

## Why the plugin is the app

Everything runs inside WordPress. There is no companion desktop app, no hosted dashboard, no daemon.

The two pieces that need to survive a broken plugin are installed as core-sanctioned drop-ins that load *before* regular plugins:

| File | Does |
|---|---|
| `wp-content/mu-plugins/wpsm-safe-mode.php` | Session overlay — swaps the active plugin list and theme for a signed cookie holder |
| `wp-content/fatal-error-handler.php` | Restores the last change if it caused a fatal error |

Neither is overwritten if another plugin already owns it. You get a warning instead, and the rest of the toolkit carries on working without that layer.

**The limit worth knowing:** drop-ins survive a broken *plugin*, not a broken WordPress core. If core itself is unbootable, this cannot help you — a standalone recovery entry point is planned but not built.

## The safety chain, in detail

**Path containment.** Every path is resolved against the real filesystem, so a symlink pointing outside the site is caught after resolution rather than trusted before it. `wp-config.php`, `.htaccess`, dotfiles, the plugin's own directory, and its snapshot store are permanently off limits. Writes are restricted to an extension allowlist.

**Syntax checking.** PHP is checked before any bytes are written — `php -l` in a subprocess where available, `token_get_all()` with `TOKEN_PARSE` where `proc_open` is disabled. If neither is available the write is refused rather than attempted, and `site_info` tells the agent which guarantee it is actually getting on this host.

**Snapshots.** Original contents, permissions and mtime are captured before the write, with a sha256 that is verified before anything is restored. A corrupted snapshot is refused rather than written back.

**Verification.** After a write the site is fetched over HTTP. A 5xx or a "critical error" page triggers an immediate rollback. A probe that cannot connect at all — common on hosts that block loopback — is treated as inconclusive rather than as failure, and the change stays armed for automatic rollback for a few minutes instead.

**Concurrency.** `read_file` returns a sha256; `write_file` accepts it back as `expected_sha256` and refuses the write if the file changed in between. An edit built on a stale read fails loudly instead of silently discarding someone else's work.

## Two front doors

The same tool objects back both, so there is one place where a capability is defined and one place where it is guarded.

**Built-in assistant.** Chat in `Tools → Safe Mode AI`. Needs an Anthropic API key, stored encrypted with the site's own salts, or supplied via `ANTHROPIC_API_KEY` / the `WPSM_ANTHROPIC_API_KEY` constant — which is better, because then it never touches the database.

**MCP.** Tools are registered through the WordPress Abilities API (6.9+), so the official [`WordPress/mcp-adapter`](https://github.com/wordpress/mcp-adapter) plugin exposes them to Claude Code, Claude Desktop, or anything else that speaks MCP. Sites without the adapter can use the built-in `wpsm/v1/mcp` endpoint instead.

Read-only tools are exposed over MCP by default. Change-making tools are opt-in per site — and even then they can only queue a proposal, never apply one.

**The agent loop runs in the browser.** A multi-turn loop inside one PHP request would hit `max_execution_time` on most WordPress hosts. Driving it from the admin UI keeps every server request to a single unit of work, and puts the approval gate exactly where a human already is.

## What it will not do

- **Write to the database.** Read-only in this version: browse tables, describe schemas, run guarded `SELECT`s. Structured writes are the next milestone.
- **Run arbitrary SQL.** Statements are parsed, not pattern-matched. Anything that is not `SELECT`/`SHOW`/`DESCRIBE`/`EXPLAIN` is refused, as is anything touching the filesystem or able to stall the server.
- **Return password hashes.** `user_pass` and `user_activation_key` come back redacted from every query.
- **Edit itself.** An agent that can rewrite its own sandbox does not have one.

## Requirements

- WordPress 6.0+ (6.9+ for the Abilities API path)
- PHP 8.0+
- An Anthropic API key, for the assistant only. Safe mode, the file tools and the change history all work without one.

## Configuration

```php
// wp-config.php

// Keeps the key out of the database entirely.
define( 'WPSM_ANTHROPIC_API_KEY', 'sk-ant-…' );

// Required to let the toolkit write files on a site that sets either of
// WordPress' file-modification constants.
define( 'WPSM_ALLOW_FILE_EDIT', true );
```

Access is gated on a dedicated `wpsm_manage` capability rather than `manage_options`, so it can be revoked from one administrator without demoting them, or granted to a limited automation user.

## Development

```bash
npm install && npm run build   # admin UI (built assets are committed)
composer install               # dev tooling only — no runtime dependencies
composer test                  # safety-chain tests: no database, no container
npx wp-env start               # full WordPress for end-to-end work
```

The safety-chain tests deliberately need nothing but PHP and PHPUnit. Tests you can only run inside a container are tests that get run less often, and these are the ones where a bug is a security bug.

### Why there are no runtime dependencies

The Anthropic PHP SDK is PSR-18 based and needs an HTTP client bundled with it — in practice Guzzle plus a PSR-7 implementation. Because this ships as a drop-in folder, those would have to be committed into `vendor/`, and a committed Guzzle is the most common cause of fatal class collisions between WordPress plugins.

So the Claude client is written against WordPress' own HTTP API instead. Zero runtime dependencies, and the site's proxy and TLS configuration apply for free. `src/Agent/ClaudeClient.php` is deliberately narrow so swapping in a PHP-Scoper-prefixed SDK later is a single-file change.

## Licence

GPL-2.0-or-later.
