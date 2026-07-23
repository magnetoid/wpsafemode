# WP Safe Mode — Design

**Date:** 2026-07-24
**Status:** Approved, pre-implementation
**Scope:** v1 (Rescue + Doctor blades), milestones M0–M6

This document is the design for the whole of v1. It is deliberately larger than a single
implementation plan: each milestone in §14 gets its own plan, and the first of those covers
**M0 only**.

---

## 1. What we are building

WP Safe Mode is an AI-powered diagnostic and rescue tool for WordPress, delivered as a
**drop-in PHP script**. The user uploads one folder (or ZIP) next to their WordPress
installation, opens it in a browser, and gets an AI agent that can diagnose, rescue, and
report on the site — **including when WordPress is completely broken**: white screen,
fatal error, corrupt database, locked-out admin.

The product name carries two meanings and both must be visible in the UI:

1. **Rescue mode** — it works when WordPress does not.
2. **The safety contract** — every mutating action is snapshotted, previewed, approved,
   executed, verified, and reversible.

Distribution is a single ZIP. No cloud, no account, no external control plane. It must
run on cheap shared hosting reached only by FTP or a hosting file manager.

Target users: site owners in a panic, and freelancers/agencies who keep the tool in a
toolbox for client emergencies.

---

## 2. Runtime constraints

These are facts to design around, not choices.

- **PHP 8.0 minimum.** No Node on the server. No guaranteed Composer. No `exec` on many
  hosts. Zero runtime dependencies; anything needed is vendored into the ZIP.
- **Short request lifetimes.** `max_execution_time` is commonly 30s. Nginx
  `fastcgi_read_timeout` commonly 60s. The agent loop therefore cannot run in one request.
- **SQLite via PDO** is near-universal but not guaranteed. Detect at install; fall back to
  JSON files.
- **No build step on the server, ever.** The frontend is compiled during development and
  shipped as static files.
- **`exec`/WP-CLI is a bonus tier**, detected at runtime, never a requirement.

---

## 3. Architecture decisions

### 3.1 Step-wise agent loop (core design)

The browser drives the loop over AJAX. Each request performs **exactly one unit of work**
— one LLM call, or one tool execution, or one snapshot — persists state, and returns. The
frontend immediately requests the next step. This makes every step debuggable, resumable,
and immune to request-lifetime limits.

### 3.2 Model: Opus 4.8 by default, user-switchable

Default `claude-opus-4-8` ($5/$25 per MTok, 1M context). A settings screen allows
`claude-sonnet-5` ($3/$15; $2/$10 introductory through 2026-08-31) or `claude-haiku-4-5`
($1/$5, 200K context) for cost-sensitive users. Reading a mangled stack trace and
inferring which plugin caused it is exactly the kind of ambiguous reasoning the Opus tier
is best at, and the flagship demo must feel reliable.

Request parameters:

- `thinking: {"type": "adaptive", "display": "summarized"}` — adaptive must be set
  **explicitly**; omitting the field on Opus 4.8 runs without thinking. `display` defaults
  to `"omitted"`, which returns thinking blocks with empty text; `"summarized"` gives us
  readable progress text for the step-wise UI ("Analyzing the fatal error…").
- `output_config: {"effort": "high"}` — default. Configurable; `xhigh` is the best setting
  for agentic work on Opus 4.8, `low`/`medium` for cost-sensitive users. An effort sweep
  happens in M5 against the playground.
- **No `temperature`, `top_p`, `top_k`** — removed on Opus 4.8 and Sonnet 5; sending any
  of them returns 400.
- **No `budget_tokens`** — removed on Opus 4.8 and Sonnet 5; returns 400. Depth is
  controlled by `effort`.

### 3.3 Server-side streaming as timeout insurance

The brief specified no streaming in v1. We amend this: PHP opens the Messages API request
with `"stream": true`, accumulates SSE frames through a `CURLOPT_WRITEFUNCTION` callback,
and returns the fully assembled message to the browser as ordinary JSON.

**The browser API and the step-wise UI are unchanged.** There is no SSE to the browser, so
no fights with output buffering, `mod_deflate`, or proxy buffering. The only purpose is
that bytes keep arriving on the connection, so FastCGI and proxy read timeouts never fire
during a long Opus turn. Without this, a slow turn produces a 504 mid-rescue on a step the
user has already been billed for.

Note that PHP's own `max_execution_time` generally does not advance while blocked on a
network read on Unix SAPIs — the real risk is the web server's read timeout, which is
exactly what streaming defeats.

### 3.4 Tier W is probe-only in v1

The connection doctor detects and reports whether WordPress boots, but **no Tier W tools
ship in v1**. Rescue never uses Tier W by design, and Doctor is fully served by filesystem
and database access. Building either the sandboxed `wp-load.php` HTTP endpoint or the REST
+ application-password path would add significant M2 work, an extra onboarding step a
locked-out user cannot complete, and — in the loader case — the single most dangerous file
in the ZIP. The `Tier` type and adapter interface still exist so v2 can fill it in.

### 3.5 Frontend: Preact + Tailwind, compiled to static files

Preact's ~4KB runtime versus React's ~45KB matters when the deliverable is a ZIP uploaded
over slow FTP to a site that is already down, and every byte in `src/public/` ships to
every user. `preact/compat` preserves the React authoring experience. The UI is a chat
pane, action cards, and a few tables — nothing that needs the React runtime.

