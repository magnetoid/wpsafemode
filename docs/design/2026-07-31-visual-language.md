# WP SafeMode — Visual Language

**Date:** 2026-07-31
**Source:** "WP SafeMode UI Mockups" (`claude.ai/code/artifact/948d4aa6-7131-4d0a-8668-af42b72f4dd8`), round 1
**Status:** Extracted from the mockups, not yet reconciled with `docs/superpowers/specs/2026-07-24-wp-safe-mode-design.md` — see §5.

The design spec covers architecture and says nothing about how the product should
look. This fills that gap. Values below are lifted from the mockups directly, not
invented.

---

## 1. Palette

Warm cream and deep green, not the usual dashboard blue-grey. The effect is calm
and editorial rather than alarming — which is the right instinct for a tool
someone opens when their site is already down.

| Token | Hex | Use |
|---|---|---|
| `ink` | `#17252A` | Primary text, headings. The dominant colour by a wide margin. |
| `ink-muted` | `#5C6B6A` | Secondary text |
| `ink-faint` | `#8A9490` | Tertiary text, metadata, disabled |
| `surface` | `#FAF8F3` | Lightest surface — cards |
| `surface-sunken` | `#F4F1EA` | Page background |
| `surface-raised` | `#ECE8DD` | Panels, inset areas |
| `border` | `#E5E0D5` | Dividers, card edges |
| `border-strong` | `#CFC9BA` | Emphasised borders |
| `green` | `#0E7A5C` | Primary action, brand |
| `green-hover` | `#0E6B4F` | Hover |
| `green-active` | `#0B644C` | Pressed |
| `green-light` | `#178A5E` | Lighter fill |
| `green-accent` | `#37C08F` | Success indicator |
| `green-soft` | `#7BD4B4` | Success tint |
| `red` | `#D64541` | Danger, "site down" |
| `red-deep` | `#A63528` | Danger text on light |
| `amber` | `#C98A1B` | Warning, "updates waiting" |
| `amber-deep` | `#8F5F0D` | Warning text on light |

## 2. Typography

| Family | Role |
|---|---|
| **Bricolage Grotesque** | Display — page titles, big numbers |
| **Schibsted Grotesk** | Body and UI. The workhorse. |
| **JetBrains Mono** | Code, log output, diffs, file paths |

All three ship as woff2 in the mockup bundle. For a product distributed as a ZIP
to a broken site, self-hosting them is mandatory — there is no CDN budget on a
site that is down, and no external requests should be required to render the
rescue UI.

## 3. Shape and depth

```
radius   7px  9px  10px  14px  16px   999px (pills)   50% (avatars, dots)
```

`10px` and `14px` dominate; `999px` for status pills and badges.

Shadows are low and wide, tinted with the ink colour rather than pure black:

```css
box-shadow: 0 1px 2px rgba(0,0,0,.2);              /* hairline lift */
box-shadow: 0 10px 24px -14px rgba(23,37,42,.25);  /* card */
box-shadow: 0 14px 30px -18px rgba(23,37,42,.25);  /* raised card */
box-shadow: 0 8px 20px -12px rgba(14,122,92,.4);   /* primary button */
box-shadow: 0 8px 20px -12px rgba(214,69,65,.35);  /* danger button */
```

## 4. Voice

The strongest signal in the mockups, and the hardest thing to retrofit later.
Copy is plain, calm, and written for a worried human rather than an engineer:

- *"What happened — in plain English"*
- *"We're doing a bit of quick maintenance. Your cart is saved."*
- *"Safe mode is ON — the site is up, with 2 plugins paused"*
- *"WooCommerce 9.4.1 crash — auto-fixed by rollback in 6 m 14 s"*
- *"No visible change on the site"*

Rules this implies:

1. **Lead with the consequence, not the mechanism.** "The site is up, with 2
   plugins paused" — not "2 plugins deactivated via option filter".
2. **Always say what visitors currently see.** The mockups give this its own
   panel. Someone in a rescue does not know whether their customers are seeing a
   white screen right now, and that is the first thing they want to know.
3. **State outcomes in wall-clock time.** "6 m 14 s", "4 minutes ago", "auto-exits
   after…".
4. **Every paused thing carries a reason.** `PAUSED — suspected cause` versus
   `PAUSED — unused` versus `RUNNING — verified stable in safe mode`. A list of
   disabled plugins with no rationale is not actionable.
5. **Name the escape hatch.** "Start guided exit", "Test un-pause", "Exit safe
   mode" are always visible.

## 5. Unresolved: this depicts a different product

The mockups are not a skin for the specced product. They depict a hosted,
multi-site, agency-facing service:

| Mockup shows | Spec §1–§2 says |
|---|---|
| "Good morning, Maya", seven client sites | Single site, sitting next to one WordPress install |
| Connect by hosting-provider sign-in (Kinsta, WP Engine, SiteGround) | Upload a ZIP by FTP |
| Uptime monitoring, "went down 4 minutes ago" | Nothing runs in the background |
| Escalation chains, quiet hours, webhooks, on-call | No cloud, no account, no external control plane |
| Monthly client reports, PDF export, email to client | Out of scope for v1 |
| "WordPress plugin — PARTIAL PROTECTION" as one connection option | Tier W is probe-only in v1 |

Two observations worth carrying forward regardless of how that is resolved:

- The mockups already treat a **WordPress plugin as one connection method among
  several, explicitly weaker than the others.** That matches the "app plus
  connector" direction and independently reaches the same conclusion about the
  plugin's limits.
- Everything in §1–§4 above is architecture-independent. The palette, type,
  shape and voice apply whether this ships as a ZIP or a service.
