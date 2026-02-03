# Custom Integrations Guide

Extend WP Sell Services with custom e-commerce platforms, payment gateways, storage providers, and more. This guide covers building custom integrations using the plugin's extensible architecture.

## Overview

WP Sell Services supports custom integrations through:

- **E-commerce Adapters**: Add support for custom shopping cart platforms
- **Payment Gateways**: Integrate custom payment processors
- **Storage Providers**: Add cloud storage options for file uploads
- **REST API Controllers**: Create custom API endpoints
- **Settings Tabs**: Add custom admin settings pages
- **Gutenberg Blocks**: Register custom blocks
- **Analytics Widgets**: Add dashboard widgets

## E-Commerce Adapters

### Overview

E-commerce adapters connect WP Sell Services to shopping cart platforms. The free version includes WooCommerce adapter. Pro adds EDD, FluentCart, and SureCart.

### EcommerceAdapterInterface

All adapters must implement this interface:

```php
<?php
namespace WPSellServices\Integrations;

interface EcommerceAdapterInterface {
    /**
     * Get adapter name.
     *
     * @return string
     */
    public function get_name(): string;

    /**
     * Check if platform is active.
     *
     * @return bool
     */
    public function is_available(): bool;

    /**
     * Create product for service.
     *
     * @param int $service_id Service post ID.
     * @param array $data Product data.
     * @return int|false Product ID or false on failure.
     */
    public function create_product( int $service_id, array $data );

    /**
     * Update product.
     *
     * @param int $product_id Product ID.
     * @param array $data Updated data.
     * @return bool
     */
    public function update_product( int $product_id, array $data ): bool;

    /**
     * Delete product.
     *
     * @param int $product_id Product ID.
     * @return bool
     */
    public function delete_product( int $product_id ): bool;

    /**
     * Get product ID for service.
     *
     * @param int $service_id Service post ID.
     * @return int|null
     */
    public function get_product_id( int $service_id ): ?int;

    /**
     * Get order details.
     *
     * @param int $order_id Platform order ID.
     * @return array|null
     */
    public function get_order( int $order_id ): ?array;

    /**
     * Process refund.
     *
     * @param int $order_id Platform order ID.
     * @param float $amount Refund amount.
     * @param string $reason Refund reason.
     * @return bool
     */
    public function process_refund( int $order_id, float $amount, string $reason ): bool;

    /**
     * Get checkout URL for service.
     *
     * @param int $service_id Service post ID.
     * @param string $package Package tier (basic/standard/premium).
     * @return string
     */
    public function get_checkout_url( int $service_id, string $package ): string;
}
```

### Creating Custom Adapter

**Step 1: Create adapter class**

