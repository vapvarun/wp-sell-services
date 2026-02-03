# Hooks and Filters Reference

WP Sell Services provides extensive hooks and filters for developers to customize and extend functionality. This reference documents all available actions and filters organized by category.

## Using Hooks

### Actions

Actions let you execute custom code at specific points:

```php
add_action( 'wpss_service_created', 'my_custom_function', 10, 2 );

function my_custom_function( $service_id, $service_data ) {
    // Your custom code here
}
```

### Filters

Filters let you modify data before it's used:

```php
add_filter( 'wpss_review_window_days', 'my_custom_filter' );

function my_custom_filter( $days ) {
    return 14; // Change review window to 14 days
}
```

## Plugin Lifecycle Hooks

### wpss_loaded

Fires after WP Sell Services is fully loaded.

**Parameters:** None

**Example:**
```php
add_action( 'wpss_loaded', function() {
    // Plugin is ready, safe to use all functions
    if ( class_exists( 'WPSellServices\Core\Plugin' ) ) {
        // Your initialization code
    }
} );
```

**Use cases:**
- Initialize custom integrations
- Register custom post types or taxonomies
- Set up third-party plugin compatibility

## Service Hooks

### wpss_service_created

Fires when a new service is created.

**Parameters:**
- `int $service_id` - The service post ID
- `array $service_data` - Service metadata

**Example:**
```php
add_action( 'wpss_service_created', function( $service_id, $service_data ) {
    // Send notification to admin
    wp_mail(
        get_option( 'admin_email' ),
        'New Service Created',
        "Service ID: $service_id"
    );
}, 10, 2 );
```

### wpss_service_updated

Fires when a service is updated.

**Parameters:**
- `int $service_id` - The service post ID
- `array $service_data` - Updated service metadata
- `array $old_data` - Previous service metadata

**Example:**
```php
add_action( 'wpss_service_updated', function( $service_id, $service_data, $old_data ) {
    // Log price changes
    if ( $service_data['basic_price'] !== $old_data['basic_price'] ) {
        error_log( "Service $service_id price changed" );
    }
}, 10, 3 );
```

### wpss_before_service_deleted

Fires before a service is deleted.

**Parameters:**
- `int $service_id` - The service post ID

**Example:**
```php
add_action( 'wpss_before_service_deleted', function( $service_id ) {
    // Archive service data before deletion
    $archive = get_post_meta( $service_id );
    update_option( "archived_service_$service_id", $archive );
} );
```

### wpss_service_approved

Fires when admin approves a pending service.

**Parameters:**
- `int $service_id` - The service post ID
- `int $vendor_id` - The vendor user ID

**Example:**
```php
add_action( 'wpss_service_approved', function( $service_id, $vendor_id ) {
    // Award points for first approved service
    $vendor = get_user_by( 'ID', $vendor_id );
    $vendor_meta = get_user_meta( $vendor_id, 'wpss_vendor_data', true );

    if ( $vendor_meta['approved_services'] === 1 ) {
        // First service bonus
        update_user_meta( $vendor_id, 'welcome_bonus_awarded', true );
    }
}, 10, 2 );
```

### wpss_service_rejected

Fires when admin rejects a pending service.

**Parameters:**
- `int $service_id` - The service post ID
- `int $vendor_id` - The vendor user ID
- `string $reason` - Rejection reason

**Example:**
```php
add_action( 'wpss_service_rejected', function( $service_id, $vendor_id, $reason ) {
    // Send custom rejection email with guidelines
    $vendor = get_user_by( 'ID', $vendor_id );
    $subject = 'Service Submission Feedback';
    $message = "Your service was not approved. Reason: $reason";

    wp_mail( $vendor->user_email, $subject, $message );
}, 10, 3 );
```

### wpss_service_pending_moderation

Fires when a service is submitted for moderation.

**Parameters:**
- `int $service_id` - The service post ID
- `int $vendor_id` - The vendor user ID

**Example:**
```php
add_action( 'wpss_service_pending_moderation', function( $service_id, $vendor_id ) {
    // Add to moderation queue
    $queue = get_option( 'wpss_moderation_queue', [] );
    $queue[] = [
        'service_id' => $service_id,
        'vendor_id' => $vendor_id,
        'submitted' => current_time( 'timestamp' ),
    ];
    update_option( 'wpss_moderation_queue', $queue );
}, 10, 2 );
```

