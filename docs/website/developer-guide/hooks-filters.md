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
| `wpss_loaded` | `Plugin $plugin` | `src/Core/Plugin.php:293` |
| `wpss_adapter_initialized` | `EcommerceAdapterInterface $adapter` | `src/Integrations/IntegrationManager.php:136` |
| `wpss_register_field_types` | `FieldManager $manager` | `src/CustomFields/FieldManager.php:61` |

**`wpss_loaded`** is the primary extension hook. All Pro features register here:

```php
add_action( 'wpss_loaded', function( $plugin ) {
    // Plugin is ready - register extensions
}, 10, 1 );
```

## Service Actions

| Hook | Parameters | File |
|------|-----------|------|
| `wpss_service_created` | `int $post_id, array $data` | `src/Services/ServiceManager.php:177` |
| `wpss_service_updated` | `int $service_id, array $data` | `src/Services/ServiceManager.php:289` |

### Service Filters

| Filter | Parameters | File |
|--------|-----------|------|
| `wpss_pre_create_service` | `array $data` | `ServiceManager.php` |
| `wpss_pre_update_service` | `array $data, int $service_id` | `ServiceManager.php` |
| `wpss_before_service_deleted` | `int $service_id` | `src/Services/ServiceManager.php:323` |
| `wpss_service_meta_saved` | `int $post_id, WP_Post $post` | `src/Admin/Metaboxes/ServiceMetabox.php:969` |
| `wpss_rest_service_created` | `int $service_id, WP_REST_Request $request` | `src/API/ServicesController.php:691` |
| `wpss_rest_service_updated` | `int $service_id, WP_REST_Request $request` | `src/API/ServicesController.php:777` |
| `wpss_rest_service_deleted` | `int $service_id, bool $force` | `src/API/ServicesController.php:826` |

## Moderation Actions

| Hook | Parameters | File |
|------|-----------|------|
| `wpss_service_approved` | `int $service_id, string $notes` | `src/Services/ModerationService.php:184` |
| `wpss_service_rejected` | `int $service_id, string $reason` | `src/Services/ModerationService.php:236` |
| `wpss_service_pending_moderation` | `int $service_id` | `src/Services/ModerationService.php:276` |

## Order Actions

| Hook | Parameters | File |
|------|-----------|------|
| `wpss_order_status_changed` | `int $order_id, string $new_status, string $old_status` | `src/Services/OrderService.php:429` |
| `wpss_order_status_{status}` | `int $order_id, string $old_status` | `OrderService.php:197` |
| `wpss_order_created` | `int $order_id, string $status` | `src/functions/orders.php:813` |

### Order Filters

| Filter | Parameters | File |
|--------|-----------|------|
| `wpss_pre_create_order` | `array $order_data` | `StandaloneOrderProvider.php` |
| `wpss_pre_order_status_change` | `bool $allow, int $order_id, string $new_status, string $old_status` | `OrderService.php` |
| `wpss_order_started` | `int $order_id` | `src/API/OrdersController.php:791` |
| `wpss_order_completed` | `int $order_id, object $order` | `src/Services/OrderWorkflowManager.php:685` |
| `wpss_order_cancelled` | `int $order_id, int $user_id, string $reason` | `src/Services/OrderWorkflowManager.php:762` |
| `wpss_order_disputed` | `int $order_id, int $opened_by, string $reason` | `src/API/OrdersController.php:959` |
| `wpss_order_message_created` | `int $message_id, int $order_id, int $user_id` | `src/API/OrdersController.php:623` |
| `wpss_order_requirements_submitted` | `int $order_id, array $requirements` | `src/API/OrdersController.php:1165` |
| `wpss_requirement_field_label` | `string $label, string $key` | `src/functions/orders.php:298` |
| `wpss_after_status_change_notification` | `int $order_id, string $new_status, string $old_status` | `src/Services/OrderWorkflowManager.php:646` |
| `wpss_send_requirements_reminder_email` | `int $order_id, int $reminder_num, string $message` | `src/Services/OrderWorkflowManager.php:413` |
| `wpss_requirements_timeout` | `int $order_id, bool $auto_start` | `src/Services/OrderWorkflowManager.php:546` |

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
| `wpss_delivery_submitted` | `int $delivery_id, int $order_id` | `src/Services/DeliveryService.php:151` |
| `wpss_delivery_accepted` | `int $order_id` | `src/Services/DeliveryService.php:197` |
| `wpss_revision_requested` | `int $order_id, string $reason` | `src/Services/DeliveryService.php:251` |
| `wpss_requirements_submitted` | `int $order_id, array $field_data, array $attachments` | `src/Services/RequirementsService.php:543` |
| `wpss_cancellation_requested` | `int $order_id, int $user_id, string $reason, string $note` | `src/Services/OrderService.php:945` |
| `wpss_order_auto_refunded` | `int $order_id, object $order, mixed $refund_result` | `src/Services/OrderWorkflowManager.php:1491` |
| `wpss_new_order_message` | `int $order_id, int $sender_id, string $content` | `src/Services/ConversationService.php:351` |

