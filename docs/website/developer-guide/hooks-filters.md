# Hooks and Filters Reference

WP Sell Services and WP Sell Services Pro fire **454** actions and filters between them.

This page covers the **behavioural** surface -- services, orders, delivery, money, disputes, REST, and the Pro seams -- each with its parameters and the source file that fires it.

**Template hooks are on their own page.** The ~110 `wpss_before_*` / `wpss_after_*` markup slots, the dashboard section hooks, and the display filters are documented next to the templates that fire them, in [Template Overrides](../marketplace-display/template-overrides.md). Use that page when you want to inject markup rather than change behaviour.

Between the two, **377 of the 454** hooks are documented. The rest are internal and may change without notice -- if you need one, open a support request and we will promote it rather than have you bind to a moving target.

> **Verify before you ship.** Hook names and signatures on this page are checked against the 1.3.0 source. Some names in pre-1.3.0 documentation were never fired at all -- if a callback of yours has silently stopped running, search this page for the hook name before assuming a regression.

## Using Hooks

```php
// Actions execute code at specific points
add_action( 'wpss_order_status_changed', 'my_func', 10, 3 );
function my_func( $order_id, $new_status, $old_status ) {
    // Your code here
}

// Filters modify data before it is used
add_filter( 'wpss_review_window_days', fn( $days ) => 14 );
```

## Plugin Lifecycle Actions

| Hook | Parameters | File |
|------|-----------|------|
| `wpss_loaded` | `Plugin $plugin` | `Plugin.php:261` |
| `wpss_adapter_initialized` | `EcommerceAdapterInterface $adapter` | `IntegrationManager.php:124` |
| `wpss_register_field_types` | `FieldManager $manager` | `FieldManager.php:59` |

**`wpss_loaded`** is the primary extension hook. All Pro features register here:

```php
add_action( 'wpss_loaded', function( $plugin ) {
    // Plugin is ready - register extensions
}, 10, 1 );
```

## Service Actions

| Hook | Parameters | File |
|------|-----------|------|
| `wpss_service_created` | `int $post_id, array $data` | `ServiceManager.php:144` |
| `wpss_service_updated` | `int $service_id, array $data` | `ServiceManager.php:225` |

### Service Filters

| Filter | Parameters | File |
|--------|-----------|------|
| `wpss_pre_create_service` | `array $data` | `ServiceManager.php` |
| `wpss_pre_update_service` | `array $data, int $service_id` | `ServiceManager.php` |
| `wpss_before_service_deleted` | `int $service_id` | `ServiceManager.php:259` |
| `wpss_service_meta_saved` | `int $post_id, WP_Post $post` | `ServiceMetabox.php:1052` |
| `wpss_rest_service_created` | `int $service_id, WP_REST_Request $request` | `ServicesController.php:321` |
| `wpss_rest_service_updated` | `int $service_id, WP_REST_Request $request` | `ServicesController.php:386` |
| `wpss_rest_service_deleted` | `int $service_id, bool $force` | `ServicesController.php:431` |

## Moderation Actions

| Hook | Parameters | File |
|------|-----------|------|
| `wpss_service_approved` | `int $service_id, string $notes` | `ModerationService.php:181` |
| `wpss_service_rejected` | `int $service_id, string $reason` | `ModerationService.php:233` |
| `wpss_service_pending_moderation` | `int $service_id` | `ModerationService.php:273` |

## Order Actions

| Hook | Parameters | File |
|------|-----------|------|
| `wpss_order_status_changed` | `int $order_id, string $new_status, string $old_status` | `OrderService.php:196` |
| `wpss_order_status_{status}` | `int $order_id, string $old_status` | `OrderService.php:197` |
| `wpss_order_created` | `int $order_id, string $status` | `ManualOrderPage.php:716` |

### Order Filters

| Filter | Parameters | File |
|--------|-----------|------|
| `wpss_pre_create_order` | `array $order_data` | `StandaloneOrderProvider.php` |
| `wpss_pre_order_status_change` | `bool $allow, int $order_id, string $new_status, string $old_status` | `OrderService.php` |
| `wpss_order_started` | `int $order_id` | `OrdersController.php:747` |
| `wpss_order_completed` | `int $order_id, object $order` | `OrderWorkflowManager.php:685` |
| `wpss_order_cancelled` | `int $order_id, int $user_id, string $reason` | `OrderService.php:427` |
| `wpss_order_disputed` | `int $order_id, int $opened_by, string $reason` | `OrdersController.php:670` |
| `wpss_order_message_created` | `int $message_id, int $order_id, int $user_id` | `OrdersController.php:406` |
| `wpss_order_requirements_submitted` | `int $order_id, array $requirements` | `OrdersController.php:839` |
| `wpss_after_status_change_notification` | `int $order_id, string $new_status, string $old_status` | `OrderWorkflowManager.php:638` |
| `wpss_send_requirements_reminder_email` | `int $order_id, int $reminder_num, string $message` | `OrderWorkflowManager.php:338` |
| `wpss_requirements_timeout` | `int $order_id, bool $auto_start` | `OrderWorkflowManager.php:472` |

> **Removed in 1.4.0: `wpss_order_accepted`, `wpss_order_rejected`,
> `wpss_order_delivered`.** The first two went when the dead `accept` / `reject`
> order verbs were removed -- they never had a real transition behind them.
> `wpss_order_delivered` went when `deliver` was routed through
> `DeliveryService`, which already fires its own, better-shaped hooks.
>
> Rebind as follows:
>
> | Was | Use instead |
> |---|---|
> | `wpss_order_accepted` | `wpss_order_paid`, or `wpss_order_status_changed` -- an order is accepted by being paid |
> | `wpss_order_rejected` | `wpss_order_cancelled` |
> | `wpss_order_delivered` | `wpss_delivery_submitted` (`$delivery_id, $order_id`) or `wpss_delivery_accepted` (`$order_id`) |
>
> All three are gone from the source, so a callback still bound to them runs
> never -- silently. Grep your integrations.

## Delivery Actions

### Delivery Filters

| Filter | Parameters | File |
|--------|-----------|------|
| `wpss_pre_submit_delivery` | `array $delivery_data, int $order_id` | `DeliveryService.php` |

| Hook | Parameters | File |
|------|-----------|------|
| `wpss_delivery_submitted` | `int $delivery_id, int $order_id` | `DeliveryService.php:127` |
| `wpss_delivery_accepted` | `int $order_id` | `DeliveryService.php:168` |
| `wpss_revision_requested` | `int $order_id, string $reason` | `DeliveryService.php:234` |
| `wpss_requirements_submitted` | `int $order_id, array $field_data, array $attachments` | `RequirementsService.php:461` |
| `wpss_cancellation_requested` | `int $order_id, int $user_id, string $reason, string $note` | `OrderService.php:598` |
| `wpss_order_auto_refunded` | `int $order_id, object $order, mixed $refund_result` | `OrderWorkflowManager.php:861` |
| `wpss_new_order_message` | `int $order_id, int $sender_id, string $content` | `ConversationService.php:337` |

## Payment and Gateway Actions

These hooks fire during payment processing, gateway interactions, and checkout flow.

### Standalone Adapter

