# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**WP Sell Services** is a Fiverr-style service marketplace platform for WordPress with optional Upwork-style milestone contracts on buyer-request projects.

- **Free Version**: Full standalone marketplace with built-in checkout, offline payments, milestone contracts, paid extensions, tipping, and vendor intro videos.
- **Pro Version**: WooCommerce, EDD, FluentCart, SureCart integrations, Stripe/PayPal/Razorpay payments, advanced analytics, wallet integrations, earnings ledger + CSV export.

## Recent Changes

| Date | Version | Summary |
|---|---|---|
| 2026-04-23 | 1.1.0 | Admin UX + ops polish ready for release: unified listing UX across Vendors/Withdrawals/Moderation (shared `wpss-listing-page` + responsive stats grid); guided onboarding tours on both the admin dashboard and the `[wpss_dashboard]` shortcode (Shepherd.js, role-aware, per-user completion persisted via REST); all recurring jobs migrated from WP-Cron to Action Scheduler with a `Services\Scheduler` facade, upgrade-path legacy cron sweep, and `wpss` / `wpss-pro` group convention; `Services\Icon` helper for Lucide placeholders; Orders + Disputes admin wrap in `.wpss-list-card` with designed empty-states; SchemaManager drives off a single `CORE_TABLES` constant. |
| 2026-04-21 | 1.1.0 | Upwork-style milestone contracts (lock-step phase payments, auto-complete parent on final approval, cascade-cancel pending phases), paid extensions (catalog-order add-ons with deadline push), vendor intro video on profile, NET earnings ledger with period selector and CSV export, money-flow integrity fixes (tip idempotency, deferred-hook transaction, rate-limit scoping, mark_as_paid sub-order skip, Pro double-credit guard). New sub-order pattern documented in `docs/architecture/SUB_ORDER_PATTERN.md`. 7 new email templates (4 milestone + 3 extension). |
| 2026-04-02 | 1.0.0 | Initial release — marketplace core, 11-status order lifecycle, standalone checkout + offline gateway, Stripe/PayPal gateways, 26 email notification types, 21 REST controllers, 6 Gutenberg blocks, 19 shortcodes, seller levels, disputes, reviews, commissions, withdrawals. |

## Key Features (1.1.0)

Marketplace fundamentals (from 1.0.0): service wizard, tiered packages, buyer requests, proposals, order workflow with 11 statuses, requirements collection, deliveries + revisions, reviews, disputes, commissions, withdrawals, seller levels, vacation mode, 26 email notifications.

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

| Column | ID | URL | Purpose |
|--------|-----|-----|---------|
| Bugs | `9381846253` | https://3.basecamp.com/5798509/buckets/45156734/card_tables/columns/9381846253 | Active bugs to fix |
| Ready for Testing | `9381846060` | https://3.basecamp.com/5798509/buckets/45156734/card_tables/columns/9381846060 | Fixed bugs awaiting QA |

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
