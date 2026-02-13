# WP Sell Services for Developers: API-First Marketplace Architecture

**SEO Title:** REST API and Developer Features in WP Sell Services WordPress Plugin

**Meta Description:** Build mobile apps and custom integrations with WP Sell Services. Twenty REST API controllers, Gutenberg blocks, WP-CLI commands, and 100+ hooks make it developer-friendly.

---

Service marketplace platforms need robust APIs and extensibility. Whether you're building a mobile app, integrating with external systems, or customizing marketplace behavior, you need clean architecture and comprehensive developer tools.

WP Sell Services was built API-first with developers in mind. Here's everything you need to know about the technical architecture.

## REST API: Twenty Controllers for Complete Coverage

Every marketplace feature is accessible through REST API endpoints under `/wp-json/wpss/v1/`. No AJAX handlers - everything uses WordPress REST API standards.

**Core Controllers:**
- ServicesController - CRUD, search, featured services
- OrdersController - Lifecycle management, status transitions
- ReviewsController - Ratings with multi-criteria support
- VendorsController - Profiles, statistics, service listings
- ConversationsController - Order messaging with attachments
- DisputesController - Dispute workflow and resolution
- BuyerRequestsController - Job posting system
- ProposalsController - Vendor proposal management

**Additional Controllers:**
- NotificationsController - In-app notifications
- PortfolioController - Vendor work samples
- EarningsController - Revenue tracking and withdrawals
- ExtensionRequestsController - Deadline extensions
- MilestonesController - Milestone-based payments
- TippingController - Optional tipping system
- SellerLevelsController - Tier definitions and progress
- ModerationController - Admin approval queues
- FavoritesController - Buyer wishlist
- MediaController - File upload and management
- CartController - Shopping cart operations
- AuthController - Session management (Pro)

### Authentication Methods

**Cookie Authentication** for same-origin browser requests:

```javascript
const nonce = wpApiSettings.nonce; // From wp_localize_script

fetch('/wp-json/wpss/v1/services', {
    credentials: 'same-origin',
    headers: {
        'X-WP-Nonce': nonce
    }
});
```

**Application Passwords** for external integrations (WordPress 5.6+):

```bash
curl -X GET \
  https://yoursite.com/wp-json/wpss/v1/orders \
  -u "username:xxxx xxxx xxxx xxxx"
```

**JWT Tokens** (Pro, requires third-party JWT plugin) for mobile apps.

### Batch Endpoint for Mobile Apps

Execute up to 25 API requests in a single HTTP call:

```javascript
POST /wp-json/wpss/v1/batch

{
  "requests": [
    {
      "method": "GET",
      "path": "/wpss/v1/services?per_page=5"
    },
    {
      "method": "GET",
      "path": "/wpss/v1/vendors?per_page=5"
    },
    {
      "method": "POST",
      "path": "/wpss/v1/favorites",
      "body": {
        "service_id": 123
      }
    }
  ]
}
```

Responses include status and body for each sub-request. Failed requests don't stop batch processing. Perfect for reducing round trips in mobile environments.

### Pagination and Headers

All list endpoints support standard WordPress pagination:

```
GET /wpss/v1/services?page=2&per_page=20
```

Response headers include total count and page links:

```
X-WP-Total: 156
X-WP-TotalPages: 8
Link: <url?page=3>; rel="next", <url?page=8>; rel="last"
```

### Error Handling

Standard WordPress REST API error format:

```json
{
  "code": "invalid_request",
  "message": "Missing required parameter: service_id",
  "data": {
    "status": 400,
    "params": {
      "service_id": "required"
    }
  }
}
```

Common codes: `rest_forbidden` (401), `rest_forbidden_context` (403), `invalid_request` (400), `not_found` (404), `server_error` (500).

## Gutenberg Blocks: Six Blocks for Page Building

All blocks are grouped under the "WP Sell Services" category in the block inserter.

**Service Grid Block** - Display services with filters:

```php
// Equivalent shortcode
[wpss_services category="5" limit="12" columns="4" orderby="rating"]
```

**Service Search Block** - Search form with autocomplete:

```php
[wpss_service_search placeholder="Find services..." show_categories="true"]
```

**Service Categories Block** - Category directory with icons:

```php
[wpss_service_categories columns="4" show_count="true" limit="12"]
```

**Featured Services Block** - Highlight premium services:

```php
[wpss_featured_services limit="8" columns="4"]
```

**Seller Card Block** - Vendor profile display:

