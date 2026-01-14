# Developer Documentation

This guide covers hooks, filters, and integration points for developers extending WP Sell Services.

## Architecture Overview

```
WPSellServices\               # Root namespace
├── Core\                     # Plugin bootstrap
├── Models\                   # Data models (Service, Order, etc.)
├── Services\                 # Business logic
├── Integrations\             # E-commerce adapters
├── Admin\                    # Admin functionality
├── Frontend\                 # Frontend functionality
├── API\                      # REST API endpoints
├── Blocks\                   # Gutenberg blocks
├── SEO\                      # Schema markup, Open Graph
├── PostTypes\                # Custom post types
└── Taxonomies\               # Custom taxonomies
```

## Extension Points

### Main Plugin Hooks

| Hook | Type | When Fired |
|------|------|------------|
| `wpss_loaded` | Action | Plugin fully loaded |
| `wpss_pro_loaded` | Action | Pro plugin loaded |

**Example: Extend on plugin load**
```php
add_action( 'wpss_loaded', function( $plugin ) {
    // Plugin instance available
    // Register custom features
}, 10, 1 );
```

### Registering Integrations

**E-commerce Adapters:**
```php
add_filter( 'wpss_ecommerce_adapters', function( $adapters ) {
    $adapters['my_platform'] = new MyPlatformAdapter();
    return $adapters;
});
```

**Payment Gateways:**
```php
add_filter( 'wpss_payment_gateways', function( $gateways ) {
    $gateways['my_gateway'] = new MyPaymentGateway();
    return $gateways;
});
```

**Wallet Providers:**
```php
add_filter( 'wpss_wallet_providers', function( $providers ) {
    $providers['my_wallet'] = new MyWalletProvider();
    return $providers;
});
```

**Storage Providers:**
```php
add_filter( 'wpss_storage_providers', function( $providers ) {
    $providers['my_storage'] = new MyStorageProvider();
    return $providers;
});
```

## Order Lifecycle Hooks

### Status Changes

```php
// Order completed
add_action( 'wpss_order_completed', function( $order_id, $order ) {
    // Trigger integrations, notifications, etc.
}, 10, 2 );

// Order cancelled
add_action( 'wpss_order_cancelled', function( $order_id, $order ) {
    // Handle cancellation
}, 10, 2 );

// Generic status change
add_action( 'wpss_after_status_change_notification', function( $order_id, $new_status, $old_status ) {
    // React to any status change
}, 10, 3 );
```

### Delivery Events

```php
// Delivery submitted by vendor
add_action( 'wpss_delivery_submitted', function( $delivery_id, $order_id ) {
    // Notify buyer, log event
}, 10, 2 );

// Delivery accepted by buyer
add_action( 'wpss_delivery_accepted', function( $order_id ) {
    // Process completion
}, 10, 1 );

// Revision requested
add_action( 'wpss_revision_requested', function( $order_id, $reason ) {
    // Notify vendor
}, 10, 2 );
```

### Dispute Events

```php
// Dispute opened
add_action( 'wpss_dispute_opened', function( $dispute_id, $order_id, $opened_by, $data ) {
    // Alert admin
}, 10, 4 );

// Dispute resolved
add_action( 'wpss_dispute_resolved', function( $dispute_id, $resolution, $dispute, $refund_amount ) {
    // Process resolution
}, 10, 4 );

// Dispute escalated
add_action( 'wpss_dispute_escalated', function( $dispute_id, $reason, $escalated_by ) {
    // Notify higher authority
}, 10, 3 );
```

## Vendor Hooks

```php
// Vendor registered
add_action( 'wpss_vendor_registered', function( $user_id, $profile_data ) {
    // Send welcome email, create profile
}, 10, 2 );

// Profile updated
add_action( 'wpss_vendor_profile_updated', function( $user_id, $data ) {
    // Sync to CRM, update cache
}, 10, 2 );

// Vacation mode changed
add_action( 'wpss_vendor_vacation_mode_changed', function( $user_id, $enabled, $message ) {
    // Update service availability
}, 10, 3 );

// Vendor tier changed
add_action( 'wpss_vendor_tier_changed', function( $user_id, $tier ) {
    // Update badges, permissions
}, 10, 2 );
```

## Review Hooks

```php
// Review created
add_action( 'wpss_review_created', function( $review_id, $order_id ) {
    // Notify vendor, update ratings
}, 10, 2 );

// Filter review window
add_filter( 'wpss_review_window_days', function( $days ) {
    return 60; // Extend to 60 days
});
```