## Order Hooks

### wpss_order_status_changed

Fires when any order status changes.

**Parameters:**
- `int $order_id` - The order ID
- `string $old_status` - Previous status
- `string $new_status` - New status
- `object $order` - Order object

**Example:**
```php
add_action( 'wpss_order_status_changed', function( $order_id, $old_status, $new_status, $order ) {
    // Log all status changes
    error_log( "Order $order_id: $old_status → $new_status" );
}, 10, 4 );
```

### wpss_order_status_{status}

Fires when order changes to specific status. Replace `{status}` with: `pending`, `accepted`, `in_progress`, `delivered`, `completed`, `cancelled`, `disputed`, `in_revision`.

**Parameters:**
- `int $order_id` - The order ID
- `object $order` - Order object

**Example:**
```php
add_action( 'wpss_order_status_in_progress', function( $order_id, $order ) {
    // Start tracking time when work begins
    update_post_meta( $order_id, '_work_started', current_time( 'timestamp' ) );
}, 10, 2 );
```

### wpss_order_accepted

Fires when vendor accepts an order.

**Parameters:**
- `int $order_id` - The order ID
- `int $vendor_id` - The vendor user ID

**Example:**
```php
add_action( 'wpss_order_accepted', function( $order_id, $vendor_id ) {
    // Update vendor statistics
    $stats = get_user_meta( $vendor_id, 'wpss_stats', true );
    $stats['acceptance_rate']++;
    update_user_meta( $vendor_id, 'wpss_stats', $stats );
}, 10, 2 );
```

### wpss_order_rejected

Fires when vendor rejects an order.

**Parameters:**
- `int $order_id` - The order ID
- `int $vendor_id` - The vendor user ID
- `string $reason` - Rejection reason

**Example:**
```php
add_action( 'wpss_order_rejected', function( $order_id, $vendor_id, $reason ) {
    // Track rejection reasons for analysis
    $reasons = get_option( 'wpss_rejection_reasons', [] );
    $reasons[] = [
        'vendor_id' => $vendor_id,
        'reason' => $reason,
        'date' => current_time( 'mysql' ),
    ];
    update_option( 'wpss_rejection_reasons', $reasons );
}, 10, 3 );
```

### wpss_order_started

Fires when vendor starts working on order.

**Parameters:**
- `int $order_id` - The order ID
- `int $vendor_id` - The vendor user ID

### wpss_order_delivered

Fires when vendor submits delivery.

**Parameters:**
- `int $order_id` - The order ID
- `int $vendor_id` - The vendor user ID
- `array $delivery_data` - Delivery files and message

### wpss_order_completed

Fires when buyer accepts delivery and order completes.

**Parameters:**
- `int $order_id` - The order ID
- `int $buyer_id` - The buyer user ID

**Example:**
```php
add_action( 'wpss_order_completed', function( $order_id, $buyer_id ) {
    // Award loyalty points to buyer
    $points = get_user_meta( $buyer_id, 'loyalty_points', true );
    $order_value = get_post_meta( $order_id, '_order_total', true );
    $points += floor( $order_value / 10 ); // 10% back in points
    update_user_meta( $buyer_id, 'loyalty_points', $points );
}, 10, 2 );
```

### wpss_order_cancelled

Fires when order is cancelled.

**Parameters:**
- `int $order_id` - The order ID
- `int $cancelled_by` - User ID who cancelled
- `string $reason` - Cancellation reason

### wpss_order_disputed

Fires when order is disputed.

**Parameters:**
- `int $order_id` - The order ID
- `int $dispute_id` - The dispute ID
- `int $opened_by` - User ID who opened dispute

## Financial Hooks

### wpss_commission_recorded

Fires when commission is recorded for an order.

**Parameters:**
- `int $order_id` - The order ID
- `float $commission_amount` - Commission amount
- `float $commission_rate` - Commission percentage
- `int $vendor_id` - The vendor user ID

