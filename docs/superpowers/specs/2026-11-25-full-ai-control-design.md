# WP Safe Mode — Full AI Control (design amendment)

**Date:** 2026-11-25
**Status:** Approved by the owner, 2026-11-25
**Amends:** `2026-07-24-wp-safe-mode-design.md` (called "the base spec" below)
**Scope:** everything after M0; Phases 0–8

Where this document and the base spec disagree, this document wins. Section 17 lists every
base-spec section that changes. Anything not mentioned there still holds as written.

This is a program-level design. Each phase in §18 gets its own implementation plan, and
Phases 3–8 each get a short design pass of their own before that plan is written; the
items in §20 are what those passes must settle.

---

## 1. Intent

The base spec describes a rescue tool: upload a ZIP beside a broken WordPress, let a Claude
agent diagnose and repair it behind a strict safety contract, then remove the tool.

The owner now wants WP Safe Mode to be the tool that **controls a WordPress site completely
through AI**: not only rescue, but everyday administration, content and design work, and
building whole sites, with a library of reusable material and administration features for
people who run many sites with a team.

Why this is one product and not a new one: every capability on that list is a change to a
live site made by an agent that will sometimes be wrong. The base spec's safety contract
(snapshot, preview, approve, execute, verify, roll back) is the hard part, and it is the
same hard part for "disable the broken plugin" as for "rebuild the home page". So the wider
product is built as more tools and more surfaces **on top of the same chain**, not beside it.

## 2. Goals, non-goals, anti-goals

**Goals**

1. A site owner can still rescue a dead site with one uploaded ZIP and nothing else.
2. The agent can carry out day-to-day administration, content and design changes, and
   site builds, each through the safety chain.
3. A library gives users ready-made playbooks, templates, blueprints and tutorial links,
   and lets them add their own.
4. One person or a team can administer one site or many, with roles, from the site itself
   or from one dashboard.
5. The owner controls the AI: which model, how much it may spend, what it may do without
   asking, and what extra instructions it follows.

**Non-goals**

- Billing, licensing, plans, or a hosted multi-tenant service. The Hub is self-hosted and
  serves one organisation.
- Malware removal (detection stays; removal is out).
- Site migration between hosts.
- Replacing wp-admin. Manual screens exist for the tool's own operations, not for
  everything WordPress can do.

**Anti-goals** (it would be a failure even if it worked)

- Any path that changes a site without a snapshot and a verification, whoever triggers it.
- An Engine that needs the Hub, an account, or a cloud service to rescue a site.
- A permanent install that is as exposed as the v1.1 tool was (§15.1 of the base spec).
- Library content, or anything the agent reads from the site, being able to widen what the
  agent is allowed to do.

## 3. Constraints

Carried from the base spec, unchanged: PHP 8.0 floor; zero runtime dependencies on the
site; no Node, Composer or `exec` assumed; 30-second requests; no build step on the server;
one unit of work per request; no file under `data/` with a statically served extension.

New:

- **The Engine is complete alone.** Nothing in Phases 1–7 may depend on the Hub.
- **One API.** The browser and remote clients call the same endpoints; only the way the
  caller is authenticated differs (§14).
- **One loop, one safety implementation.** They live in the Engine (§5).
- **Nothing is released yet** (`Version::APP` is `2.0.0-dev`). Schema changes before the
  first release need no data migration, but the migration runner (§16) must exist before
  that release.
- **The Engine does not require a working WordPress beside it.** It must install, log in
  and report its tiers when `wp-config.php` is missing or unreadable. Rescue needs this
  (a corrupt config is a rescue case) and so does provisioning a new site in Phase 6.
  Database credentials for a new site are typed by a person into a manual form; the model
  never supplies or sees them.

## 4. What the owner decided

| Question | Decision |
|---|---|
| What runs on the site | A standalone PHP app that works on files and the database directly, even when WordPress is dead. Every WordPress site has its own complete app. |
| What the AI may do | Rescue and diagnose; day-to-day admin; content and design; building sites from blueprints. |
| Library | Playbooks the AI runs; snippet and config templates; whole-site blueprints; tutorial links in context. |
| Super-admin | Full power on one site; many sites in one dashboard; team roles and permissions; control over the AI. |
| Hub | Self-hosted, one team per install. |

Everything else in this document is the designer's call and is open to correction.

## 5. Approaches considered

**A. Engine plus optional Hub (chosen).** Each site runs the full PHP Engine: login, agent
loop, safety chain, tools, library. A separate TypeScript Hub, hosted once by the team,
adds the many-sites dashboard, team management and library publishing, and drives each
Engine over a signed API.
*Trade-off:* two codebases, and the Hub arrives last.

**B. Engine only, with a hub mode.** One PHP codebase; any install can be switched into a
mode that connects to the others.
*Rejected because* fleet and team features would be built inside 30-second requests with no
background work, on the least capable host in the fleet, and the hub install would hold
access to every site while sitting on shared hosting.

**C. JavaScript brain, thin PHP connector.** The agent loop, library and dashboard live in
a JS app; each site gets only a small executor.
*Rejected because* nobody could rescue a site with just a ZIP, which is goal 1, and because
the safety chain would be split between two runtimes.