### 3.6 Parallel tool calls are disabled

Every request sends `tool_choice: {"type": "auto", "disable_parallel_tool_use": true}`.

The API may otherwise return several `tool_use` blocks in one assistant message, and all
their `tool_result` blocks must be returned in a **single** user message — splitting them
across messages silently trains the model to stop making parallel calls. Supporting that
would require a result queue and a partial-turn buffer, which collides with "one unit of
work per request". Disabling it makes "exactly one `tool_use` block per assistant turn" a
structural guarantee.

Cost: read-only fan-out (tail `debug.log` **and** list plugins) takes two round trips
instead of one. This is an acceptable price for a state machine that can be reasoned about.

### 3.7 `ask_always` is the only approval mode in v1

No override, no per-tool allowlist, no "yolo mode". Safe Mode is the brand promise;
shipping an off switch before the safety loop is proven in the field undercuts it.

### 3.8 Verification is system-driven, not model-chosen

If verification exists only as a tool the model may call, the safety contract holds only
when the model remembers to call it. Instead, the state machine runs the front-end health
check itself as a mandatory step after **any** mutating tool.

`http_health_check` remains a callable tool for diagnosis before a change; the guarantee
after a change does not depend on the model.

### 3.9 Single active session, advisory-locked

Two browser tabs must not interleave steps on one session. Sessions carry a `lock_token`
and `lock_expires_at`; a second tab gets read-only spectator mode. Closing the browser
mid-loop is safe — state is durable and reload resumes from the persisted state. Nothing
runs in the background.

### 3.10 Retries are steps

On a retryable API failure (429, 500, 529, connection error), the step does **not** sleep
inside the request. It persists `retry_count` and `retry_after_at` (honouring the
`retry-after` header on 429), stays in `awaiting_llm`, and returns `{"retry_after": N}`.
The frontend waits and re-requests. This keeps backoff entirely outside the PHP request
budget — a property the step-wise architecture gives us for free.

Terminal failures (400, 401, 403, 404, 413) go straight to `error` with a specific
message; a 401 in particular must read "your Anthropic API key was rejected", not
"something went wrong".

---

## 4. Access tiers

All local. The script sits next to WordPress.

| Tier | Name | Mechanism | Availability |
|---|---|---|---|
| **F** | Filesystem | Native PHP | Always |
| **D** | Database | `mysqli`/PDO, credentials parsed from `wp-config.php` | Whenever the DB is reachable |
| **W** | WordPress | *Probe only in v1* | Reported, not used |
| **X** | exec/WP-CLI | `exec()` + WP-CLI detection | Bonus, runtime-detected |

**Tier F** — read `wp-config.php`, `debug.log`, `error_log`; list and rename plugin/theme
folders; edit `.htaccess`; manage the `.maintenance` file; check permissions.

**Tier D** — works when WordPress will not boot. `wp_options` read/write, `active_plugins`,
`siteurl`/`home`, user password reset, table inspection.

**Tier X** — core checksums, cache flush, search-replace, when available.

The connection doctor probes all tiers at session start and reports what is available.
Tier selection is part of the agent's reasoning and is surfaced in every plan preview.

---

## 5. Security model

A standalone admin script with database access is a scan target. This section is
existential.

### 5.1 Installer flow

On first run, generate a random install token. Force the user to set a strong password
before anything else functions. **Refuse to operate unconfigured** — every endpoint returns
a "not installed" response until setup completes.

Password hashing uses argon2id via `password_hash` **when available**. `PASSWORD_ARGON2ID`
is undefined on PHP builds without libargon2, which is common on shared hosting. Detect at
runtime, prefer argon2id, fall back to bcrypt with cost 12, and record which algorithm was
used so a later login can transparently rehash if the host improves.

### 5.2 Random directory name

The ZIP extracts to `wpsafemode/`. The installer offers, and strongly recommends, a
one-click self-rename to `wpsm-{random}/`, then redirects to the new location.

### 5.3 Authentication

Session-based login with our own session storage (not PHP's `session.save_path`, which is
often a shared world-readable directory). Rate-limited with a lockout table. Optional IP
allowlist. Cookies are `HttpOnly` + `SameSite=Strict`, and `Secure` whenever the request
arrives over HTTPS.

### 5.4 Data directory protection

`data/` receives an `.htaccess` with `Require all denied` **and** an `index.php` blocker.
Because nginx ignores `.htaccess` entirely, the installer runs a **web-accessibility
self-test**: it writes a canary file into `data/` and fetches its own URL over HTTP via
cURL. If the canary is retrievable, the installer displays a loud, blocking warning with
nginx-specific remediation and refuses to store the API key until acknowledged.

### 5.5 API key handling

The user pastes an Anthropic API key at setup. It is encrypted at rest with libsodium
(`sodium_crypto_secretbox`) when available, otherwise OpenSSL `aes-256-gcm`, using a key
derived from a random secret generated at install and stored in the data directory with
`0600` permissions. The key is never sent back to the browser after entry and is never
written to any log.

**Honest limitation, to be documented in the README and the installer:** because the
master secret lives in the data directory, an attacker who can read that directory can
decrypt the API key. Encryption at rest here defends against partial disclosure — a leaked
backup, a stray directory listing, a misconfigured `data/` on nginx — not against full
filesystem read. This is why the web-accessibility self-test and one-click uninstall are
load-bearing rather than nice-to-have. A browser-held key-encryption key is a documented
v2 hardening path, out of scope for v1.