## Payment and Gateway Actions

These hooks fire during payment processing, gateway interactions, and checkout flow.

### Standalone Adapter

| Hook | Parameters | File |
|------|-----------|------|
| `wpss_standalone_adapter_init` | `StandaloneAdapter $adapter` | `src/Integrations/Standalone/StandaloneAdapter.php:156` |
| `wpss_standalone_checkout_processed` | `int $order_id, array $order_data` | `StandaloneCheckoutProvider.php:133` |
| `wpss_standalone_order_complete` | `object $order` | `src/Integrations/Standalone/StandaloneOrderProvider.php:719` |
| `wpss_order_paid` | `int $order_id, string $transaction_id` | `src/Integrations/Standalone/StandaloneOrderProvider.php:415` |
| `wpss_order_status_pending_requirements` | `int $order_id, string $old_status` | `src/Integrations/Standalone/StandaloneOrderProvider.php:406` |
| `wpss_payment_callback` | `string $gateway_id` | `src/Integrations/Standalone/StandaloneAdapter.php:335` |

### Offline Gateway

| Hook | Parameters | File |
|------|-----------|------|
| `wpss_offline_multi_orders_created` | `array $order_ids, int $customer_id` | `src/Integrations/Gateways/OfflineGateway.php:699` |
| `wpss_offline_order_created` | `int $order_id, object $order` | `src/Integrations/Gateways/OfflineGateway.php:649` |
| `wpss_offline_order_paid` | `int $order_id, string $transaction_id` | `src/Integrations/Gateways/OfflineGateway.php:868` |

### Stripe Gateway

| Hook | Parameters | File |
|------|-----------|------|
| `wpss_stripe_webhook_received` | `string $event_type, object $data, string $payload` | `src/Integrations/Stripe/StripeGateway.php:649` |
| `wpss_stripe_refund_processed` | `string $payment_intent_id, object $charge` | `src/Integrations/Stripe/StripeGateway.php:1503` |

### PayPal Gateway

| Hook | Parameters | File |
|------|-----------|------|
| `wpss_paypal_refund_processed` | `string $url, array $resource` | `src/Integrations/PayPal/PayPalGateway.php:938` |

### Payment REST API

| Hook | Parameters | File |
|------|-----------|------|
| `wpss_rest_offline_order_created` | `int $order_id, object $order, string $gateway_id` | `src/API/PaymentController.php:475` |

### Payment Filters

| Filter | Parameters | File |
|--------|-----------|------|
| `wpss_stripe_payment_intent_args` | `array $params, int $order_id, int $vendor_id` | `src/Integrations/Stripe/StripeGateway.php:302` |
| `wpss_rest_create_payment_intent` | `null, object $gateway, float $amount, string $currency, int $service_id, int $package_id, object $pay_order` | `src/API/PaymentController.php:267` |
| `wpss_rest_confirm_payment` | `null, object $gateway, string $payment_id, int $service_id, int $package_id, object $pay_order` | `src/API/PaymentController.php:316` |
| `wpss_checkout_tax_rate` | `float $tax_rate, int $vendor_id, int $service_id` | `src/functions/money.php:484` |

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
| `wpss_after_cascade_delete_user` | `int $user_id` | `src/Services/DataCascadeHandler.php:260` |

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
| `wpss_vendor_pitch_stats` | `array $stats` | `src/functions/vendors.php:804` |
| `wpss_vendor_pitch_steps` | `array $steps` | `src/Frontend/Shortcodes.php:1363` |

