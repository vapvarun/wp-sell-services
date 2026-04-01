# Custom Integrations Guide

Extend WP Sell Services with custom e-commerce platforms, payment gateways, REST API controllers, and more. This guide documents the actual interfaces and extension patterns available in the plugin source code.

## Extension Architecture

WP Sell Services provides six contract interfaces in `src/Integrations/Contracts/` and several filter-based registration points. The free version ships with standalone checkout, Stripe, and PayPal. The Pro version adds WooCommerce, EDD, FluentCart, SureCart, and Razorpay.

| Extension Type | Interface/Filter | Free | Pro |
|---------------|-----------------|------|-----|
| E-commerce Platform | `EcommerceAdapterInterface` | Standalone (built-in) | WooCommerce, EDD, FluentCart, SureCart **[PRO]** |
| Payment Gateway | `PaymentGatewayInterface` | Stripe, PayPal, Offline | Razorpay **[PRO]** |
| Storage Provider | `wpss_storage_providers` | Local uploads | S3, GCS, DigitalOcean Spaces **[PRO]** |
| Email Provider | `wpss_email_providers` | WordPress mail | SendGrid, Mailgun, SES |
| REST API Controller | `wpss_api_controllers` | 21 controllers | Additional endpoints |
| Analytics Widget | `wpss_analytics_widgets` | Basic stats | Revenue, conversion, vendor analytics **[PRO]** |

## E-Commerce Adapters

### EcommerceAdapterInterface

Located at `src/Integrations/Contracts/EcommerceAdapterInterface.php`. All e-commerce integrations must implement this interface, which delegates to four specialized providers.

```php
interface EcommerceAdapterInterface {
    public function get_id(): string;
    public function get_name(): string;
    public function is_active(): bool;
    public function init(): void;
    public function supports_feature( string $feature ): bool;
    public function get_order_provider(): OrderProviderInterface;
    public function get_product_provider(): ProductProviderInterface;
    public function get_checkout_provider(): CheckoutProviderInterface;
    public function get_account_provider(): AccountProviderInterface;
}
```

### Provider Interfaces

**OrderProviderInterface** (`src/Integrations/Contracts/OrderProviderInterface.php`) -- Handles order data retrieval: `get_order()`, `get_order_item()`, `get_customer_orders()`, `get_vendor_orders()`, `has_service_items()`, `get_service_items()`, `update_item_meta()`, `get_item_meta()`, `get_customer_data()`, `handle_order_complete()`.

**ProductProviderInterface** (`src/Integrations/Contracts/ProductProviderInterface.php`) -- Handles service-to-product mapping: `is_service_product()`, `get_service()`, `get_service_vendors()`, `get_requirements()`, `get_delivery_time()`, `set_service_type()`, `add_service_type_option()`, `save_service_meta()`, `sync_with_service()`.

**CheckoutProviderInterface** (`src/Integrations/Contracts/CheckoutProviderInterface.php`) -- Handles cart and checkout: `add_cart_item_data()`, `validate_add_to_cart()`, `get_checkout_url()`, `cart_has_services()`, `get_cart_services()`, `process_checkout()`, `get_thankyou_redirect()`, `filter_quantity_max()`.

**AccountProviderInterface** (`src/Integrations/Contracts/AccountProviderInterface.php`) -- Handles user account integration: `add_menu_items()`, `register_endpoints()`, `get_account_url()`, `get_orders_url()`, `get_vendor_dashboard_url()`, `render_orders_endpoint()`, `render_services_endpoint()`, `render_notifications_endpoint()`, `can_access_vendor_dashboard()`, `get_login_url()`, `get_register_url()`.

### Creating a Custom Adapter