### 5.6 CSRF and input validation

CSRF tokens on every mutating endpoint. All tool inputs are validated server-side against
their schemas — **the model's tool calls are untrusted input**. Validation is structural
where possible: a plugin `slug` is checked against an actual directory listing rather than
a regex, which makes path traversal impossible by construction rather than by filtering.

**SQL identifiers are whitelisted, not escaped.** Table names, column names, and
`wp_options` option names cannot be parameter-bound — only *values* can. Every Tier D tool
that touches an identifier (`switch_theme_db`, `fix_siteurl`, `disable_all_plugins_db`,
`reset_user_password`, `analyze_db_health`) resolves it against a fixed allowlist, or
against the live result of `SHOW TABLES`, before it reaches a query. Values are always
bound with `bindValue()`. This is the single most repeated finding in v1.1's
`SECURITY_PATCH_PLAN.md` — table names interpolated into `SHOW TABLE STATUS`, option
values concatenated into `UPDATE … SET option_value = '…'`, and an unvalidated path in
`SELECT … INTO OUTFILE` — and every one of those is reachable again from a tool handler
that takes a string from the model.

### 5.7 No credentials to the model

Tools receive logical parameters only. Handlers resolve paths and credentials server-side
through a `Context` object. The system prompt contains no database credentials, no API
key, and no absolute paths.

### 5.8 Uninstall

One-click self-destruct that wipes the data directory and the script itself, leaving only
a final confirmation file. The UI nags about this after any successful rescue. Leaving
rescue scripts behind is how sites get hacked; cleanup must be easier than not cleaning up.

---

## 6. The safety contract

Every mutating action follows this loop. It is built before any mutating tool.

1. **Snapshot** — a targeted backup of exactly what will change (DB rows to a dump file,
   files/folders copied) into `data/snapshots/{id}/`, with a manifest describing how to
   restore it. Never a full-site backup by default. Size-checked against free disk space
   before creation. Pruned by retention policy.
2. **Plan preview** — a human-readable plan: which tool, which tier, what changes, what
   the rollback is.
3. **Approval** — an action card in the UI. `ask_always` for every mutation.
4. **Execute** — one step.
5. **Verify** — mandatory front-end health check from the server via cURL: status code,
   fatal-error text detection, white-screen detection, response time.
6. **Rollback** — one-click restore from any snapshot; automatically offered when
   verification fails after a change.

The audit log records every tool call, its parameters, tier, snapshot ID, result, and
timestamps, and is viewable in the UI.

---

## 7. Storage

Two implementations behind **narrow per-concern interfaces** rather than one god-interface:
`ConfigStore`, `AuthStore`, `RateLimiter`, `SessionRepo`, `MessageLog`, `StepLog`,
`SnapshotIndex`, `AuditLog`. Each is small enough to test in isolation and to implement
twice without pain.

The backend is selected once at install (SQLite if `pdo_sqlite` is present, else JSON) and
recorded in `meta`. There is no runtime switching and no migration between backends in v1.

### 7.1 SQLite schema

```sql
meta            (key PK, value)                          -- schema_version, install_id, storage_backend, created_at
config          (key PK, value, encrypted INT)           -- api_key, model, effort, retention_days, ip_allowlist
auth            (id PK CHECK(id=1), password_hash, hash_algo, created_at, password_changed_at)
auth_sessions   (token_hash PK, created_at, last_seen_at, expires_at, ip, ua_hash)
rate_limit      (bucket PK, window_start, attempts, locked_until)

sessions        (id PK, title, blade, status, model, effort,
                 created_at, updated_at,
                 lock_token, lock_expires_at,
                 retry_count, retry_after_at,
                 in_tokens, out_tokens, cache_read_tokens, cache_write_tokens, cost_micros)
                 -- blade ∈ {rescue, doctor}: selects system-prompt steering ONLY.
                 -- The tool array is identical in both (see §10.2).

messages        (id PK, session_id FK, seq, role, content_json, api_message_id, created_at,
                 UNIQUE(session_id, seq))

steps           (id PK, session_id FK, seq, kind, state, tool_name, tool_use_id,
                 input_json, result_json, is_error, snapshot_id, health_check_id,
                 started_at, finished_at, duration_ms, error,
                 UNIQUE(session_id, seq))
                 -- kind ∈ {llm_call, tool_exec, snapshot, verify, rollback, approval_wait}
                 -- state = the machine state this step was serving (see §9)

approvals       (id PK, session_id FK, step_id FK, plan_json, rollback_text,
                 decision, decided_at)

snapshots       (id PK, session_id FK, step_id FK, kind, target,
                 manifest_json, bytes, created_at, restored_at, expires_at)

health_checks   (id PK, session_id FK, url, http_status, ttfb_ms, bytes,
                 fatal_detected, excerpt, created_at)

audit_log       (id PK, at, actor, event, subject, meta_json, ip)
```

Two properties are load-bearing:

**`messages` stores verbatim Anthropic content blocks**, not a prettified projection. The
entire history is replayed to the API every turn, and any lossy round-trip breaks
`tool_use`/`tool_result` pairing and thinking-block signatures. The UI never renders from
`messages`; it renders from `steps`.