**Why the loop stays in the Engine under A.** The Engine has to work alone, so the loop
exists in PHP regardless. A second loop in the Hub would be a second safety
implementation. The base spec's step API is already one unit of work per request,
idempotent by `seq`, and resumable, which is exactly what a remote driver needs. Snapshot,
verify and rollback must never depend on the Hub being reachable.

## 6. Shape

```
 Browser ─────────────► ENGINE  (PHP 8.0 drop-in, one per WordPress site)
                          agent loop · safety chain · tools · library · roles
                          │
                          ├─ Tier F  files             always
                          ├─ Tier D  database          when the DB is reachable
                          ├─ Tier W  WordPress bridge  when WordPress boots
                          └─ Tier X  exec / WP-CLI     bonus
                          ▲
                          │ the same API, signed instead of cookie-authenticated
 HUB  (TypeScript, optional, self-hosted, one team)
   sites · team and roles · library studio · AI policy and budgets · MCP
```

Repository layout additions (PascalCase directories, matching the code as built rather
than the lowercase tree drawn in base spec §12):

```
src/
  Agent/  Tools/{Rescue,Doctor,Plugins,Themes,Users,Database,Files,Backups,Config,Core,Content,Library}/
  Tiers/  Safety/  Library/builtin/  Remote/  Installer/  Api/
hub/                  # Phase 8: TypeScript app
packages/ui/          # Phase 8: components shared by frontend/ and hub/
```

## 7. Install postures

Base spec §5.8 says leaving a rescue script on a server is how sites get hacked. A
permanent administration app contradicts that unless it is held to a higher bar. The
installer therefore asks which posture the install is in, and records it in `meta`.

| | **Emergency** | **Keep installed** |
|---|---|---|
| Purpose | Fix this site, then leave | Standing administration |
| Directory rename (§5.2) | Strongly recommended | Required |
| Second factor | Not offered | Required for owner and admin, no exception: TOTP implemented without dependencies, with one-time recovery codes issued at enrolment |
| IP allowlist | Optional | Optional, offered at setup |
| Data-directory self-test | At install and every login | Also on a recurring basis: at most daily, triggered by any authenticated request or Hub poll |
| Dormant lock | — | After 30 days without activity, every endpoint is refused except login and a signed read-only status request |
| Uninstall nag after a rescue | Yes | No |
| Remote API, team, auto-approval | Unavailable | Available |

Moving from Emergency to Keep installed is an owner action that must pass the hardening
bar first. Moving back is always allowed.

**When each posture exists.** Version 2.0.0 (Phase 1) ships the Emergency posture only and
the installer does not ask. The hardening bar (TOTP and recovery codes, mandatory rename,
recurring self-test, dormant lock) is the first deliverable of Phase 2, which is the first
phase whose features need a standing install. Team screens for adding more users stay in
Phase 7.

**Activity for the dormant lock** is a browser login, or a signed request that carries an
acting Hub user. Either one wakes a dormant install. Unattended Hub polling does not count.

**Lost second factor.** After the recovery codes, the last resort is the way the tool was
installed: someone with filesystem access deletes the guarded second-factor record, which
forces re-enrolment at the next login and is written to the audit log.

## 8. Identity, users and roles

### 8.1 Principal

Every API request resolves to a `Principal` before it reaches a handler: who is acting
(a user, or a remote client acting for a named Hub user), with which role, through which
channel (`browser`, `remote`). Handlers and tools receive the principal; nothing reads the
session cookie or signature headers directly.

Code changes this forces:

- `src/Core/Router.php` matches exact paths only. It gains path parameters and, per
  route, a required permission and the channels it accepts. A route with no declared
  permission does not dispatch. Owner-only routes accept the browser channel only.
- `src/Core/Request.php` gains request headers (it already carries `rawBody`, which
  signature verification needs).
- The M1 plan's Task 10 middleware (static calls plus a path-prefix allowlist in
  `src/index.php`) is replaced by: resolve principal → check route permission → dispatch.
  The CSRF gate applies to the browser channel only; signed requests skip it.

### 8.2 Users

`src/Storage/Contracts/AuthStore.php` ("exactly one credential") becomes `UserStore`:
find by id and by login, list, create, update, disable. `src/Storage/Credential.php` gains
`userId`, `login`, `role` and `disabledAt` (second-factor fields follow in Phase 2). The
`auth` table and its
`CHECK (id = 1)` are replaced by `users` in both backends. Phase 1 creates exactly one
user, the owner, at install; the screens to add more arrive in Phase 7. Nothing else about
Tasks 1–4 of M1 changes.

### 8.3 Roles

| Permission | viewer | operator | admin | owner |
|---|---|---|---|---|
| Read sessions, reports, audit log, library | ✓ | ✓ | ✓ | ✓ |
| Start sessions, run playbooks, approve `reversible` actions | | ✓ | ✓ | ✓ |
| Approve `destructive`, `code` and `credential` actions | | | ✓ | ✓ |
| Author library items, edit AI settings below the owner's ceiling | | | ✓ | ✓ |
| Manage users, change posture, pair a Hub, set policy ceilings, store the API key, one-click admin login, uninstall | | | | ✓ |