## Service Hooks

### Moderation

```php
// Service approved
add_action( 'wpss_service_approved', function( $service_id, $notes ) {
    // Notify vendor
}, 10, 2 );

// Service rejected
add_action( 'wpss_service_rejected', function( $service_id, $reason ) {
    // Notify vendor with reason
}, 10, 2 );

// Service pending moderation
add_action( 'wpss_service_pending_moderation', function( $service_id ) {
    // Notify admin
}, 10, 1 );
```

### Service Addons

```php
// Addon created
add_action( 'wpss_addon_created', function( $addon_id, $service_id, $addon_data ) {
    // Log, sync
}, 10, 3 );

// Addon updated
add_action( 'wpss_addon_updated', function( $addon_id, $update_data ) {
    // Sync changes
}, 10, 2 );

// Addon deleted
add_action( 'wpss_addon_deleted', function( $addon_id, $addon ) {
    // Clean up
}, 10, 2 );
```

## Buyer Request Hooks

```php
// Request created
add_action( 'wpss_buyer_request_created', function( $post_id, $data ) {
    // Notify matching vendors
}, 10, 2 );

// Request status changed
add_action( 'wpss_buyer_request_status_changed', function( $request_id, $status, $old_status ) {
    // Handle status change
}, 10, 3 );

// Request converted to order
add_action( 'wpss_request_converted_to_order', function( $order_id, $request_id, $proposal_id, $request, $proposal ) {
    // Process conversion
}, 10, 5 );
```

## Proposal Hooks

```php
// Proposal submitted
add_action( 'wpss_proposal_submitted', function( $proposal_id, $request_id, $vendor_id, $proposal_data ) {
    // Notify buyer
}, 10, 4 );

// Proposal accepted
add_action( 'wpss_proposal_accepted', function( $proposal_id, $proposal, $request ) {
    // Create order
}, 10, 3 );

// Proposal rejected
add_action( 'wpss_proposal_rejected', function( $proposal_id, $proposal, $reason ) {
    // Notify vendor
}, 10, 3 );
```

## Earnings & Withdrawal Hooks

```php
// Commission recorded
add_action( 'wpss_commission_recorded', function( $order_id, $commission, $vendor_id ) {
    // Track in accounting
}, 10, 3 );

// Withdrawal requested
add_action( 'wpss_withdrawal_requested', function( $withdrawal_id, $vendor_id, $amount ) {
    // Notify admin
}, 10, 3 );

// Withdrawal processed
add_action( 'wpss_withdrawal_processed', function( $withdrawal_id, $status, $withdrawal ) {
    // Notify vendor
}, 10, 3 );
```

## Milestone Hooks

```php
// Milestone created
add_action( 'wpss_milestone_created', function( $milestone_id, $order_id, $milestone ) {
    // Initialize tracking
}, 10, 3 );

// Milestone submitted
add_action( 'wpss_milestone_submitted', function( $milestone_id, $order_id ) {
    // Notify buyer
}, 10, 2 );

// Milestone approved
add_action( 'wpss_milestone_approved', function( $milestone_id, $order_id, $amount ) {
    // Release payment
}, 10, 3 );

// Milestone rejected
add_action( 'wpss_milestone_rejected', function( $milestone_id, $order_id, $feedback ) {
    // Notify vendor
}, 10, 3 );
```

## Messaging Hooks

```php
// Message sent
add_action( 'wpss_message_sent', function( $message, $conversation ) {
    // Send notifications
    // Trigger webhooks
}, 10, 2 );
```

## Filter Hooks Reference

### Core Filters

```php
// Currency
add_filter( 'wpss_currency', function( $currency ) {
    return 'EUR'; // Override currency
});

// Platform name
add_filter( 'wpss_platform_name', function( $name ) {
    return 'My Marketplace';
});

// Check if user is vendor
add_filter( 'wpss_is_vendor', function( $is_vendor, $user_id ) {
    // Custom vendor check
    return $is_vendor;
}, 10, 2 );
```

### Order Filters

```php
// Order number prefix
add_filter( 'wpss_order_number_prefix', function( $prefix ) {
    return 'ORD-'; // Custom prefix
});

// Dispute number prefix
add_filter( 'wpss_dispute_number_prefix', function( $prefix ) {
    return 'DISP-';
});

// Order status transitions
add_filter( 'wpss_order_status_transitions', function( $transitions, $from, $to ) {
    // Modify allowed transitions
    return $transitions;
}, 10, 3 );

// All order statuses
add_filter( 'wpss_order_statuses', function( $statuses ) {
    // Add custom status
    $statuses['custom_status'] = __( 'Custom Status', 'my-plugin' );
    return $statuses;
});
```

