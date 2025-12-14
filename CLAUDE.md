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