Roles are a closed set of class constants, not an enum (PHP 8.0 floor).

The owner row is reachable from the browser channel only. A remote client can act as
admin at most (§14.4), so no Hub, however it is compromised, can manage users, change the
posture, pair another Hub, raise its own ceiling, read or replace the API key, use the
one-click login, or uninstall.

## 9. Tool contract

The base spec's `Tool` interface (§8) gains three methods. They are added in Phase 1 so
that no tool is ever written without them.

```php
/**
 * One of Risk::READ | REVERSIBLE | DESTRUCTIVE | CREDENTIAL | CODE.
 * A pure function of the validated input: no I/O, no side effects.
 */
public function risk(array $input): string;

/** Family name, e.g. 'plugins'. Used for mode toolsets, tutorial matching, screens. */
public static function family(): string;

/** Tiers this tool can run on, best first, e.g. [Tier::WORDPRESS, Tier::DATABASE]. */
public function tiers(): array;
```

**Risk classes**

| Class | Meaning | Examples |
|---|---|---|
| `read` | Changes nothing | read log, list plugins, health check |
| `reversible` | The snapshot restores the exact prior state | rename a plugin folder, change one option, toggle maintenance mode |
| `destructive` | Large blast radius, or loss beyond what a targeted snapshot holds | delete a plugin, search-replace, bulk cleanup, core reinstall, restore a full backup |
| `credential` | Touches who can log in | reset a password, create a user, change a role |
| `code` | Writes something the server executes or obeys | PHP files, `.htaccess`, `wp-config.php`, the bridge plugin |

`mutates()` stays and is true for everything except `read`. Risk takes the input because
a few tools span classes: `apply_template` is `code` when the target is PHP, `.htaccess`
or `wp-config.php`, and `reversible` for `robots.txt`. Most tools return a constant.

**Tier fallback.** `tiers()` replaces the single `tier()`. At plan-preview time the Engine
picks the first listed tier the connection doctor reports as available and names it on the
approval card. A tool with no available tier returns an error result that says which tier
it needs. Activating a plugin is the model case: Tier W runs the plugin's activation hook;
Tier D edits `active_plugins` and the card says the hook will not run.

**Secrets.** Base spec §5.7 says no credentials reach the model. Power tools return file
and database contents, so that rule needs teeth: a redaction pass runs over every
`ToolResult::content` before it is appended to `messages`. It masks the values of
`wp-config.php` secrets and salts, password-hash and activation-key columns, and anything
matching the stored API key. `structured` output for the human-facing screens is redacted
by the same pass unless the principal is an owner viewing a manual screen. The pass is
built in Phase 1, because `read_wp_config` already needs it; Phase 4 adds the rules for
the database and file tools.

## 10. Approval policy

Base spec §3.7 fixed `ask_always` with no override. That remains the shipped default and
the only behaviour in Phase 1. From Phase 2, in the Keep-installed posture, the owner may
set a policy per risk class.

`src/Safety/ApprovalPolicy.php` is consulted at the "mutates?" branch of the state machine
(base spec §9) and returns `ask`, `auto` or `deny`.

| Risk | Allowed settings |
|---|---|
| `read` | always runs (unchanged) |
| `reversible` | `ask` (default), `auto`, `deny` |
| `destructive` | `ask` (default), `deny` |
| `credential` | `ask` (default), `deny` |
| `code` | `ask` (default), `deny` |

Rules that policy cannot change:

- An `auto` action is still snapshotted and verified. A failed verification rolls it back
  automatically and suspends `auto` for the rest of the session.
- `approvals.decision` records `auto` and the rule that produced it; the audit trail is as
  complete as for a human approval.
- The policy in force is copied onto the session when it starts. Editing settings does not
  change a running session.
- A remote client's ceiling (§14.4) can only narrow this policy.

## 11. Safety contract extensions

The six-step loop in base spec §6 is unchanged. Wider scope needs five additions.

1. **Snapshot kinds.** Add `fullSite` (files archive plus database dump, written in
   resumable chunks because it cannot finish in one request) and `contentRows` (the rows of
   a post, its meta and term links). "Never a full-site backup by default" still holds: a
   full-site snapshot is taken only when a tool's `snapshotPlan()` asks for one.
2. **Verification targets.** The health check fetches the front page today. A tool may
   declare extra URLs to verify (the page it edited, wp-login for a credential change).
   The front page is always included.
3. **Plans** (Phase 6). A blueprint run is a *plan*: an ordered list of tool calls shown in
   full on one approval card, with the highest risk class among its steps deciding who may
   approve. Each step still snapshots and verifies. The first verification that does not
   pass stops the plan and offers to roll back every completed step in reverse. Plans are
   created only by `run_blueprint`. A playbook run is an ordinary session with one approval
   per change. Whether the agent may also propose a plan is a Phase 6 question; no plan
   storage is built before then.
