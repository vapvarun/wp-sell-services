# WP Sell Services

A Fiverr-style service marketplace for WordPress. Vendors list services with tiered packages, buyers order through a managed workflow with requirements, deliveries and revisions, and the site owner earns a commission on every sale. Buyer requests can run as Upwork-style milestone contracts.

No WooCommerce needed: the free plugin has its own checkout with Stripe, PayPal and offline payments.

## What it does

- **Services**: a step-by-step service wizard with Basic, Standard and Premium packages, add-ons, gallery, video, FAQs and buyer requirements.
- **Orders**: requirements collection, messaging with attachments, deliveries, revision requests, deadline extensions, cancellations and a full order timeline.
- **Buyer requests and proposals**: buyers post a job, vendors send proposals, and accepted proposals can be paid in milestone phases.
- **Vendors**: open, approval-based or closed registration, seller levels, portfolio, intro video, vacation mode and a vendor dashboard.
- **Reviews and disputes**: multi-criteria ratings with moderation, and a dispute workflow with evidence and admin mediation.
- **Money**: global and per-vendor commission, an earnings ledger, withdrawal requests, mark-paid payouts and CSV export of what is owed. Refunds are made by the payment rail that collected the money.
- **Extras**: paid extensions, favorites, email and in-app notifications, guided tours for admins and members, and dark mode on every frontend screen.
- **Display**: 20 shortcodes and 6 blocks for service grids, search, categories, seller cards, buyer requests and dashboards.

## Who it is for

Site owners building a freelance, coaching, tutoring or agency services marketplace on WordPress, who want the whole order flow in one plugin instead of stitching a store, a messaging plugin and a payout tool together.

## Get it

- Free plugin: [wbcomdesigns.com/downloads/wp-sell-services](https://wbcomdesigns.com/downloads/wp-sell-services/)
- [WP Sell Services Pro](https://wbcomdesigns.com/downloads/wp-sell-services-pro/) adds WooCommerce, EDD and FluentCart checkout, Razorpay, Stripe Connect and PayPal payouts, tipping, tiered commission, vendor subscription plans, wallet providers, cloud storage, white label, display currency, push notifications and analytics. Pricing is on that page.
- Product site: [wpsellservices.com](https://wpsellservices.com/)

## Documentation

- Docs: [wpsellservices.com/docs](https://wpsellservices.com/docs/) and [docs.wbcomdesigns.com/products/wp-sell-services](https://docs.wbcomdesigns.com/products/wp-sell-services/)
- Release notes: [Free](https://wbcomdesigns.com/release-notes/wp-sell-services/) and [Pro](https://wbcomdesigns.com/release-notes/wp-sell-services-pro/)
- Docs source lives in this repo under [`docs/website/`](docs/website/). Free and Pro are documented together here.

## Requirements

- WordPress 6.4 or later
- PHP 8.1 or later

## Installation

1. Download the plugin zip from [wbcomdesigns.com](https://wbcomdesigns.com/downloads/wp-sell-services/).
2. Go to **Plugins > Add New > Upload Plugin**, upload the zip and activate it.
3. Complete the **Setup Wizard**.

## For developers

| | |
|---|---|
| REST API | `/wp-json/wpss/v1/`, covering every member-facing feature, plus `POST /wpss/v1/batch` for up to 25 sub-requests. See the [REST API overview](docs/website/developer-guide/rest-api-overview.md). |
| Hooks | Actions and filters prefixed `wpss_`. See the [hooks reference](docs/website/developer-guide/hooks-reference.md). |
| WP-CLI | `wp wpss` commands. See [WP-CLI commands](docs/website/developer-guide/wp-cli-commands.md). |
| Template overrides | Copy a file from `templates/` to `your-theme/wp-sell-services/`. See [theme integration](docs/website/developer-guide/theme-integration.md). |
| Background jobs | Action Scheduler, group `wpss`. |
| Namespace and prefix | `WPSellServices\`, functions `wpss_` |
| Text domain | `wp-sell-services` |

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

PHPStan is configured at level 6 with a baseline. New code must pass without regressions.

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
| PHPStan | Static analysis level 6 | Yes |
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

## Support

Open a ticket at [wbcomdesigns.com/support](https://wbcomdesigns.com/support/#submit-ticket-section).

## License

GPL v2 or later. See [LICENSE](LICENSE).