| Hook | Parameters | File |
|------|-----------|------|
| `wpss_vendor_registered` | `int $user_id, array $profile_data` | `src/Services/VendorService.php:182` |
| `wpss_vendor_profile_updated` | `int $user_id, array $filtered_data` | `src/Services/VendorService.php:492` |
| `wpss_vendor_vacation_mode_changed` | `int $user_id, bool $enabled, string $message` | `src/Services/VendorService.php:543` |
| `wpss_vendor_tier_changed` | `int $user_id, string $tier` | `src/Services/VendorService.php:584` |
| `wpss_vendor_level_promoted` | `int $user_id, string $new_level, string $current_level` | `src/Services/OrderWorkflowManager.php:617` |
| `wpss_vendor_level_updated` | `int $user_id, string $level` | `src/Services/SellerLevelService.php:285` |
| `wpss_vendor_status_updated` | `int $vendor_id, string $status` | `src/Admin/Pages/VendorsPage.php:1151` |
| `wpss_vendor_commission_updated` | `int $vendor_id, float $rate` | `src/Admin/Pages/VendorsPage.php:1550` |
| `wpss_vendor_contacted` | `int $vendor_id, int $user_id, int $service_id, string $message, array $attachments` | `src/Frontend/AjaxHandlers.php:2314` |
| `wpss_vendor_access_granted` | `int $user_id` | `src/Services/VendorService.php:371` |
| `wpss_vendor_access_revoked` | `int $user_id` | `src/Services/VendorService.php:425` |

## Financial Actions

### Financial Filters

| Filter | Parameters | File |
|--------|-----------|------|
| `wpss_commission_base_amount` | `float $base_amount, int $order_id, int $vendor_id` | `CommissionService.php` |

| Hook | Parameters | File |
|------|-----------|------|
| `wpss_commission_recorded` | `int $order_id, array $commission, int $vendor_id` | `src/Services/CommissionService.php:255` |
| `wpss_withdrawal_requested` | `int $withdrawal_id, int $vendor_id, float $amount` | `src/Services/EarningsService.php:696` |
| `wpss_withdrawal_processed` | `int $withdrawal_id, string $status, object $withdrawal` | `src/Services/EarningsService.php:498` |
| `wpss_auto_withdrawal_created` | `int $withdrawal_id, int $vendor_id, float $amount` | `src/Services/EarningsService.php:1299` |
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
| `wpss_dispute_opened` | `int $dispute_id, int $order_id, int $opened_by, array $data` | `src/Services/DisputeService.php:321` |
| `wpss_dispute_evidence_added` | `int $dispute_id, int $user_id` | `src/Services/DisputeService.php:454` |
| `wpss_dispute_status_changed` | `int $dispute_id, string $status, string $old_status` | `src/Services/DisputeService.php:720` |
| `wpss_dispute_resolved` | `int $dispute_id, string $resolution, object $dispute, float $refund_amount` | `src/Services/DisputeService.php:846` |
| `wpss_dispute_response_submitted` | `int $message_id, int $dispute_id, int $user_id` | `src/Services/DisputeWorkflowManager.php:183` |
| `wpss_dispute_escalated` | `int $dispute_id, string $reason, int $escalated_by` | `src/Services/DisputeWorkflowManager.php:310` |
| `wpss_dispute_cancelled` | `int $dispute_id, int $user_id, string $reason` | `src/Services/DisputeWorkflowManager.php:473` |

## Review, Request, and Proposal Actions

### Review Filters

| Filter | Parameters | File |
|--------|-----------|------|
| `wpss_pre_create_review` | `array $review_data, int $order_id` | `ReviewService.php` |