**`steps` deliberately serves double duty** as the state-machine journal and the tool-call
audit trail, so there is exactly one place that answers "what did this thing do to my site".
`audit_log` covers only non-agent events: install, login, config change, restore, uninstall.

Snapshot payloads live on disk in both backends. Dumps and copied folders never go into
SQLite; the manifest points at `data/snapshots/{id}/payload/`.

### 7.2 JSON fallback layout

Same interfaces. The two unbounded logs become **append-only JSONL**, so every write is
O(1) and a crashed request truncates at most one line.

```
data/
  .htaccess            # Require all denied
  index.php            # blocker
  keyvault             # 0600, master secret
  meta.json
  config.json
  auth.json
  auth_sessions.json
  rate_limit.json
  audit_log.jsonl
  sessions/
    index.json
    {session_id}/
      session.json        # mutable: flock + write-temp + atomic rename
      messages.jsonl      # append-only
      steps.jsonl         # append-only
      approvals.json
      health_checks.jsonl # append-only
  snapshots/
    {snapshot_id}/
      manifest.json
      payload/…
```

Small mutable files use `flock` + write-to-temp + `rename()`. JSONL logs are opened in
append mode with `LOCK_EX` per write. Readers stream line-by-line rather than
`file_get_contents` — a long session's `messages.jsonl` is the largest object in the data
directory.

---

## 8. Tool definition interface

```php
<?php
declare(strict_types=1);

interface Tool
{
    /** Stable identifier; also the Anthropic tool name. */
    public static function name(): string;

    /**
     * The Anthropic tool schema. MUST be pure and deterministic,
     * key order included — prompt caching depends on it.
     */
    public static function definition(): array;

    public function tier(): Tier;         // Tier::Filesystem | Database | WordPress | Exec
    public function mutates(): bool;

    /** Null for read-only tools. Describes what to back up, before anything changes. */
    public function snapshotPlan(array $input, Context $ctx): ?SnapshotPlan;

    /** Human-readable plan for the approval card. Never touches the site. */
    public function preview(array $input, Context $ctx): Plan;

    /** Does the work. A plain typed method — testable with zero LLM involvement. */
    public function execute(array $input, Context $ctx): ToolResult;
}
```

Supporting types:

- `Tier` — `final class` exposing `public const` members `FILESYSTEM`, `DATABASE`,
  `WORDPRESS`, `EXEC`. **Not a PHP enum:** enums are PHP 8.1 and our floor is 8.0 (§2).
  The same rule applies to every other closed set in the codebase — session status,
  step kind, approval decision.
- `SnapshotPlan` — named constructors: `renameRecord()`, `fileCopy()`, `dbRows()`,
  `dbTable()`. Carries an estimated byte size for the disk precheck.
- `Plan` — `summary`, `changes[]`, `rollback`, `tier`, `risk`.
- `ToolResult` — `content` (string, what the model sees), `structured` (array, what the UI
  renders), `isError`, `truncated`, `bytesTotal`.

Example:

```php
final class DisablePlugin implements Tool
{
    public static function name(): string { return 'disable_plugin'; }

    public static function definition(): array
    {
        return [
            'name' => 'disable_plugin',
            'description' =>
                'Disable a plugin by renaming its folder in wp-content/plugins. '
                . 'Call this when debug.log or a fatal error message names a specific '
                . 'plugin as the source of the failure. Works when WordPress cannot '
                . 'boot. Reversible via enable_plugin.',
            'strict' => true,
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'slug'   => ['type' => 'string', 'description' => 'Plugin folder name, e.g. "wp-super-cache".'],
                    'reason' => ['type' => 'string', 'description' => 'Why this plugin is suspected. Shown to the user on the approval card.'],
                ],
                'required' => ['slug', 'reason'],
                'additionalProperties' => false,
            ],
        ];
    }

    public function tier(): Tier    { return Tier::Filesystem; }
    public function mutates(): bool { return true; }

    public function snapshotPlan(array $in, Context $ctx): ?SnapshotPlan
    {
        return SnapshotPlan::renameRecord(
            from: $ctx->pluginDir($in['slug']),
            to:   $ctx->pluginDir($in['slug']) . '.wpsm-disabled',
        );
    }

    public function preview(array $in, Context $ctx): Plan { /* … */ }
    public function execute(array $in, Context $ctx): ToolResult { /* … */ }
}
```

### 8.1 Design rules

**`strict: true` + `additionalProperties: false` + `required` on every schema.** The API
then guarantees `tool_use.input` validates. We still validate server-side: a schema is a
correctness guarantee, not a security boundary.

**Handlers never receive credentials or absolute paths.** `Context` resolves
`pluginDir()`, opens the database connection, and holds the decrypted API key.

**`definition()` is static and pure.** This is a caching requirement, not a style
preference — see §10.2.

**Tool descriptions are prescriptive about *when* to call.** Opus 4.8 reaches for tools
more conservatively than earlier models, and trigger conditions written into the
description itself give measurable lift over descriptions that only state what a tool
does. Every description says when the tool applies, not just what it is.

**Every tool is testable without the LLM.** `execute()` is an ordinary typed method against
a `Context` pointed at the Docker playground.

### 8.2 v1 tool registry

Rescue:

