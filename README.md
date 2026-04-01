# WP Sell Services

A complete Fiverr-style service marketplace platform for WordPress. Vendors list services with tiered pricing, buyers purchase through a managed order workflow, and administrators earn commissions on every transaction.

**No WooCommerce required** — includes a built-in standalone checkout with offline payments out of the box.

## Features

### Marketplace Core
- Multi-step service creation wizard with live preview
- Three-tier pricing packages (Basic, Standard, Premium)
- Service add-ons, gallery, video embeds, FAQs, and requirements
- Category and tag organization

### Order Workflow
- 11 distinct order statuses with complete lifecycle management
- Requirements collection, file delivery, revision requests
- Built-in messaging per order with file attachments
- Deadline extensions and cancellation flow

### Vendor System
- Vendor registration (open, approval, or closed)
- Four-tier seller levels (New, Level 1, Level 2, Top Rated)
- Unified dashboard with earnings, portfolio, and vacation mode
- Commission tracking and withdrawal requests

### Buyer Features
- Post buyer requests for vendor proposals
- Favorites/wishlist, tipping, and order tracking
- Full purchase history

### Reviews and Disputes
- 5-star multi-criteria ratings with moderation queue
- Structured dispute workflow with evidence submission
- Admin mediation with multiple resolution types

### Payments and Earnings
- Standalone checkout with offline gateway (no e-commerce plugin needed)
- Global and per-vendor commission rates (0-50%)
- Automated withdrawal scheduling (weekly, bi-weekly, monthly)

### Developer Features
- 21 REST API controllers with 125+ endpoints
- 100% REST coverage for all user-facing features (mobile-app ready)
- Batch endpoint for mobile apps (up to 25 sub-requests)
- 6 Gutenberg blocks and 16+ shortcodes
- Template override system
- 17 custom database tables
- PSR-4 autoloading, PHP 8.1+
- 100+ action and filter hooks
- WP-CLI commands for bulk operations

## Requirements

- WordPress 6.4+
- PHP 8.1+
- MySQL 5.7+

## Installation

1. Upload the `wp-sell-services` folder to `/wp-content/plugins/`
2. Activate through **Plugins** menu
3. Complete the **Setup Wizard** to create pages and configure your marketplace

## Development

### Setup

```bash
composer install
npm install
```

### Build

```bash
npm run build      # Production build
npm run dev        # Watch mode
```

### Linting

```bash
composer phpcs     # WPCS check
composer phpcbf    # Auto-fix WPCS issues
```

### Static Analysis

```bash
vendor/bin/phpstan analyse --memory-limit=1G
```

PHPStan is configured at level 5 with a baseline. New code must pass without regressions.

### Tests

```bash
composer test                                    # All tests
vendor/bin/phpunit --testsuite unit              # Unit tests only
vendor/bin/phpunit --filter TestClassName         # Specific test
```

## CI/CD

GitHub Actions runs on every push and PR to `main`:

| Check | Description | Blocking |
|-------|-------------|----------|
| PHP Lint | Syntax check on PHP 8.1, 8.2, 8.3, 8.4 | Yes |
| WPCS | WordPress Coding Standards (0 errors required) | Yes |
| PHPStan | Static analysis level 5 | Yes |
| PHPUnit | Unit tests across PHP/WP matrix | No |

Branch protection requires all blocking checks to pass before merge. PRs require 1 approval.

## REST API

All endpoints are under `/wp-json/wpss/v1/`. Authentication via WordPress cookies, Application Passwords, or JWT tokens.

| Controller | Base Route | Endpoints |
|-----------|-----------|-----------|
| Services | `/services` | CRUD, search, packages, addons, compare |
| Orders | `/orders` | CRUD, status transitions, requirements, cancel |
| Reviews | `/reviews` | CRUD, helpful, vendor reviews, stats |
| Vendors | `/vendors` | List, profile, stats, portfolio |
| Conversations | `/conversations` | Messages, send, read, attachments |
| Disputes | `/disputes` | Create, respond, resolve, escalate |
| Buyer Requests | `/buyer-requests` | CRUD, proposals |
| Proposals | `/proposals` | CRUD, accept/reject, withdraw |
| Notifications | `/notifications` | List, read, delete |
| Earnings | `/earnings` | Summary, history, withdrawals |
| Favorites | `/favorites` | Add, remove, list |
| Portfolio | `/portfolio` | CRUD, reorder, featured |
| Milestones | `/milestones` | CRUD, submit, approve |
| Auth | `/auth` | Login, register, forgot-password, devices |
| Cart | `/cart` | Add, get, remove, checkout |
| Moderation | `/moderation` | Queue, approve, reject |
| Seller Levels | `/seller-levels` | Definitions, progress |
| Tipping | `/tips` | Send, list |
| Extensions | `/extensions` | Create, approve, reject |
| Media | `/media` | Upload, info, delete |
| Payment | `/payment` | Gateways, process, webhook |

Batch endpoint: `POST /wpss/v1/batch` (up to 25 sub-requests per call)

## Pro Extension

[WP Sell Services Pro](https://wbcomdesigns.com/downloads/wp-sell-services-pro/) adds:

- E-commerce adapters (EDD, FluentCart, SureCart)
- Payment gateways (Stripe, PayPal, Razorpay)
- Wallet integrations (Internal, TeraWallet, WooWallet, MyCred)
- Cloud storage (S3, GCS, DigitalOcean Spaces)
- Advanced analytics with Chart.js
- Tiered commissions, White-label, Stripe Connect, PayPal Payouts
- Vendor subscription plans, Recurring services
- 10 additional REST API controllers

## License

GPL v2 or later. See [LICENSE](LICENSE) for details.