```php
[wpss_vendor_profile id="42"]
```

**Buyer Requests Block** - Job listings:

```php
[wpss_buyer_requests limit="10" category="5"]
```

Each block provides visual editing with sidebar controls. Blocks render shortcodes internally, so you can filter output using shortcode hooks.

## Template Override System

Copy any template to your theme for customization:

```
theme/
└── wp-sell-services/
    ├── archive-service.php
    ├── single-service.php
    ├── vendor-profile.php
    └── dashboard/
        ├── overview.php
        └── orders.php
```

Template hierarchy follows WordPress standards:

```php
// Theme override
wp-content/themes/your-theme/wp-sell-services/single-service.php

// Plugin default
wp-content/plugins/wp-sell-services/templates/single-service.php
```

Filter template paths:

```php
add_filter( 'wpss_locate_template', function( $template, $template_name, $template_path ) {
    if ( $template_name === 'single-service.php' ) {
        return get_stylesheet_directory() . '/custom-service.php';
    }
    return $template;
}, 10, 3 );
```

## Hooks and Filters: Over 100 Extension Points

### Essential Action Hooks

**Plugin Lifecycle:**

```php
// Plugin loaded - register extensions here
add_action( 'wpss_loaded', function( $plugin ) {
    // Your code
}, 10, 1 );

// E-commerce adapter initialized
add_action( 'wpss_adapter_initialized', function( $adapter ) {
    // Adapter-specific setup
}, 10, 1 );
```

**Order Workflow:**

```php
// Order status changed
add_action( 'wpss_order_status_changed', function( $order_id, $new_status, $old_status ) {
    // Track status transitions
}, 10, 3 );

// Specific status reached
add_action( 'wpss_order_status_completed', function( $order_id, $old_status ) {
    // Order completed actions
}, 10, 2 );

// Order delivered
add_action( 'wpss_order_delivered', function( $order_id ) {
    // Delivery notifications
}, 10, 1 );
```

**Service Events:**

```php
// Service created
add_action( 'wpss_service_created', function( $post_id, $data ) {
    // New service actions
}, 10, 2 );

// Service approved (moderation)
add_action( 'wpss_service_approved', function( $service_id, $notes ) {
    // Approval notifications
}, 10, 2 );
```

**Financial Events:**

```php
// Commission recorded
add_action( 'wpss_commission_recorded', function( $order_id, $commission, $vendor_id ) {
    // Commission tracking
}, 10, 3 );

// Withdrawal requested
add_action( 'wpss_withdrawal_requested', function( $withdrawal_id, $vendor_id, $amount ) {
    // Withdrawal notifications
}, 10, 3 );
```

### Essential Filters

**Register Extensions:**

```php
// Add custom payment gateway
add_filter( 'wpss_payment_gateways', function( $gateways ) {
    $gateways['custom'] = new CustomGateway();
    return $gateways;
} );

// Add custom e-commerce adapter
add_filter( 'wpss_ecommerce_adapters', function( $adapters ) {
    $adapters['custom'] = new CustomAdapter();
    return $adapters;
} );

// Add custom REST API controller
add_filter( 'wpss_api_controllers', function( $controllers ) {
    $controllers[] = new CustomController();
    return $controllers;
} );
```

**Modify Data:**

```php
// Change commission rate dynamically
add_filter( 'wpss_commission_rate', function( $rate, $order, $vendor_id, $service_id ) {
    // Return custom rate based on conditions
    return $rate;
}, 10, 4 );

// Modify order statuses
add_filter( 'wpss_order_statuses', function( $statuses ) {
    $statuses['custom_status'] = 'Custom Status';
    return $statuses;
} );

// Change service creation limits
add_filter( 'wpss_service_max_gallery', function( $max ) {
    return 10; // Increase gallery limit
} );
```

**Template Control:**

```php
// Override template location
add_filter( 'wpss_get_template', function( $template, $template_name, $args ) {
    // Return custom template path
    return $template;
}, 10, 3 );
```

## WP-CLI Commands

Manage marketplace from command line:

```bash
# List all services
wp wpss service list

# Create test service
wp wpss service create --vendor_id=5 --title="Test Service" --price=100

# Update order status
wp wpss order update 123 --status=completed

# Approve vendor
wp wpss vendor approve 42

# Process pending withdrawals
wp wpss earnings process-withdrawals

# Generate test data
wp wpss generate services --count=50
```

WP-CLI commands support bulk operations and automation scripts. Perfect for development, testing, and scheduled tasks.