4. **Mutation lock.** The per-session tab lock in base spec §3.9 and §9.4 stays as it is:
   a second tab on the same session is a spectator. What changes is "single active
   session". Any number of sessions may read; at most one session per site holds the
   *mutation lock*. It is taken at `awaiting_snapshot` and released after `verifying`
   passes; a plan holds it from its first snapshot until it completes or is rolled back.
   A second session that needs it waits in `awaiting_approval` with a message saying who
   holds it. The lock is a lease renewed by each step. If the driver disappears and the
   lease runs out while a change is unverified, the lock is not dropped: the next person
   with the right to act is shown the interrupted change and must resume its verification
   or roll it back before anything else can mutate the site. Nothing runs in the
   background to do this for them.
5. **Verification has three outcomes.** `pass`, `fail`, and `inconclusive` when the server
   cannot reach its own site (some hosts block loopback requests). Inconclusive is never
   treated as a pass. The session stops and asks the person to look at the site and answer
   working or broken, with rollback one click away. `auto` approval and plans do not
   continue past an inconclusive result. The connection doctor tests loopback at session
   start, so the user is told before any change that verification will need them.

Ported from the plugin spike in Phase 3, both installed only on approval and never
overwriting a drop-in that another plugin owns:

- **Fatal-error drop-in** (`wp-content/fatal-error-handler.php`): if the last change is
  followed by a fatal error, WordPress itself restores that snapshot. This covers failures
  the HTTP health check cannot see, such as a fatal only on an admin page. It runs before
  the database is loaded, so it restores file-only snapshots and nothing else, and it does
  so by loading the Engine's own restore routine from the recorded install path. It is a
  second trigger for the one restore implementation, not a second implementation. If the
  Engine has been removed it does nothing.
- **Session overlay** (must-use plugin): one signed browser session sees the site with a
  different set of active plugins or a different theme while visitors see it unchanged.
  This makes bisecting a plugin conflict on a live site safe.

## 12. Tier W: the WordPress bridge

Base spec §3.4 made Tier W probe-only. Content work and correct plugin handling need
WordPress's own functions, so Phase 3 builds it.

- `src/Tiers/WordPressBridge.php` in the Engine, and a small must-use plugin that the
  Engine writes into `wp-content/mu-plugins/` as an approved `code`-class action.
- The Engine calls the bridge over loopback HTTP, the same way the health check reaches the
  site. Each request carries a timestamp, a nonce and an HMAC over the operation name and
  body. The key is a purpose-scoped subkey (§16), never the master secret, stored
  die-guarded beside the plugin and rotated on every bridge install.
- The bridge executes a **fixed list of named operations** with typed parameters, each
  mapped to WordPress API calls (`activate_plugin`, `wp_insert_post`,
  `wp_update_nav_menu_item`, `switch_theme`, …). It never accepts PHP source or SQL.
- If the host blocks loopback requests or WordPress will not boot, Tier W is reported
  unavailable and tools fall back per §9 or report that they need it.
- The Engine's uninstaller removes the bridge, its key file and both drop-ins.

**No content authoring through the database.** Creating or editing posts, pages, menus,
media and styles is Tier W only. Writing them straight into the tables skips hooks,
corrupts serialized data and leaves stale object caches. When WordPress is dead, content
tools are unavailable and the agent is steered to rescue first.

Tier D writes are limited to an explicit list, each its own tool with its own allowlist:
the rescue writes in base spec §8.2; option edits on an allowlist; plugin and theme
activation through their options (the fallback in §9); creating a user and changing a
role or password; serialization-aware search-replace over existing rows; cleanup deletes
(revisions, spam, transients); table optimise and repair. Anything that moves content
between owners, such as deleting a user and reassigning their posts, is Tier W only.

## 13. Tools, modes and the prompt

### 13.1 Families

| Family | Phase | Tools (outline) | Preferred tier |
|---|---|---|---|
| Rescue, Doctor | 1 | the 21 tools in base spec §8.2 | F, D |
| Library | 2 | `library_search`, `library_get`, `apply_template`; `run_blueprint` in Phase 6 | — |
| Plugins, Themes | 4 | install from wordpress.org, update, activate, deactivate, delete, roll back a version | W, then F/D |
| Users | 4 | list, create, change role, reset password (W, then D); delete with content reassignment (W only) | W, then D |
| Database | 4 | browse tables, guarded read-only query, search-replace (serialization-aware), optimise, cleanup, export | D |
| Files | 4 | list, read, edit with diff and lint, upload, fix permissions | F |
| Backups | 4 | create, list, download, restore, prune | F+D |
| Config | 4 | `wp-config.php` constants, `.htaccess` blocks, `robots.txt`, cron events, maintenance mode | F, D |
| Core | 4 | verify checksums, reinstall core files | F, X |
| Content | 5 | posts and pages, menus, media, Additional CSS, global styles, theme switch with preview | W only |

Every tool is an ordinary typed method, testable with no LLM (base spec §8.1). Every
family in Phase 4 also has a **manual screen** that calls the same `preview()` and
`execute()` through the same state machine, so a person without an API key still has a
working tool and the audit trail is identical either way.

The one-click admin login is **not a tool**. It is a human-only endpoint: owner role,
single use, expires in under a minute, audited, off by default, available only through
Tier W (Phase 7). The agent cannot call it and cannot see its link.

### 13.2 Modes