**Example:**
```php
add_action( 'wpss_commission_recorded', function( $order_id, $amount, $rate, $vendor_id ) {
    // Record in accounting system
    my_accounting_system_add_entry( [
        'type' => 'commission',
        'amount' => $amount,
        'reference' => "Order #$order_id",
    ] );
}, 10, 4 );
```

### wpss_withdrawal_requested

Fires when vendor requests withdrawal.

**Parameters:**
- `int $withdrawal_id` - The withdrawal request ID
- `int $vendor_id` - The vendor user ID
- `float $amount` - Withdrawal amount
- `string $method` - Withdrawal method

**Example:**
```php
add_action( 'wpss_withdrawal_requested', function( $withdrawal_id, $vendor_id, $amount, $method ) {
    // Alert accounting team
    wp_mail(
        'accounting@example.com',
        'Withdrawal Request',
        "Vendor $vendor_id requested $$amount via $method"
    );
}, 10, 4 );
```

## Dispute Hooks

### wpss_dispute_opened

Fires when a dispute is opened.

**Parameters:**
- `int $dispute_id` - The dispute ID
- `int $order_id` - The order ID
- `int $opened_by` - User ID who opened dispute

### wpss_dispute_evidence_added

Fires when evidence is added to dispute.

**Parameters:**
- `int $dispute_id` - The dispute ID
- `array $evidence` - Evidence data (files, message)
- `int $submitted_by` - User ID who submitted

### wpss_dispute_status_changed

Fires when dispute status changes.

**Parameters:**
- `int $dispute_id` - The dispute ID
- `string $old_status` - Previous status
- `string $new_status` - New status

### wpss_dispute_resolved

Fires when dispute is resolved.

**Parameters:**
- `int $dispute_id` - The dispute ID
- `string $resolution` - Resolution outcome
- `int $resolved_by` - Admin user ID

### wpss_dispute_escalated

Fires when dispute is escalated to admin.

**Parameters:**
- `int $dispute_id` - The dispute ID
- `int $escalated_by` - User ID who escalated

### wpss_dispute_cancelled

Fires when dispute is cancelled/withdrawn.

**Parameters:**
- `int $dispute_id` - The dispute ID
- `int $cancelled_by` - User ID who cancelled

## Review Hooks

### wpss_review_created

Fires when a review is submitted.

**Parameters:**
- `int $review_id` - The review ID
- `int $order_id` - The order ID
- `int $reviewer_id` - Buyer user ID
- `int $vendor_id` - Vendor user ID
- `array $review_data` - Rating, comment, etc.

**Example:**
```php
add_action( 'wpss_review_created', function( $review_id, $order_id, $reviewer_id, $vendor_id, $review_data ) {
    // Update vendor average rating
    $vendor_meta = get_user_meta( $vendor_id, 'wpss_vendor_data', true );
    $total_reviews = $vendor_meta['total_reviews'] + 1;
    $total_rating = $vendor_meta['total_rating'] + $review_data['rating'];
    $avg_rating = $total_rating / $total_reviews;

    $vendor_meta['total_reviews'] = $total_reviews;
    $vendor_meta['total_rating'] = $total_rating;
    $vendor_meta['average_rating'] = $avg_rating;

    update_user_meta( $vendor_id, 'wpss_vendor_data', $vendor_meta );
}, 10, 5 );
```

## Vendor Hooks

### wpss_vendor_registered

Fires when a new vendor registers.

**Parameters:**
- `int $vendor_id` - The vendor user ID
- `array $vendor_data` - Registration data

**Example:**
```php
add_action( 'wpss_vendor_registered', function( $vendor_id, $vendor_data ) {
    // Send welcome email with onboarding guide
    $vendor = get_user_by( 'ID', $vendor_id );
    wp_mail(
        $vendor->user_email,
        'Welcome to Our Marketplace',
        'Get started by creating your first service...'
    );
}, 10, 2 );
```

### wpss_vendor_profile_updated

Fires when vendor updates their profile.

**Parameters:**
- `int $vendor_id` - The vendor user ID
- `array $profile_data` - Updated profile data
- `array $old_data` - Previous profile data

### wpss_vendor_vacation_mode_changed

Fires when vendor toggles vacation mode.