| Hook | Parameters | File |
|------|-----------|------|
| `wpss_standalone_adapter_init` | `StandaloneAdapter $adapter` | `StandaloneAdapter.php:155` |
| `wpss_standalone_checkout_processed` | `int $order_id, array $order_data` | `StandaloneCheckoutProvider.php:133` |
| `wpss_standalone_order_complete` | `object $order` | `StandaloneOrderProvider.php:688` |
| `wpss_order_paid` | `int $order_id, string $transaction_id` | `StandaloneOrderProvider.php:391` |
| `wpss_order_status_pending_requirements` | `int $order_id, string $old_status` | `StandaloneOrderProvider.php:383` |
| `wpss_payment_callback` | `string $gateway_id` | `StandaloneAdapter.php:232` |

### Offline Gateway

| Hook | Parameters | File |
|------|-----------|------|
| `wpss_offline_multi_orders_created` | `array $order_ids, int $customer_id` | `OfflineGateway.php:387` |
| `wpss_offline_order_created` | `int $order_id, object $order` | `OfflineGateway.php:484` |
| `wpss_offline_order_paid` | `int $order_id, string $transaction_id` | `OfflineGateway.php:561` |

### Stripe Gateway

| Hook | Parameters | File |
|------|-----------|------|
| `wpss_stripe_webhook_received` | `string $event_type, object $data, string $payload` | `StripeGateway.php:313` |
| `wpss_stripe_refund_processed` | `string $payment_intent_id, object $charge` | `StripeGateway.php:1023` |

### PayPal Gateway

| Hook | Parameters | File |
|------|-----------|------|
| `wpss_paypal_refund_processed` | `string $url, array $resource` | `PayPalGateway.php:1094` |

### Payment REST API

| Hook | Parameters | File |
|------|-----------|------|
| `wpss_rest_offline_order_created` | `int $order_id, object $order, string $gateway_id` | `PaymentController.php:440` |

### Payment Filters

| Filter | Parameters | File |
|--------|-----------|------|
| `wpss_stripe_payment_intent_args` | `array $params, int $order_id, int $vendor_id` | `StripeGateway.php:181` |
| `wpss_rest_create_payment_intent` | `null, object $gateway, float $amount, string $currency, int $service_id, int $package_id, object $pay_order` | `PaymentController.php:254` |
| `wpss_rest_confirm_payment` | `null, object $gateway, string $payment_id, int $service_id, int $package_id, object $pay_order` | `PaymentController.php:303` |
| `wpss_checkout_tax_rate` | `float $tax_rate, int $vendor_id, int $service_id` | `StandaloneCheckoutProvider.php:401` |

**`wpss_stripe_payment_intent_args`** lets you modify Stripe PaymentIntent parameters before creation:

```php
add_filter( 'wpss_stripe_payment_intent_args', function( $params, $order_id, $vendor_id ) {
    $params['metadata']['custom_field'] = 'value';
    return $params;
}, 10, 3 );
```

**`wpss_checkout_tax_rate`** lets you apply different tax rates per vendor or service:

```php
add_filter( 'wpss_checkout_tax_rate', function( $rate, $vendor_id, $service_id ) {
    // Apply 15% tax for services in a specific category
    if ( has_term( 'consulting', 'wpss_service_category', $service_id ) ) {
        return 15.0;
    }
    return $rate;
}, 10, 3 );
```

## Data Cascade Actions

These hooks fire when services, requests, or users are deleted and related data is cleaned up. Use them for custom cleanup logic.

| Hook | Parameters | File |
|------|-----------|------|
| `wpss_before_cascade_delete_service` | `int $service_id` | `DataCascadeHandler.php:101` |
| `wpss_after_cascade_delete_service` | `int $service_id` | `DataCascadeHandler.php:139` |
| `wpss_before_cascade_delete_request` | `int $request_id` | `DataCascadeHandler.php:155` |
| `wpss_after_cascade_delete_request` | `int $request_id` | `DataCascadeHandler.php:166` |
| `wpss_before_cascade_delete_user` | `int $user_id` | `DataCascadeHandler.php:182` |
| `wpss_after_cascade_delete_user` | `int $user_id` | `DataCascadeHandler.php:226` |

```php
// Clean up custom data when a service is deleted
add_action( 'wpss_before_cascade_delete_service', function( $service_id ) {
    global $wpdb;
    $wpdb->delete( $wpdb->prefix . 'my_custom_table', [ 'service_id' => $service_id ] );
} );
```

## Vendor Actions

### Vendor Filters

| Filter | Parameters | File |
|--------|-----------|------|
| `wpss_pre_vendor_register` | `array $profile_data, int $user_id` | `VendorService.php` |
| `wpss_vendor_profile_allowed_fields` | `array $allowed_fields` | `VendorService.php` |

| Hook | Parameters | File |
|------|-----------|------|
| `wpss_vendor_registered` | `int $user_id, array $profile_data` | `VendorService.php:131` |
| `wpss_vendor_profile_updated` | `int $user_id, array $filtered_data` | `VendorService.php:250` |
| `wpss_vendor_vacation_mode_changed` | `int $user_id, bool $enabled, string $message` | `VendorService.php:299` |
| `wpss_vendor_tier_changed` | `int $user_id, string $tier` | `VendorService.php:340` |
| `wpss_vendor_level_promoted` | `int $user_id, string $new_level, string $current_level` | `OrderWorkflowManager.php:539` |
| `wpss_vendor_level_updated` | `int $user_id, string $level` | `SellerLevelService.php:299` |
| `wpss_vendor_status_updated` | `int $vendor_id, string $status` | `VendorsPage.php:1583` |
| `wpss_vendor_commission_updated` | `int $vendor_id, float $rate` | `VendorsPage.php:1884` |
| `wpss_vendor_contacted` | `int $vendor_id, int $user_id, int $service_id, string $message, array $attachments` | `AjaxHandlers.php:2052` |
| `wpss_vendor_access_granted` | `int $user_id` | `VendorService.php:356` |
| `wpss_vendor_access_revoked` | `int $user_id` | `VendorService.php:401` |

## Financial Actions

### Financial Filters

| Filter | Parameters | File |
|--------|-----------|------|
| `wpss_commission_base_amount` | `float $base_amount, int $order_id, int $vendor_id` | `CommissionService.php` |

| Hook | Parameters | File |
|------|-----------|------|
| `wpss_commission_recorded` | `int $order_id, array $commission, int $vendor_id` | `CommissionService.php:116` |
| `wpss_withdrawal_requested` | `int $withdrawal_id, int $vendor_id, float $amount` | `EarningsService.php:344` |
| `wpss_withdrawal_processed` | `int $withdrawal_id, string $status, object $withdrawal` | `EarningsService.php:489` |
| `wpss_auto_withdrawal_created` | `int $withdrawal_id, int $vendor_id, float $amount` | `EarningsService.php:866` |
| `wpss_tip_order_created` | `int $tip_order_id, int $parent_order_id, int $customer_id, float $amount` | `TippingService.php:280` |
| `wpss_tip_sent` | `int $tip_txn_id, int $parent_order_id, int $vendor_id, int $customer_id, float $vendor_earnings, string $vendor_notes` | `TippingService.php:482` |

