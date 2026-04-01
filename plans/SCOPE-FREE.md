# WP Sell Services — Project Scope

> Everything a new developer needs to understand about this project.

**Last Updated:** 2026-04-01

---

## Goal

Build a complete, production-ready Fiverr-style service marketplace plugin for WordPress. Vendors list services with tiered pricing, buyers purchase through a managed order workflow, and administrators earn commissions on every transaction.

The Free plugin must deliver a fully functional marketplace with zero external dependencies — no WooCommerce, no EDD, no third-party e-commerce plugin required. The Pro plugin extends it with additional payment platforms, analytics, and advanced features for larger marketplaces.

---

## What Was Built

A standalone marketplace platform that handles:

- **Service listings** with a multi-step creation wizard, three-tier pricing (Basic/Standard/Premium), add-ons, galleries, video embeds, FAQs, and buyer requirements.
- **Order lifecycle** with 11 statuses, requirements collection, file delivery, revision requests, deadline extensions, cancellations, and auto-completion via cron.
- **Vendor system** with registration workflows (open/approval/closed), four-tier seller levels (New, Level 1, Level 2, Top Rated), dashboard, portfolio, vacation mode, and profile management.
- **Buyer features** including buyer requests with vendor proposals, favorites, tipping, and full purchase history.
- **Reviews and disputes** with 5-star multi-criteria ratings, moderation queue, structured dispute workflow, evidence submission, and admin mediation.
- **Payments and earnings** with standalone checkout, offline/Stripe/PayPal gateways, global and per-vendor commission rates, earnings tracking, withdrawal requests, and automated withdrawal scheduling.
- **Notifications** with 11 email types, in-app notification center, and customizable templates.
- **REST API** with 21 controllers, 125+ endpoints, and a batch endpoint for mobile apps.
- **Display** with 6 Gutenberg blocks, 16+ shortcodes, template override system, and SEO schema markup.
- **Automation** with 12 cron jobs handling late orders, auto-completion, reminders, seller level recalculation, and more.

---

## Free vs Pro Split

### Free Plugin (this repository)

The Free plugin is a complete marketplace. It ships with:

| Area | What's Included |
|------|----------------|
| Checkout | Standalone checkout system (no e-commerce plugin needed) |
| Gateways | Offline, Stripe, PayPal |
| Order workflow | Full 11-status lifecycle |
| Vendor system | Registration, levels, dashboard, portfolio, vacation |
| Reviews & disputes | Full workflow with admin mediation |
| Commission | Global + per-vendor flat rates |
| Earnings | Dashboard, withdrawals, auto-scheduling |
| REST API | 21 controllers, 125+ endpoints, batch endpoint |
| Blocks | 6 Gutenberg blocks |
| Shortcodes | 16+ shortcodes |
| Database | 17 custom tables |
| Cron | 12 scheduled jobs |
| Templates | Override system compatible with any theme |

### Pro Plugin (separate repository: `wp-sell-services-pro`)

Pro extends Free via hooks — it never duplicates functionality. It adds:

| Area | What's Added |
|------|-------------|
| E-commerce adapters | WooCommerce, EDD, FluentCart, SureCart |
| Payment gateways | Razorpay |
| Wallet integrations | Internal wallet, TeraWallet, WooWallet, MyCred |
| Cloud storage | Amazon S3, Google Cloud Storage, DigitalOcean Spaces |
| Analytics | Revenue charts, order analytics, vendor stats, CSV/Excel export |
| Tiered commissions | Priority-based commission rules with conditions |
| White-label | Admin, dashboard, and email branding |
| Stripe Connect | Express accounts, split payments |
| PayPal Payouts | Batch vendor payouts |
| Vendor subscriptions | Plan management, billing, enforcement |
| Recurring services | Subscription billing for services |
| Wizard enhancements | AI suggestions, templates, bulk uploads, scheduled publishing |
| REST API | 10 additional controllers |
| Database | 7 additional tables |

### Historical Note

WooCommerce was originally an adapter in the Free plugin. In February 2026, it was moved to the Pro plugin because the Free plugin's standalone checkout with Stripe and PayPal gateways provides a complete payment solution without requiring WooCommerce.

---

## Technical Stack