`Session::BLADE_RESCUE` / `BLADE_DOCTOR` generalise to a **mode**: `rescue`, `doctor`,
`admin`, `content`, `build`. `src/Storage/Session.php` and the `sessions` table rename
`blade` to `mode` and add `playbook_id`, `created_by`, `origin` (`browser` or `remote`),
and `policy_json` (the frozen policy and custom instructions from §10 and §15).

### 13.3 Prompt and caching

Base spec §10.2 sent one frozen tool array, the whole registry, to every session. That
cannot hold at around a hundred tools. The amended rule:

- Each mode has a fixed, deterministically ordered toolset, chosen when the session starts
  and unchanged for its life. Changing mode starts a new session with a short handoff
  summary. `rescue` and `doctor` keep sharing one toolset, as today.
- Custom instructions and library content never enter the cached prefix. Custom
  instructions go in as a mid-conversation system message after the breakpoint; library
  content arrives only as tool results.
- Whether a mode's toolset should instead use deferred tool loading is decided by
  measurement in Phase 4, not now.
- Base spec §10.3 ruled out compaction. `build` mode cannot fit a site build in one
  context, so Phase 6 adds it. The choice of mechanism belongs to that phase's design; the
  constraint is that the message log stays append-only and is replayed verbatim.

### 13.4 Claude API refresh

The base spec was written against models that have since been superseded. Before M5 the
agent section is re-checked line by line against the current API reference; the known
changes are:

- Default model `claude-opus-5-5`; user-selectable `claude-sonnet-5-5` and
  `claude-haiku-5-5`. The model is fixed per session, because thinking blocks are tied to
  the model and conversation that produced them.
- `output_config.effort` is set explicitly on every request. The API default on the new
  default model is `medium`; the base spec intends `high`. The M5 effort sweep stands.
- Thinking cannot be disabled on the default model; send `{"type": "adaptive", "display":
  "summarized"}` so the step UI still has progress text.
- `tool_choice` stays `auto` with `disable_parallel_tool_use: true`. Forced tool choice is
  rejected by current models and is not used.
- `stop_reason: "refusal"` is handled as its own outcome (base spec §9 already routes it
  to `done`), with the server-side fallback option enabled.
- The message history is never edited after the fact. `MessageLog` is already append-only.
- Minimum cacheable prefix and cost figures in base spec §10.2–10.3 are re-measured with
  `count_tokens` in M5.
- The raw cURL client stays. The official PHP SDK needs Composer, which the target hosts
  cannot be assumed to have. The loop is self-hosted on the Messages API because tools must
  run on the customer's own host with no cloud account.

## 14. Remote API

### 14.1 Same endpoints, different authentication

There is no separate remote API. `/api/…` routes accept either a browser session (cookie
plus CSRF token) or a signed request. Both resolve to a `Principal` (§8.1).

What Phase 1 delivers of this is deliberately small: the `Principal` with its channel, the
per-route permission and channel declarations, and the protocol written down as a
document so the browser API is designed against the contract the Hub will use. **No
signature-verification code ships in 2.0.0.** Pairing, signing and the stores behind them
are built in Phase 7 and work only in the Keep-installed posture.

Remote access requires libsodium (bundled with PHP since 7.2 but an optional extension).
On a host without it the pairing screen says so and the Engine keeps working locally.
There is no weaker fallback: a second signature scheme would be a second thing to get
wrong, and the same extension is needed to check pack signatures (§15.1).

### 14.2 Pairing

1. A logged-in owner clicks *Connect a Hub*. The Engine creates a key pair for this
   pairing and a one-time code valid for ten minutes.
2. The Hub administrator enters the site URL and the code. The Hub posts its public key
   for this site to `/api/remote/pair`; the Engine returns its own public key and its
   capabilities (version, protocol version, tiers, posture).
3. Both sides show key fingerprints for the owner to compare.

The code travels from the Engine to the Hub, so only someone who is already an owner on
the site can enrol it. Pairing requires HTTPS. Either side can unpair; the Engine keeps
working.

### 14.3 Signing

Each request signs, in order: method, path, timestamp, nonce, SHA-256 of the body, the
acting Hub user, and the role the Hub asserts for that user on this site. The signature
is Ed25519. The Engine rejects a request whose timestamp is more than 300 seconds off,
whose nonce it has seen, or whose client is revoked.

The Engine cannot check who a Hub user is, so it trusts the Hub's assertion of user and
role only up to the client's ceiling (§14.4): the effective role is the lower of the two.
The acting Hub user is recorded as `decided_by` on approvals and as the actor in the audit
log. A signed request with no acting user (a status poll) gets the viewer role.

Failed signature checks are counted per client and per source address with the existing
`RateLimiter` (five failures in fifteen minutes, then a lockout), using buckets of the
form `remote-fail:{client_id}`. That interface is a lockout counter, not a throughput
throttle; limiting the request rate of a healthy client is not designed here.

### 14.4 Ceiling

A paired client carries a ceiling set by the site owner in the browser: the highest role
it may act as (admin at most, never owner), the risk classes it may approve, the modes it
may start. The Engine enforces it and the Hub cannot raise it, because every route that
edits a ceiling is browser-only (§8.3). By default the ceiling excludes approving
`credential` actions; the owner may allow it. The one-click login is never available to a
remote client under any ceiling.