```php
<?php
namespace MyPlugin;

use WPSellServices\Integrations\EcommerceAdapterInterface;

class CustomCartAdapter implements EcommerceAdapterInterface {

    public function get_name(): string {
        return 'CustomCart';
    }

    public function is_available(): bool {
        // Check if CustomCart plugin is active
        return class_exists( 'CustomCart' );
    }

    public function create_product( int $service_id, array $data ) {
        // Create product in CustomCart
        $product = new \CustomCart\Product();
        $product->set_name( $data['title'] );
        $product->set_price( $data['price'] );
        $product->set_description( $data['description'] );

        // Link to service
        $product->set_meta( 'wpss_service_id', $service_id );

        $product_id = $product->save();

        if ( $product_id ) {
            // Store relationship
            update_post_meta( $service_id, '_customcart_product_id', $product_id );
            return $product_id;
        }

        return false;
    }

    public function update_product( int $product_id, array $data ): bool {
        $product = new \CustomCart\Product( $product_id );

        if ( isset( $data['title'] ) ) {
            $product->set_name( $data['title'] );
        }
        if ( isset( $data['price'] ) ) {
            $product->set_price( $data['price'] );
        }
        if ( isset( $data['description'] ) ) {
            $product->set_description( $data['description'] );
        }

        return $product->save() !== false;
    }

    public function delete_product( int $product_id ): bool {
        $product = new \CustomCart\Product( $product_id );
        return $product->delete();
    }

    public function get_product_id( int $service_id ): ?int {
        $product_id = get_post_meta( $service_id, '_customcart_product_id', true );
        return $product_id ? (int) $product_id : null;
    }

    public function get_order( int $order_id ): ?array {
        $order = new \CustomCart\Order( $order_id );

        if ( ! $order->exists() ) {
            return null;
        }

        return [
            'id' => $order->get_id(),
            'total' => $order->get_total(),
            'currency' => $order->get_currency(),
            'customer_id' => $order->get_customer_id(),
            'customer_email' => $order->get_customer_email(),
            'status' => $order->get_status(),
            'payment_method' => $order->get_payment_method(),
            'date_created' => $order->get_date_created(),
            'items' => $order->get_items(),
        ];
    }

    public function process_refund( int $order_id, float $amount, string $reason ): bool {
        $order = new \CustomCart\Order( $order_id );

        if ( ! $order->exists() ) {
            return false;
        }

        $refund = $order->create_refund( [
            'amount' => $amount,
            'reason' => $reason,
        ] );

        return $refund !== false;
    }

    public function get_checkout_url( int $service_id, string $package ): string {
        $product_id = $this->get_product_id( $service_id );

        if ( ! $product_id ) {
            return '';
        }

        return \CustomCart\Checkout::get_url( [
            'product_id' => $product_id,
            'package' => $package,
        ] );
    }
}
```

**Step 2: Register adapter**

```php
<?php
add_filter( 'wpss_ecommerce_adapters', function( $adapters ) {
    $adapters['customcart'] = 'MyPlugin\CustomCartAdapter';
    return $adapters;
} );
```

**Step 3: Activate adapter**

The adapter becomes available in **Settings → E-commerce → Platform**.

### Testing Adapter

```php
<?php
// Get adapter instance
$adapter = wpss_get_ecommerce_adapter(); // Returns active adapter

// Check availability
if ( $adapter->is_available() ) {
    echo 'CustomCart is available';
}

// Create product
$product_id = $adapter->create_product( $service_id, [
    'title' => 'Test Service',
    'price' => 100.00,
    'description' => 'Test description',
] );

// Get checkout URL
$url = $adapter->get_checkout_url( $service_id, 'basic' );
```

## Payment Gateways

### Overview

**[PRO]** Payment gateways process direct payments without e-commerce platforms. Used in standalone mode.

### PaymentGatewayInterface

```php
<?php
namespace WPSellServices\Integrations;

interface PaymentGatewayInterface {
    /**
     * Get gateway ID.
     *
     * @return string
     */
    public function get_id(): string;

    /**
     * Get gateway name.
     *
     * @return string
     */
    public function get_name(): string;

    /**
     * Check if gateway is configured and available.
     *
     * @return bool
     */
    public function is_available(): bool;

    /**
     * Create payment intent/session.
     *
     * @param array $data Payment data (amount, currency, etc.).
     * @return array Payment details (client_secret, redirect_url, etc.).
     */
    public function create_payment( array $data ): array;

    /**
     * Verify payment completion.
     *
     * @param string $payment_id Payment ID from gateway.
     * @return array Payment status and details.
     */
    public function verify_payment( string $payment_id ): array;

    /**
     * Process refund.
     *
     * @param string $payment_id Original payment ID.
     * @param float $amount Refund amount.
     * @param string $reason Refund reason.
     * @return bool
     */
    public function refund_payment( string $payment_id, float $amount, string $reason ): bool;

    /**
     * Get payment details.
     *
     * @param string $payment_id Payment ID.
     * @return array|null
     */
    public function get_payment( string $payment_id ): ?array;
}
```

### Creating Custom Gateway

