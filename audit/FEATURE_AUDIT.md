# WP Sell Services — Feature Audit Report

**Generated**: 2026-05-08 · **last refreshed** 2026-08-26 (branch `1.7.0`)
**Version**: 1.7.0-dev
**Source**: [`audit/manifest.json`](manifest.json)
**Counts**: 208 PHP files in `src/` · 151 `register_rest_route` calls (26 controllers) · 119 AJAX handlers · 20 custom DB tables · 9 WP-CLI command files · 377 hook firings (165 do_actions + 212 apply_filters) · 6 blocks · 20 shortcodes · 37 HTML + 14 plain email templates · 24 switchable notification types. Re-measured after the 1.7.0 dead-code sweep (-1,299 lines).

> Counts are enumerated by hand, not by `write-manifest.mjs` — the generator undercounts REST
> because controllers register through an array wrapper. See `audit/manifest.json`.

---

## 1. Frontend features

### 1.1 Marketplace dashboard (`[wpss_dashboard]` shortcode)
- **Class**: `WPSellServices\Frontend\UnifiedDashboard`
- **File**: `src/Frontend/UnifiedDashboard.php` (810+ lines)
- **Sections** (13 total): orders, favorites, requests, services, sales, earnings, wallet, analytics, portfolio, create, create-request, messages, profile
- **Templates**: `templates/dashboard/sections/<section>.php`
- **Vendor-only sections** (7): services, sales, earnings, wallet, analytics, portfolio, create
- **CSS**: `assets/css/unified-dashboard.css` (1700+ rules)
- **JS**: `assets/js/unified-dashboard.js`
- **Tour**: 9-step (vendor) / 7-step (buyer) Shepherd.js tour at `src/Frontend/Tour.php`

### 1.2 Service detail page (single `wpss_service`)
- **Template**: `templates/single-service.php`
- **View class**: `WPSellServices\Frontend\SingleServiceView` at `src/Frontend/SingleServiceView.php`
- **CSS**: `assets/css/single-service.css`
- **Sub-templates**: `templates/partials/service-reviews.php`, `vendor-portfolio.php`

### 1.3 Service archive
- **Template**: `templates/archive-service.php`
- **View class**: `WPSellServices\Frontend\ServiceArchiveView`
- **CSS**: `assets/css/archive-service.css`

### 1.4 Service creation wizard
- **Class**: `WPSellServices\Frontend\ServiceWizard` (2281 lines)
- **Multi-step**: package config, addons, gallery, requirements, FAQs, pricing
- **JS**: `assets/js/service-wizard.js`
- **CSS**: `assets/css/service-wizard.css`

### 1.5 Buyer request archive + create
- **Templates**: `templates/archive-buyer-request.php`, `templates/dashboard/sections/create-request.php`

### 1.6 Public signup
- **Class**: `WPSellServices\Frontend\PublicSignup`
- **Variants**: buyer, vendor (intent-based registration)

## 2. AJAX handlers

72 logged-in + 5 nopriv handlers in `src/Frontend/AjaxHandlers.php` (4158 lines). Plus 6 admin AJAX handlers in `src/Admin/Admin.php` and submenu pages.

**Categories** (full enumeration deferred):
- Order workflow: accept, decline, start-work, deliver, accept-delivery, request-revision, cancel, accept-cancellation, reject-cancellation
- Milestones: propose, submit, approve, decline, delete
- Extensions: request, decline
- Tipping: send
- Messaging: send-message, mark-read, load-conversation
- Reviews: submit, mark-helpful (nopriv)
- Disputes: open, respond
- Wallet: cancel-withdrawal, withdraw-request
- Portfolio: add, edit, delete, reorder
- Service: search, live-search (nopriv), favorites
- Cart: add (nopriv), remove, update-item

**Security pattern (verified)**:
- Nonce: `check_ajax_referer()` on all 67 logged-in handlers
- Auth: 3 patterns — inline `current_user_can`, service-class delegation, `WHERE user_id=X` scoping
- Rate limiting: `RateLimiter::check_and_track` on order_action, helpful_vote, delivery (8 sites)

## 3. REST endpoints (138 across 24 controllers in `src/API/`)

| Controller | Base | Notes |
|---|---|---|
| API | (generic) | /categories, /tags, /settings, /me, /dashboard, /search, /batch (≤25 sub-requests) |
| AuditLogController | /audit-log | Read-only audit trail |
| AuthController | /auth | login, register, logout, devices |
| BuyerRequestsController | /buyer-requests | CRUD, proposals |
| CartController | /cart | add, get, remove, checkout |
| ConversationsController | /conversations | messages, attachments |
| DisputesController | /disputes | create, respond, resolve |
| EarningsController | /earnings | summary, history, withdrawals |
| ExtensionRequestsController | /extensions | create, approve, reject |
| FavoritesController | /favorites | add, remove, list |
| MediaController | /media | upload, info, delete |
| MilestonesController | /milestones | CRUD, submit, approve |
| ModerationController | /moderation | approve, reject, history |
| NotificationsController | /notifications | list, read, delete |
| OrdersController | /orders | CRUD, status transitions |
| PaymentController | /payment | gateways, process, status, webhook |
| PortfolioController | /portfolio | CRUD, reorder |
| ProposalsController | /proposals | CRUD, accept/reject |
| ReviewsController | /reviews | CRUD, vendor reviews |
| SellerLevelsController | /seller-levels | definitions, progress |
| ServicesController | /services | CRUD, search, featured |
| TippingController | /tips | send, list |
| VendorsController | /vendors | list, profile, stats |