### File Upload Filters

```php
// Delivery allowed file types
add_filter( 'wpss_delivery_allowed_file_types', function( $types ) {
    $types['psd'] = 'Photoshop Files';
    return $types;
});

// Requirements allowed file types
add_filter( 'wpss_requirements_allowed_file_types', function( $types ) {
    return $types;
});

// Max upload size
add_filter( 'wpss_max_upload_size', function( $bytes ) {
    return 50 * 1024 * 1024; // 50MB
});
```

### SEO Filters

```php
// Service schema markup
add_filter( 'wpss_service_schema', function( $schema, $service_id ) {
    // Add custom schema properties
    return $schema;
}, 10, 2 );

// Open Graph data
add_filter( 'wpss_open_graph_data', function( $data, $service_id ) {
    return $data;
}, 10, 2 );

// Breadcrumbs
add_filter( 'wpss_breadcrumbs', function( $breadcrumbs, $service_id ) {
    return $breadcrumbs;
}, 10, 2 );

// Sitemap post types
add_filter( 'wpss_sitemap_post_types', function( $post_types ) {
    return $post_types;
});
```

### Template Filters

```php
// Get template path
add_filter( 'wpss_get_template', function( $template, $template_name, $args ) {
    // Override template location
    return $template;
}, 10, 3 );

// Get template part
add_filter( 'wpss_get_template_part', function( $template, $slug, $name ) {
    return $template;
}, 10, 3 );
```

### Post Type & Taxonomy Filters

```php
// Service post type args
add_filter( 'wpss_service_post_type_args', function( $args ) {
    $args['public'] = false; // Make private
    return $args;
});

// Service slug
add_filter( 'wpss_service_slug', function( $slug ) {
    return 'gig'; // Change to /gig/
});

// Category args
add_filter( 'wpss_service_category_args', function( $args ) {
    return $args;
});

// Category taxonomy args
add_filter( 'wpss_service_category_taxonomy_args', function( $args ) {
    return $args;
});

// Tag taxonomy args
add_filter( 'wpss_service_tag_taxonomy_args', function( $args ) {
    return $args;
});

// Buyer request post type args
add_filter( 'wpss_buyer_request_post_type_args', function( $args ) {
    return $args;
});

// Buyer request slug
add_filter( 'wpss_buyer_request_slug', function( $slug ) {
    return 'job-request';
});
```

### Notification Filters

```php
// Email content
add_filter( 'wpss_notification_email_content', function( $content, $subject, $user_id, $data ) {
    // Customize email
    return $content;
}, 10, 4 );

// Vendor welcome email
add_filter( 'wpss_vendor_welcome_email_content', function( $content, $user, $platform_name ) {
    return $content;
}, 10, 3 );
```

### Search Filters

```php
// Search results
add_filter( 'wpss_search_results', function( $results, $query, $args ) {
    // Modify results
    return $results;
}, 10, 3 );

// Search suggestions
add_filter( 'wpss_search_suggestions', function( $suggestions, $query ) {
    return $suggestions;
}, 10, 2 );
```

### API Filters

```php
// API controllers
add_filter( 'wpss_api_controllers', function( $controllers ) {
    $controllers[] = new MyController();
    return $controllers;
});

// Public settings
add_filter( 'wpss_api_public_settings', function( $settings ) {
    $settings['custom'] = 'value';
    return $settings;
});

// CORS origins
add_filter( 'wpss_api_cors_origins', function( $origins ) {
    $origins[] = 'https://myapp.com';
    return $origins;
});
```

### Miscellaneous Filters

```php
// Currency symbols
add_filter( 'wpss_currency_symbols', function( $symbols ) {
    $symbols['BTC'] = '₿';
    return $symbols;
});

// Currency format
add_filter( 'wpss_currency_format', function( $format, $symbol, $currency ) {
    return '%s ' . $symbol; // Amount before symbol
}, 10, 3 );

// All currencies
add_filter( 'wpss_currencies', function( $currencies ) {
    $currencies['BTC'] = 'Bitcoin';
    return $currencies;
});

// Withdrawal methods
add_filter( 'wpss_withdrawal_methods', function( $methods ) {
    $methods['crypto'] = __( 'Cryptocurrency', 'my-plugin' );
    return $methods;
});

// Gutenberg blocks
add_filter( 'wpss_blocks', function( $blocks ) {
    // Add/remove blocks
    return $blocks;
});

// Wallet manager
add_filter( 'wpss_wallet_manager', function( $manager ) {
    return new MyWalletManager();
});
```

