# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**WP Sell Services** is a Fiverr-style service marketplace platform for WordPress.

- **Free Version**: Full marketplace with WooCommerce integration
- **Pro Version**: Additional e-commerce platforms, payment gateways, analytics

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
│   │   └── WooCommerce/    # WC integration (free)
│   ├── Admin/              # Admin classes
│   ├── Frontend/           # Frontend classes
│   ├── API/                # REST API
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
| Core marketplace, CPTs, database | EDD/Fluent/SureCart adapters |
| WooCommerce adapter | Direct payment gateways (Stripe, PayPal, Razorpay) |
| Base admin settings UI | Cloud storage (S3, GCS) |
| Frontend dashboard framework | Advanced analytics |
| Order workflow, conversations | Wallet integrations |

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

### Structure

```
docs/website/
├── docs_config.json          # All categories, docs, slugs (-wpss suffix)
├── images/                   # Screenshots (added later)
├── getting-started/          # 3 docs
├── service-management/       # 6 docs
├── order-management/         # 5 docs
├── vendor-guide/             # 6 docs
├── buyer-guide/              # 4 docs
├── disputes-resolution/      # 3 docs
├── admin-settings/           # 7 docs
├── marketplace-display/      # 4 docs
├── integrations/             # 6 docs
├── analytics-reporting/      # 3 docs
├── developer-guide/          # 3 docs
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
