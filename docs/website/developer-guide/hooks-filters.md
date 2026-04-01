# Hooks and Filters Reference

WP Sell Services exposes action hooks and filter hooks throughout its codebase. Every hook listed here is verified in the source code with file location and parameters.

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
| `wpss_woocommerce_adapter_init` **[PRO]** | `WooCommerceAdapter $adapter` | `WooCommerceAdapter.php:162` |

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
| `wpss_before_service_deleted` | `int $service_id` | `ServiceManager.php:259` |
| `wpss_service_meta_saved` | `int $post_id, WP_Post $post` | `ServiceMetabox.php:1052` |
| `wpss_rest_service_created` | `int $service_id, WP_REST_Request $request` | `ServicesController.php:321` |
| `wpss_rest_service_updated` | `int $service_id, WP_REST_Request $request` | `ServicesController.php:386` |
| `wpss_rest_service_deleted` | `int $service_id, bool $force` | `ServicesController.php:431` |
| `wpss_service_synced_to_wc_product` **[PRO]** | `int $service_id, int $product_id` | `WCProductProvider.php:454` |

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
| `wpss_order_accepted` | `int $order_id` | `OrdersController.php:564` |
| `wpss_order_rejected` | `int $order_id, string $reason` | `OrdersController.php:582` |
| `wpss_order_started` | `int $order_id` | `OrdersController.php:598` |
| `wpss_order_delivered` | `int $order_id` | `OrdersController.php:613` |
| `wpss_order_completed` | `int $order_id, object $order` | `OrderWorkflowManager.php:685` |
| `wpss_order_cancelled` | `int $order_id, int $user_id, string $reason` | `OrderService.php:427` |
| `wpss_order_disputed` | `int $order_id, int $opened_by, string $reason` | `OrdersController.php:670` |
| `wpss_order_message_created` | `int $message_id, int $order_id, int $user_id` | `OrdersController.php:406` |
| `wpss_order_requirements_submitted` | `int $order_id, array $requirements` | `OrdersController.php:839` |
| `wpss_after_status_change_notification` | `int $order_id, string $new_status, string $old_status` | `OrderWorkflowManager.php:638` |
| `wpss_send_requirements_reminder_email` | `int $order_id, int $reminder_num, string $message` | `OrderWorkflowManager.php:338` |
| `wpss_requirements_timeout` | `int $order_id, bool $auto_start` | `OrderWorkflowManager.php:472` |
| `wpss_after_checkout_process` **[PRO]** | `int $order_id, array $order_data` | `WCCheckoutProvider.php:332` |

## Delivery Actions

| Hook | Parameters | File |
|------|-----------|------|
| `wpss_delivery_submitted` | `int $delivery_id, int $order_id` | `DeliveryService.php:127` |
| `wpss_delivery_accepted` | `int $order_id` | `DeliveryService.php:168` |
| `wpss_revision_requested` | `int $order_id, string $reason` | `DeliveryService.php:234` |
| `wpss_requirements_submitted` | `int $order_id, array $field_data, array $attachments` | `RequirementsService.php:461` |

## Vendor Actions

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

## Financial Actions

| Hook | Parameters | File |
|------|-----------|------|
| `wpss_commission_recorded` | `int $order_id, array $commission, int $vendor_id` | `CommissionService.php:116` |
| `wpss_withdrawal_requested` | `int $withdrawal_id, int $vendor_id, float $amount` | `EarningsService.php:344` |
| `wpss_withdrawal_processed` | `int $withdrawal_id, string $status, object $withdrawal` | `EarningsService.php:489` |
| `wpss_auto_withdrawal_created` | `int $withdrawal_id, int $vendor_id, float $amount` | `EarningsService.php:866` |
| `wpss_tip_sent` | `int $tip_id, int $order_id, int $vendor_id, int $customer_id, float $amount, string $message` | `TippingService.php:171` |

## Dispute Actions

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

## Milestone and Extension Actions

| Hook | Parameters | File |
|------|-----------|------|
| `wpss_milestone_created` | `int $milestone_id, int $order_id, array $milestone` | `MilestoneService.php:111` |
| `wpss_milestone_submitted` | `int $milestone_id, int $order_id` | `MilestoneService.php:263` |
| `wpss_milestone_approved` | `int $milestone_id, int $order_id, float $amount` | `MilestoneService.php:311` |
| `wpss_milestone_rejected` | `int $milestone_id, int $order_id, string $feedback` | `MilestoneService.php:360` |
| `wpss_extension_request_created` | `int $request_id, int $order_id, array $data` | `ExtensionRequestService.php:246` |
| `wpss_extension_request_approved` | `int $request_id, object $request` | `ExtensionRequestService.php:363` |
| `wpss_extension_request_rejected` | `int $request_id, object $request` | `ExtensionRequestService.php:447` |

## Other Actions

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
| `wpss_available_payment_methods` | `$methods` | `CartController.php:395` |
| `wpss_seller_levels` | `$levels` | `SellerLevelsController.php:284` |
| `wpss_rest_service_data` | `$data, $service, $request` | `ServicesController.php:608` |
| `wpss_can_access_dashboard_section` | `$allowed, $section, $user_id` | `UnifiedDashboard.php:173` |
| `wpss_dashboard_sections` | `$sections, $user_id, $is_vendor` | `UnifiedDashboard.php:243` |
| `wpss_dashboard_section_titles` | `$titles` | `UnifiedDashboard.php:371` |
| `wpss_service_to_wc_status_map` **[PRO]** | `$status_map, $new_status, $old_status` | `WooCommerceAdapter.php:388` |

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
| `wpss_email_data` | `$email` | `EmailService.php:642` |

## Pro Plugin Actions **[PRO]**

These hooks are fired exclusively by the Pro plugin and require an active Pro license.

### WooCommerce Integration Actions

| Hook | Parameters | File |
|------|-----------|------|
| `wpss_woocommerce_adapter_init` | `WooCommerceAdapter $adapter` | `WooCommerceAdapter.php:162` |
| `wpss_service_synced_to_wc_product` | `int $service_id, int $product_id` | `WCProductProvider.php:454` |
| `wpss_after_checkout_process` | `int $order_id, array $order_data` | `WCCheckoutProvider.php:332` |

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
| `wpss_fluentcart_product_created` | `int $product_id, int $service_id` | `FluentCartProductProvider.php:96` |
| `wpss_fluentcart_order_detail` | `object $order` | `FluentCartAccountProvider.php:384` |

### SureCart Integration Actions

| Hook | Parameters | File |
|------|-----------|------|
| `wpss_surecart_adapter_init` | `SureCartAdapter $adapter` | `SureCartAdapter.php:152` |
| `wpss_surecart_order_created` | `int $order_id, int $external_order_id, array $order_data` | `SureCartOrderProvider.php:99` |
| `wpss_surecart_product_created` | `int $product_id, int $service_id` | `SureCartProductProvider.php:174` |
| `wpss_surecart_order_detail` | `object $order` | `SureCartAccountProvider.php:453` |

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
| `wpss_service_to_wc_status_map` | `$status_map, $new_status, $old_status` | `WooCommerceAdapter.php:388` |

## Related Documentation

- [REST API Reference](rest-api.md) - API endpoints and authentication
- [Custom Integrations](custom-integrations.md) - Building custom adapters and gateways
- [Theme Integration](theme-integration.md) - Template overrides and styling