| Hook | Parameters | File |
|------|-----------|------|
| `wpss_review_created` | `int $review_id, int $order_id` | `src/Services/ReviewService.php:122` |
| `wpss_review_reply_created` | `int $review_id` | `src/API/ReviewsController.php:626` |
| `wpss_buyer_request_created` | `int $post_id, array $data` | `BuyerRequestService.php:112` |
| `wpss_buyer_request_updated` | `int $request_id, array $data` | `BuyerRequestService.php:164` |
| `wpss_buyer_request_status_changed` | `int $request_id, string $status, string $old_status` | `src/Services/BuyerRequestService.php:478` |
| `wpss_request_converted_to_order` | `int $order_id, int $request_id, int $proposal_id, object $request, object $proposal` | `src/Services/BuyerRequestService.php:913` |
| `wpss_proposal_submitted` | `int $proposal_id, int $request_id, int $vendor_id, array $proposal_data` | `src/Services/ProposalService.php:187` |
| `wpss_proposal_updated` | `int $proposal_id, array $update_data` | `src/Services/ProposalService.php:361` |
| `wpss_proposal_accepted` | `int $proposal_id, object $proposal, object $request` | `src/Services/BuyerRequestService.php:855` |
| `wpss_proposal_rejected` | `int $proposal_id, object $proposal, string $reason` | `src/Services/ProposalService.php:451` |
| `wpss_proposal_withdrawn` | `int $proposal_id, object $proposal` | `src/Services/ProposalService.php:493` |
| `wpss_proposal_deleted` | `int $proposal_id, object $proposal` | `src/Services/ProposalService.php:873` |
| `wpss_proposal_status_updated` | `int $proposal_id, string $status` | `src/Services/ProposalService.php:576` |
| `wpss_buyer_request_deleted` | `int $request_id` | `src/Services/BuyerRequestService.php:1073` |
| `wpss_buyer_request_meta_saved` | `int $post_id, WP_Post $post` | `src/Admin/Metaboxes/BuyerRequestMetabox.php:349` |

## Milestone and Extension Actions