**Parameters:**
- `int $vendor_id` - The vendor user ID
- `bool $vacation_mode` - New vacation mode state
- `string $return_date` - Expected return date (if enabled)

### wpss_vendor_tier_changed

Fires when vendor tier level changes.

**Parameters:**
- `int $vendor_id` - The vendor user ID
- `string $old_tier` - Previous tier
- `string $new_tier` - New tier

**Example:**
```php
add_action( 'wpss_vendor_tier_changed', function( $vendor_id, $old_tier, $new_tier ) {
    // Award badge for tier upgrade
    if ( $new_tier === 'top_rated' ) {
        update_user_meta( $vendor_id, 'wpss_badge_top_rated', current_time( 'mysql' ) );

        // Send congratulations email
        $vendor = get_user_by( 'ID', $vendor_id );
        wp_mail(
            $vendor->user_email,
            'Congratulations on Top Rated Status!',
            'You are now a Top Rated seller...'
        );
    }
}, 10, 3 );
```

## Buyer Request Hooks

### wpss_buyer_request_created

Fires when buyer posts a request.

**Parameters:**
- `int $request_id` - The buyer request post ID
- `int $buyer_id` - The buyer user ID
- `array $request_data` - Request details

### wpss_buyer_request_status_changed

Fires when buyer request status changes.

**Parameters:**
- `int $request_id` - The buyer request post ID
- `string $old_status` - Previous status
- `string $new_status` - New status

### wpss_request_converted_to_order

Fires when buyer request becomes an order (proposal accepted).

**Parameters:**
- `int $request_id` - The buyer request post ID
- `int $proposal_id` - The accepted proposal ID
- `int $order_id` - The new order ID

**Example:**
```php
add_action( 'wpss_request_converted_to_order', function( $request_id, $proposal_id, $order_id ) {
    // Close other pending proposals
    $proposals = get_posts( [
        'post_type' => 'wpss_proposal',
        'meta_query' => [
            [
                'key' => '_request_id',
                'value' => $request_id,
            ],
        ],
    ] );

    foreach ( $proposals as $proposal ) {
        if ( $proposal->ID !== $proposal_id ) {
            update_post_meta( $proposal->ID, '_status', 'closed' );
        }
    }
}, 10, 3 );
```

## Proposal Hooks

### wpss_proposal_submitted

Fires when vendor submits a proposal.

**Parameters:**
- `int $proposal_id` - The proposal ID
- `int $request_id` - The buyer request post ID
- `int $vendor_id` - The vendor user ID
- `array $proposal_data` - Proposal details

### wpss_proposal_accepted

Fires when buyer accepts a proposal.

**Parameters:**
- `int $proposal_id` - The proposal ID
- `int $buyer_id` - The buyer user ID
- `int $vendor_id` - The vendor user ID

### wpss_proposal_rejected

Fires when buyer rejects a proposal.

**Parameters:**
- `int $proposal_id` - The proposal ID
- `int $buyer_id` - The buyer user ID

### wpss_proposal_withdrawn

Fires when vendor withdraws their proposal.

**Parameters:**
- `int $proposal_id` - The proposal ID
- `int $vendor_id` - The vendor user ID

## Communication Hooks

### wpss_message_sent

Fires when a message is sent in order conversation.

**Parameters:**
- `int $message_id` - The message ID
- `int $order_id` - The order ID
- `int $sender_id` - User ID who sent message
- `string $message_content` - Message text

### wpss_requirements_submitted

Fires when buyer submits order requirements.

**Parameters:**
- `int $order_id` - The order ID
- `int $buyer_id` - The buyer user ID
- `array $requirements` - Requirements data and files

### wpss_delivery_submitted

Fires when vendor submits delivery.

**Parameters:**
- `int $order_id` - The order ID
- `int $vendor_id` - The vendor user ID
- `array $delivery_files` - Delivery files
- `string $delivery_message` - Delivery message

### wpss_delivery_accepted

Fires when buyer accepts delivery.

**Parameters:**
- `int $order_id` - The order ID
- `int $buyer_id` - The buyer user ID

### wpss_revision_requested

Fires when buyer requests revision.

**Parameters:**
- `int $order_id` - The order ID
- `int $buyer_id` - The buyer user ID
- `string $revision_reason` - Revision instructions