| Component | Technology |
|-----------|-----------|
| Language | PHP 8.1+ with `declare(strict_types=1)` |
| Autoloading | PSR-4 via Composer |
| Namespace | `WPSellServices\` (Free), `WPSellServicesPro\` (Pro) |
| Standards | WordPress Coding Standards (WPCS) via PHP_CodeSniffer |
| Static analysis | PHPStan level 5 with baseline and WordPress stubs |
| JavaScript | jQuery (legacy AJAX), vanilla JS (new features) |
| Blocks | React/JSX via `@wordpress/scripts` |
| CSS | BEM-like naming with `wpss-` prefix, RTL support |
| Database | Custom tables via `$wpdb`, repository pattern |
| API | WordPress REST API (`wpss/v1` namespace) |
| Distribution | Self-hosted via wbcomdesigns.com (NOT WordPress.org) |
| Licensing | GPL v2+, Pro gated by EDD Software Licensing |
| Documentation | wbcomdesigns.com/docs/wp-sell-services/ |

---

## Architecture Overview

### Directory Structure

```
wp-sell-services/
├── src/                        # PHP source (PSR-4 autoloaded)
│   ├── Core/                   # Plugin bootstrap, activation, deactivation
│   ├── Models/                 # Data models (ServiceOrder, VendorProfile, etc.)
│   ├── Services/               # Business logic (26+ service classes)
│   ├── Database/               # Schema, migrations, repositories
│   ├── Integrations/           # E-commerce adapters, payment gateways
│   │   ├── Standalone/         # Built-in standalone checkout
│   │   ├── Gateways/           # OfflineGateway, TestGateway
│   │   ├── Stripe/             # StripeGateway
│   │   ├── PayPal/             # PayPalGateway
│   │   └── Contracts/          # PaymentGatewayInterface
│   ├── Admin/                  # Admin pages, metaboxes, settings
│   ├── Frontend/               # AJAX handlers, templates, dashboard, wizard
│   ├── API/                    # REST controllers (21 controllers + base)
│   └── Blocks/                 # 6 Gutenberg blocks
├── assets/                     # CSS, JS, images
├── templates/                  # PHP templates (overridable via theme)
├── languages/                  # Translation files (.pot)
├── docs/                       # Developer documentation
├── phpstan.neon                # PHPStan configuration
├── phpstan-baseline.neon       # PHPStan baseline (existing issues)
├── phpcs.xml                   # WPCS configuration
├── composer.json               # PHP dependencies
└── package.json                # JS/CSS build tooling
```

### Bootstrap Flow

```
plugins_loaded (priority 10)
  → Plugin::get_instance()->init()
    → maybe_upgrade_database()
    → register_post_types()
    → define hooks (admin, frontend, AJAX, REST, cron, cascade)
    → loader->run()
    → do_action('wpss_loaded', $plugin)  ← Pro extends here
```

### Key Design Patterns

- **Repository pattern** for all database access (AbstractRepository base class)
- **Adapter/Provider pattern** for e-commerce platforms, wallets, storage
- **State machine** for order status transitions (OrderService::can_transition())
- **Hook-based extension** for Free-to-Pro communication
- **Template override** for theme customization

### Settings Storage

Settings are stored as grouped option arrays, not individual options:

| Option Name | Keys |
|------------|------|
| `wpss_general` | `platform_name`, `currency`, `ecommerce_platform` |
| `wpss_vendor` | `vendor_registration`, `max_services_per_vendor`, `require_verification`, `require_service_moderation` |
| `wpss_pages` | `services_page`, `dashboard`, `become_vendor`, `checkout` |
| `wpss_commission` | `commission_rate`, `enable_vendor_rates` |
| `wpss_payouts` | `min_withdrawal`, `clearance_days`, `auto_withdrawal_*` |
| `wpss_tax` | `enable_tax`, `tax_label`, `tax_rate`, `tax_included`, `tax_on_commission` |
| `wpss_orders` | `auto_complete_days`, `revision_limit`, `allow_disputes`, `dispute_window_days` |
| `wpss_notifications` | `notify_new_order`, `notify_order_completed`, etc. |
| `wpss_advanced` | `delete_data_on_uninstall`, etc. |

Access via helper: `wpss_get_currency()` for currency, or directly: `get_option('wpss_general')['currency']`.

---

## Key Numbers

| Metric | Count |
|--------|-------|
| REST API controllers | 21 (+ 7 generic endpoints in API.php) |
| REST API endpoints | 125+ |
| AJAX handlers | 87 (legacy; no new AJAX — REST-first) |
| Custom DB tables | 17 (Free) + 7 (Pro) |
| Gutenberg blocks | 6 |
| Shortcodes | 16+ |
| Cron jobs | 12 (11 in OrderWorkflowManager + 1 auto-withdrawal) |
| Email notification types | 11 |
| Order statuses | 11 |
| Seller levels | 4 |
| Service classes | 26+ |
| Action/filter hooks | 100+ |
| Currencies supported | 10 |

---

## CI/CD Pipeline

GitHub Actions runs on every push and PR to `main`:

| Check | Tool | What It Does | Blocking? |
|-------|------|-------------|-----------|
| PHP Lint | `php -l` | Syntax check on PHP 8.1, 8.2, 8.3, 8.4 | Yes |
| WPCS | PHP_CodeSniffer | WordPress Coding Standards (0 errors required, warnings allowed) | Yes |
| PHPStan | PHPStan | Static analysis at level 5 (no regressions from baseline) | Yes |
| PHPUnit | PHPUnit | Unit tests across PHP/WP matrix | No |

### Branch Protection Rules

- All blocking checks must pass before merge
- PRs require at least 1 approval
- Direct pushes to `main` are not allowed

### Running Locally

```bash
# WPCS
composer phpcs                    # Check all files
composer phpcbf                   # Auto-fix issues

