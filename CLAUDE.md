# CLAUDE.md — WP Sell Services

> **READ FIRST (v1.3.1):** [`audit/manifest.json`](audit/manifest.json) is the canonical inventory — 142 REST endpoints (23 controllers + 7 generic in API.php + 1 in Tour.php), 111 AJAX handlers, 18 custom DB tables, 6 Gutenberg blocks, 19 shortcodes, 5 WP-CLI commands, 4 cron jobs, 237 hook firings, 25 admin pages. Use this before grepping. See also [`audit/FEATURE_AUDIT.md`](audit/FEATURE_AUDIT.md), [`audit/CODE_FLOWS.md`](audit/CODE_FLOWS.md), [`audit/ROLE_MATRIX.md`](audit/ROLE_MATRIX.md), [`audit/wppqa-baseline-2026-06-06/SUMMARY.md`](audit/wppqa-baseline-2026-06-06/SUMMARY.md). Refresh via `/wp-plugin-onboard --refresh` after non-trivial changes. **manifest_refresh: agent-enumeration-only** — REST is registered through a controller-array wrapper (`src/API/API.php`), so the deterministic generator (`write-manifest.mjs`) undercounts REST (142 → 7) and will silently clobber this manifest. Do NOT run the generator against this plugin; refresh categories by agent enumeration only.

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**WP Sell Services** is a Fiverr-style service marketplace platform for WordPress with optional Upwork-style milestone contracts on buyer-request projects.

- **Free Version**: Full standalone marketplace with built-in checkout, offline payments, milestone contracts, paid extensions, tipping, and vendor intro videos.
- **Pro Version**: WooCommerce, EDD, FluentCart, SureCart integrations, Stripe/PayPal/Razorpay payments, advanced analytics, wallet integrations, earnings ledger + CSV export.

## Recent Changes