### wpss_after_status_change_notification

Fires after status change notification is sent.

**Parameters:**
- `int $order_id` - The order ID
- `string $status` - New status
- `int $recipient_id` - User who received notification

## Filters

Filters modify data before use. Return the modified value.

### wpss_ecommerce_adapters

Filter registered e-commerce platform adapters.

**Parameters:**
- `array $adapters` - Array of adapter class names

**Example:**
```php
add_filter( 'wpss_ecommerce_adapters', function( $adapters ) {
    $adapters['my_custom_platform'] = 'MyPlugin\CustomPlatformAdapter';
    return $adapters;
} );
```

See [Custom Integrations](custom-integrations.md) for adapter development.

### wpss_payment_gateways

Filter registered payment gateways.

**Parameters:**
- `array $gateways` - Array of gateway class names

**Example:**
```php
add_filter( 'wpss_payment_gateways', function( $gateways ) {
    $gateways['custom_gateway'] = 'MyPlugin\CustomGateway';
    return $gateways;
} );
```

### wpss_storage_providers

Filter registered cloud storage providers.

**Parameters:**
- `array $providers` - Array of provider class names

### wpss_analytics_widgets

Filter registered analytics dashboard widgets.

**Parameters:**
- `array $widgets` - Array of widget class names

### wpss_settings_tabs

Filter admin settings page tabs.

**Parameters:**
- `array $tabs` - Array of tab configurations

**Example:**
```php
add_filter( 'wpss_settings_tabs', function( $tabs ) {
    $tabs['custom_tab'] = [
        'label' => 'Custom Settings',
        'callback' => 'my_custom_settings_callback',
        'priority' => 50,
    ];
    return $tabs;
} );
```

### wpss_api_controllers

Filter REST API controller registrations.

**Parameters:**
- `array $controllers` - Array of controller class names

### wpss_blocks

Filter registered Gutenberg blocks.

**Parameters:**
- `array $blocks` - Array of block configurations

### wpss_service_slug

Filter the service post type slug.

**Parameters:**
- `string $slug` - Default: 'service'

**Example:**
```php
add_filter( 'wpss_service_slug', function( $slug ) {
    return 'gig'; // Change service URL to /gig/
} );
```

### wpss_buyer_request_slug

Filter the buyer request post type slug.

**Parameters:**
- `string $slug` - Default: 'buyer-request'

### wpss_requirements_allowed_file_types

Filter allowed file types for order requirements.

**Parameters:**
- `array $types` - Array of allowed MIME types

**Example:**
```php
add_filter( 'wpss_requirements_allowed_file_types', function( $types ) {
    $types[] = 'application/x-photoshop'; // Allow PSD files
    return $types;
} );
```

### wpss_delivery_allowed_file_types

Filter allowed file types for delivery uploads.

**Parameters:**
- `array $types` - Array of allowed MIME types

### wpss_review_window_days

Filter the number of days buyers can leave reviews.

**Parameters:**
- `int $days` - Default: 7 days

**Example:**
```php
add_filter( 'wpss_review_window_days', function( $days ) {
    return 30; // Extend review window to 30 days
} );
```

## Hook Priority Best Practices

### Priority Guidelines

- **10**: Default priority (most hooks)
- **5**: Run before default
- **15-20**: Run after default
- **100+**: Run last (cleanup, logging)

**Example with priorities:**
```php
// Run first
add_action( 'wpss_order_completed', 'send_notification', 5 );

// Run at default
add_action( 'wpss_order_completed', 'update_statistics', 10 );

// Run last
add_action( 'wpss_order_completed', 'log_completion', 100 );
```

### Parameter Count

Always specify parameter count when using multiple parameters:

```php
// ✅ Correct
add_action( 'wpss_service_created', 'my_function', 10, 2 );

// ❌ Wrong - will only receive first parameter
add_action( 'wpss_service_created', 'my_function' );
```

## Related Documentation

- [REST API Reference](rest-api.md) - API endpoints and authentication
- [Custom Integrations](custom-integrations.md) - Building custom adapters
- [Template Overrides](../customization/template-overrides.md) - Customizing templates