```php
<?php
namespace MyPlugin;

use WPSellServices\Integrations\PaymentGatewayInterface;

class CustomPaymentGateway implements PaymentGatewayInterface {

    public function get_id(): string {
        return 'custom_gateway';
    }

    public function get_name(): string {
        return 'Custom Payment Gateway';
    }

    public function is_available(): bool {
        $api_key = get_option( 'custom_gateway_api_key' );
        return ! empty( $api_key );
    }

    public function create_payment( array $data ): array {
        $api_key = get_option( 'custom_gateway_api_key' );

        // Make API request to create payment
        $response = wp_remote_post( 'https://api.custompayment.com/v1/payments', [
            'headers' => [
                'Authorization' => "Bearer $api_key",
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode( [
                'amount' => $data['amount'] * 100, // Convert to cents
                'currency' => $data['currency'],
                'description' => $data['description'],
                'metadata' => [
                    'order_id' => $data['order_id'],
                ],
                'return_url' => $data['return_url'],
            ] ),
        ] );

        if ( is_wp_error( $response ) ) {
            return [
                'success' => false,
                'error' => $response->get_error_message(),
            ];
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        return [
            'success' => true,
            'payment_id' => $body['id'],
            'client_secret' => $body['client_secret'],
            'redirect_url' => $body['redirect_url'],
        ];
    }

    public function verify_payment( string $payment_id ): array {
        $api_key = get_option( 'custom_gateway_api_key' );

        $response = wp_remote_get(
            "https://api.custompayment.com/v1/payments/$payment_id",
            [
                'headers' => [
                    'Authorization' => "Bearer $api_key",
                ],
            ]
        );

        if ( is_wp_error( $response ) ) {
            return [
                'success' => false,
                'error' => $response->get_error_message(),
            ];
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        return [
            'success' => true,
            'status' => $body['status'], // 'succeeded', 'pending', 'failed'
            'amount' => $body['amount'] / 100,
            'currency' => $body['currency'],
            'payment_method' => $body['payment_method'],
        ];
    }

    public function refund_payment( string $payment_id, float $amount, string $reason ): bool {
        $api_key = get_option( 'custom_gateway_api_key' );

        $response = wp_remote_post(
            "https://api.custompayment.com/v1/refunds",
            [
                'headers' => [
                    'Authorization' => "Bearer $api_key",
                    'Content-Type' => 'application/json',
                ],
                'body' => json_encode( [
                    'payment_id' => $payment_id,
                    'amount' => $amount * 100,
                    'reason' => $reason,
                ] ),
            ]
        );

        if ( is_wp_error( $response ) ) {
            return false;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        return isset( $body['status'] ) && $body['status'] === 'succeeded';
    }

    public function get_payment( string $payment_id ): ?array {
        $verify_result = $this->verify_payment( $payment_id );

        if ( ! $verify_result['success'] ) {
            return null;
        }

        return $verify_result;
    }
}
```

**Register gateway:**

```php
<?php
add_filter( 'wpss_payment_gateways', function( $gateways ) {
    $gateways['custom_gateway'] = 'MyPlugin\CustomPaymentGateway';
    return $gateways;
} );
```

## Storage Providers

### Overview

**[PRO]** Storage providers handle file uploads for requirements, deliveries, and attachments. Default is local WordPress uploads. Pro adds cloud options.

### StorageProviderInterface

```php
<?php
namespace WPSellServices\Services;

interface StorageProviderInterface {
    /**
     * Upload file.
     *
     * @param string $file_path Local file path.
     * @param string $destination Remote destination path.
     * @return array Upload result with URL.
     */
    public function upload( string $file_path, string $destination ): array;

    /**
     * Delete file.
     *
     * @param string $file_path Remote file path.
     * @return bool
     */
    public function delete( string $file_path ): bool;

    /**
     * Get file URL.
     *
     * @param string $file_path Remote file path.
     * @param int $expires Expiration time in seconds (for signed URLs).
     * @return string
     */
    public function get_url( string $file_path, int $expires = 0 ): string;

    /**
     * Check if file exists.
     *
     * @param string $file_path Remote file path.
     * @return bool
     */
    public function exists( string $file_path ): bool;
}
```

### Creating Custom Storage Provider