| Tool | Tier | Mutates | Snapshot |
|---|---|---|---|
| `probe_environment` | F | no | — |
| `read_wp_config` | F | no | — |
| `read_debug_log` | F | no | — |
| `http_health_check` | F | no | — |
| `list_plugins_fs` | F | no | — |
| `list_themes_fs` | F | no | — |
| `disable_plugin` | F | yes | rename record |
| `enable_plugin` | F | yes | rename record |
| `disable_all_plugins_db` | D | yes | `wp_options` row dump |
| `switch_theme_db` | D | yes | `wp_options` rows dump |
| `fix_siteurl` | D | yes | `wp_options` rows dump |
| `reset_user_password` | D | yes | `wp_users` row dump |
| `toggle_maintenance_mode` | F | yes | `.maintenance` file record |
| `create_snapshot` | F+D | no | — |
| `restore_snapshot` | F+D | yes | pre-restore snapshot of current state |
| `list_snapshots` | — | no | — |

Doctor (all read-only):

| Tool | Tier |
|---|---|
| `site_overview` | F+D |
| `scan_recent_file_changes` | F |
| `analyze_db_health` | D |
| `check_file_permissions` | F |
| `performance_snapshot` | F |

`create_snapshot` does not modify the site and therefore needs no approval, but is subject
to the disk-space precheck. `restore_snapshot` mutates and takes a pre-restore snapshot of
current state first, so a rollback is itself reversible.

`reset_user_password` displays the generated password exactly once and never stores it —
not in `steps.result_json`, not in the audit log, not in the message history sent to the
API.

`scan_recent_file_changes` is time-budgeted and resumable: a 40,000-file install cannot be
walked in 30 seconds. It caps by wall clock and file count and returns a cursor the agent
can continue from.

---

## 9. Agent step state machine

Every `POST /api/agent/step` performs **exactly one transition** and returns. The frontend
immediately requests the next.

```
                    ┌──────┐
   user message ───▶│ idle │
                    └──┬───┘
                       ▼
              ┌─────────────────┐
        ┌────▶│  awaiting_llm   │  ── one Messages API call, streamed and buffered
        │     └────────┬────────┘
        │              │ stop_reason
        │   ┌──────────┼──────────┬─────────────┐
        │   │ end_turn │ tool_use │ refusal     │ max_tokens / terminal api error
        │   ▼          ▼          ▼             ▼
        │ ┌──────┐  (mutates?)  ┌──────┐    ┌───────┐
        │ │ done │      │       │ done │    │ error │
        │ └───┬──┘      │       └──────┘    └───────┘
        │     │      no │ yes
        │     │         │
        │     │  ┌──────┴────────────┐
        │     │  ▼                   ▼
        │     │ ┌──────────────┐  ┌────────────────────┐
        │     │ │awaiting_tool │  │ awaiting_approval  │◀── blocks; UI shows action card
        │     │ └──────┬───────┘  └─────┬──────────┬───┘
        │     │        │            allow│          │deny
        │     │        │                 ▼          │
        │     │        │        ┌──────────────────┐│
        │     │        │        │awaiting_snapshot ││ ── targeted backup + manifest
        │     │        │        └────────┬─────────┘│
        │     │        │                 ▼          │
        │     │        │        ┌──────────────┐    │
        │     │        │        │awaiting_tool │    │
        │     │        │        └──────┬───────┘    │
        │     │        │               ▼            │
        │     │        │        ┌─────────────┐     │
        │     │        │        │  verifying  │ ── mandatory health check
        │     │        │        └──────┬──────┘     │
        │     │        │               │            │
        │     └────────┴───────────────┴────────────┘
        │              (append tool_result to messages[])
        └──────────────────────────────────────────────┘

   user-initiated:  any ──▶ awaiting_rollback ──▶ verifying ──▶ awaiting_llm
   user-initiated:  any ──▶ aborted
```

| State | Work done in this request | Next state |
|---|---|---|
| `idle` | none — awaiting user input | `awaiting_llm` |
| `awaiting_llm` | one `/v1/messages` call, streamed and buffered; persist assistant message | by `stop_reason`; or stays put with `retry_after` |
| `awaiting_approval` | none — poll only; `POST /api/approve` drives it | `awaiting_snapshot` \| `awaiting_llm` (deny) |
| `awaiting_snapshot` | disk precheck, create snapshot, write manifest | `awaiting_tool` |
| `awaiting_tool` | `Tool::execute()`; result held **pending** if mutating | `verifying` \| `awaiting_llm` |
| `verifying` | health check; merge into pending `tool_result`; append to `messages` | `awaiting_llm` |
| `awaiting_rollback` | restore from snapshot | `verifying` |
| `done` | none — turn complete | `idle` |
| `error` / `aborted` | terminal | — |

### 9.1 The mutating path emits one `tool_result`, not two

Execution and verification are separate requests but one logical unit. The tool result is
held pending until `verifying` completes, then appended with both outcomes merged:

```
Renamed wp-content/plugins/broken-cache → broken-cache.wpsm-disabled
Snapshot snap_01H8… (rename record, reversible)
Verification: GET https://example.com/ → 200 OK, 412ms, no fatal signature.
Before this change: 500, fatal detected.
```

This keeps the conversation well-formed — every `tool_use` has exactly one matching
`tool_result` — and puts the verification signal exactly where the model needs it to decide
whether to continue or propose a rollback.