`wpss_tip_order_created` fires when the tip checkout is started; `wpss_tip_sent`
fires only once the tip is actually paid and credited. The first argument of
`wpss_tip_sent` is the **wallet transaction id**, not the tip order id, and the
amount passed is the vendor's net earnings after commission -- not the gross
tip. Tips are excluded from commission by default, so the two usually match.

## Dispute Actions

### Dispute Filters

| Filter | Parameters | File |
|--------|-----------|------|
| `wpss_pre_open_dispute` | `array $dispute_data, int $order_id` | `DisputeService.php` |

| Hook | Parameters | File |
|------|-----------|------|
| `wpss_dispute_opened` | `int $dispute_id, int $order_id, int $opened_by, array $data` | `DisputeService.php:132` |
| `wpss_dispute_evidence_added` | `int $dispute_id, int $user_id` | `DisputeService.php:248` |
| `wpss_dispute_status_changed` | `int $dispute_id, string $status, string $old_status` | `DisputeService.php:334` |
| `wpss_dispute_resolved` | `int $dispute_id, string $resolution, object $dispute, float $refund_amount` | `DisputeService.php:400` |
| `wpss_dispute_response_submitted` | `int $message_id, int $dispute_id, int $user_id` | `DisputeWorkflowManager.php:193` |
| `wpss_dispute_escalated` | `int $dispute_id, string $reason, int $escalated_by` | `DisputeWorkflowManager.php:321` |
| `wpss_dispute_cancelled` | `int $dispute_id, int $user_id, string $reason` | `DisputeWorkflowManager.php:463` |

## Review, Request, and Proposal Actions

### Review Filters

| Filter | Parameters | File |
|--------|-----------|------|
| `wpss_pre_create_review` | `array $review_data, int $order_id` | `ReviewService.php` |

| Hook | Parameters | File |
|------|-----------|------|
| `wpss_review_created` | `int $review_id, int $order_id` | `ReviewService.php:120` |
| `wpss_review_reply_created` | `int $review_id` | `ReviewsController.php:542` |
| `wpss_buyer_request_created` | `int $post_id, array $data` | `BuyerRequestService.php:112` |
| `wpss_buyer_request_updated` | `int $request_id, array $data` | `BuyerRequestService.php:164` |
| `wpss_buyer_request_status_changed` | `int $request_id, string $status, string $old_status` | `BuyerRequestService.php:425` |
| `wpss_request_converted_to_order` | `int $order_id, int $request_id, int $proposal_id, object $request, object $proposal` | `BuyerRequestService.php:704` |
| `wpss_proposal_submitted` | `int $proposal_id, int $request_id, int $vendor_id, array $proposal_data` | `ProposalService.php:136` |
| `wpss_proposal_updated` | `int $proposal_id, array $update_data` | `ProposalService.php:229` |
| `wpss_proposal_accepted` | `int $proposal_id, object $proposal, object $request` | `ProposalService.php:283` |
| `wpss_proposal_rejected` | `int $proposal_id, object $proposal, string $reason` | `ProposalService.php:331` |
| `wpss_proposal_withdrawn` | `int $proposal_id, object $proposal` | `ProposalService.php:373` |
| `wpss_proposal_deleted` | `int $proposal_id, object $proposal` | `ProposalService.php:665` |
| `wpss_proposal_status_updated` | `int $proposal_id, string $status` | `ProposalService.php:418` |
| `wpss_buyer_request_deleted` | `int $request_id` | `BuyerRequestService.php:897` |
| `wpss_buyer_request_meta_saved` | `int $post_id, WP_Post $post` | `BuyerRequestMetabox.php:341` |

## Milestone and Extension Actions