## Database Architecture

Seventeen custom tables for optimal performance:

**Core Tables:**
- `wpss_orders` - Service orders
- `wpss_service_packages` - Pricing tiers
- `wpss_conversations` - Order messages
- `wpss_deliveries` - Final deliveries
- `wpss_reviews` - Ratings and reviews
- `wpss_disputes` - Dispute cases

**Financial Tables:**
- `wpss_earnings` - Vendor earnings
- `wpss_withdrawals` - Payout requests
- `wpss_commissions` - Platform fees

**Request System:**
- `wpss_buyer_requests` - Job postings
- `wpss_proposals` - Vendor proposals

**Features Tables:**
- `wpss_service_addons` - Service extras
- `wpss_requirements` - Order requirements
- `wpss_portfolio` - Vendor portfolio items
- `wpss_notifications` - In-app notifications
- `wpss_favorites` - Buyer wishlist
- `wpss_milestones` - Payment milestones

Custom tables provide better performance than post meta for transaction data. All tables use proper indexes and foreign key relationships.

## Code Architecture

PSR-4 autoloaded namespaces with clean separation:

```php
WPSellServices\               # Root namespace
├── Core\                     # Plugin bootstrap
├── Models\                   # Data models
├── Services\                 # Business logic
├── Integrations\             # E-commerce adapters
├── Admin\                    # Admin functionality
├── Frontend\                 # Frontend functionality
├── API\                      # REST controllers
└── Blocks\                   # Gutenberg blocks
```

All classes follow WordPress Coding Standards with PHP 8.1+ features (typed properties, enums, constructor property promotion).

## Extending with Pro Features

Pro plugin extends free plugin through hooks - no code duplication:

```php
// In Pro plugin
add_action( 'wpss_loaded', function( $plugin ) {
    // Check license
    if ( ! License::is_valid() ) {
        return;
    }

    // Register Pro features
    add_filter( 'wpss_ecommerce_adapters', function( $adapters ) {
        $adapters['edd'] = new EDDAdapter();
        $adapters['fluent'] = new FluentAdapter();
        return $adapters;
    } );

    // Register Pro controllers
    add_filter( 'wpss_api_controllers', function( $controllers ) {
        $controllers[] = new WalletController();
        $controllers[] = new PaymentController();
        return $controllers;
    } );
} );
```

## Building a Mobile App

Complete REST API makes mobile app development straightforward:

**Authentication:** Use Application Passwords or JWT tokens

**Batch Requests:** Reduce HTTP round trips with `/batch` endpoint

**Pagination:** All list endpoints support `page` and `per_page` parameters

**File Uploads:** Use `MediaController` for attachment uploads

**Push Notifications:** Integrate with in-app notification system

**Offline Support:** Cache responses and sync when online

## CORS Support

CORS headers automatically added for `/wp-json/wpss/` namespace:

```php
// Allow mobile app domain
add_filter( 'wpss_api_cors_origins', function( $origins ) {
    $origins[] = 'https://mobile-app.example.com';
    return $origins;
} );
```

## Rate Limiting (Pro)

API rate limiting protects against abuse:

- Authenticated users: 300 requests/hour
- Application passwords: 1000 requests/hour
- Administrators: Unlimited

Rate limit headers included in responses:

```
X-RateLimit-Limit: 300
X-RateLimit-Remaining: 245
X-RateLimit-Reset: 1706785200
```

## SEO Features

JSON-LD schema markup for services, vendors, and categories. Compatible with Yoast SEO and RankMath. Custom breadcrumbs and Open Graph data for social sharing.

## Testing and Development

Sandbox mode for payment gateways. Test order creation without real transactions. WP-CLI commands generate test data. REST API available for automated testing.

## Documentation

Comprehensive developer documentation included covering:

- REST API endpoints with request/response examples
- All action hooks with parameters and file locations
- All filter hooks with use cases
- Template hierarchy and override examples
- Code architecture and design patterns
- Extension examples and patterns

## Get Started

Install WP Sell Services from WordPress.org, explore the REST API at `/wp-json/wpss/v1/`, and read the full developer documentation in the plugin dashboard.

Build custom integrations, mobile apps, and extensions on a solid marketplace foundation.

---

**Resources:**
- API Documentation: In plugin dashboard
- Source Code: Available in plugin installation
- Support: WordPress.org forums
- Pro Features: [wbcomdesigns.com/downloads/wp-sell-services-pro](https://wbcomdesigns.com/downloads/wp-sell-services-pro)