## 4. Admin pages (25 total)

Top-level menu **WP Sell Services** (`wp-sell-services`). Submenus:
- Dashboard, All Services, Moderation, Categories, Tags, All Requests, Orders, Vendors, Withdrawals, Disputes, Analytics, Settings, License (Pro)

Settings page: 10 tabs (`src/Admin/Settings.php` — 2346 lines).

## 5. Settings inventory

Stored in `wpss_*_settings` options. Tabs: General, Vendor, Orders, Earnings, Reviews, Disputes, Pages, Emails, Gateways, Advanced. Setting keys are tab-specific; full enumeration deferred.

## 6. Database tables (18)

See manifest.json `tables[]` for complete list with purpose. All managed via `WPSellServices\Database\SchemaManager::CORE_TABLES` constant. Schema migrations via `WPSellServices\Database\MigrationManager`.

Repository classes in `src/Database/Repositories/` (8 repos: AbstractRepository, OrderRepository, ConversationRepository, DeliveryRepository, ProposalRepository, ReviewRepository, ServicePackageRepository, VendorProfileRepository).

## 7. Content types (CPTs / taxonomies)

| CPT | Slug | Purpose |
|---|---|---|
| Service | wpss_service | Vendor-created service offerings |
| Buyer Request | wpss_request | Buyer job postings |

| Taxonomy | Slug | Hierarchical |
|---|---|---|
| Service Category | wpss_service_category | Yes |
| Service Tag | wpss_service_tag | No |

## 8. JavaScript modules (23 unminified files)

Key handles: `wpss-frontend`, `wpss-icons`, `wpss-ux-primitives`, `wpss-tour`, `wpss-blocks-editor`, `wpss-blocks`, `wpss-service-wizard`, `wpss-admin`, `wpss-admin-settings-{nav,pages,emails,demo}`, `wpss-admin-icons`, `wpss-admin-toast`.

i18n: `wp_set_script_translations()` on every wpss-* handle (commit `4c74224`). 125 JS strings in POT.

## 9. Email templates (35 files)

- HTML variants: `templates/emails/*.php`
- Plain-text variants: `templates/emails/plain/*.php`
- Categories: order lifecycle (11), milestones (4), extensions (3), tips (3), disputes (4), reviews (2), withdrawals (3), notifications (5)

## 10. Cron jobs (4)

All on Action Scheduler (group `wpss`). Facade: `WPSellServices\Services\Scheduler`.
- Order lifecycle sweeps
- Dispute deadlines
- Audit-log retention
- Vendor-stat refresh + seller-level recalc

## 11. Integrations

Free plugin includes:
- Standalone adapter (built-in checkout, no e-commerce dependency)
- Stripe gateway, PayPal gateway, Offline gateway, Test gateway

Pro extends via `wpss_loaded` action with: WooCommerce, EDD, Fluent Cart, SureCart, Razorpay, advanced wallet/storage/analytics.

## 12. Custom capabilities

No custom capability slugs registered. Plugin uses standard WP capabilities (`edit_posts`, `manage_options`) plus role-based ownership comparisons. Vendor role created on activation via `add_role`.

---

## Known issues surfaced by audit

See `audit/wppqa-baseline-2026-05-08/SUMMARY.md`:
- 5 `alert()`/`confirm()` calls in admin JS (admin-ux Rule 10) — replace with toast
- 3 `$_POST/$_GET` iteration patterns (security.md) — read explicit keys
- ~13 nonce-without-current_user_can findings (some valid object-ownership patterns; 3-5 in `Admin.php`/`ServiceModerationPage.php` may be real gaps)
- ~65 low-severity tap-target warnings (button height < 40px)

PCP scan summary (after token-pass commits):
- 0 OutputNotEscaped errors in our code
- 0 SQL injection errors in our code
- 0 register_setting missing sanitize_callback
- ~375 remaining errors are: false-positive WordPress.DB.PreparedSQL.NotPrepared in Repository pattern (verified safe), false-positive WordPress.WP.I18n.MissingTranslatorsComment style issues (comments exist, just positioned above wrapper not directly above __()), and vendored EDD_SL_Plugin_Updater.php (suppressed via phpcs:ignoreFile)