A milestone is a **sub-order** of the parent order, so every hook passes both
ids: `$milestone_id` is the sub-order, `$order_id` is the parent. See
[Sub-Order Pattern](https://github.com/vapvarun/wp-sell-services/blob/main/docs/architecture/SUB_ORDER_PATTERN.md).

| Hook | Parameters | File |
|------|-----------|------|
| `wpss_milestone_proposed` | `int $milestone_id, int $order_id, int $vendor_id` | `MilestoneService.php:262` |
| `wpss_milestone_paid` | `int $milestone_id, int $order_id, int $vendor_id, int $customer_id, float $vendor_earnings` | `MilestoneService.php:411` |
| `wpss_milestone_submitted` | `int $milestone_id, int $order_id, int $vendor_id, int $customer_id` | `MilestoneService.php:483` |
| `wpss_milestone_approved` | `int $milestone_id, int $order_id, int $vendor_id, int $customer_id` | `MilestoneService.php:536` |
| `wpss_milestone_declined` | `int $milestone_id, int $order_id, int $customer_id` | `MilestoneService.php:591` |
| `wpss_extension_request_created` | `int $request_id, int $order_id, array $data` (`requested_by`, `extra_days`, `reason`) | `ExtensionRequestService.php:249` |
| `wpss_extension_request_approved` | `int $request_id, object $request` | `ExtensionRequestService.php:371` |
| `wpss_extension_request_rejected` | `int $request_id, object $request` | `ExtensionRequestService.php:455` |

> **Renamed in 1.3.0.** The milestone lifecycle uses *proposed* and *declined*,
> not *created* and *rejected* -- see [Milestone terminology](https://github.com/vapvarun/wp-sell-services/blob/main/docs/decisions/milestone-terminology.md).
> `wpss_milestone_created`, `wpss_milestone_rejected`, `wpss_extension_requested`
> and `wpss_extension_approved` were listed in earlier docs but are **not fired
> by the plugin**. Callbacks bound to those names never run. Note also that
> `wpss_milestone_approved` passes `$vendor_id` as its third argument, not an
> amount -- if you need the money, read it from the sub-order or hook
> `wpss_milestone_paid`.

## Admin and Settings Actions

These hooks fire in the WordPress admin area for order management, service meta, and settings pages.

| Hook | Parameters | File |
|------|-----------|------|
| `wpss_admin_order_actions` | `object $order, string $status` | `OrderMetabox.php:831` |
| `wpss_admin_requirements_submitted` | `int $order_id, array $field_data` | `OrderMetabox.php:1097` |
| `wpss_gateway_cards` | `Settings $settings` | `Settings.php:1341` |

### Admin Filters

| Filter | Parameters | File |
|--------|-----------|------|
| `wpss_service_meta_fields` | `array $fields, int $post_id` | `ServiceMetabox.php:155` |
| `wpss_pro_upgrade_url` | `string $url` (default `https://wpsellservices.com/`) | `UpgradePage.php`, `ServiceWizard.php:961` |
| `wpss_docs_url` | `string $url` (default `https://wpsellservices.com/docs/`) | `UpgradePage.php:349` |

_`wpss_pro_upgrade_url` (added 1.3.0) controls where every "Upgrade to Pro" call-to-action points — the admin upgrade screen and the in-wizard prompts. Point it at your own landing page or an in-site URL:_

```php
add_filter( 'wpss_pro_upgrade_url', function( $url ) {
    return home_url( '/go-pro/' );
} );
```

## Dashboard Menu Visibility Filters

_Added in 1.3.0._ Role-based menu visibility lets you show or hide dashboard sections per user role. Gate a section programmatically with:

| Filter | Parameters | File |
|--------|-----------|------|
| `wpss_can_access_dashboard_section` | `bool $can_access, string $section, int $user_id` | `MenuVisibility.php` |

```php
// Hide the "Earnings" section from a custom role
add_filter( 'wpss_can_access_dashboard_section', function( $can, $section, $user_id ) {
    if ( 'earnings' === $section && user_can( $user_id, 'my_limited_role' ) ) {
        return false;
    }
    return $can;
}, 10, 3 );
```

## Currency Display Filters

| Filter | Parameters | File |
|--------|-----------|------|
| `wpss_catalog_price_html` | `string $html, float $amount, string $context` | `functions.php:111` |

_`wpss_catalog_price_html` (added 1.3.0) is the single seam for catalog price display. Base currency is authoritative for all stored amounts; this filter is where an add-on (such as the Pro display-currency hint) injects a converted, visitor-facing price without changing the stored value._

```php
// Add custom fields to the service meta box in wp-admin
add_filter( 'wpss_service_meta_fields', function( $fields, $post_id ) {
    $fields['custom_field'] = [
        'label' => 'Custom Field',
        'type'  => 'text',
        'value' => get_post_meta( $post_id, '_wpss_custom_field', true ),
    ];
    return $fields;
}, 10, 2 );
```

## Service Wizard Actions

| Hook | Parameters | File |
|------|-----------|------|
| `wpss_service_wizard_saved` | `int $service_id, array $sanitized_data` | `ServiceWizard.php:1603` |
| `wpss_wizard_pricing_after` | `WP_Post\|null $service` | `ServiceWizard.php` |
| `wpss_wizard_save_service_meta` | `int $service_id, array $data` | `ServiceWizard.php` |

### Service Wizard Filters

| Filter | Parameters | File |
|--------|-----------|------|
| `wpss_vendor_can_create_service` | `bool $can_create, int $user_id` | `ServiceWizard.php:288` |
| `wpss_services_per_page` | `int $per_page` (default 12) | `ServiceArchiveView.php:525` |
| `wpss_wizard_service_data` | `array $data, int $service_id` | `ServiceWizard.php` |
| `wpss_wizard_sanitize_service_data` | `array $sanitized, array $raw` | `ServiceWizard.php` |

```php
// Prevent unverified vendors from creating services
add_filter( 'wpss_vendor_can_create_service', function( $allowed, $user_id ) {
    if ( ! get_user_meta( $user_id, '_wpss_identity_verified', true ) ) {
        return false;
    }
    return $allowed;
}, 10, 2 );
```

### Wizard Extension Surface (Add-on Integration — 1.2.0)

These four hooks form the contract that lets add-ons (such as Pro's recurring billing) inject fields into the frontend Create/Edit Service wizard without patching core files.

**`wpss_wizard_service_data`** — seed extra keys into the wizard's edit-form data model so they pre-fill when a vendor edits an existing service:

```php
add_filter( 'wpss_wizard_service_data', function( $data, $service_id ) {
    $data['my_billing_cycle'] = get_post_meta( $service_id, '_wpss_billing_cycle', true ) ?: 'one_time';
    return $data;
}, 10, 2 );
```

**`wpss_wizard_pricing_after`** — render extra fields after the Pricing step. Markup runs inside the wizard's Alpine.js scope; bind inputs with `x-model="data.your_key"`:

```php
add_action( 'wpss_wizard_pricing_after', function( $service ) {
    ?>
    <div class="wpss-field-group">
        <label><?php esc_html_e( 'Billing cycle', 'my-addon' ); ?></label>
        <select x-model="data.my_billing_cycle">
            <option value="one_time"><?php esc_html_e( 'One-time', 'my-addon' ); ?></option>
            <option value="monthly"><?php esc_html_e( 'Monthly', 'my-addon' ); ?></option>
        </select>
    </div>
    <?php
} );
```

**`wpss_wizard_sanitize_service_data`** — sanitize your injected keys from the untrusted client JSON payload before save:

```php
add_filter( 'wpss_wizard_sanitize_service_data', function( $sanitized, $raw ) {
    $allowed = [ 'one_time', 'monthly', 'yearly' ];
    $sanitized['my_billing_cycle'] = in_array( $raw['my_billing_cycle'] ?? '', $allowed, true )
        ? $raw['my_billing_cycle']
        : 'one_time';
    return $sanitized;
}, 10, 2 );
```

**`wpss_wizard_save_service_meta`** — persist your custom meta. Fires on **both** save-draft and publish (unlike `wpss_service_wizard_saved`, which fires on publish only):

```php
add_action( 'wpss_wizard_save_service_meta', function( $service_id, $data ) {
    if ( isset( $data['my_billing_cycle'] ) ) {
        update_post_meta( $service_id, '_wpss_billing_cycle', $data['my_billing_cycle'] );
    }
}, 10, 2 );
```

## Dashboard Actions

| Hook | Parameters | File |
|------|-----------|------|
| `wpss_dashboard_section_before_content` | `string $section, int $user_id` | `UnifiedDashboard.php:529` |

### Dashboard Filters

| Filter | Parameters | File |
|--------|-----------|------|
| `wpss_dashboard_default_section` | `string $section, int $user_id` | `UnifiedDashboard.php` |

```php
// Add a notice at the top of the earnings dashboard section
add_action( 'wpss_dashboard_section_before_content', function( $section, $user_id ) {
    if ( 'earnings' === $section ) {
        echo '<div class="wpss-notice">Minimum withdrawal is $50.</div>';
    }
}, 10, 2 );
```

**`wpss_dashboard_default_section`** — the landing section when no section is in the URL. Defaults to `sales` for active vendors and `orders` for buyers:

```php
// Always land on the messages section regardless of role
add_filter( 'wpss_dashboard_default_section', function( $section, $user_id ) {
    return 'messages';
}, 10, 2 );
```

## Cart and Checkout Filters

| Filter | Parameters | File |
|--------|-----------|------|
| `wpss_add_service_to_cart` | `bool $added, array $cart_item, object $adapter` | `AjaxHandlers.php:2524` |
| `wpss_pay_order_url` | `string $url, int $order_id` | `functions.php:3391` |

### `wpss_pay_order_url` -- the payment-handoff seam

This is the one seam for **"send the buyer somewhere they can pay THIS
order."** Every tip, milestone phase, paid extension and accepted proposal
resolves its pay link through it -- on the order page, in the dashboard, in the
REST `checkout_url` field, and in the emails the plugin sends.

Never rebuild that URL inline. Code that does is correct on the standalone rail
and broken on every other one.

```php
// The helper. Call this; do not re-author it.
$url = wpss_get_pay_order_url( $order_id );   // src/functions.php:3375
```

**Default (unfiltered):** `<checkout page>?pay_order={id}`. The standalone
checkout understands that query arg and renders the single order.

**Why it needs a filter at all:** a cart-based rail has no concept of "pay this
existing order." Appending `?pay_order=N` to a WooCommerce checkout URL lands
the buyer on an **empty cart with no way to pay and no error message** -- which
is exactly what every tip, milestone and extension link did in Woo mode before
1.4.0.

**Who hooks it:**

| Rail | Hooks it? | What the buyer gets |
|---|---|---|
| Standalone | n/a (the default) | `?pay_order=N` on the plugin's own checkout |
| WooCommerce | **Yes** -- `WCPayOrderResolver` (Pro) | A native WC order-pay URL |
| EDD | No | The unfiltered default -> empty cart |
| FluentCart | No | The unfiltered default -> empty cart |
| SureCart | No | The unfiltered default -> empty cart |

**Two things to know before you hook it:**

1. **It is called speculatively.** Rendering the order page asks for a pay URL
   for every unpaid phase, and `MilestoneService::propose()` asks for one when
   the phase is created. A resolver that has side effects (Woo's creates a WC
   order) will have them at render time, not at click time -- so make yours
   idempotent, as `WCPayOrderResolver` is.
2. **It is not a security gate.** The milestone lock-step guard lives on the
   *checkout* side, not here. Returning a URL for a locked phase is possible and
   the plugin's own Woo resolver does it.

```php
// Point a custom rail at its own pay page.
add_filter( 'wpss_pay_order_url', function ( string $url, int $order_id ): string {
    $order = wpss_get_order( $order_id );
    if ( ! $order || 'paid' === ( $order->payment_status ?? '' ) ) {
        return $url;
    }
    return my_gateway_build_pay_page( $order_id, (float) $order->total );
}, 10, 2 );
```

See [WooCommerce Checkout](../payments-checkout/woocommerce-checkout.md#paying-a-milestone-tip-or-extension)
for the full WC implementation and the platform-support matrix.

## Email Filters

These filters let you customize outgoing email content without modifying templates.

| Filter | Parameters | File |
|--------|-----------|------|
| `wpss_email_from_name` | `string $from_name` | `EmailService.php:1118` |
| `wpss_email_header_vars` | `array $template_vars, string $type` | `EmailService.php:1102` |
| `wpss_vendor_pending_email_content` | `string $content, object $user, string $platform_name` | `NotificationService.php:1160` |
| `wpss_vendor_approved_email_content` | `string $content, object $user, string $platform_name` | `NotificationService.php:1293` |
| `wpss_vendor_rejected_email_content` | `string $content, object $user, string $platform_name` | `NotificationService.php:1361` |

```php
// Change the "From" name on all marketplace emails
add_filter( 'wpss_email_from_name', function( $name ) {
    return 'DesignHub Marketplace';
} );

// Customize the vendor approval email content
add_filter( 'wpss_vendor_approved_email_content', function( $content, $user, $platform ) {
    $content .= '<p>Welcome aboard! Here are some tips to get started...</p>';
    return $content;
}, 10, 3 );
```

## Other Actions

### Other Filters

| Filter | Parameters | File |
|--------|-----------|------|
| `wpss_pre_send_message` | `array $message_data, int $conversation_id` | `ConversationService.php` |
| `wpss_validate_add_to_cart` | `bool $valid, int $service_id, int $package_id, int $user_id` | `CartController.php` |
| `wpss_cart_item_data` | `array $cart_item, int $service_id, int $package_id` | `CartController.php` |
| `wpss_email_subject` | `string $subject, string $type, string $to` | `EmailService.php` |
| `wpss_search_query_args` | `array $query_args, string $query` | `SearchService.php` |
| `wpss_template_args` | `array $args, string $template_name` | `functions.php` |

| Hook | Parameters | File |
|------|-----------|------|
| `wpss_message_sent` | `object $message, object $conversation` | `ConversationService.php:223` |
| `wpss_notification_created` | `int $notification_id, int $user_id, string $type, array $data` | `NotificationService.php:80` |
| `wpss_portfolio_item_created` | `int $item_id, int $vendor_id, array $data` | `PortfolioService.php:194` |
| `wpss_portfolio_item_updated` | `int $item_id, array $data` | `PortfolioService.php:289` |
| `wpss_portfolio_item_deleted` | `int $item_id, object $item` | `PortfolioService.php:339` |
| `wpss_addon_created` | `int $addon_id, int $service_id, array $addon_data` | `ServiceAddonService.php:143` |
| `wpss_addon_updated` | `int $addon_id, array $update_data` | `ServiceAddonService.php:229` |
| `wpss_addon_deleted` | `int $addon_id, object $addon` | `ServiceAddonService.php:353` |
| `wpss_settings_tab_{tab}` | *(none)* | `Settings.php:985` |
| `wpss_advanced_settings_sections` | *(none)* | `Settings.php:1317` |

## Filters

### Provider Registration

| Filter | File | Default |
|--------|------|---------|
| `wpss_ecommerce_adapters` | `IntegrationManager.php:67` | Standalone only (Pro adds WooCommerce, EDD, FluentCart, SureCart) |
| `wpss_payment_gateways` | `Plugin.php:813` | Test gateway (debug) |
| `wpss_wallet_providers` **[PRO]** | `Plugin.php:825` | Empty |
| `wpss_storage_providers` **[PRO]** | `Plugin.php:837` | Empty |
| `wpss_email_providers` **[PRO]** | `Plugin.php:849` | Empty |
| `wpss_analytics_widgets` **[PRO]** | `Plugin.php:861` | Empty |

### Service Wizard Limits

| Filter | File | Free Default |
|--------|------|-------------|
| `wpss_service_max_packages` | `ServiceWizard.php:116` | 3 |
| `wpss_service_max_gallery` | `ServiceWizard.php:126` | 4 |
| `wpss_service_max_videos` | `ServiceWizard.php:136` | 1 |
| `wpss_service_max_extras` | `ServiceWizard.php:146` | 3 |
| `wpss_service_max_faq` | `ServiceWizard.php:156` | 5 |
| `wpss_service_max_requirements` | `ServiceWizard.php:166` | 5 |
| `wpss_service_wizard_features` | `ServiceWizard.php:175` | All false |

### Data Filters

| Filter | Parameters | File |
|--------|-----------|------|
| `wpss_format_price` | `$formatted, $price, $currency` | `functions.php:68` |
| `wpss_currency` | `$currency` | `functions.php:91` |
| `wpss_platform_name` | `$platform_name` | `functions.php:117` |
| `wpss_is_vendor` | `$is_vendor, $user_id` | `functions.php:331` |
| `wpss_order_number_prefix` | `$prefix` (default `'WPSS-'`) | `functions.php:385` |
| `wpss_dispute_number_prefix` | `$prefix` (default `'DSP-'`) | `functions.php:397` |
| `wpss_currency_symbols` | `$symbols` | `functions.php:490` |
| `wpss_currency_format` | `$format, $symbol, $currency` | `functions.php:517` |
| `wpss_currencies` | `$currencies` | `functions.php:564` |
| `wpss_order_statuses` | `$statuses` | `functions.php:620` |
| `wpss_max_upload_size` | `$upload_max` | `functions.php:834` |
| `wpss_allow_late_requirements_submission` | `$allow_late` | `functions.php:888` |
| `wpss_wallet_manager` | `null` | `functions.php:1029` |

### Currency System Filters (1.2.1)

As of 1.2.1, currencies are driven by a single canonical registry (code → name, symbol, decimals). Every currency surface — price formatting, the settings dropdown, manual orders, decimal handling — reads from it, so overriding one filter updates all of them consistently.

| Filter | Parameters | File |
|--------|-----------|------|
| `wpss_currency_registry` | `array<string, array{name:string, symbol:string, decimals:int}> $registry` | `functions.php:793` |
| `wpss_currency_decimals` | `int $decimals, string $currency` | `functions.php:101` |
| `wpss_zero_decimal_currencies` | `string[] $codes` | `functions.php:130` |
| `wpss_settings_currencies` | `array $currencies` | `Settings.php:3052` |
| `wpss_manual_order_currencies` | `array $currencies` | `ManualOrderPage.php:814` |

**`wpss_currency_registry`** is the preferred, single-place override — add, remove, or adjust a currency (name / symbol / decimals) and every currency surface updates. Prefer it over the older per-surface currency filters (`wpss_currency_symbols`, `wpss_currency_format`, `wpss_currencies`):

```php
// Register a custom currency and change USD's symbol
add_filter( 'wpss_currency_registry', function( $registry ) {
    $registry['XCD'] = [
        'name'     => 'East Caribbean Dollar',
        'symbol'   => 'EC$',
        'decimals' => 2,
    ];
    $registry['USD']['symbol'] = 'US$';
    return $registry;
} );
```

**`wpss_currency_decimals`** overrides the decimal places for a specific currency at format time (for example, to render USD without minor units):

```php
add_filter( 'wpss_currency_decimals', function( $decimals, $currency ) {
    return 'USD' === $currency ? 0 : $decimals;
}, 10, 2 );
```

**`wpss_zero_decimal_currencies`** returns the codes rendered without minor units. It is derived from the registry (`decimals === 0`); filter it only when you need to force a currency into or out of zero-decimal formatting independently of its registry entry.

**`wpss_settings_currencies`** and **`wpss_manual_order_currencies`** narrow (or extend) the currency choices offered in the admin settings dropdown and the manual-order screen respectively — useful for restricting a store to a subset of the registry:

```php
// Only allow USD and EUR to be selected in settings
add_filter( 'wpss_settings_currencies', function( $currencies ) {
    return array_intersect_key( $currencies, array_flip( [ 'USD', 'EUR' ] ) );
} );
```

### Template Filters

| Filter | Parameters | File |
|--------|-----------|------|
| `wpss_get_template_part` | `$template, $slug, $name` | `functions.php:165` |
| `wpss_get_template` | `$template, $template_name, $args` | `functions.php:211` |
| `wpss_locate_template` | `$template, $template_name, $template_path` | `TemplateLoader.php:318` |
| `wpss_dashboard_section_template` | `$template_path, $section` | `UnifiedDashboard.php:418` |

### URL and Taxonomy Filters

| Filter | Parameters | File |
|--------|-----------|------|
| `wpss_vendor_slug` | `$slug` (default `'provider'`) | `Plugin.php`, `functions.php` |
| `wpss_service_order_slug` | `$slug` (default `'service-order'`) | `Plugin.php`, `functions.php` |
| `wpss_checkout_slug` | `$slug` (default `'service-checkout'`) | `StandaloneAdapter.php` |
| `wpss_service_slug` | `$slug` (default `'service'`) | `ServicePostType.php:184` |
| `wpss_buyer_request_slug` | `$slug` (default `'buyer-request'`) | `BuyerRequestPostType.php:112` |
| `wpss_service_post_type_args` | `$args` | `ServicePostType.php:106` |
| `wpss_service_tag_args` | `$args` | `ServicePostType.php:168` |
| `wpss_service_category_taxonomy_args` | `$args` | `ServiceCategoryTaxonomy.php:118` |
| `wpss_service_tag_taxonomy_args` | `$args` | `ServiceTagTaxonomy.php:103` |
| `wpss_buyer_request_post_type_args` | `$args` | `BuyerRequestPostType.php:96` |

### Order, Commission, and API Filters

| Filter | Parameters | File |
|--------|-----------|------|
| `wpss_order_status_transitions` | `$transitions, $from, $to` | `OrderService.php:290` |
| `wpss_commission_rate` | `$rate, $order, $vendor_id, $service_id` | `CommissionService.php:163` |
| `wpss_proposal_order_revisions` | `$revisions, $proposal, $request` | `BuyerRequestService.php:628` |
| `wpss_max_order_quantity` | `$max` | `SingleServiceView.php:743` |
| `wpss_api_controllers` | `$controllers` | `API.php:76` |
| `wpss_api_public_settings` | `$settings` | `API.php:346` |
| `wpss_batch_max_requests` | `$max` (default 25) | `API.php:571` |
| `wpss_api_cors_origins` | `$origins` | `API.php:641` |
| `wpss_settings_tabs` | `$tabs` | `Settings.php:161` |
| `wpss_blocks` | `$blocks` | `BlocksManager.php:93` |
| `wpss_rate_limits` | `$limits, $action` | `RateLimiter.php:243` |

### Miscellaneous Filters

| Filter | Parameters | File |
|--------|-----------|------|
| `wpss_realtime_settings` | `array $settings` | `RealtimeService.php` |
| `wpss_review_window_days` | `$days` | `ReviewService.php:420` |
| `wpss_auto_approve_reviews` | `$auto_approve` (default true) | `ReviewsController.php:350` |
| `wpss_vendor_registration_open` | `$open` (default true) | `VendorsController.php:380` |
| `wpss_auto_approve_vendors` | `$auto_approve` (default true) | `VendorsController.php:390` |
| `wpss_delivery_allowed_file_types` | `$types` | `DeliveryService.php:374` |
| `wpss_requirements_allowed_file_types` | `$types` | `RequirementsService.php:411` |
| `wpss_withdrawal_methods` | `$methods` | `EarningsService.php:575` |
| `wpss_search_results` | `$results, $query, $args` | `SearchService.php:121` |
| `wpss_search_suggestions` | `$suggestions, $query` | `SearchService.php:498` |
| `wpss_related_services_args` | `$args, $service` | `SingleServiceView.php:647` |
| `wpss_cart_checkout` | `$result, $cart, $user_id, $payment_method` | `CartController.php:378` |
| `wpss_seller_levels` | `$levels` | `SellerLevelsController.php:284` |
| `wpss_rest_service_data` | `$data, $service, $request` | `ServicesController.php:608` |
| `wpss_rest_order_data` | `$data, $order, $request` | `OrdersController.php` |
| `wpss_rest_review_data` | `$data, $review, $request` | `ReviewsController.php` |
| `wpss_rest_vendor_data` | `$data, $vendor, $request` | `VendorsController.php` |
| `wpss_can_access_dashboard_section` | `$allowed, $section, $user_id` | `UnifiedDashboard.php:173` |
| `wpss_dashboard_sections` | `$sections, $user_id, $is_vendor` | `UnifiedDashboard.php:243` |
| `wpss_dashboard_section_titles` | `$titles` | `UnifiedDashboard.php:371` |

**`wpss_realtime_settings`** — filter the resolved real-time/WebSocket connection settings before they are used. The `$settings` array includes: `enabled`, `app_id`, `key`, `secret`, `host`, `cluster`, `port`, `use_tls`. The `secret` field is server-only; it is never sent to the browser:

```php
add_filter( 'wpss_realtime_settings', function( $settings ) {
    // Force a specific cluster at runtime
    $settings['cluster'] = 'eu';
    return $settings;
} );
```

### Full-width Plugin Pages Filters

| Filter | Parameters | File |
|--------|-----------|------|
| `wpss_use_fullwidth_template` | `bool $use` | `src/Frontend/TemplateLoader.php:246` (and `:332`) |
| `wpss_fullwidth_page_keys` | `string[] $page_keys` | `src/Frontend/TemplateLoader.php:292` |

**`wpss_use_fullwidth_template`** — return `false` to keep the active theme's normal page template (with sidebar) on the plugin's pages instead of the sidebar-free full-width layout:

```php
add_filter( 'wpss_use_fullwidth_template', '__return_false' );
```

**`wpss_fullwidth_page_keys`** — control which mapped plugin pages render full-width. Default: `['dashboard', 'cart', 'checkout', 'become_vendor']`:

```php
// Remove the cart page from full-width treatment
add_filter( 'wpss_fullwidth_page_keys', function( $keys ) {
    return array_diff( $keys, [ 'cart' ] );
} );
```

### SEO and Email Filters

| Filter | Parameters | File |
|--------|-----------|------|
| `wpss_service_schema` | `$schema, $service_id` | `SchemaMarkup.php:183` |
| `wpss_service_list_schema` | `$schema` | `SchemaMarkup.php:221` |
| `wpss_category_schema` | `$schema, $term` | `SchemaMarkup.php:280` |
| `wpss_person_schema` | `$schema, $user_id` | `SchemaMarkup.php:328` |
| `wpss_vendor_page_schema` | `$schema, $user_id` | `SchemaMarkup.php:375` |
| `wpss_organization_schema` | `$schema` | `SchemaMarkup.php:406` |
| `wpss_open_graph_data` | `$data, $service_id` | `SEO.php:257` |
| `wpss_sitemap_post_types` | `$post_types` | `SEO.php:321` |
| `wpss_breadcrumbs` | `$breadcrumbs, $service_id` | `SEO.php:387` |
| `wpss_notification_email_content` | `$content, $subject, $user_id, $data` | `NotificationService.php:1195` |
| `wpss_vendor_welcome_email_content` | `$content, $user, $platform_name` | `NotificationService.php:994` |
| `wpss_admin_vendor_notification_content` | `$content, $user` | `NotificationService.php:1049` |

## Pro Plugin Actions **[PRO]**

These hooks are fired exclusively by the Pro plugin and require an active Pro license.

### WooCommerce Integration Actions

Unlike the EDD, FluentCart, and SureCart adapters, the WooCommerce adapter does
not fire its own namespaced lifecycle hooks. It reuses the **core order hooks**
instead, so code written against `wpss_order_created` /
`wpss_order_status_changed` works identically whether the sale came through
WooCommerce or standalone checkout.

| Hook | Parameters | File |
|------|-----------|------|
| `wpss_order_created` | `int $order_id, string $status` | `WCOrderProvider.php` |
| `wpss_order_status_changed` | `int $order_id, string $new_status, string $old_status` | `WCOrderProvider.php` |
| `wpss_max_order_quantity` | `int $max, int $service_id` | `WCCheckoutProvider.php` |

> Earlier documentation listed `wpss_woocommerce_adapter_init`,
> `wpss_service_synced_to_wc_product`, `wpss_after_checkout_process` and
> `wpss_service_to_wc_status_map`. **None of these are fired** -- use the core
> order hooks above. To react to product sync, hook `wpss_service_updated`.

### EDD Integration Actions

| Hook | Parameters | File |
|------|-----------|------|
| `wpss_edd_adapter_init` | `EDDAdapter $adapter` | `EDDAdapter.php:163` |
| `wpss_edd_service_purchased` | `ServiceItem $item, int $order_id` | `EDDOrderProvider.php:355` |
| `wpss_edd_services_processed` | `int $order_id, ServiceItem[] $items` | `EDDOrderProvider.php:370` |
| `wpss_edd_order_record_created` | `int $record_id, ServiceItem $item, int $order_id` | `EDDOrderProvider.php:595` |
| `wpss_edd_service_meta_saved` | `int $product_id` | `EDDProductProvider.php:232` |
| `wpss_edd_service_checkout_processed` | `int $order_id, int $download_id, array $service_data, int $index` | `EDDCheckoutProvider.php:222` |

### FluentCart Integration Actions

| Hook | Parameters | File |
|------|-----------|------|
| `wpss_fluentcart_adapter_init` | `FluentCartAdapter $adapter` | `FluentCartAdapter.php:157` |
| `wpss_fluentcart_order_created` | `int $order_id, int $external_order_id, array $order_data` | `FluentCartOrderProvider.php:93` |
| `wpss_fluentcart_order_detail` | `object $order` | `FluentCartAccountProvider.php:384` |

> `wpss_fluentcart_product_created` was removed in 1.6.1. FluentCart is a
> payment rail, not a catalogue: the plugin no longer creates FluentCart
> products, so there is no creation event to fire. A service is linked to an
> existing FluentCart product instead.

> **SureCart integration removed in 1.6.1.** Its four namespaced hooks
> (`wpss_surecart_adapter_init`, `wpss_surecart_order_created`,
> `wpss_surecart_product_created`, `wpss_surecart_order_detail`) no longer
> exist. SureCart keeps products and prices as objects in its own cloud and
> settles through webhooks, so it cannot act as a payment rail the way
> WooCommerce, EDD and FluentCart do -- charging an arbitrary amount would mean
> creating a remote price object per order.

### Wallet Actions

| Hook | Parameters | File |
|------|-----------|------|
| `wpss_wallet_credited` | `int $user_id, float $amount, string $description, string $provider_id` | `WalletManager.php:253` |
| `wpss_wallet_debited` | `int $user_id, float $amount, string $description, string $provider_id` | `WalletManager.php:292` |
| `wpss_vendor_payout_processed` | `int $order_id, int $vendor_id, float $amount` | `WalletManager.php:391` |
| `wpss_terawallet_recharged` | `int $transaction_id, float $amount` | `TeraWalletProvider.php:203` |
| `wpss_mycred_balance_changed` | `int $user_id, float $amount, string $reference` | `MyCredProvider.php:253` |

### Razorpay Actions

| Hook | Parameters | File |
|------|-----------|------|
| `wpss_razorpay_refund_processed` | `string $payment_id, array $refund` | `RazorpayGateway.php:876` |

### Stripe Connect Actions

| Hook | Parameters | File |
|------|-----------|------|
| `wpss_pro_connect_payout_paid` | `string $payout_id, string $account_id, float $amount, string $currency` | `ConnectWebhookHandler.php:185` |
| `wpss_pro_connect_payout_failed` | `string $payout_id, string $account_id, string $failure_code, string $failure_message` | `ConnectWebhookHandler.php:226` |
| `wpss_pro_connect_transfer_created` | `string $transfer_id, string $account_id, float $amount, string $currency` | `ConnectWebhookHandler.php:267` |

### Recurring Services Actions

| Hook | Parameters | File |
|------|-----------|------|
| `wpss_recurring_renewal_order_created` | `int $new_order_id, int $subscription_id, object $subscription` | `RecurringOrderFactory.php:119` |
| `wpss_recurring_payment_failed` | `int $subscription_id, object $subscription` | `RecurringWebhookHandler.php:191` |
| `wpss_recurring_subscription_cancelled` | `int $subscription_id, object $subscription` | `RecurringWebhookHandler.php:229` |

### Analytics Actions

| Hook | Parameters | File |
|------|-----------|------|
| `wpss_analytics_init` | `AnalyticsManager $manager` | `AnalyticsManager.php:93` |

### Gateway Settings Actions

| Hook | Parameters | File |
|------|-----------|------|
| `wpss_gateway_settings_{$gateway_id}` | *(none)* | `Pro.php:1057` |

## Pro Plugin Filters **[PRO]**

### EDD Filters

| Filter | Parameters | File |
|--------|-----------|------|
| `wpss_edd_cart_item_data` | `$cart_item_data, $product_id, $variation_id` | `EDDCheckoutProvider.php:56` |
| `wpss_edd_validate_add_to_cart` | `$valid, $product_id, $quantity` | `EDDCheckoutProvider.php:97` |
| `wpss_edd_thankyou_redirect` | `$redirect, $order_id` | `EDDCheckoutProvider.php:249` |
| `wpss_edd_can_access_vendor_dashboard` | `$can_access, $user_id` | `EDDAccountProvider.php:516` |

### WooCommerce Filters

| Filter | Parameters | File |
|--------|-----------|------|

## Related Documentation

- [REST API Reference](rest-api-overview.md) - API endpoints and authentication
- [Custom Integrations](custom-integrations.md) - Building custom adapters and gateways
- [Theme Integration](theme-integration.md) - Template overrides and styling

---

## Paying for a single order (sub-orders)

Tips, milestone phases and extension quotes are **sub-orders**: a real WPSS
order of their own, created against a parent order, that the buyer pays
separately. They are the one deliberate exception to the one-rail catalog rule
— the catalog checkout belongs entirely to the active platform, but a sub-order
has no cart and cannot go through it.

### `wpss_get_pay_order_url( int $order_id ): string`

**The single source for "pay THIS order."** Every surface that links a buyer to
a payment — dashboard timeline, order view, emails, notifications — must call
it. Never build a `?pay_order=` link by hand; it is correct only on one rail.

```php
$url = wpss_get_pay_order_url( $order_id );
```

### `wpss_pay_order_url` (filter)

```php
apply_filters( 'wpss_pay_order_url', string $url, int $order_id );
```

A cart-based rail replaces the URL entirely. Pro's WooCommerce implementation
(`WCPayOrderResolver`) creates — or reuses — a real WooCommerce order for the
sub-order and returns its native order-pay URL, so the link works from an email
days later with no cart session.

### Supported rails

| `ecommerce_platform` | Sub-order Pay | How |
|---|---|---|
| `standalone` | Supported | `?pay_order=N` on the WPSS checkout page |
| `woocommerce` | Supported | Real WC order + native order-pay URL (Pro) |

These are the two rails the sub-order payment path is built and tested against.

A platform that has not implemented `wpss_pay_order_url` inherits the standalone
URL, which is not the checkout that rail owns — so implement the filter for any
new platform the way `WCPayOrderResolver` does. Do **not** solve it by
re-enabling WPSS gateways alongside the platform's own, which would break the
one-rail contract.

### Why `wpss_get_checkout_base_url()` is not the answer

It returns the **active rail's** checkout — WooCommerce's under Woo. Sending a
buyer there for a sub-order lands them on an empty cart, because a sub-order was
never added to one. That is the trap the filter above exists to avoid, and the
reason browser Pay never reaches `StandaloneCheckoutProvider::render_pay_order_checkout()`
on a cart rail.

## Mobile Session and Selling Limits (1.6.0)

### Token lifetime

| Filter | Parameters | File |
|--------|-----------|------|
| `wpss_app_token_lifetime` | `array{idle:int, absolute:int} $lifetime` | `functions/misc.php` |
| `wpss_token_recovery_routes` | `array<int,string> $routes` | `API/AppTokenGuard.php` |

App tokens expire 30 days after last use or 90 days after issue, whichever comes
first. Returning `0` for either key disables that limit; disabling both restores
the pre-1.6.0 behaviour of tokens that never expire, which is what that release
was filed to end.

```php
// A 7-day idle window for a high-security marketplace.
add_filter( 'wpss_app_token_lifetime', function ( array $lifetime ): array {
    $lifetime['idle'] = 7 * DAY_IN_SECONDS;
    return $lifetime;
} );
```

`wpss_token_recovery_routes` lists the routes reachable with an **expired** token
in the Authorization header - by default `/auth/login`, `/auth/register` and
`/auth/forgot-password`.

Do not add a route that reads the current user. WordPress 401s the whole request
on a failed application password, so without this carve-out an app that attaches
its stored token to every request could never reach the login route to replace
it. These three take their credentials from the request body and grant nothing
on their own, which is what makes them safe to open.

### Selling limits

| Filter | Parameters | File |
|--------|-----------|------|
| `wpss_member_bypasses_limits` | `bool $bypasses, int $user_id` | `functions/vendors.php` |
| `wpss_vendor_can_create_service` | `bool $can_create, int $vendor_id` | two gates, see below |

Administrators bypass vendor selling limits by default, so a site owner seeding
demo content or building services for a client does not meet their own paywall.

```php
// Meter administrators too.
add_filter( 'wpss_member_bypasses_limits', '__return_false' );
```

**`wpss_vendor_can_create_service` has TWO gates on it**, and this catches
people out: this plugin enforces a per-profile maximum at priority 10, and Pro's
plan enforcer runs at priority 20. If you are testing whether a member may
create a service, run the **filter** - calling either class directly can return
`true` while the filter answers `false`.

## Presence and Messaging (1.6.0)

| Filter | Parameters | File |
|--------|-----------|------|
| `wpss_skip_message_email_when_online` | `bool $skip, int $recipient_id, bool $enabled` | `functions/notifications.php` |
| `wpss_presence_window` | `int $seconds` | `functions/notifications.php` |
| `wpss_messages_per_page` | `int $per_page` | `templates/dashboard/sections/messages.php` |

There is no settings screen for the presence behaviour - it is on by default and
adjusted here.

## Gallery and Layout (1.6.0)

| Filter | Parameters | File |
|--------|-----------|------|
| `wpss_video_thumbnail_cache_ttl` | `int $ttl, string $video_url` | `functions/services.php` |
| `wpss_gallery_image_size` | `string $size, int $service_id` | `partials/service-gallery.php` |
| `wpss_sticky_top_offset` | `int $offset` | `Frontend/Frontend.php` |

A video thumb uses the embed provider's own poster frame, fetched through oEmbed
and cached for a week. That call is an HTTP request to the provider, so if you
shorten the TTL, shorten it deliberately - an uncached lookup puts a third-party
round trip in front of every visitor.

Use `wpss_sticky_top_offset` when a theme has its own sticky header that the
plugin's measurement cannot see.

## Categories (1.6.0)

| Filter | Parameters | File |
|--------|-----------|------|
| `wpss_category_terms_limit` | `int $limit` | `functions/services.php` |

Category choosers cap at 200 terms. The helper `wpss_group_category_terms()`
turns a flat term list into parents each carrying their children, and is what
every single-dropdown chooser in the plugin uses - reuse it rather than grouping
by hand, or the two will drift as they did before 1.6.0.

Orphans - a child whose parent is missing because `hide_empty` dropped it - are
promoted to top level rather than discarded, so a category with services in it
always stays reachable.