```php
<?php
namespace MyPlugin;

use WPSellServices\Services\StorageProviderInterface;

class CustomStorageProvider implements StorageProviderInterface {

    private $api_key;
    private $bucket;

    public function __construct() {
        $this->api_key = get_option( 'custom_storage_api_key' );
        $this->bucket = get_option( 'custom_storage_bucket' );
    }

    public function upload( string $file_path, string $destination ): array {
        if ( ! file_exists( $file_path ) ) {
            return [
                'success' => false,
                'error' => 'File not found',
            ];
        }

        $file_content = file_get_contents( $file_path );
        $file_name = basename( $destination );

        // Upload to custom storage API
        $response = wp_remote_post(
            "https://api.customstorage.com/upload",
            [
                'headers' => [
                    'Authorization' => "Bearer {$this->api_key}",
                ],
                'body' => [
                    'bucket' => $this->bucket,
                    'file' => base64_encode( $file_content ),
                    'filename' => $file_name,
                    'path' => dirname( $destination ),
                ],
            ]
        );

        if ( is_wp_error( $response ) ) {
            return [
                'success' => false,
                'error' => $response->get_error_message(),
            ];
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        return [
            'success' => true,
            'url' => $body['url'],
            'file_id' => $body['id'],
        ];
    }

    public function delete( string $file_path ): bool {
        $response = wp_remote_request(
            "https://api.customstorage.com/delete",
            [
                'method' => 'DELETE',
                'headers' => [
                    'Authorization' => "Bearer {$this->api_key}",
                ],
                'body' => [
                    'bucket' => $this->bucket,
                    'path' => $file_path,
                ],
            ]
        );

        return ! is_wp_error( $response );
    }

    public function get_url( string $file_path, int $expires = 0 ): string {
        $url = "https://cdn.customstorage.com/{$this->bucket}/$file_path";

        if ( $expires > 0 ) {
            // Generate signed URL
            $expiry = time() + $expires;
            $signature = hash_hmac( 'sha256', "$file_path:$expiry", $this->api_key );
            $url .= "?expires=$expiry&signature=$signature";
        }

        return $url;
    }

    public function exists( string $file_path ): bool {
        $response = wp_remote_head(
            "https://api.customstorage.com/exists",
            [
                'headers' => [
                    'Authorization' => "Bearer {$this->api_key}",
                ],
                'body' => [
                    'bucket' => $this->bucket,
                    'path' => $file_path,
                ],
            ]
        );

        return wp_remote_retrieve_response_code( $response ) === 200;
    }
}
```

**Register provider:**

```php
<?php
add_filter( 'wpss_storage_providers', function( $providers ) {
    $providers['custom_storage'] = 'MyPlugin\CustomStorageProvider';
    return $providers;
} );
```

## REST API Controllers

### Creating Custom Endpoint

Add custom REST API endpoints to extend functionality.