### 9.2 A denial is a `tool_result`, not a dropped turn

Denying an approval synthesizes a `tool_result` with `is_error: true` explaining that the
user declined and why. Dropping it would leave an unpaired `tool_use` block and the next
API call would return 400.

### 9.3 Two paths to rollback, one implementation

Rollback is reachable two ways, and they are not redundant:

- **Model-initiated** — the agent calls the `restore_snapshot` tool, which travels the
  ordinary mutating-tool path: `awaiting_approval` → `awaiting_snapshot` (capturing current
  state, so the rollback is itself reversible) → `awaiting_tool` → `verifying`.
- **User-initiated** — the one-click restore button in the UI, and the automatic offer that
  appears when `verifying` reports a failure. This posts to `/api/snapshots/{id}/restore`
  and enters `awaiting_rollback` directly, without an approval card, because the click
  *is* the approval and the user may need it when the agent is stuck or wrong.

Both call the same restore routine in `src/safety/` and both are followed by `verifying`.
The user-initiated path must keep working when the session is in `error`.

### 9.4 Guards

- Maximum iterations per session.
- Per-session token ceiling from config, enforced before each API call.
- Monotonically increasing `seq` for idempotency: a double-submitted step is a no-op.
- Advisory lock so a second tab cannot interleave.
- `pause_turn` is not expected in v1 (no server-side tools are used) but is handled by
  re-sending, so it degrades safely if it ever appears.

---

## 10. Anthropic API integration

Raw cURL against `https://api.anthropic.com/v1/messages`. No SDK: the official PHP SDK
requires Composer and a PSR-18 HTTP client, neither of which can be assumed on the target
hosts, and vendoring that dependency tree into a rescue ZIP is disproportionate.

Headers: `x-api-key`, `anthropic-version: 2023-06-01`, `content-type: application/json`.

### 10.1 Streaming client

`"stream": true` with a `CURLOPT_WRITEFUNCTION` callback that accumulates the response
body and parses SSE frames (`message_start`, `content_block_start`, `content_block_delta`,
`content_block_stop`, `message_delta`, `message_stop`) into a complete message object.
The assembled message is returned to the browser as ordinary JSON. `usage` is read from
`message_start` and `message_delta` and accumulated onto the session.

### 10.2 Prompt caching

Caching is a **prefix match** over `tools → system → messages`. Three consequences shape
the design rather than being bolted on afterwards:

1. **One frozen, deterministically ordered tool array for the whole session.** Tools render
   at byte 0. If the Rescue and Doctor blades exposed different tool sets, or ordering
   varied, the cache would be destroyed on every switch. Both blades receive the full
   registry; the *system prompt* steers which tools are appropriate.
2. **Nothing volatile in the system prompt.** No timestamps, session IDs, or site URLs —
   those go in the first user message, after the breakpoint. This is the most common way
   caching silently stops working.
3. **The minimum cacheable prefix on Opus 4.8 is 4,096 tokens** (2,048 on Sonnet 5). Our
   21 tool definitions plus system prompt are estimated at roughly 2,500–3,500 tokens,
   which likely falls **below** that threshold — in which case caching no-ops silently,
   with `cache_creation_input_tokens: 0` and no error. **M5 measures this with
   `count_tokens` before we claim caching works.** If we are short, the remedy is to add
   genuine WordPress diagnostic knowledge to the system prompt, which is independently
   valuable, rather than padding.

Breakpoints (maximum 4 per request): one at the end of the stable `tools + system` prefix,
one rolling on the last content block of the most recent turn.

**TTL is 1 hour, not the 5-minute default.** A session pauses on approval cards while a
worried human reads them, and five minutes evaporates. A 1h TTL costs 2× on write versus
1.25×, breaks even at three requests, and a rescue session runs ten or more.

Mid-session context (a re-probe of the tier report, a configuration change) is injected as
a `{"role": "system"}` message appended to `messages` — supported on Opus 4.8 — rather than
by editing the top-level system prompt, which would invalidate the entire cached prefix.
On any other model this falls back to a marked block inside a user turn.

### 10.3 Token budgeting

- `read_debug_log` **must seek backward from EOF** (`fseek` with a negative offset, or
  `SplFileObject::seek`) and never read the whole file. This is a hard requirement, not an
  optimisation: v1.1's `ErrorLogService::readLogFile()` does `file_get_contents()` on the
  entire log and then `preg_split` on the result — roughly twice the file size resident. A
  site stuck in a fatal-error loop produces a `debug.log` of hundreds of megabytes, so on a
  128MB shared host the log viewer fataled on exactly the sites that needed it (see §15).
  A cap implemented by reading everything and then trimming is the same bug.
  Having seeked, cap at 64KB / 400 lines and return head **and** tail with an explicit
  `[… N lines omitted …]` marker: fatal errors cluster at the tail, but the *first*
  occurrence is what identifies the culprit, so both ends must survive truncation.
- Every `ToolResult` carries `truncated` and `bytesTotal`, so the model knows it is looking
  at a fragment and can request a narrower slice.
- A per-session token ceiling with a running counter in the UI. Reaching it stops the
  session cleanly with "start a fresh session" rather than overflowing context. No
  compaction in v1.