### 14.5 Key sources

`config.key_source` is `local` (as in base spec §5.5) or `hub_proxy`. With `hub_proxy` the
Engine's Claude client sends its Messages requests to a compatible endpoint on the Hub,
signed as in §14.3. The Hub holds the one API key and enforces organisation spend and model
limits. No key is stored on the site, which removes the limitation described in base spec
§5.5 for sites run this way and softens the first-run problem in base spec §16.

## 15. Library and AI control

### 15.1 Packs

A pack is a directory:

```
{pack-id}/
  pack.json          # id, name, version, author, description, source
  playbooks/*.md
  templates/*        # template body + templates.json describing each
  tutorials.json
  blueprints/*.json
```

- The **built-in pack** ships in the ZIP at `src/Library/builtin/` and is read from disk.
- **Local** items (written by the site's own admins) and **Hub-published** packs are stored
  through a new `LibraryStore` contract (`library_items`: id, pack, type, slug, version,
  source, signature, body, enabled), in both backends, under the same die-guard rules as
  every other state file.
- Third-party pack import does not exist until signing does (Phase 7). A pack is signed
  with its publisher's Ed25519 key. Each Engine keeps a list of **trusted publishers**
  that only the owner edits, by pasting a public key and confirming its fingerprint. A
  paired Hub is one such publisher, added automatically at pairing. Import needs a valid
  signature from a listed publisher, so signing does not depend on a Hub existing.
- In Phase 1 only the built-in pack exists, read from disk, and only its playbooks are
  used: they are the start-screen choices that open a rescue or doctor session. There is
  no `LibraryStore`, no library tool and no authoring before Phase 2.

### 15.2 Item types

**Playbook.** Markdown with front matter: `title`, `summary`, `when_to_use`, `mode`,
`risk` (the highest class it may lead to), `questions` (asked before it starts),
`tutorials`. The body is guidance for the agent: goal, what to check, what to try, when to
stop. It contains no executable content. Running one starts a session in the playbook's
mode; the body and the user's answers go in the first user message inside a delimited
block that the system prompt describes as library guidance. The built-in rescue flows
(white screen, locked out, wrong site URL, plugin conflict) are the first playbooks and are
written in Phase 1.

**Template.** A text body with `{{variables}}` plus metadata: target kind (`htaccess`
block, `wp-config` constant, `robots.txt`, PHP snippet, CSS), variable schema, description.
It is applied only by the `apply_template` tool, which is `code`-class for PHP,
`.htaccess` and `wp-config` targets. Before writing, PHP is syntax-checked (the spike's
`src/Files/Lint.php` approach: `php -l` where a subprocess is allowed, the tokenizer
otherwise, and refusal if neither works).

**Tutorial link.** `{id, title, url, topics[], families[], error_signatures[]}`. Shown
beside approval cards, tool results and playbooks by matching tool family and plain
substring error signatures; the agent can also find them with `library_search`. HTTPS
only. The Engine never fetches a tutorial URL. Built-in links point at wordpress.org
documentation and the project's own docs, and CI checks they still resolve.

**Blueprint.** JSON: metadata plus an ordered list of declarative steps (install plugin or
theme by wordpress.org slug, set allow-listed options, create pages and menus, apply
templates, import content). No raw PHP or SQL steps. It runs as a plan (§11). Step naming
is aligned with the WordPress Playground blueprint format where the two overlap; the exact
subset is fixed in the Phase 6 design after reading that format's current schema.

### 15.3 Trust

Library content is untrusted input, like the model's tool calls and like anything read
from the site:

- A playbook cannot name a tool outside its mode's toolset, change policy, or skip an
  approval. It can only influence what the agent asks for; the state machine decides what
  happens.
- Only admins and owners create local items. Only owners enable a pack.
- The approval card shows where a template or blueprint came from (built-in, local author,
  Hub publisher).

### 15.4 AI control

One settings screen, owner-controlled, with an admin-editable subset:

- model and effort (§13.4);
- token ceiling per session (base spec §10.3) and a cost ceiling per calendar month,
  summed from `sessions.cost_micros`. At the ceiling, new model calls are refused with a
  plain message; manual screens keep working;
- custom instructions (free text, copied onto each session at start);
- approval policy (§10);
- key source (§14.5).

## 16. Storage

New contracts, each implemented for SQLite and JSON and added to the conformance suite.
A store is built in the phase that first uses it, never earlier:

| Phase | Stores | Schema |
|---|---|---|
| 1 | `UserStore` (replaces `AuthStore`), `AuthSessionStore`, `AuditLog`, `StepLog`, `ApprovalStore`, `SnapshotIndex`, and the site mutation lock | `users`; `auth_sessions.user_id`; `sessions.{mode, playbook_id, created_by, origin, policy_json}`; `approvals.{decided_by, rule}`; `site_lock` |
| 2 | `LibraryStore`; second-factor fields on users | `library_items`; `users.totp_*`, recovery codes |
| 6 | `PlanStore` | `plans`; `steps.plan_id` |
| 7 | `RemoteClientStore`, `NonceStore`, trusted publishers | `remote_clients(id, name, public_key, ceiling_json, created_at, revoked_at)`; `remote_nonces(client_id, nonce, seen_at)`; `trusted_publishers` |

`Storage` (`src/Storage/Contracts/Storage.php`) gains an accessor as each store arrives.
The Phase 1 session and approval columns are added now, although some stay empty until
later phases, because adding a column before the first release is free and adding it
after is a migration.

**Migrations.** `SqliteSchema::install()` is `CREATE TABLE IF NOT EXISTS` only and
`Version::SCHEMA` is `1`. Phase 1 adds an ordered migration list keyed by schema version,
run inside a transaction for SQLite and as write-temp-then-rename steps for JSON. The
conformance suite migrates a recorded version-1 fixture on both backends.

**Key vault.** `src/Security/KeyVault.php` derives one key with a private `CONTEXT`. It
gains purpose-scoped subkeys (`config`, `bridge`, `totp`, `remote`) through the same HKDF
with different context strings, so compromise of one use does not expose the others.

## 17. Amendments to the base spec

| § | Change |
|---|---|
| Header, 1 | "No cloud, no account, no external control plane" stays true for the Engine. The product is now a standing tool with an optional self-hosted Hub. |
| 3.2 | Replaced by §13.4. |
| 3.4 | Replaced by §12. |
| 3.6 | Unchanged (parallel tool calls stay disabled). |
| 3.7 | Replaced by §10. |
| 3.9 | Extended, not replaced. The per-session tab lock stays. "Single active session" becomes the site mutation lock in §11. From Phase 8 a Hub may drive a session with no browser open. |
| 4 | Tier W is a real tier. Tools declare an ordered list of tiers. |
| 5.1, 5.3 | One password becomes users with roles (§8). Hashing, cookies and rate limiting are unchanged. |
| 5.2, 5.8 | Depend on posture (§7). |
| 5.5 | Adds the `hub_proxy` key source (§14.5). |
| 5.7 | Adds the redaction pass (§9). |
| 6 | Extended by §11. Step 5 gains the `inconclusive` outcome. |
| 7 | More stores and a migration runner (§16). |
| 8, 8.2 | `Tool` gains `risk()`, `family()`, `tiers()`. The registry grows by family (§13.1). |
| 9 | The "mutates?" branch consults `ApprovalPolicy`. `verifying` may end `inconclusive`, which waits for a person. The site mutation lock is taken and released as in §11; §9.4's per-session lock is unchanged. Plans add a `plan_id` to steps in Phase 6. |
| 10.2 | One frozen toolset per mode, not one for the product (§13.3). |
| 10.3 | Compaction allowed in `build` mode from Phase 6. |
| 10.4 | The audience is no longer only a panicking owner; the plain-language rule stays, and the prompt for `admin`, `content` and `build` modes is written for a working user. |
| 11 | Content and design, multi-site management, teams, MCP and compaction move into scope. Still out: billing and licensing, a hosted multi-tenant service, malware removal, migrations between hosts, backend migration between SQLite and JSON. |
| 12 | Layout per §6. |
| 14 | Replaced by §18. |
| 15.5 | The magic-link login returns, as a human-only endpoint under §13.1's limits. |

## 18. Phases

Each phase ends with its acceptance scenario run in a browser against the Docker
playground. Order is a dependency order, not a calendar.

**Phase 0 — Ground.** Approve this document. Merge the M0 pull request. Re-cut M1 tasks
5–13 (below).

**Phase 1 — Engine v1: rescue and diagnose, then release.** Base spec M1–M6, Emergency
posture only, with exactly these foundations added:

| From | What Phase 1 builds | What it leaves for later |
|---|---|---|
| §3 | Installs and runs with no working WordPress beside it | Provisioning (Phase 6) |
| §8 | `Principal`, permissions and channels per route, `UserStore`, one owner user | Team screens, second factor |
| §9 | `risk()`, `family()`, `tiers()` on every tool; the redaction pass | Database and file redaction rules |
| §10 | `ApprovalPolicy` consulted by the state machine, returning `ask` for everything | Any other setting |
| §11 | The site mutation lock; the three verification outcomes | Snapshot kinds beyond the base spec's, plans, both drop-ins |
| §13.2 | `mode` on sessions (`rescue`, `doctor`), the session columns in §16 | Other modes |
| §13.4 | The API refresh | — |
| §14 | The protocol document; no remote code | Everything else |
| §15 | The pack format; built-in rescue playbooks as start-screen choices | Stores, tools, authoring |
| §16 | Phase 1 stores, the migration runner, key-vault subkeys | Later stores |

M1 re-cut:

| Task | Change from the 2026-08-01 M1 plan |
|---|---|
| new, first | `AuthStore` → `UserStore`; migration runner; key-vault subkeys |
| 5 Login sessions | sessions carry `user_id`; login takes a login name |
| 6 CSRF | browser channel only |
| 7 Audit log | actor is the principal, not the literal `operator` |
| 8 Self-test | unchanged |
| 9 Installer | creates the owner user; must complete with no `wp-config.php` found |
| 10 Middleware | principal resolution; per-route permission and channel; router path parameters; request headers |
| 11, 12, 13 | unchanged in substance |

*Done when* the fatal-plugin fixture site is rescued end to end from an uploaded ZIP, and
version 2.0.0 is released.

**Phase 2 — Library and AI control.** First the Keep-installed hardening bar from §7
(second factor with recovery codes, mandatory rename, recurring self-test, dormant lock),
because everything after it assumes a standing install. Then library screens, local
authoring, `apply_template`, tutorial links in the UI, the AI control screen, and approval
policy.
*Done when* a user runs the built-in white-screen playbook, applies a built-in `.htaccess`
template through an approval card, sees the matching tutorial link, and a monthly cost
ceiling stops a session cleanly.

**Phase 3 — WordPress bridge.** §12 and the two ported drop-ins. A throwaway spike of the
bridge is done during M2, because it shapes the tier adapter interface.
*Done when* a plugin is activated through the bridge with its activation hook firing, and
with WordPress dead the same request falls back to Tier D and says so on the card.

**Phase 4 — Admin tools and manual screens.** The families in §13.1, with the redaction
rules their output needs.
*Done when* every family has LLM-free tests against the playground, and the manual screen
and the agent leave identical audit entries for the same action.

**Phase 5 — Content and design.**
*Done when* the agent drafts a page, adds it to a menu and changes a colour, each behind
an approval card, each verified at the changed URL, each rolled back successfully.

**Phase 6 — Blueprints.** Plans, `build` mode, compaction, provisioning into an empty
directory, exporting a site as a blueprint.
*Done when* an empty playground directory becomes a working starter site from a built-in
blueprint, and a deliberately failing step rolls the plan back.

**Phase 7 — Team and remote access.** Team screens (inviting users, roles), the remote API
built and switched on, trusted publishers with pack signing and import, one-click admin
login.
*Done when* a viewer cannot approve, a paired test client drives a session within its
ceiling, and replayed, expired and revoked requests are refused.

**Phase 8 — Hub.** TypeScript app under `hub/`, shipped as a Docker image. Site registry
and pairing, fleet dashboard, remote sessions driven server-side, team with per-site
grants, library studio, AI policy and budgets, `hub_proxy`, read-only fleet runs, and an
MCP server so Claude Code or Claude Desktop can work with paired sites (read-only by
default; changes only queue a proposal for a human to approve).
*Done when* two playground sites are paired, a signed pack is published to both, and a
rescue on one is run and approved from the Hub.

Work that can proceed in parallel once the Phase 1 protocol is frozen: the frontend
component set, the bridge spike, writing library content, and a Hub skeleton against a
mock Engine.

## 19. Risks

- **A permanent admin app is a permanent target.** Mitigated by the posture split and its
  hardening bar (§7), and by keeping uninstall one click.
- **The Hub concentrates access.** One compromise reaches every paired site. Mitigated by
  per-site keys, Engine-side ceilings the Hub cannot raise, no `credential` actions
  remotely by default, and unpairing from the site side.
- **Library content as an attack path.** A template is code and a playbook is persuasion.
  Mitigated by §15.3, by `code`-class approval with syntax checking, and by signing before
  any import exists.
- **Auto-approval combined with hostile input.** Log lines and post content are written by
  strangers. Mitigated by limiting `auto` to the `reversible` class and suspending it on a
  failed verification.
- **Hosts that block loopback requests.** Tier W and the health check both need them. On
  such a host verification is `inconclusive` and a person must confirm each change (§11),
  the bridge reports itself unavailable, and tools fall back to other tiers. The tool
  still works there, more slowly and with no automatic approval.
- **Scope.** This is eight phases. The defence is the order: Phase 1 ships a complete
  product, and each later phase is useful without the ones after it.

## 20. Left to the builder, and to later design passes

Decided here, not to be reopened without the owner: everything in §4, the choice of
approach in §5, the posture split and that 2.0.0 is Emergency-only, the role table, "a
remote client is never an owner", the risk classes and their allowed policy settings,
"inconclusive is never a pass", "no content authoring through the database", "one-click
login is not a tool", "remote access needs libsodium", and "the loop stays in the Engine".

Left to the implementation plan for the phase concerned:

- Class, table and endpoint names not given above; exact JSON shapes; UI layout within the
  visual language in `docs/design/2026-07-31-visual-language.md`.
- The canonical string format and header names for request signing (§14.3).
- The bridge's exact operation list and its transport details (§12).
- Which tools each mode's toolset contains, beyond the family table in §13.1.

Needs its own short design pass before its phase is planned:

- Phase 3: how the fatal-error drop-in locates and calls the Engine's restore routine
  safely before WordPress has loaded.
- Phase 4: frozen per-mode toolsets versus deferred tool loading, by measurement.
- Phase 6: the blueprint step subset, the compaction mechanism, whether the agent may
  propose a plan, and the manual form that collects database credentials for a new site.
- Phase 7: request-rate limiting for healthy remote clients, if it turns out to be needed.
- Phase 8: the Hub's stack below "TypeScript, Docker image, one organisation, SQLite by
  default", its MCP surface, and how `packages/ui` is shared with `frontend/`.
