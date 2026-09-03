# WP Sell Services — Plugin audit criteria

**Plugin-level**, not site-level. Judge the product as a stranger installing it on any WordPress site.

Last frozen: 2026-09-03 for the 1.7.1 full-catalog pass.

## Three pillars

| Pillar | Pass when | Fail when |
|--------|-----------|-----------|
| **Stable** | One authority for status/money/permissions; idempotent webhooks; upgrade does not rewrite money; zero fatals/notices on happy path | Dual stores disagree; replay mutates; silent success; PHP noise on happy path |
| **Plug-and-play** | Activate → wizard → pages → one gateway → first sale without spelunking; honest defaults and Free/Pro copy | Setup lies; cart badge disagrees with cart; CLI can wipe production; uninstall leaves junk |
| **Developer-friendly** | One seam for hooks/REST/templates; docs match source; OpenAPI honest; Pro extends Free | Phantom hooks; Pro routes in Free OpenAPI; duplicate “is vendor?”; settings saved but never read |

## Level 1 — Code flow (required)

1. Is this key **written**?
2. Is this key **read**?
3. Is the answer in **more than one place**?
4. Does the setting **reach the thing it names**?

Toggle test: off → do the thing → confirm the **side effect** is gone.

## Level 2 — Browser / HTTP (required)

1. Do two numbers about the same thing **agree**?
2. Would a stranger know **what to do next**?
3. Does the action show up **where they look next**?
4. REST over real HTTP, not only `rest_do_request()`.

## Scores

- **Good** — both levels pass; no dual store; docs match
- **Acceptable** — works; debt documented; not release-blocking
- **Bad** — wrong money, wrong permission, data loss, or broken first-run
- **Improve** — works; friction, copy, DX, or missing seam
- **Skip** — out of reach on this install (missing plugin/keys); must say why

## Groups

| ID | Surface |
|----|---------|
| G0 | Install / upgrade / uninstall |
| G1 | Owner setup |
| G2 | Catalog & discovery |
| G3 | Vendor lifecycle |
| G4 | Buy / checkout / rails (Standalone, Woo, EDD, FluentCart) |
| G5 | Order machine |
| G6 | Money & trust |
| G7 | Requests & proposals |
| G8 | Developer surface |
| PX | Pro overlays (license, Connect, storage, Razorpay, analytics, …) |

## Rails

Test Standalone and WooCommerce always. EDD and FluentCart are **in scope** for this pass (enable with the beta filter when needed). Restore `ecommerce_platform` to `standalone` after each rail change.

## Roles

Buyer = ownership checks only (no WPSS caps). Vendor = profile row + `wpss_vendor_orders`. Admin = `wpss_manage_orders` / manage_options.