- Rough cost, **to be validated in M5**: a ~10-turn white-screen rescue on Opus 4.8 lands
  around **$0.30–0.50 with caching active**, roughly half that on Sonnet 5. Without caching
  it is several times worse, which is why the frozen-prefix discipline in §10.2 is
  load-bearing rather than an optimisation.

### 10.4 System prompt behaviour notes

Opus 4.8 narrates more than earlier models, asks permission more often, and reaches for
tools more conservatively. The system prompt therefore includes:

- **Autonomy guidance for read-only tools** — proceed without asking; the state machine
  already gates every mutation, so the model should never ask "shall I read the debug log?"
- **Explicit triggering guidance** for the diagnostic tools, reinforcing the prescriptive
  tool descriptions.
- **A plain-language output instruction** — the audience is a panicking non-technical site
  owner, so lead with the outcome, avoid jargon and shorthand, and never assume the reader
  followed the tool calls.

---

## 11. Explicitly out of scope for v1

Content and design editing, page builders, malware removal (detection only), migrations,
multi-site management, scheduled jobs, user accounts and teams, billing and licensing, MCP,
browser-facing streaming, WP REST content tools, context compaction, backend migration
between SQLite and JSON.

---

## 12. Repository structure

```
wp-safe-mode/
├── src/                      # PHP application — everything here ships in the ZIP
│   ├── index.php             # router / entry
│   ├── bootstrap.php         # env checks, storage init, auth gate
│   ├── installer/            # first-run setup wizard
│   ├── api/                  # JSON endpoints: auth, agent-step, approve, snapshots, audit
│   ├── agent/
│   │   ├── StateMachine.php
│   │   ├── AnthropicClient.php
│   │   ├── StreamBuffer.php
│   │   └── PromptBuilder.php
│   ├── tools/
│   │   ├── rescue/           # one file per tool
│   │   ├── doctor/
│   │   └── Registry.php      # deterministic ordering — caching depends on it
│   ├── tiers/                # fs.php, db.php, wp.php, execx.php + connection doctor
│   ├── safety/               # snapshot engine, health check, audit log
│   ├── storage/
│   │   ├── contracts/        # the eight narrow interfaces
│   │   ├── sqlite/
│   │   └── json/
│   ├── security/             # sessions, rate limit, CSRF, key vault
│   └── public/               # compiled frontend assets (committed to git)
├── frontend/                 # Preact + Vite + Tailwind, builds into src/public
├── docker/
│   ├── playground/           # wordpress + mariadb
│   └── dev/                  # php 8.0-cli + phpunit — all PHP runs here
├── tests/
│   └── fixtures/fatal-plugin/
├── docs/superpowers/specs/
├── build.php                 # produces wpsafemode.zip
└── README.md
```

The installer moves under `src/` because it must ship. `src/public/` build output is
committed so `build.php` never needs npm; a pre-commit check fails if it is stale relative
to `frontend/`.

---

## 13. Testing strategy

There is no local PHP or Composer on the development machine, and there does not need to
be. **All PHP execution and all PHPUnit runs happen inside `docker/dev/`**, which pins
tests to the real PHP 8.0 floor rather than whatever PHP a developer's machine has. The
host needs only Docker and Node.

- **Unit tests** — every `Tool::execute()` against a `Context` pointed at the playground.
  No LLM involved.
- **Integration tests for the safety loop** — the full snapshot → mutate → failed health
  check → rollback cycle against a disposable WordPress + MariaDB playground, including a
  deliberately fatal test plugin in `tests/fixtures/fatal-plugin/`.
- **Storage conformance suite** — one test suite run twice, against the SQLite and JSON
  backends, asserting identical behaviour.
- **Lowest-common-denominator posture** — tests assume no `exec`, no Composer, 30-second
  request limits, and nginx without `.htaccess` support. The data-directory
  self-test is exercised in both Apache and nginx playground configurations.
- **Agent tests** are seeded with recorded API responses so the state machine can be tested
  without live API calls; a small number of live smoke tests cover the real client.

Docker Desktop or colima must be started before the playground can run; the daemon is
currently not running on this machine.

---

## 14. Milestones

| # | Name | Deliverable |
|---|---|---|
| **M0** | Skeleton | Repo layout, router, bootstrap env checks, storage layer with both backends and the conformance suite, Docker playground and dev container, Preact shell building into `src/public` |
| **M1** | Installer + security | Setup wizard (password, API key, self-rename), auth/sessions/rate-limit/CSRF, data-dir protection plus web-accessibility self-test, key vault |
| **M2** | Tiers + connection doctor | fs/db/exec adapters, `wp-config.php` parser, Tier W probe, tier report |
| **M3** | Safety engine | Snapshot store with manifests, restore, health checker, audit log — integration-tested |
| **M4** | Tools | Rescue and Doctor registries against the playground, each independently tested |
| **M5** | Agent | Step-wise state machine, streaming Anthropic client, prompt assembly, caching measurement with `count_tokens`, effort sweep, chat UI with progress steps and approval cards |
| **M6** | Flagship demo + packaging | White-screen rescue end-to-end with the fatal test plugin, `build.php` producing the ZIP, uninstall self-destruct |

Work proceeds in order. Small commits, conventional commit messages.

---

## 15. Lessons carried forward from v1.1