```php
<?php
namespace MyPlugin;

use WPSellServices\Integrations\Contracts\EcommerceAdapterInterface;

class CustomPlatformAdapter implements EcommerceAdapterInterface {
    public function get_id(): string { return 'custom_platform'; }
    public function get_name(): string { return 'Custom Platform'; }
    public function is_active(): bool { return class_exists( 'CustomPlatform' ); }
    public function init(): void { /* Register platform hooks */ }
    public function supports_feature( string $feature ): bool {
        return in_array( $feature, [ 'checkout', 'orders' ], true );
    }
    public function get_order_provider(): OrderProviderInterface { return new CustomOrderProvider(); }
    public function get_product_provider(): ProductProviderInterface { return new CustomProductProvider(); }
    public function get_checkout_provider(): CheckoutProviderInterface { return new CustomCheckoutProvider(); }
    public function get_account_provider(): AccountProviderInterface { return new CustomAccountProvider(); }
}

// Register via filter
add_filter( 'wpss_ecommerce_adapters', function( $adapters ) {
    $adapters['custom_platform'] = new \MyPlugin\CustomPlatformAdapter();
    return $adapters;
} );
```

### Adapter Selection Logic

The `IntegrationManager` (`src/Integrations/IntegrationManager.php`) selects the active adapter:

1. Reads `ecommerce_platform` from `wpss_general` settings
2. If set to a specific adapter ID and that adapter's `is_active()` is true, uses it
3. If set to `'auto'` (default), iterates all registered adapters and uses the first active one
4. After selection, calls `$adapter->init()` and fires `wpss_adapter_initialized`

## Payment Gateways

### PaymentGatewayInterface

Located at `src/Integrations/Contracts/PaymentGatewayInterface.php`. Used for standalone payment processing without an e-commerce platform.

```php
interface PaymentGatewayInterface {
    public function get_id(): string;
    public function get_name(): string;
    public function get_description(): string;
    public function is_enabled(): bool;
    public function supports_currency( string $currency ): bool;
    public function init(): void;
    public function create_payment( float $amount, string $currency, array $metadata = [] ): array;
    public function process_payment( string $payment_id ): array;
    public function process_refund( string $transaction_id, ?float $amount = null, string $reason = '' ): array;
    public function handle_webhook( array $payload ): array;
    public function get_settings_fields(): array;
    public function render_payment_form( float $amount, string $currency, int $order_id ): string;
}
```

### Creating a Custom Gateway

The plugin includes a reference implementation at `src/Integrations/Gateways/TestGateway.php` (debug-only, auto-completes payments). Register your gateway via the `wpss_payment_gateways` filter (`Plugin.php:813`):

```php
add_filter( 'wpss_payment_gateways', function( $gateways ) {
    $gateways['custom_pay'] = new \MyPlugin\CustomGateway();
    return $gateways;
} );
```

Key methods to implement:
- `create_payment()` should return `['success' => true, 'id' => '...', 'client_secret' => '...']`
- `process_payment()` should return `['success' => true, 'transaction_id' => '...', 'status' => 'completed']`
- `process_refund()` should return `['success' => true, 'refund_id' => '...', 'status' => 'completed']`
- `get_settings_fields()` returns an array of field definitions (type, label)
- `render_payment_form()` returns HTML for the payment form

## REST API Controllers

Custom controllers extend `RestController` (`src/API/RestController.php`) which provides:

- `check_permissions( $request )` -- Verifies user is logged in (returns 401 if not)
- `check_admin_permissions( $request )` -- Verifies `manage_options` capability (returns 403 if not)
- `user_owns_resource( $resource_id, $resource_type )` -- Checks ownership for `'service'` or `'order'`
- `paginated_response( $items, $total, $page, $per_page )` -- Returns paginated response with `X-WP-Total` and `X-WP-TotalPages` headers