## Helper Functions

### Order Functions

```php
// Get order by ID
$order = wpss_get_order( $order_id );

// Generate order number
$number = wpss_generate_order_number();

// Generate dispute number
$number = wpss_generate_dispute_number();
```

### Vendor Functions

```php
// Check if user is vendor
if ( wpss_is_vendor( $user_id ) ) {
    // Vendor-specific logic
}

// Get vendor profile
$profile = wpss_get_vendor_profile( $user_id );
```

### Currency Functions

```php
// Get current currency
$currency = wpss_get_currency();

// Get currency symbol
$symbol = wpss_get_currency_symbol( 'USD' );

// Format price
$formatted = wpss_format_price( 99.99 );
```

### Template Functions

```php
// Get template
wpss_get_template( 'single-service.php', $args );

// Get template part
wpss_get_template_part( 'content', 'service' );
```

### Option Functions

```php
// Get option from settings group
$value = wpss_get_option( 'general', 'platform_name', 'Default' );
```

### E-commerce Functions

```php
// Add service to cart
wpss_add_service_to_cart( $service_id, $package_id, $addons );

// Get checkout URL
$url = wpss_get_service_checkout_url( $service_id, $package_id );

// Get active e-commerce adapter
$adapter = wpss_get_ecommerce_adapter();
```

## Creating Custom Adapters

### E-commerce Adapter Interface

```php
interface EcommerceAdapterInterface {
    public function get_id(): string;
    public function get_name(): string;
    public function is_available(): bool;
    public function init(): void;
    public function get_product_provider(): ProductProviderInterface;
    public function get_order_provider(): OrderProviderInterface;
    public function get_checkout_provider(): CheckoutProviderInterface;
    public function get_account_provider(): AccountProviderInterface;
}
```

### Payment Gateway Interface

```php
interface PaymentGatewayInterface {
    public function get_id(): string;
    public function get_name(): string;
    public function is_available(): bool;
    public function process_payment( array $order_data ): array;
    public function handle_webhook( array $payload ): void;
    public function get_settings_fields(): array;
}
```

### Wallet Provider Interface

```php
interface WalletProviderInterface {
    public function get_id(): string;
    public function get_name(): string;
    public function get_balance( int $user_id ): float;
    public function credit( int $user_id, float $amount, string $description ): bool;
    public function debit( int $user_id, float $amount, string $description ): bool;
    public function get_transactions( int $user_id, int $limit ): array;
}
```

## Template Overrides

Override plugin templates in your theme:

```
theme/
└── wp-sell-services/
    ├── single-service.php
    ├── archive-service.php
    ├── dashboard/
    │   ├── dashboard.php
    │   └── sections/
    │       ├── orders.php
    │       └── services.php
    └── order/
        └── order-view.php
```

## Database Tables

| Table | Purpose |
|-------|---------|
| `{prefix}wpss_orders` | Service orders |
| `{prefix}wpss_conversations` | Order conversations |
| `{prefix}wpss_messages` | Conversation messages |
| `{prefix}wpss_deliveries` | Work deliveries |
| `{prefix}wpss_reviews` | Ratings and reviews |
| `{prefix}wpss_disputes` | Dispute cases |
| `{prefix}wpss_service_packages` | Pricing packages |
| `{prefix}wpss_vendor_profiles` | Vendor data |
| `{prefix}wpss_order_requirements` | Order requirements |

## REST API

See [REST API Documentation](./api-reference.md) for complete endpoint reference.

### Base URL

```
/wp-json/wpss/v1/
```

### Authentication

Uses WordPress REST API authentication:
- Cookie authentication (logged-in users)
- Application passwords
- OAuth plugins

### Example Request

```bash
curl -X GET "https://yoursite.com/wp-json/wpss/v1/services" \
  -H "Content-Type: application/json"
```

## Coding Standards

- WordPress Coding Standards (WPCS)
- PHP 8.1+ features
- PSR-4 autoloading
- Text domain: `wp-sell-services`
- Prefix: `wpss_` or `WPSellServices`