A milestone is a **sub-order** of the parent order, so every hook passes both
ids: `$milestone_id` is the sub-order, `$order_id` is the parent. See
[Sub-Order Pattern](https://github.com/vapvarun/wp-sell-services/blob/main/docs/architecture/SUB_ORDER_PATTERN.md).

| Hook | Parameters | File |
|------|-----------|------|
| `wpss_milestone_proposed` | `int $milestone_id, int $order_id, int $vendor_id` | `src/Services/MilestoneService.php:262` |
| `wpss_milestone_paid` | `int $milestone_id, int $order_id, int $vendor_id, int $customer_id, float $vendor_earnings` | `src/Services/MilestoneService.php:411` |
| `wpss_milestone_submitted` | `int $milestone_id, int $order_id, int $vendor_id, int $customer_id` | `src/Services/MilestoneService.php:482` |
| `wpss_milestone_approved` | `int $milestone_id, int $order_id, int $vendor_id, int $customer_id` | `src/Services/MilestoneService.php:535` |
| `wpss_milestone_declined` | `int $milestone_id, int $order_id, int $customer_id` | `src/Services/MilestoneService.php:709` |
| `wpss_milestone_revision_requested` | `int $milestone_id, int $parent_id, int $vendor_id, int $customer_id, string $reason` | `src/Services/MilestoneService.php:655` |
| `wpss_extension_request_created` | `int $request_id, int $order_id, array $data` (`requested_by`, `extra_days`, `reason`) | `ExtensionRequestService.php:249` |
| `wpss_extension_request_approved` | `int $request_id, object $request` | `ExtensionRequestService.php:371` |
| `wpss_extension_request_rejected` | `int $request_id, object $request` | `ExtensionRequestService.php:455` |
| `wpss_extension_approved` | `int $pay_order_id, int $parent_order_id, int $vendor_id, int $customer_id, float $vendor_earnings, int $extra_days, int $request_id` | Paid extension approved and the vendor credited. NET earnings, not gross. `ExtensionOrderService.php` |

> **Renamed in 1.3.0.** The milestone lifecycle uses *proposed* and *declined*,
> not *created* and *rejected* -- see [Milestone terminology](https://github.com/vapvarun/wp-sell-services/blob/main/docs/decisions/milestone-terminology.md).
> `wpss_milestone_created`, `wpss_milestone_rejected` and
> `wpss_extension_requested` were listed in earlier docs but are **not fired by
> the plugin**. Callbacks bound to those names never run.
>
> `wpss_extension_approved` is a different case: it **does** fire, with seven
> arguments, when a paid extension is approved and the vendor is credited. It
> was wrongly grouped with the three above. See the extensions table. Note also that
> `wpss_milestone_approved` passes `$vendor_id` as its third argument, not an
> amount -- if you need the money, read it from the sub-order or hook
> `wpss_milestone_paid`.

## Admin and Settings Actions

These hooks fire in the WordPress admin area for order management, service meta, and settings pages.

| Hook | Parameters | File |
|------|-----------|------|
| `wpss_admin_order_actions` | `object $order, string $status` | `src/Admin/Admin.php:2382` |
| `wpss_admin_requirements_submitted` | `int $order_id, array $field_data` | `src/Admin/OrderScreen.php:335` |
| `wpss_gateway_cards` | `Settings $settings` | `src/Admin/Settings.php:1945` |

### Admin Filters

| Filter | Parameters | File |
|--------|-----------|------|
| `wpss_service_meta_fields` | `array $fields, int $post_id` | `src/Admin/Metaboxes/ServiceMetabox.php:245` |
| `wpss_pro_upgrade_url` | `string $url` (default `https://wpsellservices.com/`) | `UpgradePage.php`, `ServiceWizard.php:961` |
| `wpss_docs_url` | `string $url` (default `https://wpsellservices.com/docs/`) | `src/Admin/Pages/UpgradePage.php:347` |

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
| `wpss_catalog_price_html` | `string $html, float $amount, string $context` | `src/functions/money.php:145` |

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
| `wpss_service_wizard_saved` | `int $service_id, array $sanitized_data` | `src/Frontend/ServiceWizard.php:1708` |
| `wpss_wizard_pricing_after` | `WP_Post\|null $service` | `ServiceWizard.php` |
| `wpss_wizard_save_service_meta` | `int $service_id, array $data` | `ServiceWizard.php` |

### Service Wizard Filters

| Filter | Parameters | File |
|--------|-----------|------|
| `wpss_vendor_can_create_service` | `bool $can_create, int $user_id` | `src/API/ServicesController.php:1126` |
| `wpss_services_per_page` | `int $per_page` (default 12) | `src/Frontend/ServiceArchiveView.php:653` |
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
| `wpss_dashboard_section_before_content` | `string $section, int $user_id` | `src/Frontend/UnifiedDashboard.php:887` |

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
| `wpss_add_service_to_cart` | `bool $added, array $cart_item, object $adapter` | `src/Frontend/AjaxHandlers.php:2500` |
| `wpss_pay_order_url` | `string $url, int $order_id` | `src/functions/urls.php:845` |

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
| `wpss_email_from_name` | `string $from_name` | `src/Services/EmailService.php:2018` |
| `wpss_email_header_vars` | `array $template_vars, string $type` | `src/Services/EmailService.php:2002` |
| `wpss_vendor_pending_email_content` | `string $content, object $user, string $platform_name` | `src/Services/NotificationService.php:1463` |
| `wpss_vendor_approved_email_content` | `string $content, object $user, string $platform_name` | `src/Services/NotificationService.php:1596` |
| `wpss_vendor_rejected_email_content` | `string $content, object $user, string $platform_name` | `src/Services/NotificationService.php:1664` |

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
| `wpss_message_sent` | `object $message, object $conversation` | `src/Services/ConversationService.php:342` |
| `wpss_notification_created` | `int $notification_id, int $user_id, string $type, array $data` | `src/Services/NotificationService.php:111` |
| `wpss_portfolio_item_created` | `int $item_id, int $vendor_id, array $data` | `src/Services/PortfolioService.php:199` |
| `wpss_portfolio_item_updated` | `int $item_id, array $data` | `src/Services/PortfolioService.php:294` |
| `wpss_portfolio_item_deleted` | `int $item_id, object $item` | `src/Services/PortfolioService.php:344` |
| `wpss_addon_created` | `int $addon_id, int $service_id, array $addon_data` | `src/Services/ServiceAddonService.php:145` |
| `wpss_addon_updated` | `int $addon_id, array $update_data` | `src/Services/ServiceAddonService.php:231` |
| `wpss_addon_deleted` | `int $addon_id, object $addon` | `src/Services/ServiceAddonService.php:355` |
| `wpss_settings_tab_{tab}` | *(none)* | `Settings.php:985` |
| `wpss_advanced_settings_sections` | *(none)* | `src/Admin/Settings.php:2073` |

## Filters

### Provider Registration

| Filter | File | Default |
|--------|------|---------|
| `wpss_ecommerce_adapters` | `IntegrationManager.php:67` | Standalone only (Pro adds WooCommerce, EDD, FluentCart) |
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

### Data Filters

| Filter | Parameters | File |
|--------|-----------|------|
| `wpss_format_price` | `$formatted, $price, $currency` | `src/functions/money.php:57` |
| `wpss_currency` | `$currency` | `src/functions/money.php:714` |
| `wpss_platform_name` | `$platform_name` | `src/functions/misc.php:36` |
| `wpss_is_vendor` | `$is_vendor, $user_id` | `src/functions/vendors.php:147` |
| `wpss_order_number_prefix` | `$prefix` (default `'WPSS-'`) | `src/Database/Repositories/OrderRepository.php:92` |
| `wpss_currency_symbols` | `$symbols` | `src/functions/money.php:749` |
| `wpss_currency_format` | `$format, $symbol, $currency` | `src/functions/money.php:776` |
| `wpss_currencies` | `$currencies` | `src/functions/money.php:1571` |
| `wpss_order_statuses` | `$statuses` | `src/functions/orders.php:126` |
| `wpss_max_upload_size` | `$upload_max` | `src/functions/misc.php:126` |
| `wpss_allow_late_requirements_submission` | `$allow_late` | `src/functions/orders.php:493` |
| `wpss_wallet_manager` | `null` | `src/functions/money.php:1593` |

### Currency System Filters (1.2.1)

As of 1.2.1, currencies are driven by a single canonical registry (code → name, symbol, decimals). Every currency surface — price formatting, the settings dropdown, manual orders, decimal handling — reads from it, so overriding one filter updates all of them consistently.

| Filter | Parameters | File |
|--------|-----------|------|
| `wpss_currency_registry` | `array<string, array{name:string, symbol:string, decimals:int}> $registry` | `src/functions/money.php:1550` |
| `wpss_currency_decimals` | `int $decimals, string $currency` | `src/functions/money.php:185` |
| `wpss_zero_decimal_currencies` | `string[] $codes` | `src/functions/money.php:676` |
| `wpss_settings_currencies` | `array $currencies` | `src/Admin/Settings.php:3790` |
| `wpss_manual_order_currencies` | `array $currencies` | `src/Admin/Pages/ManualOrderPage.php:835` |

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
| `wpss_get_template_part` | `$template, $slug, $name` | `src/functions/templates.php:90` |
| `wpss_get_template` | `$template, $template_name, $args` | `src/functions/templates.php:141` |
| `wpss_locate_template` | `$template, $template_name, $template_path` | `src/Frontend/TemplateLoader.php:519` |
| `wpss_dashboard_section_template` | `$template_path, $section` | `src/Frontend/UnifiedDashboard.php:862` |

### URL and Taxonomy Filters

| Filter | Parameters | File |
|--------|-----------|------|
| `wpss_vendor_slug` | `$slug` (default `'provider'`) | `Plugin.php`, `functions.php` |
| `wpss_service_order_slug` | `$slug` (default `'service-order'`) | `Plugin.php`, `functions.php` |
| `wpss_checkout_slug` | `$slug` (default `'service-checkout'`) | `StandaloneAdapter.php` |
| `wpss_service_slug` | `$slug` (default `'service'`) | `src/PostTypes/ServicePostType.php:320` |
| `wpss_buyer_request_slug` | `$slug` (default `'buyer-request'`) | `src/PostTypes/BuyerRequestPostType.php:114` |
| `wpss_service_post_type_args` | `$args` | `src/PostTypes/ServicePostType.php:242` |
| `wpss_service_tag_args` | `$args` | `src/PostTypes/ServicePostType.php:304` |
| `wpss_service_category_taxonomy_args` | `$args` | `ServiceCategoryTaxonomy.php:118` |
| `wpss_service_tag_taxonomy_args` | `$args` | `ServiceTagTaxonomy.php:103` |
| `wpss_buyer_request_post_type_args` | `$args` | `src/PostTypes/BuyerRequestPostType.php:98` |

### Order, Commission, and API Filters

| Filter | Parameters | File |
|--------|-----------|------|
| `wpss_order_status_transitions` | `$transitions, $from, $to` | `src/Services/OrderService.php:636` |
| `wpss_commission_rate` | `$rate, $order, $vendor_id, $service_id` | `src/Services/CommissionService.php:320` |
| `wpss_proposal_order_revisions` | `$revisions, $proposal, $request` | `src/Services/BuyerRequestService.php:743` |
| `wpss_max_order_quantity` | `$max` | `src/Frontend/SingleServiceView.php:929` |
| `wpss_api_controllers` | `$controllers` | `src/API/API.php:183` |
| `wpss_api_public_settings` | `$settings` | `src/API/API.php:647` |
| `wpss_batch_max_requests` | `$max` (default 25) | `src/API/API.php:1290` |
| `wpss_api_cors_origins` | `$origins` | `src/API/API.php:1363` |
| `wpss_settings_tabs` | `$tabs` | `src/Admin/Settings.php:224` |
| `wpss_blocks` | `$blocks` | `src/Blocks/BlocksManager.php:95` |
| `wpss_rate_limits` | `$limits, $action` | `src/Core/RateLimiter.php:266` |

### Miscellaneous Filters

| Filter | Parameters | File |
|--------|-----------|------|
| `wpss_realtime_settings` | `array $settings` | `RealtimeService.php` |
| `wpss_review_window_days` | `$days` | `src/Services/ReviewService.php:426` |
| `wpss_auto_approve_reviews` | `$auto_approve` (default true) | `src/API/ReviewsController.php:425` |
| `wpss_vendor_registration_open` | `$open` (default true) | `src/API/VendorsController.php:615` |
| `wpss_auto_approve_vendors` | `$auto_approve` (default true) | `src/Services/VendorService.php:112` |
| `wpss_delivery_allowed_file_types` | `$types` | `src/Services/DeliveryService.php:346` |
| `wpss_requirements_allowed_file_types` | `$types` | `src/Services/RequirementsService.php:493` |
| `wpss_withdrawal_methods` | `$methods` | `src/Services/EarningsService.php:979` |
| `wpss_search_results` | `$results, $query, $args` | `SearchService.php:121` |
| `wpss_search_suggestions` | `$suggestions, $query` | `src/Services/SearchService.php:522` |
| `wpss_related_services_args` | `$args, $service` | `src/Frontend/SingleServiceView.php:816` |
| `wpss_cart_checkout` | `$result, $cart, $user_id, $payment_method` | `src/API/CartController.php:387` |
| `wpss_seller_levels` | `$levels` | `src/API/SellerLevelsController.php:266` |
| `wpss_rest_service_data` | `$data, $service, $request` | `src/API/ServicesController.php:1242` |
| `wpss_rest_order_data` | `$data, $order, $request` | `OrdersController.php` |
| `wpss_rest_review_data` | `$data, $review, $request` | `ReviewsController.php` |
| `wpss_rest_vendor_data` | `$data, $vendor, $request` | `VendorsController.php` |
| `wpss_can_access_dashboard_section` | `$allowed, $section, $user_id` | `src/Frontend/UnifiedDashboard.php:431` |
| `wpss_dashboard_sections` | `$sections, $user_id, $is_vendor` | `src/Frontend/UnifiedDashboard.php:522` |
| `wpss_dashboard_section_titles` | `$titles` | `src/Frontend/UnifiedDashboard.php:811` |

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
| `wpss_fullwidth_page_keys` | `string[] $page_keys` | `src/Frontend/TemplateLoader.php:291` |

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
| `wpss_service_schema` | `$schema, $service_id` | `src/SEO/SchemaMarkup.php:190` |
| `wpss_service_list_schema` | `$schema` | `src/SEO/SchemaMarkup.php:228` |
| `wpss_category_schema` | `$schema, $term` | `src/SEO/SchemaMarkup.php:287` |
| `wpss_person_schema` | `$schema, $user_id` | `src/SEO/SchemaMarkup.php:340` |
| `wpss_vendor_page_schema` | `$schema, $user_id` | `src/SEO/SchemaMarkup.php:387` |
| `wpss_organization_schema` | `$schema` | `src/SEO/SchemaMarkup.php:418` |
| `wpss_open_graph_data` | `$data, $service_id` | `src/SEO/SEO.php:259` |
| `wpss_sitemap_post_types` | `$post_types` | `src/SEO/SEO.php:323` |
| `wpss_breadcrumbs` | `$breadcrumbs, $service_id` | `src/SEO/SEO.php:389` |
| `wpss_notification_email_content` | `$content, $subject, $user_id, $data` | `src/Services/NotificationService.php:1942` |
| `wpss_vendor_welcome_email_content` | `$content, $user, $platform_name` | `src/Services/NotificationService.php:1414` |
| `wpss_admin_vendor_notification_content` | `$content, $user` | `src/Services/NotificationService.php:1518` |

## Pro Plugin Actions **[PRO]**

These hooks are fired exclusively by the Pro plugin and require an active Pro license.

### WooCommerce Integration Actions

Unlike the EDD and FluentCart adapters, the WooCommerce adapter does
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
| `wpss_edd_adapter_init` | `EDDAdapter $adapter` | `src/Integrations/EDD/EDDAdapter.php:166` |
| `wpss_edd_service_purchased` | `ServiceItem $item, int $order_id` | `src/Integrations/EDD/EDDOrderProvider.php:119` |
| `wpss_edd_services_processed` | `int $order_id, ServiceItem[] $items` | `src/Integrations/EDD/EDDOrderProvider.php:149` |
| `wpss_edd_order_record_created` | `int $record_id, ServiceItem $item, int $order_id` | `src/Integrations/EDD/EDDOrderProvider.php:410` |
| `wpss_edd_service_meta_saved` | `int $product_id` | `src/Integrations/EDD/EDDProductProvider.php:239` |
| `wpss_edd_service_checkout_processed` | `int $order_id, int $download_id, array $service_data, int $index` | `EDDCheckoutProvider.php:222` |

### FluentCart Integration Actions

| Hook | Parameters | File |
|------|-----------|------|
| `wpss_fluentcart_adapter_init` | `FluentCartAdapter $adapter` | `src/Integrations/FluentCart/FluentCartAdapter.php:213` |
| `wpss_fluentcart_order_created` | `int $order_id, int $external_order_id, array $order_data` | `src/Integrations/FluentCart/FluentCartOrderProvider.php:110` |
| `wpss_fluentcart_order_detail` | `object $order` | `src/Integrations/FluentCart/FluentCartAccountProvider.php:366` |

> `wpss_fluentcart_product_created` was removed in 1.6.1. FluentCart is a
> payment rail, not a catalogue: the plugin no longer creates FluentCart
> products, so there is no creation event to fire. A service is linked to an
> existing FluentCart product instead.

> **SureCart integration removed in 1.6.0.** Its four namespaced hooks
> (`wpss_surecart_adapter_init`, `wpss_surecart_order_created`,
> `wpss_surecart_product_created`, `wpss_surecart_order_detail`) no longer
> exist. SureCart keeps products and prices as objects in its own cloud and
> settles through webhooks, so it cannot act as a payment rail the way
> WooCommerce, EDD and FluentCart do -- charging an arbitrary amount would mean
> creating a remote price object per order.

### Wallet Actions

| Hook | Parameters | File |
|------|-----------|------|
| `wpss_terawallet_recharged` | `int $transaction_id, float $amount` | `TeraWalletProvider.php:203` |
| `wpss_mycred_balance_changed` | `int $user_id, float $amount, string $reference` | `MyCredProvider.php:253` |

### Razorpay Actions

| Hook | Parameters | File |
|------|-----------|------|
| `wpss_razorpay_refund_processed` | `string $payment_id, array $refund` | `src/Integrations/Razorpay/RazorpayGateway.php:836` |

### Stripe Connect Actions

| Hook | Parameters | File |
|------|-----------|------|
| `wpss_pro_connect_payout_paid` | `string $payout_id, string $account_id, float $amount, string $currency` | `src/StripeConnect/ConnectWebhookHandler.php:253` |
| `wpss_pro_connect_payout_failed` | `string $payout_id, string $account_id, string $failure_code, string $failure_message` | `src/StripeConnect/ConnectWebhookHandler.php:294` |
| `wpss_pro_connect_transfer_created` | `string $transfer_id, string $account_id, float $amount, string $currency` | `src/StripeConnect/ConnectWebhookHandler.php:335` |

### Recurring Services Actions

| Hook | Parameters | File |
|------|-----------|------|
| `wpss_recurring_renewal_order_created` | `int $new_order_id, int $subscription_id, object $subscription` | `src/RecurringServices/RecurringOrderFactory.php:139` |
| `wpss_recurring_payment_failed` | `int $subscription_id, object $subscription` | `RecurringWebhookHandler.php:191` |
| `wpss_recurring_subscription_cancelled` | `int $subscription_id, object $subscription` | `RecurringWebhookHandler.php:229` |

### Analytics Actions

| Hook | Parameters | File |
|------|-----------|------|
| `wpss_analytics_init` | `AnalyticsManager $manager` | `AnalyticsManager.php:103` |

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
| `wpss_edd_can_access_vendor_dashboard` | `$can_access, $user_id` | `src/Integrations/EDD/EDDAccountProvider.php:483` |

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