WP Safe Mode v1.1 lives on `main` in this repository: ~175 files, an MVC-ish PHP codebase
with 21 services, last released 2025-12-31. It is the direct predecessor and the best
available evidence about this problem domain. What it teaches:

### 15.1 The data directory *will* be web-readable unless proven otherwise

v1.1 stores its state in `sfstore/`: `global_settings.json` (the API key),
`wpsm_login.json` (username, email, bcrypt password hash), and
`wp-config-safemode-backup.php` (a copy of wp-config, therefore database credentials).

Its only protection is `sfstore/index.php`, whose entire contents are:

```php
<?php
//mighty bush 
?>
```

That prevents directory *listing*. It does nothing for sibling files. There is no
`.htaccess` in `sfstore/` and none at the document root — `htaccess.safemode.backup` is an
empty file. Both Apache and nginx serve a `.json` as a static file without ever invoking
PHP, so on a deployed install `GET /<install-dir>/sfstore/global_settings.json` discloses
the API key in plaintext.

**This is the motivating evidence for §5.4.** The web-accessibility self-test — write a
canary into `data/`, fetch our own URL over cURL, refuse to store the API key while it is
reachable — was specified as a defence against a hypothetical. It is not hypothetical: it
is the failure that shipped. The self-test is a launch blocker for M1, not a nice-to-have,
and it must run on every login as well as at install, because a host migration or a
control-panel change can silently remove protection later.

Corollary for v1: no file under `data/` may have an extension the web server will serve
as a static asset. State files are `.php` with a leading `die()` guard, or they live
outside the document root where the host permits it.

### 15.2 Read logs by seeking, never by loading

`ErrorLogService::readLogFile()` reads the whole log with `file_get_contents()` and then
`preg_split`s it. See §10.3 — this fatals on the sites that most need the feature.

### 15.3 Identifiers are the recurring SQL injection vector

`SECURITY_PATCH_PLAN.md` is 465 lines of real, found-in-production vulnerabilities. The
pattern that repeats is not unbound *values* — it is interpolated *identifiers*. See §5.6.

### 15.4 The v1.1 AI is a different species, and validates our scope

`services/AIService.php` calls OpenAI `gpt-4` with eight fixed one-shot prompts:
`analyze_error_log`, `detect_conflicts`, `security_analysis`, `performance_optimization`,
`chat`, `explain_error`, `suggest_code`. No tool calling, no loop, no agency — it explains
problems while the human performs every fix.

There is therefore no code to port: different provider, different paradigm. But those eight
endpoints are strong evidence of what users actually wanted, and they map almost one-to-one
onto the Doctor blade in §8.2. **The leap from v1.1 to v2 is not "better AI" — it is an
agent that can act, behind the safety contract in §6.** That framing belongs in the
product copy as much as in the architecture.

### 15.5 Tier W is where the sharpest edges live

`services/AuthService.php` is not the login system; it is a WordPress magic-link
auto-login (`generateMagicLink` → `getFirstAdmin` → `loginUser`) — a token that signs the
bearer into wp-admin as the first administrator. That is Tier W by nature and it is the
most dangerous capability in the codebase. Deciding Tier W is probe-only in v1 (§3.4)
sidesteps it entirely.

### 15.6 The services map onto our tool registry

v1.1's 21 services correspond closely to the 21 tools in §8.2 — `WpConfigService`,
`ErrorLogService`, `PluginService`, `ThemeService`, `DatabaseService`, `BackupService`,
`SystemHealthService`, `PerformanceProfilerService`, `DatabaseOptimizerService`. They are
not code to copy: no `strict_types`, and coupled to a `DashboardModel` we are not carrying
forward. They *are* proven approaches to the fiddly parts, and each should be read before
its M4 counterpart is written.

### 15.7 Do not accumulate status documents

The v1.1 root holds 21 markdown files — `REFACTORING_COMPLETE.md`, `MODULES_COMPLETE.md`,
`ENHANCEMENTS_COMPLETE.md`, `COMPREHENSIVE_DEBUG_FIXES.md` and so on — roughly 4,500 lines
of progress reports, the residue of several AI-assisted refactor passes that produced
documentation instead of consolidation. v2 keeps design documents in
`docs/superpowers/specs/` and lets git history carry the narrative.

### 15.8 Unverified

No `password_verify()` call was located anywhere in v1.1 during this review;
`get_login()` / `set_login()` in `model/dashboard.model.php` are plain JSON I/O and the
comparison path was not traced. Recorded as unchecked, not as a defect.

---

## 16. Known risks

**The first-run funnel, not the rescue flow, is where users will be lost.** A site owner in
a panic almost certainly does not have an Anthropic API key. This is a direct consequence
of the no-cloud, no-account constraint and is not being changed in v1, but the installer
copy will need deliberate design work. Tracked, not solved here.

**Prompt caching may silently not engage** if the stable prefix falls below 4,096 tokens on
Opus 4.8. Measured in M5; remedy is a richer system prompt.

**API key encryption at rest does not survive a full filesystem read** (§5.5). Mitigated by
the web-accessibility self-test and by making uninstall trivial, and documented honestly
rather than oversold.

**Long Opus turns** are mitigated by server-side streaming (§3.3) and by retries-as-steps
(§3.10), but a host with an unusually aggressive proxy timeout may still fail. The model
picker gives such users a working escape hatch to Sonnet 5 or Haiku 4.5.