# PHPStan
vendor/bin/phpstan analyse --memory-limit=1G

# PHPUnit
composer test                     # All tests
vendor/bin/phpunit --testsuite unit   # Unit tests only
```

---

## Development Workflow

### Branch Strategy

1. Create a feature or fix branch from `main`
2. Develop and commit with descriptive messages
3. Push and open a PR
4. CI runs automatically (PHP lint, WPCS, PHPStan)
5. Get 1 approval from a reviewer
6. Merge to `main`

### Adding a New Feature

1. Write the business logic in a `Services/` class
2. Add REST API endpoints in `src/API/` (every feature must have REST coverage)
3. If the feature has a frontend, add template in `templates/`
4. Register hooks in `Plugin.php`
5. If Pro needs to extend it, add a filter hook

### Adding a New Payment Gateway

1. Create a class in `src/Integrations/{GatewayName}/`
2. Implement `PaymentGatewayInterface`
3. Register via the `wpss_payment_gateways` filter

### Template Overrides

Any template in `templates/` can be overridden by placing a copy at `{theme}/wp-sell-services/{template}.php`.

---

## For New Developers (Getting Started)

### Prerequisites

- WordPress 6.4+
- PHP 8.1+
- MySQL 5.7+
- Composer
- Node.js + npm

### First Setup

```bash
cd wp-content/plugins/wp-sell-services/
composer install
npm install
npm run build
```

Activate the plugin and run the Setup Wizard to create pages and seed demo content.

### Key Files to Read First

| File | Why |
|------|-----|
| `CLAUDE.md` | Project overview, namespace map, hook registry, REST controllers |
| `docs/ARCHITECTURE.md` | Bootstrap flow, database schema, state machine, extension points |
| `docs/CODING-STANDARDS.md` | PHP/JS/CSS rules, security patterns, REST API rules |
| `docs/SCOPE.md` | This file — the big picture |
| `src/Core/Plugin.php` | Entry point, see how everything wires together |
| `src/Services/OrderService.php` | Core order logic and status transitions |
| `src/API/RestController.php` | Base class for all REST controllers |

### Common Pitfalls

- **Settings are grouped arrays.** Use `get_option('wpss_general')['currency']`, not `get_option('wpss_currency')`.
- **`wpss_is_vendor()` checks capability, not role.** The `wpss_vendor` role must include `'wpss_vendor' => true` in its caps.
- **AbstractRepository::find() returns stdClass.** Wrap with `Model::from_db($row)` before returning typed models.
- **Service model uses public properties.** Access `$service->vendor_id` directly, not `$service->get_vendor_id()`.
- **No new AJAX handlers.** All new features must be REST-first. The 87 existing AJAX handlers remain for backward compatibility.
- **`wp_get_post_terms()` returns WP_Error on failure.** WP_Error is truthy, so `?: array()` does not catch it. Always use `is_wp_error()`.

### Documentation Site

Full user and developer documentation lives at [wbcomdesigns.com/docs/wp-sell-services/](https://wbcomdesigns.com/docs/wp-sell-services/). The source files are in `docs/website/` (16 categories, 67 docs). All docs for both Free and Pro live in this repository.