```php
<?php
namespace MyPlugin;

use WP_REST_Controller;
use WP_REST_Server;
use WP_Error;

class CustomController extends WP_REST_Controller {

    protected $namespace = 'wpss/v1';
    protected $rest_base = 'custom';

    public function register_routes() {
        // GET /wp-json/wpss/v1/custom
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            [
                [
                    'methods' => WP_REST_Server::READABLE,
                    'callback' => [ $this, 'get_items' ],
                    'permission_callback' => [ $this, 'get_items_permissions_check' ],
                    'args' => $this->get_collection_params(),
                ],
            ]
        );

        // POST /wp-json/wpss/v1/custom
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            [
                [
                    'methods' => WP_REST_Server::CREATABLE,
                    'callback' => [ $this, 'create_item' ],
                    'permission_callback' => [ $this, 'create_item_permissions_check' ],
                    'args' => $this->get_endpoint_args_for_item_schema( WP_REST_Server::CREATABLE ),
                ],
            ]
        );

        // GET /wp-json/wpss/v1/custom/{id}
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/(?P<id>[\d]+)',
            [
                [
                    'methods' => WP_REST_Server::READABLE,
                    'callback' => [ $this, 'get_item' ],
                    'permission_callback' => [ $this, 'get_item_permissions_check' ],
                    'args' => [
                        'id' => [
                            'required' => true,
                            'type' => 'integer',
                        ],
                    ],
                ],
            ]
        );
    }

    public function get_items( $request ) {
        $items = []; // Your custom data retrieval logic

        return rest_ensure_response( [
            'items' => $items,
            'total' => count( $items ),
        ] );
    }

    public function get_items_permissions_check( $request ) {
        return current_user_can( 'read' );
    }

    public function create_item( $request ) {
        $params = $request->get_params();

        // Validate and create item
        $item_id = $this->create_custom_item( $params );

        if ( ! $item_id ) {
            return new WP_Error(
                'creation_failed',
                'Failed to create item',
                [ 'status' => 500 ]
            );
        }

        return rest_ensure_response( [
            'success' => true,
            'item_id' => $item_id,
        ] );
    }

    public function create_item_permissions_check( $request ) {
        return current_user_can( 'edit_posts' );
    }

    public function get_item( $request ) {
        $id = $request->get_param( 'id' );
        $item = $this->get_custom_item( $id );

        if ( ! $item ) {
            return new WP_Error(
                'not_found',
                'Item not found',
                [ 'status' => 404 ]
            );
        }

        return rest_ensure_response( $item );
    }

    public function get_item_permissions_check( $request ) {
        return current_user_can( 'read' );
    }

    private function create_custom_item( $data ) {
        // Your creation logic
        return 123;
    }

    private function get_custom_item( $id ) {
        // Your retrieval logic
        return [ 'id' => $id, 'data' => 'example' ];
    }
}
```

**Register controller:**

```php
<?php
add_filter( 'wpss_api_controllers', function( $controllers ) {
    $controllers[] = 'MyPlugin\CustomController';
    return $controllers;
} );
```

## Settings Tabs

### Adding Custom Settings Tab

```php
<?php
add_filter( 'wpss_settings_tabs', function( $tabs ) {
    $tabs['custom_settings'] = [
        'label' => 'Custom Settings',
        'callback' => 'render_custom_settings_tab',
        'priority' => 50,
    ];
    return $tabs;
} );

function render_custom_settings_tab() {
    ?>
    <div class="wpss-settings-section">
        <h2>Custom Settings</h2>
        <form method="post" action="options.php">
            <?php
            settings_fields( 'wpss_custom_settings' );
            do_settings_sections( 'wpss_custom_settings' );
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

// Register settings
add_action( 'admin_init', function() {
    register_setting( 'wpss_custom_settings', 'custom_option_1' );
    register_setting( 'wpss_custom_settings', 'custom_option_2' );

    add_settings_section(
        'custom_section',
        'Custom Options',
        '__return_empty_string',
        'wpss_custom_settings'
    );

    add_settings_field(
        'custom_option_1',
        'Option 1',
        function() {
            $value = get_option( 'custom_option_1', '' );
            echo '<input type="text" name="custom_option_1" value="' . esc_attr( $value ) . '" />';
        },
        'wpss_custom_settings',
        'custom_section'
    );
} );
```

## Gutenberg Blocks

### Registering Custom Block

```php
<?php
add_filter( 'wpss_blocks', function( $blocks ) {
    $blocks[] = [
        'name' => 'wpss/custom-block',
        'args' => [
            'title' => 'Custom Service Block',
            'description' => 'Display custom service data',
            'category' => 'wpss',
            'icon' => 'star-filled',
            'attributes' => [
                'serviceId' => [
                    'type' => 'number',
                ],
            ],
            'render_callback' => 'render_custom_block',
        ],
    ];
    return $blocks;
} );

function render_custom_block( $attributes ) {
    $service_id = $attributes['serviceId'] ?? 0;

    if ( ! $service_id ) {
        return '<p>Please select a service.</p>';
    }

    $service = get_post( $service_id );

    if ( ! $service ) {
        return '<p>Service not found.</p>';
    }

    ob_start();
    ?>
    <div class="wpss-custom-block">
        <h3><?php echo esc_html( $service->post_title ); ?></h3>
        <div><?php echo wp_kses_post( $service->post_content ); ?></div>
    </div>
    <?php
    return ob_get_clean();
}
```