```php
<?php
namespace MyPlugin;
use WPSellServices\API\RestController;

class CustomController extends RestController {
    protected $rest_base = 'custom';

    public function register_routes() {
        register_rest_route( $this->namespace, '/' . $this->rest_base, [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_items' ],
                'permission_callback' => [ $this, 'check_permissions' ],
            ],
        ] );
    }

    public function get_items( \WP_REST_Request $request ): \WP_REST_Response {
        $items = []; // Your data retrieval logic
        return $this->paginated_response( $items, 0, 1, 10 );
    }
}

// Register the controller (filter at API.php:76)
add_filter( 'wpss_api_controllers', function( $controllers ) {
    $controllers[] = new \MyPlugin\CustomController();
    return $controllers;
} );
```

## Provider Registration Filters **[PRO]**

```php
// Storage providers (Plugin.php:837)
add_filter( 'wpss_storage_providers', function( $providers ) {
    $providers['custom_storage'] = new \MyPlugin\CustomStorageProvider();
    return $providers;
} );

// Email providers (Plugin.php:849)
add_filter( 'wpss_email_providers', function( $providers ) {
    $providers['custom_email'] = new \MyPlugin\CustomEmailProvider();
    return $providers;
} );

// Wallet providers (Plugin.php:825)
add_filter( 'wpss_wallet_providers', function( $providers ) {
    $providers['custom_wallet'] = new \MyPlugin\CustomWalletProvider();
    return $providers;
} );

// Analytics widgets (Plugin.php:861)
add_filter( 'wpss_analytics_widgets', function( $widgets ) {
    $widgets['custom_metric'] = new \MyPlugin\CustomAnalyticsWidget();
    return $widgets;
} );
```

## Settings Tabs

Add custom tabs to admin settings using `wpss_settings_tabs` filter (`Settings.php:161`) and the dynamic `wpss_settings_tab_{tab}` action (`Settings.php:985`):

```php
add_filter( 'wpss_settings_tabs', function( $tabs ) {
    $tabs['my_integration'] = 'My Integration';
    return $tabs;
} );

add_action( 'wpss_settings_tab_my_integration', function() {
    echo '<div class="wpss-settings-section"><h2>My Settings</h2>';
    // Your settings form here
    echo '</div>';
} );
```

## Custom Field Types

Register custom field types for service requirements via the `wpss_register_field_types` action (`FieldManager.php:59`). Default types: Text, Textarea, Select, MultiSelect, Radio, Checkbox, FileUpload, Date, Number.

```php
add_action( 'wpss_register_field_types', function( $manager ) {
    $manager->register( new \MyPlugin\ColorPickerField() );
} );
```

## Gutenberg Blocks

Register custom blocks via `wpss_blocks` filter (`BlocksManager.php:93`):

```php
add_filter( 'wpss_blocks', function( $blocks ) {
    $blocks[] = [
        'name' => 'wpss/custom-block',
        'args' => [ 'title' => 'Custom Block', 'category' => 'wpss', 'render_callback' => 'render_my_block' ],
    ];
    return $blocks;
} );
```

## How Pro Extends Free

The Pro plugin hooks into `wpss_loaded` to register all extensions:

| What Pro Changes | Filter | Free Default | Pro Value |
|-----------------|--------|-------------|-----------|
| Gallery images | `wpss_service_max_gallery` | 4 | -1 (unlimited) |
| Service extras | `wpss_service_max_extras` | 3 | -1 (unlimited) |
| FAQ items | `wpss_service_max_faq` | 5 | -1 (unlimited) |
| Video URLs | `wpss_service_max_videos` | 1 | 3 |
| Requirements | `wpss_service_max_requirements` | 5 | -1 (unlimited) |
| Wizard features | `wpss_service_wizard_features` | All false | AI titles, templates |

## Vendor Capabilities

The `wpss_vendor` role includes: `wpss_vendor`, `wpss_manage_services`, `wpss_manage_orders`, `wpss_view_analytics`, `wpss_respond_to_requests`, `read`, `upload_files`, `edit_posts`.

## Related Documentation

- [Hooks and Filters](hooks-filters.md) - Complete hooks reference with file locations
- [REST API](rest-api.md) - API endpoints and authentication
- [Theme Integration](theme-integration.md) - Template overrides and styling