| Date | Version | Summary |
|---|---|---|
| 2026-08-01 | **1.3.1** | **Payment ownership, minor units, and API contract.** (1) **One rail owns payment**: when WooCommerce/EDD/FluentCart/SureCart is enabled it owns ALL payment - our gateways and the `/payments/*` routes register only on the standalone rail (`wpss_uses_standalone_payments()`, `src/API/PaymentController.php:55`). Switching rails never rewrites past orders and gateway webhooks keep working. (2) **New pay-order seam** `wpss_get_pay_order_url()` + filter `wpss_pay_order_url` (`src/functions.php:3375/3391`) - the single source for "pay THIS order" (tips, milestone phases, extensions, accepted proposals, emails). Pro's `WCPayOrderResolver` creates/reuses a real WC order and returns its native order-pay URL; **EDD/FluentCart/SureCart have no pay-order rail** and fall back to the standalone `?pay_order=N` dead end. Woo refunds now reverse tips/milestones/extensions (shared sub-order resolver via `_wpss_pay_order_id`). (3) **Minor-unit integers** added beside every money float across both plugins. (4) Notification bodies are plain text; HTML is composed only for email. (5) New/changed REST: `GET /orders/{id}/timeline`, `GET /auth/devices`, `POST /reviews` alias, `GET /portfolio`, `GET /media`; **removed** the dead `accept`/`reject` order verbs (and their `wpss_order_accepted`/`wpss_order_rejected`/`wpss_order_delivered` hooks); `deliver` now routes through `DeliveryService`. (6) **REST auth answers 401 vs 403 correctly** - `wpss_rest_require_login()`/`require_admin()` in `functions.php`, one `wpss_not_vendor` code. (7) i18n build generates .mo/.json catalogs with a CI gate. |
| 2026-07-28 | 1.3.0 | Release: stability + polish. Payments hardened across every gateway, full dark mode on all frontend surfaces, lighter first-run setup, CheckoutIntent seam, base-currency authority with a display-currency hint, role-based menu visibility, frontend dispute messaging. Settings IA regrouped into SETUP / MONEY / MARKETPLACE / SYSTEM - the old `Payments` tab split into **Payment Gateways** + **Payouts**, `Vendor` became **Vendors**, `Orders` became **Orders & Disputes**. Docs tree rewritten and gated by `bin/docs-audit.py`. |
| 2026-07-23 | 1.3.0 | Manual payout rail complete (MONEY-FLOW-PLAN T1+T2). New money authority `EarningsService::mark_paid()` (manifest → `money_authorities.payout_terminal`): row-locked (`FOR UPDATE`), terminal-guarded, status flip + wallet-ledger debit in ONE transaction via extracted `insert_withdrawal_debit()` core; idempotent (twice = one debit). `process_withdrawal('completed')` routes through it; approve/reject row-locked; REST `PUT /withdrawals/{id}` delegates to the service (its duplicate inline UPDATE — no notification, no ledger guarantee — removed; replay returns 400 `wpss_already_finalised`). Withdrawals screen: Mark-paid on pending+approved rows, method filter, filtered empty state, `wpssConfirm` bulk (native `confirm()` removed), inline CSS/JS migrated to `admin.css`/`admin-withdrawals.js`, enqueue keyed off the real hook suffix (old hardcoded check never matched — dead code). T2: `export_csv()` admin-post CSV of the current filter (keyset-batched, bank/PayPal bulk-upload columns, **never mutates status** per MONEY-FLOW.md 2.4). Schema 1.5.1: `idx_status_created` + `idx_method` on `wpss_withdrawals`. Browser-verified end-to-end at 2003 rows + 390px; withdrawals #10-#12 / ledger #133-#135 kept as local reference artefacts. |
| 2026-06-12 | 1.2.0 | Contract-audit sweep (Basecamp #9985173772/#9985173873/#9985174247/#9985174335/#9985175442/#9985174862/#9985173976/#9985174504/#9985175367/#9985175023): Connect fee + seller-level commission read real stores (money paths); `wpss_vendor_profile_saved` fired on both profile save paths (Pro PayPal email persists); all vendor surfaces (SEO schema/REST/SellerCard/templates) read the profile table via `wpss_get_vendor()` + new `wpss_get_vendor_last_delivery()`; gateway secrets masked via `wpss_render_secret_field` with keep-on-empty; review moderation enableable (`wpss_vendor[moderate_reviews]` + checkbox); wallet provider on one key + Payouts select; 14 tuning options got settings fields (new Dispute Settings section, page mappings); moderation/auto-approve filter bridges live. Contract audit: 0 errors (was 53). Baseline at `.contract-audit-baseline.json`. |
| 2026-06-11 | 1.2.0 | Profile-table read migration (Basecamp #9985174504): every vendor read surface now resolves from the canonical `wpss_vendor_profiles` table via `wpss_get_vendor()` instead of dead legacy user-meta — Person schema (SchemaMarkup + ServiceSchemaPiece) emits jobTitle/aggregateRating, public vendor REST payload + vendor search return tagline/bio/country/social_links/is_verified/completed_orders from the table (field names unchanged), SellerCard block + vendor-card/content-service-card partials + single-request proposals render real values. New shared accessor `wpss_get_vendor_last_delivery()` (orders MAX(completed_at), tips excluded) backs all Last Delivery displays. Dead reads removed: `_wpss_highlights` block, `_wpss_max_quantity` meta (filter-only). `GET /vendors/{id}` guard fixed to `wpss_is_vendor()` (role-granted vendors 404ed). Contract-audit errors 34→18. |
| 2026-06-11 | 1.2.0 | Second bug sweep (Basecamp #9983528280/#9983528063/#9983538201/#9983472211/#9983376083): vacation-mode persistence (self-healing `run_column_migrations()` now covers `vacation_mode`+`vacation_message`; REST profile saves return 500 `wpss_profile_update_failed` on DB failure instead of fake 200); email preferences now genuinely gate notification emails (`NotificationService` reads `wpss_email_preferences` via a new type→category map mirroring `EmailService::get_user_pref_category()`); email-prefs AJAX verifies persistence by read-back; `wpss_service_meta_fields` applied in the ACTIVE metabox pipeline with a form-aware kses allow-list (`wp_kses_post` was stripping Pro's recurring inputs); single-service sticky sidebar moved to native CSS sticky (lifted `contain:layout` on single-service via :has(), viewport-capped internal scroll, removed the fighting JS `initStickyPackages`). Browser-verified 1280px+390px. |
| 2026-06-11 | 1.2.0 | Bug-fix sweep (Basecamp #9983214498/#9983236795/#9983295694/#9983295895/#9983153841): new dual-meta fallback helpers `wpss_get_service_delivery_days()` + `wpss_get_service_revisions()` in `src/functions.php`, ALL write paths (wizard/metabox/REST/save_post/CLI/seeder) now sync both delivery + revision meta keys; admin Process Refund button wired to `wpss_admin_update_order_status` AJAX via `wpssConfirm` and new `wpss_order_status_refunded` → `OrderWorkflowManager::handle_order_refunded()` (reuses idempotent `attempt_payment_refund()`); Stripe Connect refunds send explicit `reverse_transfer=true`/`refund_application_fee=false` via testable `StripeGateway::build_refund_args()` + `wpss_stripe_refund_args` filter; notification emails route through `wpss_email_header_vars` (white-label branding applies); dashboard footer "Powered by" credit behind new `wpss_show_powered_by` filter. See `~/autovap-agent/handoffs/wp-sell-services/KNOWN-GAPS.md`. |
| 2026-06-10 | 1.2.0 | Add-ons admin-metabox residual fix (Basecamp #9983237242): both ServiceMetabox add-on render sites (live `render_addons_content` panel + legacy `render_addons_metabox`) now read via `wpss_get_service_extras()` through new private `get_addons_for_editor()`, which maps the wizard's `extra_days` (and legacy `delivery_time`) onto the metabox's `delivery_days_extra` using the same coalesce as `wpss_resolve_checkout_addons()`. Metabox save now deletes `_wpss_extras` so admin rows win in the helper afterwards — gated by a new `wpss_addons_present` sentinel hidden field (same pattern as `wpss_gallery_present`) so non-metabox save paths can never wipe wizard add-ons; sentinel-present-with-zero-rows clears both keys (admin removed all add-ons). 9-state WP-CLI smoke passing. |
| 2026-06-10 | 1.2.0 | Realtime (WebSocket) layer, disabled by default. `Services\RealtimeService` speaks the Pusher protocol so one driver covers Pusher.com AND self-hosted Pusher-compatible servers (Soketi) via a custom host; secret never leaves the server (`get_client_config()` is non-sensitive only). New `POST /wpss/v1/realtime/auth` signs private-channel subscriptions after ownership checks (`private-wpss-user-{id}` own-user only; `private-wpss-order-{id}` customer/vendor/admin). `Services\RealtimeBridge` relays `wpss_message_sent` (order channel + recipient user channel, `message.created`) and `wpss_notification_created` (`notification.created`). Settings card under Advanced (option `wpss_realtime_settings`, masked secret field). Frontend `wpss-realtime.js` + vendored pusher-js 8.4.0 enqueued only when enabled + logged in; dispatches `wpss:realtime:message` / `wpss:realtime:notification` CustomEvents and bumps `[data-wpss-notification-count]` badges. Filter: `wpss_realtime_settings`. |
| 2026-06-10 | 1.2.0 | First-view polish (3 fixes, browser-verified): (1) plugin pages (dashboard/cart/checkout/become-vendor) render full-width with dedicated theme support — Reign via its native `reign_wbcom_metabox_data` layout meta (same pattern as Reign's FluentCart integration), BuddyX/BuddyX Pro via their `page-templates/full-width-container.php`, all other themes via new `templates/wpss-fullwidth-template.php`; opt-out filter `wpss_use_fullwidth_template`, page-key filter `wpss_fullwidth_page_keys`. (2) Third-party admin notices suppressed on WPSS admin screens (`Admin::hide_third_party_notices`, `in_admin_header:1`, own/Pro notices kept). (3) Role-aware dashboard landing: active vendors land on `sales`, buyers on `orders` (`wpss_dashboard_default_section` filter). |
| 2026-06-10 | 1.2.0 | Add-ons meta-key fix (Basecamp #9768866690): new shared `wpss_get_service_extras()` helper in `functions.php` resolves `_wpss_extras` with `_wpss_addons` fallback; all 5 free read sites (order modal, `add_service_to_cart`, standalone checkout, gateway addon resolver, wizard edit form) + 2 Pro WC sites now use it, so admin/CLI-seeded add-ons appear in cart and checkout. Browser-verified end-to-end. |
| 2026-06-09 | 1.2.0 | Unified payouts + release prep. Earnings section renamed "Earnings & Payouts" with extension hooks (`wpss_payout_methods`, `wpss_earnings_ledger_actions`) so Pro injects payout rails + ledger CSV into the one Free section. Wallet ledger entries now link to the related order/tip (`reference_url` on `GET /wallet/transactions`). New shared `wpssConfirm`/`wpssToast` design-system dialog (`assets/js/wpss-ui.js`) replacing native pop-ups + the duplicate `wpssAdminConfirm`/`admin-toast.js`. Fixes: dashboard section-header layout (primary action + replay-tour grouped right), cross-tab payout-banner leak removed. Basecamp cards BC1–BC6 resolved/verified. changelog extended; `composer phpcs` 0 errors. |
| 2026-06-08 | 1.2.0 | Final stretch: all 7 AJAX→REST known gaps (KG-1..7) migrated + browser-verified, none deferred. New REST routes `GET /services/grid`, `POST /orders/{id}/conversation/messages`; new shared renderers/helpers in `functions.php` (`wpss_render_services_grid`, `wpss_render_message_row`, `wpss_handle_message_attachments`, `wpss_normalize_uploaded_files`, `wpss_build_vendor_profile_update`). Fixed 2 real bugs (REST-context `wpss_pagination` fatal; vendor guard reading raw `_wpss_is_vendor` meta vs `wpss_is_vendor()`). Vendor profile now single canonical store. Dead `conversation.js` removed. readme stable tag → 1.2.0. `composer phpcs` exits 0. **Run `/wp-plugin-onboard --refresh` to regenerate manifest before tagging.** |
| 2026-06-06 | 1.2.0-dev | Branch `1.2.0` opened for full audit + hardening sprint (AutoVAP v2). Manifest refreshed; wppqa baseline re-run with zero drift vs 2026-05-08. |
| 2026-05-25 | 1.1.1 | Customer-facing changelog rewrite, tighter dist exclusions, Composer autoloader guard in main file (admin notice instead of fatal on corrupt build). |
| 2026-04-23 | 1.1.0 | Admin UX + ops polish ready for release: unified listing UX across Vendors/Withdrawals/Moderation (shared `wpss-listing-page` + responsive stats grid); guided onboarding tours on both the admin dashboard and the `[wpss_dashboard]` shortcode (Shepherd.js, role-aware, per-user completion persisted via REST); all recurring jobs migrated from WP-Cron to Action Scheduler with a `Services\Scheduler` facade, upgrade-path legacy cron sweep, and `wpss` / `wpss-pro` group convention; `Services\Icon` helper for Lucide placeholders; Orders + Disputes admin wrap in `.wpss-list-card` with designed empty-states; SchemaManager drives off a single `CORE_TABLES` constant. |
| 2026-04-21 | 1.1.0 | Upwork-style milestone contracts (lock-step phase payments, auto-complete parent on final approval, cascade-cancel pending phases), paid extensions (catalog-order add-ons with deadline push), vendor intro video on profile, NET earnings ledger with period selector and CSV export, money-flow integrity fixes (tip idempotency, deferred-hook transaction, rate-limit scoping, mark_as_paid sub-order skip, Pro double-credit guard). New sub-order pattern documented in `docs/architecture/SUB_ORDER_PATTERN.md`. 7 new email templates (4 milestone + 3 extension). |
| 2026-04-02 | 1.0.0 | Initial release — marketplace core, 11-status order lifecycle, standalone checkout + offline gateway, Stripe/PayPal gateways, 26 email notification types, 21 REST controllers, 6 Gutenberg blocks, 19 shortcodes, seller levels, disputes, reviews, commissions, withdrawals. |

## Key Features (current: 1.3.1)

Marketplace fundamentals (from 1.0.0): service wizard, tiered packages, buyer requests, proposals, order workflow with 11 statuses, requirements collection, deliveries + revisions, reviews, disputes, commissions, withdrawals, seller levels, vacation mode, 26 email notifications.

Added in 1.3.1:

- **One payment owner.** A cart plugin, when enabled, owns ALL payment; our gateways and `/payments/*` register only on the standalone rail. Rail switches never rewrite past orders.
- **`wpss_pay_order_url` seam** — the single way to send a buyer to pay one existing order. WooCommerce implements it (`WCPayOrderResolver`, creates/reuses a real WC order); EDD/FluentCart/SureCart do not, so milestone/tip/extension pay links are a dead end there. Known gap, documented.
- **Minor-unit integers** beside every money float, both plugins.
- **REST contract**: `GET /orders/{id}/timeline`, `GET /auth/devices`, `POST /reviews`, `GET /portfolio`, `GET /media`; dead `accept`/`reject` order verbs removed; `deliver` routed through `DeliveryService`; 401 vs 403 answered correctly with one `wpss_not_vendor` code.
- **Woo refunds reverse sub-orders** (tips, milestone phases, extensions).
- **Plain-text notification bodies**, HTML composed only for email.
- **i18n**: .mo/.json catalogs generated in the build, with a CI gate.

Added in 1.3.0:

- Settings IA regrouped (SETUP / MONEY / MARKETPLACE / SYSTEM). `Payments` split into **Payment Gateways** + **Payouts**; `Vendor` → **Vendors**; `Orders` → **Orders & Disputes**. Hash-routed (`#payouts`), no `?tab=`.
- Full dark mode on every frontend surface, following the host theme's toggle.
- CheckoutIntent seam; base currency authoritative with a display-currency hint.
- Role-based menu visibility; frontend dispute messaging.
- Manual payout rail complete: `EarningsService::mark_paid()` (row-locked, idempotent, ledger debit in one transaction) + filtered CSV export that never mutates status.

Added in 1.1.0:

- **Milestone contracts** on buyer-request orders — vendor picks contract type at proposal time; milestone plans are pre-created on acceptance; lock-step payment (phase N only payable once phase N-1 is approved); auto-complete parent when every phase is terminal; cascade-cancel unpaid phases on parent cancellation; paid-but-open phases route through dispute on parent cancel.
- **Paid extensions** on catalog orders only — vendor quotes extra work + days; buyer accepts or declines; commission split at payment; parent deadline pushes out by the quoted days.
- **Mutual exclusion** — a single order surfaces milestones OR extensions, never both (guarded server-side: `MilestoneService::propose()` refuses non-request orders; `ExtensionOrderService::create_extension_request()` refuses request orders).
- **Vendor intro video** — short MP4/YouTube on the public profile, with matching Introduction section on the vendor's profile edit screen.
- **Earnings ledger + CSV export** (Pro) — wallet page surfaces a dated ledger with period selector (30 days / this month / last month / this year / all time) and a CSV export carrying the same rows + a summary block.
- **Sub-order pattern** generalised across tips / extensions / milestones (shared `platform` marker, shared `wpss_order_paid` credit handler, shared abandon-cron contract with carve-out for contract milestones). See `docs/architecture/SUB_ORDER_PATTERN.md`.
- **Money-flow integrity fixes** — tip idempotency key migration to sub-order ID, deferred-hook transaction in `BuyerRequestService::convert_to_order`, rate-limit allow-list so milestone/extension/tip emails are never silently dropped, `mark_as_paid` skips the pending_requirements transition on sub-orders, Pro double-credit guard.
- **Unified admin listing UX** — Vendors, Withdrawals, and Moderation pages share the same wrapper (`wpss-listing-page`), H1 treatment (`wp-heading-inline`), stats strip (`.wpss-listing-stats` with `auto-fit minmax(150px, 1fr)` grid), and filter-row card. Status-color palette is centralised. Moderation gains its 4-card stats strip for parity.
- **Guided onboarding tour** (admin + frontend) — Shepherd.js + Lucide. Admin tour is 8 steps on the WPSS Dashboard; frontend tour is role-aware on `[wpss_dashboard]` (sellers see 9 steps covering Services/Sales/Earnings; buyers see 7 with a "Start Selling" CTA highlight). Completion persisted per-user via `wpss_tour_completed` meta + `POST /wpss/v1/tour/complete`. "Replay tour" triggers on both dashboards. Extension point: `wpss_tour_steps` filter. See `src/Frontend/Tour.php`.
- **Action Scheduler migration** — every recurring job (order lifecycle sweeps, dispute deadlines, audit-log retention, sub-order cleanups, auto-withdrawal, vendor-stat refresh, seller-level recalc) runs on AS. Facade at `Services\Scheduler` (`schedule_recurring`, `has_pending`, `unschedule_all_for_group`, `on_ready`). Every free-plugin hook uses group `wpss`; Pro uses `wpss-pro`. Upgrade-path `clear_legacy_wpcron_hooks()` runs once per site on version bump. `composer.json` pins `woocommerce/action-scheduler ^3.8`.
- **Empty-state pattern** — shared `.wpss-empty-state` BEM block (admin + frontend). Lucide icons via `Services\Icon::render()`. Orders + Disputes admin wrap their WP_List_Tables in `.wpss-list-card`; when empty they render the designed card instead of a bare sentence. Same treatment on the `[wpss_dashboard]` buyer-orders tab and the vendor-profile services section.

## Dependency & Release Rule — Ship Everything, Never Require `composer install`

**Everything required to USE the product ships in BOTH the git repo and the distributed zip.** No developer and no customer should ever have to run `composer install` to make the plugin work. QA testers and customers must never get stuck on a missing autoloader or missing class.

- **Runtime `vendor/` is committed and shipped.** Only true dev-only tooling (phpunit, phpstan, wpcs, polyfills) may be excluded — and excluding it must NEVER delete `vendor/autoload.php` or any class loaded at runtime.
- **Verify before any release/commit that touches `vendor/`, `.distignore`, or autoloading:** a fresh clone AND the dist zip must both load with zero `composer install`. (Regression to avoid: an "untrack dev vendor files" change once removed the autoloader and white-screened the site until `composer install` was re-run.)
- **Pro reuses Free's libraries.** Free is a hard dependency of Pro, so any library already shipped in Free is reused by Pro via Free's autoloader — Pro must not bundle or load its own second copy. See `wp-sell-services-pro/CLAUDE.md`.

> The `composer install` below is a **developer convenience for the dev toolchain only** — it is NOT a prerequisite for the plugin to run.

## Build & Development Commands

```bash
# Install PHP dependencies
composer install

# Install JS dependencies
npm install

# Run WPCS linting
composer phpcs

# Fix WPCS issues automatically
composer phpcbf

# Watch for CSS/JS changes during development
npm run dev

# Build assets for production
npm run build

# Run all linters
npm run lint
```

## Architecture

### Namespace Structure
```php
WPSellServices\               # Root namespace
WPSellServices\Core\          # Plugin bootstrap, activation
WPSellServices\Models\        # Data models (Service, Order, etc.)
WPSellServices\Services\      # Business logic services
WPSellServices\Integrations\  # E-commerce adapters
WPSellServices\Admin\         # Admin functionality
WPSellServices\Frontend\      # Frontend functionality
WPSellServices\API\           # REST API endpoints
```

### Directory Structure
```
wp-sell-services/
├── src/                    # PHP source (PSR-4 autoloaded)
│   ├── Core/               # Plugin core classes
│   ├── Models/             # Data models
│   ├── Services/           # Business logic
│   ├── Integrations/       # E-commerce adapters
│   │   ├── Standalone/     # Built-in standalone checkout (free)
│   │   └── Gateways/       # Payment gateways (OfflineGateway)
│   ├── Admin/              # Admin classes
│   ├── Frontend/           # Frontend classes
│   ├── API/                # REST API
│   ├── CLI/                # WP-CLI commands
│   └── Blocks/             # Gutenberg blocks
├── assets/                 # CSS, JS, images
├── templates/              # PHP templates (overridable)
├── languages/              # Translation files
└── docs/                   # Documentation
```

## Browser audits / QA browser walks

**Always use the Sonnet model for browser-driven audits and persona-based QA walks** (e.g. the `1.1.0-COMPLETENESS-AUDIT.md` 25-feature × 5-persona matrix, ad-hoc dashboard regression sweeps, any task that involves driving Playwright through many flows in sequence).

Why: browser-walk work is high-volume, highly parallel, and rarely needs deep reasoning per step. Sonnet is the right cost/throughput trade-off for this kind of repetitive UI verification.

When to override (use Opus): if an audit step turns into a deep debugging session (e.g. tracing a money-flow bug through multiple services), escalate that specific subtask to Opus, then return to Sonnet for the rest of the matrix.

## Coding Standards

This project follows **WordPress Coding Standards (WPCS)** strictly.

### Key Rules
- PHP 8.1+ features allowed (typed properties, enums, etc.)
- PSR-4 autoloading with namespaces
- WordPress hooks and filters for extensibility
- All strings must use `wp-sell-services` text domain
- Global functions/classes prefixed with `wpss_` or `WPSellServices`

### WPCS Exceptions
- Short array syntax `[]` allowed (not `array()`)
- PSR-4 file naming for classes (not hyphenated-lowercase)

## Key Hooks for Pro Extension

```php
// Main plugin loaded - extend here
do_action('wpss_loaded', $plugin);

// Register additional e-commerce adapters
apply_filters('wpss_ecommerce_adapters', $adapters);

// Register payment gateways (standalone mode)
apply_filters('wpss_payment_gateways', $gateways);

// Register storage providers
apply_filters('wpss_storage_providers', $providers);

// Extend analytics dashboard
apply_filters('wpss_analytics_widgets', $widgets);
```

## Database Tables

| Table | Purpose |
|-------|---------|
| `{prefix}wpss_orders` | Service orders |
| `{prefix}wpss_conversations` | Order messages |
| `{prefix}wpss_deliveries` | Final deliveries |
| `{prefix}wpss_reviews` | Ratings & reviews |
| `{prefix}wpss_disputes` | Dispute cases |
| `{prefix}wpss_service_packages` | Service pricing tiers |

## Custom Post Types

| CPT | Slug | Purpose |
|-----|------|---------|
| Service | `wpss_service` | Service offerings |
| Buyer Request | `wpss_request` | Buyer job posts |

## Important Patterns

### ONE FLOW, ONE IMPLEMENTATION (non-negotiable)

The single biggest source of customer-facing bugs in this plugin has been the
**same flow implemented in more than one place**, with the copies drifting apart.
Real cases found and fixed in 1.2.2:

| Flow | Copies | What customers hit |
|---|---|---|
| Stripe checkout | 2 (`assets/js/stripe.js` + inline JS in `StandaloneCheckoutProvider`) | **Checkout could not complete at all** — both bound to the same form, the inline one won the race and posted an unconfirmed PaymentIntent, so the card was never charged |
| Commission fee math | 6 sites | Wallet ledger and Stripe Connect split paid **different numbers** |
| Notifications surface | 3 (dashboard / standalone account / myaccount) | Two rendered nothing or had no mark-read |
| Featured services | 2 meta keys (`_wpss_featured` vs `_wpss_is_featured`) | Shortcode returned **no** featured services |
| Archive search params | 2 conventions (`wpss_search` vs `search`) | Search + category filters silently did nothing |

**Rules — apply to every change:**

1. **Before writing a flow, grep for an existing one.** If any code already does
   this job (a service, repository, template partial, or JS module), extend or
   call it. Never fork a second copy "just for this surface."
2. **Money math lives in exactly one place.** All fee/earnings computation goes
   through `CommissionService::compute_breakdown()`. Gateways are execution
   adapters: they move the amount we already persisted, they never re-derive it.
3. **A shared UI surface is a shared partial.** If two locations show the same
   thing (notifications, service cards, order rows), extract
   `templates/partials/*.php` and have both `require` it — see
   `templates/partials/notifications-list.php`.
4. **One writer, one reader, one key.** A meta/option/query-arg key must be
   written and read by the same name everywhere. Grep for near-identical
   siblings (`_wpss_featured` vs `_wpss_is_featured`) before inventing a key.
5. **Only one JS handler per form/element.** If a gateway or component owns
   submission, it declares `data-wpss-own-submit` and generic handlers stand
   down — never let two listeners race on the same submit.
6. **Editing `assets/js/*.js`? Rebuild the `.min`.** `Assets.php` rewrites asset
   URLs to `.min` when present, so source edits are **inert** until rebuilt.
   `npm run build:min` needs node_modules; otherwise:
   `npx terser assets/js/FILE.js -c -m -o assets/js/FILE.min.js`

Standing audit: `audit/DUPLICATE-FLOWS-money.md` and `audit/DUPLICATE-FLOWS-ui.md`
inventory known duplications. Re-run that sweep before any major release.

### Adding a New Integration
1. Create class in `src/Integrations/{Platform}/`
2. Implement `EcommerceAdapterInterface`
3. Register via `wpss_ecommerce_adapters` filter

### Adding a Service
Use the `WPSellServices\Services\ServiceManager` class, not direct DB queries.

### Template Override
Templates can be overridden in theme: `theme/wp-sell-services/{template}.php`

## REST API Development Rules

**Every new feature MUST include REST API endpoints.** This plugin is mobile-app ready.

### Checklist for New Features
1. Service class method created
2. REST API controller endpoint added in `src/API/`
3. Permission callback defined (use base class methods)
4. Request validation/sanitization in route args
5. Controller registered in `API.php` controllers array

### Pattern to Follow
- Create controller in `src/API/` extending `RestController`
- Register routes in `register_routes()` method
- Use `check_permissions()`, `check_admin_permissions()` from base
- Use `paginated_response()` for list endpoints
- Use `get_pagination_args()` for page/per_page handling

### No New AJAX Endpoints
All new features must be REST-first. Existing AJAX handlers remain for backward compatibility but new features should NOT add `wp_ajax_*` handlers.

### Batch Endpoint
`POST /wpss/v1/batch` supports up to 25 sub-requests in a single HTTP call for mobile efficiency.

### REST API Controllers

| Controller | Base | Endpoints |
|-----------|------|-----------|
| ServicesController | /services | CRUD, search, featured |
| OrdersController | /orders | CRUD, status transitions |
| ReviewsController | /reviews | CRUD, vendor reviews |
| VendorsController | /vendors | List, profile, stats |
| ConversationsController | /conversations | Messages, attachments |
| DisputesController | /disputes | Create, respond, resolve |
| BuyerRequestsController | /buyer-requests | CRUD, proposals |
| ProposalsController | /proposals | CRUD, accept/reject |
| NotificationsController | /notifications | List, read, delete |
| PortfolioController | /portfolio | CRUD, reorder |
| EarningsController | /earnings | Summary, history, withdrawals |
| ExtensionRequestsController | /extensions | Create, approve, reject |
| MilestonesController | /milestones | CRUD, submit, approve |
| TippingController | /tips | Send, list |
| SellerLevelsController | /seller-levels | Definitions, progress |
| ModerationController | /moderation | Approve, reject, history |
| FavoritesController | /favorites | Add, remove, list |
| MediaController | /media | Upload, info, delete |
| CartController | /cart | Add, get, remove, checkout |
| AuthController | /auth | Login, register, logout, devices |
| PaymentController | /payment | Gateways, process, status, webhook |

### Generic Endpoints (in API.php)
- `GET /categories` - Service categories
- `GET /tags` - Service tags
- `GET /settings` - Public settings
- `GET /me` - Current user info
- `GET /dashboard` - Dashboard stats
- `GET /search` - Search services/vendors
- `POST /batch` - Batch sub-requests

## Testing

```bash
# Run PHPUnit tests
composer test

# Run specific test
./vendor/bin/phpunit --filter TestClassName
```

## Pro Plugin Integration

The Pro plugin (`wp-sell-services-pro`) extends this plugin via hooks.
Pro features are gated by EDD Software Licensing.

```php
// In Pro plugin
add_action('wpss_loaded', function($plugin) {
    if (!WPSS_Pro\License::is_valid()) {
        return;
    }
    // Register pro features
});
```

### CRITICAL: Check Both Plugins Before Coding

**ALWAYS check both free and pro plugins before implementing ANY feature.**

| Free Plugin Owns | Pro Plugin Owns |
|------------------|-----------------|
| Core marketplace, CPTs, database | WooCommerce adapter |
| Standalone adapter + Offline gateway | EDD/Fluent/SureCart adapters |
| Stripe + PayPal gateways, Gateways tab | Razorpay gateway |
| Base admin settings UI | Cloud storage (S3, GCS) |
| Frontend dashboard framework | Advanced analytics, Wallet integrations |
| Order workflow, conversations | |

**Rules:**
1. Free plugin provides hooks - Pro extends via those hooks
2. Never duplicate functionality between plugins
3. Each gateway/adapter class owns its own settings - don't duplicate in Pro.php
4. If adding a hook in Free, check if Pro already uses a similar hook

See `wp-sell-services-pro/CLAUDE.md` for detailed Pro guidelines.

## Documentation Website

**Short ID**: `wpss`
**Docs Location**: `docs/website/`
**MCP Tool**: `wbcom-docs` (mandatory for publishing)

### Publish Workflow

```javascript
// Publish docs (local first, then live)
mcp__wbcom-docs__publish_product_docs({
  product_slug: "wp-sell-services",
  product_path: "/path/to/wp-sell-services",
  product_type: "plugin",
  sync_to_live: false  // verify LOCAL first, then true for LIVE
})
```

### Structure (16 categories, 67 docs)

```
docs/website/
├── docs_config.json          # All categories, docs, slugs (-wpss suffix)
├── images/                   # Screenshots
├── getting-started/          # 4 docs (intro, install, setup, free-vs-pro)
├── service-creation/         # 6 docs (wizard, packages, addons, media, requirements, publishing)
├── buyer-requests/           # 3 docs (posting, proposals, managing)
├── order-management/         # 7 docs (lifecycle, requirements, messaging, deliveries, milestones, tipping, settings)
├── vendor-system/            # 6 docs (registration, dashboard, profile, levels, vacation, settings)
├── reviews-ratings/          # 2 docs (review system, reputation/moderation)
├── disputes-resolution/      # 3 docs (opening, process, admin mediation)
├── payments-checkout/        # 6 docs (woocommerce, EDD/FC/SC, standalone, stripe, paypal/razorpay, currency/tax)
├── earnings-wallet/          # 5 docs (commission, dashboard, withdrawals, wallet, auto-payouts)
├── analytics-reporting/      # 3 docs (vendor analytics, admin analytics, export)
├── notifications-emails/     # 3 docs (11 email types, in-app, configuration)
├── cloud-storage/            # 2 docs (overview, setup S3/GCS/DO)
├── marketplace-display/      # 5 docs (shortcodes, blocks, search, templates, SEO)
├── admin-tools/              # 4 docs (moderation, vendor mgmt, withdrawals, manual orders)
├── platform-settings/        # 3 docs (general, pages, advanced)
├── developer-guide/          # 4 docs (REST API, hooks, integrations, theme)
└── faq/                      # 1 doc
```

### Image Naming Conventions

- `admin-*` - Admin panel screenshots
- `frontend-*` - Frontend/public-facing screenshots
- `settings-*` - Settings page screenshots
- `pro-*` - Pro-only feature screenshots
- `wizard-*` - Service creation wizard screenshots

### Rules

1. **ALL docs (free + pro) live in the FREE plugin only** - never in pro plugin
2. Pro features marked with `**[PRO]**` badge inline
3. All slugs suffixed with `-wpss` (e.g., `intro-wpss`, `order-workflow-wpss`)
4. Images referenced as `../images/filename.png`
5. Cross-doc links use relative paths (e.g., `../category/filename.md`)

---

## Basecamp Project Tracking

**Project**: WP Sell Services
**Project ID**: `45156734`

### Card Table Columns

Verified against the live board on 2026-08-13. The board has 11 columns; these
are the ones this workflow touches.

| Column | ID | Purpose |
|--------|-----|---------|
| Triage | `9381845573` | Unsorted inbox |
| Not now | `9381845574` | Parked |
| Scope | `9381845723` | Product spec + feature definitions (not bugs) |
| Suggestion | `9381846474` | Feature requests + API/UX suggestions |
| Bugs | `9381846253` | Active bugs to fix |
| UI Issues | `9542411037` | Visual/layout defects |
| Ready For Development | `9381845980` | Specced, not started |
| **Ready for Testing** | **`9381846126`** | **Fixed, awaiting QA — this is the move-to target** |
| In Testing | `9381846060` | QA actively verifying |
| In Development | `9381845879` | In progress |
| Done | `9381845577` | Verified |

> **Corrected 2026-08-13:** this table previously listed `9381846060` as "Ready
> for Testing". That ID is **In Testing**. A fixed card moved with the old ID
> skipped the QA queue and landed in the column QA uses for work already picked
> up, so it would sit there unclaimed. Use `9381846126`.

### Workflow
1. Pick bug card from **Bugs** column
2. Fix the issue and commit with descriptive message
3. Add comment to card with:
   - Root cause
   - Fix applied
   - Files changed
   - Testing steps
4. Move card to **Ready for Testing** column
5. QA team verifies fix and moves to Done

### Comment Format (HTML)
```html
<strong>✅ Fixed</strong><br><br>
<strong>Root Cause:</strong><br>Description<br><br>
<strong>Fix Applied:</strong><br>What was changed<br><br>
<strong>Files Changed:</strong><br>• file1.php<br>• file2.php<br><br>
<strong>Testing Steps:</strong><br>1. Step one<br>2. Step two
```