## Analytics Widgets

### Adding Custom Dashboard Widget

```php
<?php
add_filter( 'wpss_analytics_widgets', function( $widgets ) {
    $widgets['custom_metric'] = [
        'title' => 'Custom Metric',
        'callback' => 'render_custom_widget',
        'priority' => 50,
    ];
    return $widgets;
} );

function render_custom_widget() {
    $metric_value = get_custom_metric_value();
    ?>
    <div class="wpss-widget">
        <h3>Custom Metric</h3>
        <div class="wpss-widget-value"><?php echo esc_html( $metric_value ); ?></div>
        <p class="wpss-widget-description">Your custom metric description</p>
    </div>
    <?php
}

function get_custom_metric_value() {
    // Your custom calculation
    return 1234;
}
```

## Helper Functions

### Accessing Core Services

```php
<?php
// Get plugin instance
$plugin = \WPSellServices\Core\Plugin::instance();

// Get services
$service_service = $plugin->get_service( 'service' );
$order_service = $plugin->get_service( 'order' );
$vendor_service = $plugin->get_service( 'vendor' );

// Get active e-commerce adapter
$adapter = wpss_get_ecommerce_adapter();

// Get active payment gateway
$gateway = wpss_get_payment_gateway();

// Get storage provider
$storage = wpss_get_storage_provider();
```

### Working with Services

```php
<?php
// Create service programmatically
$service_data = [
    'title' => 'Custom Service',
    'description' => 'Description here',
    'vendor_id' => 123,
    'category_id' => 5,
    'basic_price' => 100,
    'basic_delivery' => 7,
];

$service_id = wpss_create_service( $service_data );

// Get service
$service = wpss_get_service( $service_id );

// Update service
wpss_update_service( $service_id, [
    'basic_price' => 150,
] );
```

## Best Practices

### Error Handling

Always implement proper error handling:

```php
<?php
try {
    $result = $adapter->create_product( $service_id, $data );

    if ( ! $result ) {
        throw new \Exception( 'Failed to create product' );
    }
} catch ( \Exception $e ) {
    error_log( 'WP Sell Services Error: ' . $e->getMessage() );
    return new WP_Error( 'creation_failed', $e->getMessage() );
}
```

### Security

Validate and sanitize all inputs:

```php
<?php
public function create_item( $request ) {
    $title = sanitize_text_field( $request->get_param( 'title' ) );
    $amount = floatval( $request->get_param( 'amount' ) );

    if ( empty( $title ) || $amount <= 0 ) {
        return new WP_Error(
            'invalid_data',
            'Invalid input data',
            [ 'status' => 400 ]
        );
    }

    // Process validated data
}
```

### Capability Checks

Always verify permissions:

```php
<?php
if ( ! current_user_can( 'wpss_manage_services' ) ) {
    return new WP_Error(
        'forbidden',
        'You do not have permission to perform this action',
        [ 'status' => 403 ]
    );
}
```

## Testing Integrations

### Unit Testing

```php
<?php
class CustomAdapterTest extends WP_UnitTestCase {

    private $adapter;

    public function setUp() {
        parent::setUp();
        $this->adapter = new \MyPlugin\CustomCartAdapter();
    }

    public function test_is_available() {
        $this->assertTrue( $this->adapter->is_available() );
    }

    public function test_create_product() {
        $service_id = $this->factory->post->create( [
            'post_type' => 'wpss_service',
        ] );

        $product_id = $this->adapter->create_product( $service_id, [
            'title' => 'Test Service',
            'price' => 100,
        ] );

        $this->assertNotFalse( $product_id );
        $this->assertIsInt( $product_id );
    }
}
```

## Related Documentation

- [Hooks and Filters](hooks-filters.md) - Complete hooks reference
- [REST API](rest-api.md) - API endpoints and authentication
- [Plugin Architecture](../architecture/plugin-architecture.md) - Core structure
