# Hook reference (generated)

<!-- GENERATED FILE - DO NOT EDIT.
     Written by bin/generate-hook-reference.py from the source of both
     plugins. Edits here are lost on the next run; docs-audit.py fails
     when this file is out of date. For the curated, grouped guide with
     examples, see hooks-filters.md. -->

Every hook fired by WP Sell Services and WP Sell Services Pro, taken from
source rather than maintained by hand. `hooks-filters.md` is the readable
guide; this is the complete index.

**513 hooks** across **728** firing sites.

## Actions (274)

| Hook | Args | Fired from | Description |
|---|---|---|---|
| `wpss_account_status_changed` | 2 | `src/Admin/Pages/ReportsPage.php:160` *(+1 more)* |  |
| `wpss_adapter_initialized` | 1 | `src/Integrations/IntegrationManager.php:136` | Fires after the active e-commerce adapter is initialized. |
| `wpss_admin_order_actions` | 2 | `src/Admin/Admin.php:2477` | Fires in the admin order actions area for gateway-specific actions. |
| `wpss_advanced_settings_sections` | 0 | `src/Admin/Settings.php:2077` |  |
| `wpss_after_account_deletion` | 1 | `src/Services/AccountDeletionService.php:265` | Fires after a member's account has been deleted. |
| `wpss_after_cascade_delete_request` | 1 | `src/Services/DataCascadeHandler.php:163` | Fires after buyer request cascade data is deleted. |
| `wpss_after_cascade_delete_service` | 1 | `src/Services/DataCascadeHandler.php:136` | Fires after service cascade data is deleted. |
| `wpss_after_cascade_delete_user` | 1 | `src/Services/DataCascadeHandler.php:258` | Fires after user cascade data is deleted. |
| `wpss_after_category_card` | 1 | `templates/partials/category-card.php:142` | Fires after the category card. |
| `wpss_after_conversation` | 1 | `templates/order/conversation.php:397` | Hook: wpss_after_conversation |
| `wpss_after_extension_view` | 1 | `templates/order/extension-view.php:203` | Fires after the extension sub-order view content. |
| `wpss_after_message` | 2 | `templates/order/conversation.php:274` | Hook: wpss_after_message |
| `wpss_after_milestone_view` | 1 | `templates/order/milestone-view.php:338` | Fires after the milestone sub-order view content. |
| `wpss_after_order_confirmation` | 1 | `templates/order/order-confirmation.php:269` | Hook: wpss_after_order_confirmation |
| `wpss_after_order_view` | 1 | `templates/order/order-view.php:3516` | Hook: wpss_after_order_view |
| `wpss_after_package_tab` | 3 | `templates/partials/service-packages.php:333` | Fires after a single package tab. |
| `wpss_after_request_archive` | 0 | `templates/archive-request.php:142` | Hook: wpss_after_request_archive |
| `wpss_after_request_card` | 1 | `templates/content-request-card.php:250` | Hook: wpss_after_request_card |
| `wpss_after_request_loop` | 0 | `templates/archive-request.php:119` | Hook: wpss_after_request_loop |
| `wpss_after_requirements_form` | 1 | `templates/order/order-requirements.php:193` | Hook: wpss_after_requirements_form |
| `wpss_after_requirements_form_component` | 2 | `templates/order/requirements-form.php:343` | Fires after the requirements form content. |
| `wpss_after_service_archive` | 0 | `templates/archive-service.php:147` | Hook: wpss_after_service_archive |
| `wpss_after_service_card` | 1 | `templates/content-service-card.php:282` | Hook: wpss_after_service_card |
| `wpss_after_service_faqs` | 1 | `templates/partials/service-faqs.php:67` | Fires after the service FAQs section. |
| `wpss_after_service_gallery` | 1 | `templates/partials/service-gallery.php:195` | Fires after the service gallery. |
| `wpss_after_service_loop` | 0 | `templates/archive-service.php:122` | Hook: wpss_after_service_loop |
| `wpss_after_service_packages` | 1 | `templates/partials/service-packages.php:420` | Fires after the service packages widget. |
| `wpss_after_service_reviews` | 1 | `templates/partials/service-reviews.php:201` | Fires after the service reviews section. |
| `wpss_after_single_request` | 1 | `templates/single-request.php:679` | Hook: wpss_after_single_request |
| `wpss_after_single_review` | 1 | `templates/partials/service-reviews.php:170` | Fires after a single review item. |
| `wpss_after_single_service` | 1 | `templates/single-service.php:210` | Hook: wpss_after_single_service |
| `wpss_after_status_change_notification` | 3 | `src/Services/OrderWorkflowManager.php:647` | Fires after status change processing. |
| `wpss_after_tip_view` | 1 | `templates/order/tip-view.php:149` | Fires after the tip sub-order view content. |
| `wpss_after_vendor_card` | 1 | `templates/partials/vendor-card.php:189` | Fires after the vendor card. |
| `wpss_after_vendor_portfolio` | 1 | `templates/partials/vendor-portfolio.php:315` | Fires after the vendor portfolio grid. |
| `wpss_after_vendor_profile` | 1 | `templates/vendor/profile.php:690` | Hook: wpss_after_vendor_profile |
| `wpss_analytics_init` | 1 | `src/Analytics/AnalyticsManager.php:104` **[PRO]** | Fires when analytics manager is initialized. |
| `wpss_app_session_revoked` | 2 | `src/API/AuthController.php:416` | Fires after a single app sign-in is revoked. |
| `wpss_app_sessions_revoked` | 2 | `src/functions/misc.php:512` | Fires after a member's app sessions have been revoked. |
| `wpss_auto_withdrawal_created` | 3 | `src/Services/EarningsService.php:1309` | Fires when auto withdrawal is created. |
| `wpss_before_account_deletion` | 2 | `src/Services/AccountDeletionService.php:225` | Fires before a member's account is deleted. |
| `wpss_before_cascade_delete_request` | 1 | `src/Services/DataCascadeHandler.php:152` | Fires before buyer request cascade data is deleted. |
| `wpss_before_cascade_delete_service` | 1 | `src/Services/DataCascadeHandler.php:101` | Fires before service cascade data is deleted. |
| `wpss_before_cascade_delete_user` | 1 | `src/Services/DataCascadeHandler.php:179` | Fires before user cascade data is deleted. |
| `wpss_before_category_card` | 1 | `templates/partials/category-card.php:91` | Fires before the category card. |
| `wpss_before_conversation` | 1 | `templates/order/conversation.php:142` | Hook: wpss_before_conversation |
| `wpss_before_extension_view` | 1 | `templates/order/extension-view.php:61` | Fires before the extension sub-order view content. |
| `wpss_before_milestone_view` | 1 | `templates/order/milestone-view.php:77` | Fires before the milestone sub-order view content. |
| `wpss_before_order_confirmation` | 1 | `templates/order/order-confirmation.php:59` | Hook: wpss_before_order_confirmation |
| `wpss_before_order_view` | 1 | `templates/order/order-view.php:100` | Hook: wpss_before_order_view |
| `wpss_before_package_tab` | 3 | `templates/partials/service-packages.php:93` | Fires before a single package tab. |
| `wpss_before_request_archive` | 0 | `templates/archive-request.php:78` | Hook: wpss_before_request_archive |
| `wpss_before_request_card` | 1 | `templates/content-request-card.php:101` | Hook: wpss_before_request_card |
| `wpss_before_request_loop` | 0 | `templates/archive-request.php:100` | Hook: wpss_before_request_loop |
| `wpss_before_requirements_form` | 1 | `templates/order/order-requirements.php:63` | Hook: wpss_before_requirements_form |
| `wpss_before_requirements_form_component` | 2 | `templates/order/requirements-form.php:59` | Fires before the requirements form content. |
| `wpss_before_service_archive` | 0 | `templates/archive-service.php:77` | Hook: wpss_before_service_archive |
| `wpss_before_service_card` | 1 | `templates/content-service-card.php:65` | Hook: wpss_before_service_card |
| `wpss_before_service_deleted` | 1 | `src/Services/ServiceManager.php:324` | Fires before a service is deleted. |
| `wpss_before_service_faqs` | 1 | `templates/partials/service-faqs.php:31` | Fires before the service FAQs section. |
| `wpss_before_service_gallery` | 1 | `templates/partials/service-gallery.php:70` | Fires before the service gallery. |
| `wpss_before_service_loop` | 0 | `templates/archive-service.php:103` | Hook: wpss_before_service_loop |
| `wpss_before_service_packages` | 1 | `templates/partials/service-packages.php:60` | Fires before the service packages widget. |
| `wpss_before_service_reviews` | 1 | `templates/partials/service-reviews.php:75` | Fires before the service reviews section. |
| `wpss_before_single_request` | 1 | `templates/single-request.php:133` | Hook: wpss_before_single_request |
| `wpss_before_single_service` | 1 | `templates/single-service.php:102` | Hook: wpss_before_single_service |
| `wpss_before_tip_view` | 1 | `templates/order/tip-view.php:46` | Fires before the tip sub-order view content. |
| `wpss_before_vendor_card` | 1 | `templates/partials/vendor-card.php:58` | Fires before the vendor card. |
| `wpss_before_vendor_portfolio` | 1 | `templates/partials/vendor-portfolio.php:30` | Fires before the vendor portfolio grid. |
| `wpss_before_vendor_profile` | 1 | `templates/vendor/profile.php:131` | Hook: wpss_before_vendor_profile |
| `wpss_billing_address_saved` | 2 | `src/functions/billing.php:715` | Fires after a user's billing address is saved. |
| `wpss_buyer_request_created` | 2 | `src/Services/BuyerRequestService.php:114` | Fires when a buyer request is created. |
| `wpss_buyer_request_deleted` | 1 | `src/Services/BuyerRequestService.php:1109` | Fires when a buyer request is deleted. |
| `wpss_buyer_request_meta_saved` | 2 | `src/Admin/Metaboxes/BuyerRequestMetabox.php:350` | Fires after buyer request meta is saved. |
| `wpss_buyer_request_status_changed` | 3 | `src/Services/BuyerRequestService.php:481` | Fires when request status changes. |
| `wpss_buyer_request_updated` | 2 | `src/Services/BuyerRequestService.php:167` | Fires when a buyer request is updated. |
| `wpss_cancellation_requested` | 4 | `src/Services/OrderService.php:1019` | Fires when a buyer requests order cancellation. |
| `wpss_commission_recorded` | 3 | `src/Services/CommissionService.php:290` *(+3 more)* | Fires when commission is recorded for an order. |
| `wpss_conversation_form` | 1 | `templates/order/conversation.php:293` | Hook: wpss_conversation_form |
| `wpss_conversation_header` | 1 | `templates/order/conversation.php:176` | Hook: wpss_conversation_header |
| `wpss_cron_daily` | 0 | `src/Core/Plugin.php:2919` |  |
| `wpss_dashboard_header` | 0 | `src/Frontend/UnifiedDashboard.php:719` *(+1 more)* | Fires at the start of the unified dashboard header. |
| `wpss_dashboard_section_after` | 2 | `templates/dashboard/sections/create-request.php:258` *(+13 more)* | Fires after the create request dashboard section content. |
| `wpss_dashboard_section_before` | 2 | `templates/dashboard/sections/create-request.php:25` *(+15 more)* | Fires before the create request dashboard section content. |
| `wpss_dashboard_section_before_content` | 2 | `src/Frontend/UnifiedDashboard.php:921` | Fires before the dashboard section content is rendered. |
| `wpss_delivery_accepted` | 1 | `src/Services/DeliveryService.php:198` | Fires when delivery is accepted. |
| `wpss_delivery_submitted` | 2 | `src/Services/DeliveryService.php:152` | Fires when a delivery is submitted. |
| `wpss_dispute_cancelled` | 3 | `src/Services/DisputeWorkflowManager.php:502` | Fires when a dispute is cancelled. |
| `wpss_dispute_escalated` | 3 | `src/Services/DisputeWorkflowManager.php:336` | Fires when a dispute is escalated. |
| `wpss_dispute_evidence_added` | 2 | `src/Services/DisputeService.php:463` | Fires when evidence is added to a dispute. |
| `wpss_dispute_opened` | 4 | `src/Services/DisputeService.php:328` | Fires when a dispute is opened. |
| `wpss_dispute_resolved` | 4 | `src/Services/DisputeService.php:885` | Fires when a dispute is resolved. |
| `wpss_dispute_response_submitted` | 3 | `src/Services/DisputeWorkflowManager.php:209` | Fires when a dispute response is submitted. |
| `wpss_dispute_status_changed` | 3 | `src/Services/DisputeService.php:759` | Fires when dispute status changes. |
| `wpss_earnings_ledger_actions` | 1 | `templates/dashboard/sections/earnings.php:397` | Fires in the wallet ledger header, for ledger controls. |
| `wpss_earnings_summary` | 1 | `templates/dashboard/sections/earnings.php:219` | Fires after earnings summary stats. |
| `wpss_edd_adapter_init` | 1 | `src/Integrations/EDD/EDDAdapter.php:167` **[PRO]** | Fires after EDD adapter is initialized. |
| `wpss_edd_order_record_created` | 3 | `src/Integrations/EDD/EDDOrderProvider.php:411` **[PRO]** | Fires after service order record is created. |
| `wpss_edd_service_checkout_processed` | 4 | `src/Integrations/EDD/EDDCheckoutProvider.php:223` **[PRO]** | Fires when a service item is processed during checkout. |
| `wpss_edd_service_meta_saved` | 1 | `src/Integrations/EDD/EDDProductProvider.php:238` **[PRO]** | Fires when service meta is saved for an EDD download. |
| `wpss_edd_service_purchased` | 2 | `src/Integrations/EDD/EDDOrderProvider.php:120` **[PRO]** | Fires when a service is purchased via EDD. |
| `wpss_edd_services_processed` | 2 | `src/Integrations/EDD/EDDOrderProvider.php:150` **[PRO]** | Fires after all service items in an EDD payment are processed. |
| `wpss_email_content_after` | 3 | `templates/emails/cancellation-requested.php:142` *(+35 more)* | Fires after the email content for the cancellation requested email. |
| `wpss_email_content_before` | 3 | `templates/emails/cancellation-requested.php:41` *(+35 more)* | Fires before the email content for the cancellation requested email. |
| `wpss_email_footer` | 0 | `templates/emails/email-footer.php:37` | Fires before the email footer. |
| `wpss_email_header` | 1 | `templates/emails/email-header.php:153` | Fires after the email header. |
| `wpss_extension_approved` | 7 | `src/Services/ExtensionOrderService.php:480` | Fires after a paid extension has been credited and the parent order extended. |
| `wpss_extension_rejected` | 4 | `src/Services/ExtensionOrderService.php:578` | Fires when a buyer declines a pending extension request. |
| `wpss_extension_request_approved` | 2 | `src/Services/ExtensionRequestService.php:371` | Fires after extension request is approved. |
| `wpss_extension_request_created` | 4 | `src/Services/ExtensionOrderService.php:282` *(+1 more)* | Fires after an extension sub-order has been created and is awaiting the buyer's payment. |
| `wpss_extension_request_rejected` | 2 | `src/Services/ExtensionRequestService.php:455` | Fires after extension request is rejected. |
| `wpss_fluentcart_adapter_init` | 1 | `src/Integrations/FluentCart/FluentCartAdapter.php:214` **[PRO]** | Fires after Fluent Cart adapter is initialized. |
| `wpss_fluentcart_order_created` | 3 | `src/Integrations/FluentCart/FluentCartOrderProvider.php:111` **[PRO]** | Fires when a WPSS order is created from Fluent Cart. |
| `wpss_fluentcart_order_detail` | 1 | `src/Integrations/FluentCart/FluentCartAccountProvider.php:367` **[PRO]** | Fires after order detail content. |
| `wpss_gateway_cards` | 1 | `src/Admin/Settings.php:1949` | Fires after core gateways, before Offline. |
| `wpss_gateway_refund_received` | 4 | `src/Integrations/PayPal/PayPalGateway.php:921` *(+3 more)* | Fires when a refund happened at the payment rail. |
| `wpss_gateway_settings_owned_by_rail` | 1 | `src/Admin/Settings.php:1911` | Fires instead of the gateway cards when a cart rail owns payment. |
| `wpss_loaded` | 1 | `src/Core/Plugin.php:295` | Fires after the plugin is fully loaded. |
| `wpss_message_sent` | 2 | `src/Services/ConversationService.php:343` | Fires when a message is sent. |
| `wpss_milestone_approved` | 4 | `src/Services/MilestoneService.php:533` |  |
| `wpss_milestone_declined` | 3 | `src/Services/MilestoneService.php:707` |  |
| `wpss_milestone_paid` | 5 | `src/Services/MilestoneService.php:408` | Fires after a milestone payment has cleared and the vendor has been credited. Milestone is now in_progress. |
| `wpss_milestone_proposed` | 3 | `src/Services/BuyerRequestService.php:863` *(+1 more)* |  |
| `wpss_milestone_revision_requested` | 5 | `src/Services/MilestoneService.php:652` | Fires when a buyer sends a milestone phase back for changes. |
| `wpss_milestone_submitted` | 4 | `src/Services/MilestoneService.php:480` |  |
| `wpss_mycred_balance_changed` | 3 | `src/Integrations/Wallets/MyCredProvider.php:253` **[PRO]** | Fires after MyCred balance changes for WPSS. |
| `wpss_new_order_message` | 3 | `src/Services/ConversationService.php:352` | Fires for email notification on new order message. |
| `wpss_no_requests_content` | 0 | `templates/content-no-requests.php:51` | Hook: wpss_no_requests_content |
| `wpss_no_services_content` | 0 | `templates/content-no-services.php:55` | Hook: wpss_no_services_content |
| `wpss_notification_created` | 4 | `src/Services/NotificationService.php:113` | Fires when notification is created. |
| `wpss_offline_multi_orders_created` | 2 | `src/Integrations/Gateways/OfflineGateway.php:700` | Fires after multi-service offline orders are created. |
| `wpss_offline_order_created` | 2 | `src/Integrations/Gateways/OfflineGateway.php:650` *(+1 more)* | Fires when an existing order is put on the offline rail. |
| `wpss_offline_order_paid` | 2 | `src/Integrations/Gateways/OfflineGateway.php:869` | Fires when an offline order is marked as paid. |
| `wpss_order_auto_refunded` | 3 | `src/Services/OrderWorkflowManager.php:1475` | Fires when an auto-refund is processed successfully. |
| `wpss_order_cancelled` | 2 | `src/Services/OrderWorkflowManager.php:763` | Fires when order is cancelled. |
| `wpss_order_completed` | 2 | `src/Services/OrderWorkflowManager.php:686` | Fires when order is completed. |
| `wpss_order_confirmation_details` | 1 | `templates/order/order-confirmation.php:250` | Hook: wpss_order_confirmation_details |
| `wpss_order_created` | 2 | `src/functions/orders.php:998` | Fires after a service order is created, on every e-commerce rail. |
| `wpss_order_disputed` | 3 | `src/API/OrdersController.php:1089` *(+1 more)* |  |
| `wpss_order_message_created` | 3 | `src/API/OrdersController.php:662` |  |
| `wpss_order_paid` | 2 | `src/Integrations/Standalone/StandaloneOrderProvider.php:416` | Fires when an order is marked as paid. |
| `wpss_order_partially_refunded` | 2 | `src/Services/OrderWorkflowManager.php:831` | Fires when an order is partially refunded. |
| `wpss_order_refund_processed` | 3 | `src/Services/OrderWorkflowManager.php:1395` | Fires after the gateway has processed a refund, with its raw result. |
| `wpss_order_refunded` | 2 | `src/Services/OrderWorkflowManager.php:798` | Fires when an order is refunded. |
| `wpss_order_started` | 1 | `src/API/OrdersController.php:921` |  |
| `wpss_order_status_changed` | 3 | `src/Admin/Pages/ManualOrderPage.php:767` *(+5 more)* |  |
| `wpss_order_status_pending_requirements` | 2 | `src/Integrations/Standalone/StandaloneOrderProvider.php:407` |  |
| `wpss_order_view_actions` | 1 | `templates/order/order-view.php:430` | Hook: wpss_order_view_actions |
| `wpss_order_view_details` | 1 | `templates/order/order-view.php:753` | Hook: wpss_order_view_details |
| `wpss_order_view_header` | 1 | `templates/order/order-view.php:222` | Hook: wpss_order_view_header |
| `wpss_order_view_sidebar` | 1 | `templates/order/order-view.php:1515` | Hook: wpss_order_view_sidebar |
| `wpss_orders_filters` | 1 | `templates/dashboard/sections/orders.php:181` | Fires in the orders filter area. |
| `wpss_package_features` | 3 | `templates/partials/service-packages.php:198` | Fires inside the package features list. |
| `wpss_payable_total_after` | 2 | `src/Integrations/Standalone/StandaloneCheckoutProvider.php:1133` *(+1 more)* | Fires after the payable total, before the Pay button. |
| `wpss_payment_callback` | 1 | `src/Integrations/Standalone/StandaloneAdapter.php:336` | Fires when a payment callback is received. |
| `wpss_payment_receipt_rejected` | 4 | `src/Services/PaymentReceiptService.php:268` | Fires when an admin rejects proof of an offline payment. |
| `wpss_payment_receipt_submitted` | 3 | `src/Services/PaymentReceiptService.php:171` | Fires when a buyer submits proof of an offline payment. |
| `wpss_payment_receipt_verified` | 3 | `src/Services/PaymentReceiptService.php:225` | Fires when an admin verifies proof of an offline payment. |
| `wpss_payout_methods` | 2 | `templates/dashboard/sections/earnings.php:373` *(+1 more)* |  |
| `wpss_paypal_refund_processed` | 2 | `src/Integrations/PayPal/PayPalGateway.php:939` | Fires when a PayPal refund is processed. |
| `wpss_portfolio_item_created` | 3 | `src/Services/PortfolioService.php:200` | Fires when portfolio item is created. |
| `wpss_portfolio_item_deleted` | 2 | `src/Services/PortfolioService.php:345` | Fires when portfolio item is deleted. |
| `wpss_portfolio_item_moderated` | 3 | `src/Admin/Pages/VendorsPage.php:2881` | Fires after an admin moderates a vendor portfolio item from the drawer. |
| `wpss_portfolio_item_updated` | 2 | `src/Services/PortfolioService.php:295` | Fires when portfolio item is updated. |
| `wpss_pro_connect_payout_failed` | 4 | `src/StripeConnect/ConnectWebhookHandler.php:295` **[PRO]** | Fires when a payout to a connected vendor account fails. |
| `wpss_pro_connect_payout_paid` | 4 | `src/StripeConnect/ConnectWebhookHandler.php:254` **[PRO]** | Fires when a payout to a connected vendor account succeeds. |
| `wpss_pro_connect_transfer_created` | 4 | `src/StripeConnect/ConnectWebhookHandler.php:336` **[PRO]** | Fires when a Stripe transfer to a connected vendor account is created. |
| `wpss_pro_connect_transfer_reversed` | 3 | `src/StripeConnect/ConnectWebhookHandler.php:155` **[PRO]** | Fires when a Connect transfer reversal is confirmed by Stripe. |
| `wpss_pro_loaded` | 1 | `wp-sell-services-pro.php:262` **[PRO]** | Fires after WP Sell Services Pro is fully loaded. |
| `wpss_profile_form_fields` | 1 | `templates/dashboard/sections/profile.php:231` | Fires in the profile form before the submit button. |
| `wpss_proposal_accepted` | 3 | `src/Services/BuyerRequestService.php:891` | Fires when a proposal is accepted via order conversion. |
| `wpss_proposal_deleted` | 2 | `src/Services/ProposalService.php:896` | Fires when a proposal is deleted. |
| `wpss_proposal_rejected` | 3 | `src/Services/ProposalService.php:459` *(+1 more)* | Fires when a proposal is rejected. |
| `wpss_proposal_status_updated` | 2 | `src/Services/BuyerRequestService.php:871` *(+1 more)* |  |
| `wpss_proposal_submitted` | 4 | `src/Services/ProposalService.php:188` | Fires when a proposal is submitted. |
| `wpss_proposal_updated` | 2 | `src/Services/ProposalService.php:366` | Fires when a proposal is updated. |
| `wpss_proposal_withdrawn` | 2 | `src/Services/ProposalService.php:504` | Fires when a proposal is withdrawn. |
| `wpss_public_signup_complete` | 2 | `src/Frontend/PublicSignup.php:466` | Fires after a user signs up via the public signup form or checkout. |
| `wpss_razorpay_refund_processed` | 2 | `src/Integrations/Razorpay/RazorpayGateway.php:837` **[PRO]** | Fires when a Razorpay refund is processed. |
| `wpss_recurring_payment_failed` | 2 | `src/RecurringServices/RecurringWebhookHandler.php:191` **[PRO]** | Fires after a recurring subscription is marked past_due due to a failed payment. |
| `wpss_recurring_renewal_order_created` | 3 | `src/RecurringServices/RecurringOrderFactory.php:140` **[PRO]** | Fires after a recurring renewal order is created. |
| `wpss_recurring_subscription_cancelled` | 2 | `src/RecurringServices/RecurringWebhookHandler.php:229` **[PRO]** | Fires after a recurring subscription is cancelled via Stripe webhook. |
| `wpss_render_secret_field` | 1 | `src/Admin/Settings.php:237` *(+3 more)* |  |
| `wpss_report_filed` | 4 | `src/API/ReportsController.php:245` | Fires when a member files a report. |
| `wpss_request_archive_header` | 0 | `templates/archive-request.php:90` | Hook: wpss_request_archive_header |
| `wpss_request_archive_sidebar` | 0 | `templates/archive-request.php:133` | Hook: wpss_request_archive_sidebar |
| `wpss_request_card_footer` | 1 | `templates/content-request-card.php:197` | Hook: wpss_request_card_footer |
| `wpss_request_card_header` | 1 | `templates/content-request-card.php:140` | Hook: wpss_request_card_header |
| `wpss_request_card_meta` | 1 | `templates/content-request-card.php:182` | Hook: wpss_request_card_meta |
| `wpss_request_converted_to_order` | 5 | `src/Services/BuyerRequestService.php:949` | Fires when a buyer request is converted to an order. |
| `wpss_requirements_form_fields` | 1 | `templates/order/requirements-form.php:299` | Fires after the configured requirement fields, before the notes field. |
| `wpss_requirements_submitted` | 3 | `src/Services/RequirementsService.php:553` | Fires after requirements are submitted. |
| `wpss_requirements_timeout` | 2 | `src/Services/OrderWorkflowManager.php:547` | Fires when a requirements timeout action is taken. |
| `wpss_rest_offline_order_created` | 3 | `src/API/PaymentController.php:476` | Fires when an offline order is created via REST API. |
| `wpss_rest_service_created` | 2 | `src/API/ServicesController.php:692` | Fires after a service is created via REST API. |
| `wpss_rest_service_deleted` | 2 | `src/API/ServicesController.php:827` | Fires after a service is deleted via REST API. |
| `wpss_rest_service_updated` | 2 | `src/API/ServicesController.php:778` | Fires after a service is updated via REST API. |
| `wpss_review_created` | 2 | `src/API/ReviewsController.php:463` *(+1 more)* |  |
| `wpss_review_moderated` | 2 | `src/Services/ReviewService.php:181` | Fires after an admin moderates a review from the queue. |
| `wpss_review_reply_created` | 1 | `src/API/ReviewsController.php:622` |  |
| `wpss_revision_requested` | 2 | `src/API/OrdersController.php:972` *(+1 more)* |  |
| `wpss_send_requirements_reminder_email` | 3 | `src/Services/OrderWorkflowManager.php:414` |  |
| `wpss_service_approved` | 1 | `src/Admin/Pages/ServiceModerationPage.php:663` *(+4 more)* | Fires when a service is approved. |
| `wpss_service_archive_header` | 0 | `templates/archive-service.php:92` | Hook: wpss_service_archive_header |
| `wpss_service_archive_sidebar` | 0 | `templates/archive-service.php:136` | Hook: wpss_service_archive_sidebar |
| `wpss_service_card_footer` | 1 | `templates/content-service-card.php:264` | Hook: wpss_service_card_footer |
| `wpss_service_card_header` | 1 | `templates/content-service-card.php:231` | Hook: wpss_service_card_header |
| `wpss_service_card_image_overlay` | 1 | `templates/content-service-card.php:108` | Hook: wpss_service_card_image_overlay |
| `wpss_service_card_meta` | 1 | `templates/content-service-card.php:216` | Hook: wpss_service_card_meta |
| `wpss_service_created` | 2 | `src/Services/ServiceManager.php:178` | Fires after a service is created. |
| `wpss_service_meta_saved` | 2 | `src/Admin/Metaboxes/ServiceMetabox.php:885` | Fires after service meta is saved. |
| `wpss_service_orders_after` | 1 | `templates/myaccount/service-orders.php:171` | Fires after the service orders content. |
| `wpss_service_orders_before` | 1 | `templates/myaccount/service-orders.php:29` | Fires before the service orders content. |
| `wpss_service_pending_moderation` | 1 | `src/Frontend/ServiceWizard.php:1724` *(+1 more)* |  |
| `wpss_service_rejected` | 2 | `src/Admin/Pages/ServiceModerationPage.php:714` *(+4 more)* | Fires when a service is rejected. |
| `wpss_service_updated` | 2 | `src/Services/ServiceManager.php:290` | Fires after a service is updated. |
| `wpss_service_wizard_saved` | 2 | `src/Frontend/ServiceWizard.php:1712` | Fires after a service is saved via the wizard. |
| `wpss_services_list_actions` | 1 | `templates/dashboard/sections/services.php:137` | Fires in the services list area for bulk actions or filters. |
| `wpss_settings_sections_gateways` | 0 | `src/Admin/Settings.php:1959` | Unified gateway sections hook. |
| `wpss_settings_sections_payments` | 1 | `src/Admin/Settings.php:1803` | Legacy payments-sections hook. |
| `wpss_settings_tab_` | 0 | `src/Admin/Settings.php:1684` | Fires when rendering a custom settings tab added by Pro or extensions. |
| `wpss_single_request_content` | 1 | `templates/single-request.php:238` | Hook: wpss_single_request_content |
| `wpss_single_request_header` | 1 | `templates/single-request.php:150` | Hook: wpss_single_request_header |
| `wpss_single_request_proposals` | 1 | `templates/single-request.php:253` | Hook: wpss_single_request_proposals |
| `wpss_single_request_sidebar` | 1 | `templates/single-request.php:523` | Hook: wpss_single_request_sidebar |
| `wpss_single_service_content` | 1 | `templates/single-service.php:150` | Hook: wpss_single_service_content |
| `wpss_single_service_faqs` | 1 | `templates/single-service.php:157` | Hook: wpss_single_service_faqs |
| `wpss_single_service_gallery` | 1 | `templates/single-service.php:142` | Hook: wpss_single_service_gallery |
| `wpss_single_service_header` | 1 | `templates/single-service.php:123` | Hook: wpss_single_service_header |
| `wpss_single_service_meta` | 1 | `templates/single-service.php:135` | Hook: wpss_single_service_meta |
| `wpss_single_service_portfolio` | 1 | `templates/single-service.php:189` | Hook: wpss_single_service_portfolio |
| `wpss_single_service_related` | 1 | `templates/single-service.php:196` | Hook: wpss_single_service_related |
| `wpss_single_service_reviews` | 1 | `templates/single-service.php:164` | Hook: wpss_single_service_reviews |
| `wpss_single_service_sidebar` | 1 | `templates/single-service.php:176` | Hook: wpss_single_service_sidebar |
| `wpss_standalone_adapter_init` | 1 | `src/Integrations/Standalone/StandaloneAdapter.php:157` | Fires after standalone adapter is initialized. |
| `wpss_standalone_checkout_processed` | 2 | `src/Integrations/Standalone/StandaloneCheckoutProvider.php:134` | Fires after standalone checkout processing. |
| `wpss_standalone_order_complete` | 1 | `src/Integrations/Standalone/StandaloneOrderProvider.php:720` | Fires when a standalone order is completed. |
| `wpss_stripe_refund_processed` | 2 | `src/Integrations/Stripe/StripeGateway.php:1504` | Fires when a Stripe refund is processed. |
| `wpss_stripe_webhook_received` | 3 | `src/Integrations/Stripe/StripeGateway.php:650` *(+1 more)* | Fires when a Stripe webhook event is received. |
| `wpss_terawallet_recharged` | 2 | `src/Integrations/Wallets/TeraWalletProvider.php:203` **[PRO]** | Fires when TeraWallet recharge is complete. |
| `wpss_tip_order_created` | 4 | `src/Services/TippingService.php:280` | Fires when a pending-payment tip order is created and awaits the buyer's gateway charge. |
| `wpss_tip_sent` | 6 | `src/Services/TippingService.php:479` | Fires after a paid tip has been credited to the vendor's wallet. |
| `wpss_updated` | 2 | `src/Core/Plugin.php:457` | Fires after the plugin has been installed or upgraded. |
| `wpss_user_blocked` | 2 | `src/API/BlocksController.php:164` | Fires when one member blocks another. |
| `wpss_user_unblocked` | 2 | `src/API/BlocksController.php:196` | Fires when one member unblocks another. |
| `wpss_vendor_access_granted` | 1 | `src/Services/VendorService.php:438` | Fires when vendor access is granted (after admin approval). |
| `wpss_vendor_access_revoked` | 1 | `src/Services/VendorService.php:492` | Fires when vendor access is revoked (suspended/rejected). |
| `wpss_vendor_card_meta` | 1 | `templates/partials/vendor-card.php:129` | Fires inside the vendor card meta area for custom badges/icons. |
| `wpss_vendor_commission_updated` | 2 | `src/Admin/Pages/VendorsPage.php:1545` | Fires when vendor commission rate is updated. |
| `wpss_vendor_contacted` | 5 | `src/Frontend/AjaxHandlers.php:2135` | Fires after a vendor contact message is sent. |
| `wpss_vendor_dashboard_actions` | 1 | `templates/myaccount/vendor-dashboard.php:234` | Fires at the end of vendor dashboard body for custom actions. |
| `wpss_vendor_dashboard_after` | 1 | `templates/myaccount/vendor-dashboard.php:262` | Fires after the vendor dashboard content. |
| `wpss_vendor_dashboard_before` | 1 | `templates/myaccount/vendor-dashboard.php:27` | Fires before the vendor dashboard content. |
| `wpss_vendor_dashboard_widgets` | 1 | `templates/myaccount/vendor-dashboard.php:68` | Fires at the start of vendor dashboard body for custom widgets. |
| `wpss_vendor_level_promoted` | 3 | `src/Services/OrderWorkflowManager.php:618` | Fires when a vendor is promoted to a higher level. |
| `wpss_vendor_level_updated` | 2 | `src/Services/SellerLevelService.php:286` | Fires when a vendor's level is updated. |
| `wpss_vendor_profile_bio` | 1 | `templates/vendor/profile.php:293` | Hook: wpss_vendor_profile_bio |
| `wpss_vendor_profile_header` | 1 | `templates/vendor/profile.php:149` | Hook: wpss_vendor_profile_header |
| `wpss_vendor_profile_reviews` | 1 | `templates/vendor/profile.php:402` | Hook: wpss_vendor_profile_reviews |
| `wpss_vendor_profile_saved` | 2 | `src/Frontend/AjaxHandlers.php:3565` *(+1 more)* | Fires after a vendor profile is saved from a frontend form. |
| `wpss_vendor_profile_services` | 1 | `templates/vendor/profile.php:313` | Hook: wpss_vendor_profile_services |
| `wpss_vendor_profile_sidebar` | 1 | `templates/vendor/profile.php:598` | Hook: wpss_vendor_profile_sidebar |
| `wpss_vendor_profile_stats` | 1 | `templates/vendor/profile.php:470` | Hook: wpss_vendor_profile_stats |
| `wpss_vendor_profile_updated` | 2 | `src/Services/VendorService.php:559` | Fires when a vendor profile is updated. |
| `wpss_vendor_registered` | 2 | `src/Services/VendorService.php:182` | Fires when a new vendor is registered. |
| `wpss_vendor_services_after` | 1 | `templates/myaccount/vendor-services.php:105` | Fires after the vendor services content. |
| `wpss_vendor_services_before` | 1 | `templates/myaccount/vendor-services.php:21` | Fires before the vendor services content. |
| `wpss_vendor_status_updated` | 2 | `src/Services/VendorService.php:377` | Fires when vendor status is updated. |
| `wpss_vendor_tier_changed` | 2 | `src/Services/VendorService.php:651` | Fires when vendor verification tier changes. |
| `wpss_vendor_vacation_mode_changed` | 4 | `src/Services/VendorService.php:610` | Fires when vacation mode is toggled. |
| `wpss_withdrawal_processed` | 3 | `src/Services/EarningsService.php:499` *(+1 more)* |  |
| `wpss_withdrawal_requested` | 3 | `src/Services/EarningsService.php:706` | Fires when withdrawal is requested. |
| `wpss_wizard_pricing_after` | 1 | `src/Frontend/ServiceWizard.php:699` | Fires after the pricing tiers in the wizard's Pricing step. |
| `wpss_wizard_save_service_meta` | 2 | `src/Frontend/ServiceWizard.php:2136` | Fires after the wizard persists service meta. |

## Filters (239)

| Hook | Args | Fired from | Description |
|---|---|---|---|
| `wpss_account_page_section` | 2 | `src/Integrations/Standalone/StandaloneAccountProvider.php:278` | Filters the dashboard section a legacy [wpss_account] page maps to. |
| `wpss_account_status` | 2 | `src/functions/moderation.php:180` | Filter a member's account standing. |
| `wpss_add_service_to_cart` | 3 | `src/Frontend/AjaxHandlers.php:2366` | Filter to let e-commerce adapters handle cart addition natively. |
| `wpss_admin_menu_label` | 1 | `src/Admin/Admin.php:1413` | Filter the admin menu label for white-labelling. |
| `wpss_admin_notification_email` | 1 | `src/Services/EmailService.php:617` | Tell the site owner a buyer has sent proof of an offline payment. |
| `wpss_admin_vendor_notification_content` | 2 | `src/Services/NotificationService.php:1630` | Filter admin vendor notification email content. |
| `wpss_after_become_vendor_redirect` | 2 | `src/Frontend/UnifiedDashboard.php:1142` | Filter the redirect URL after a vendor successfully registers. |
| `wpss_allow_late_requirements_submission` | 1 | `src/functions/orders.php:652` *(+1 more)* | Filter whether late requirements submission is allowed. |
| `wpss_analytics_widgets` | 1 | `src/Core/Plugin.php:2613` *(+1 more)* | Filter the registered analytics widgets. |
| `wpss_api_controllers` | 1 | `src/API/API.php:184` | Filter registered API controllers. |
| `wpss_api_cors_origins` | 0 | `src/API/API.php:1359` | Filter allowed CORS origins. |
| `wpss_api_public_settings` | 1 | `src/API/API.php:647` | Filter public API settings. |
| `wpss_app_abuse_contact` | 1 | `src/API/API.php:787` |  |
| `wpss_app_branding` | 1 | `src/API/API.php:772` | Public subset only. Pro's white label supplies the real values. |
| `wpss_app_enabled` | 1 | `src/API/API.php:769` |  |
| `wpss_app_features` | 1 | `src/API/API.php:795` |  |
| `wpss_app_min_version` | 1 | `src/API/API.php:763` |  |
| `wpss_app_token_lifetime` | 1 | `src/functions/misc.php:553` | Filter how long a mobile app token stays valid. |
| `wpss_archive_request_columns` | 1 | `templates/archive-request.php:62` | Filter: wpss_archive_request_columns |
| `wpss_archive_service_columns` | 1 | `templates/archive-service.php:61` | Filter: wpss_archive_service_columns |
| `wpss_auth_login_challenge` | 3 | `src/API/AuthController.php:537` | Filter a successful password check before a token is issued. |
| `wpss_auto_approve_reviews` | 1 | `src/API/ReviewsController.php:427` |  |
| `wpss_auto_approve_vendors` | 1 | `src/Services/VendorService.php:112` | Filter whether new vendors are auto-approved. |
| `wpss_batch_max_requests` | 1 | `src/API/API.php:1286` | Handle batch requests for mobile efficiency. |
| `wpss_billing_address` | 2 | `src/functions/billing.php:660` | Filter a user's billing address after it is read. |
| `wpss_billing_fields` | 1 | `src/functions/billing.php:58` | Filter the billing address fields. |
| `wpss_blocks` | 1 | `src/Blocks/BlocksManager.php:96` | Filter registered blocks. |
| `wpss_breadcrumbs` | 2 | `src/SEO/SEO.php:403` |  |
| `wpss_buyer_request_post_type_args` | 1 | `src/PostTypes/BuyerRequestPostType.php:99` | Filter buyer request post type arguments. |
| `wpss_buyer_request_slug` | 1 | `src/PostTypes/BuyerRequestPostType.php:115` | Filter the buyer request post type slug. |
| `wpss_can_access_dashboard_section` | 3 | `src/Frontend/UnifiedDashboard.php:444` | Filter whether user can access a dashboard section. |
| `wpss_cart_checkout` | 4 | `src/API/CartController.php:383` | Filter to create order from cart during standalone checkout. |
| `wpss_cart_item_data` | 3 | `src/API/CartController.php:236` | Filters cart item data before it is saved. |
| `wpss_cascade_preserve_shared_records` | 2 | `src/Services/DataCascadeHandler.php:207` | Filter whether records shared with another member survive this cascade. |
| `wpss_catalog_price_html` | 3 | `src/functions/money.php:146` | Filter catalog price HTML to append a display-currency hint. |
| `wpss_category_card_classes` | 2 | `templates/partials/category-card.php:82` | Filters the category card CSS classes. |
| `wpss_category_card_link` | 2 | `templates/partials/category-card.php:66` | Filters the category card's link target. |
| `wpss_category_schema` | 2 | `src/SEO/SchemaMarkup.php:302` |  |
| `wpss_category_terms_limit` | 1 | `src/functions/services.php:750` | Filter the maximum number of category terms a chooser will render. |
| `wpss_checkout_badges` | 2 | `src/functions/payments.php:197` | Filter the checkout reassurance badges. |
| `wpss_checkout_creates_accounts` | 1 | `src/functions/billing.php:826` | Filter whether checkout creates an account for a logged-out buyer. |
| `wpss_checkout_slug` | 1 | `src/Integrations/Standalone/StandaloneAdapter.php:48` | Filter the checkout URL slug. |
| `wpss_checkout_tax_rate` | 3 | `src/functions/money.php:627` |  |
| `wpss_commission_base_amount` | 3 | `src/Services/CommissionService.php:166` | Filters the base amount used for commission calculation. |
| `wpss_commission_fee` | 4 | `src/Services/CommissionService.php:192` | Filters the platform fee AMOUNT for an order. |
| `wpss_commission_rate` | 4 | `src/Services/CommissionService.php:354` *(+1 more)* | Filter the commission rate for a specific order. |
| `wpss_countries` | 1 | `src/functions/billing.php:520` | Filter the billing country list. |
| `wpss_currencies` | 1 | `src/functions/money.php:1714` | Filter supported currencies. |
| `wpss_currency` | 1 | `src/functions/money.php:857` | Filter the default currency. |
| `wpss_currency_decimals` | 2 | `src/functions/money.php:186` | Filter the number of decimal places for a currency. |
| `wpss_currency_format` | 3 | `src/functions/money.php:919` | Filter the currency format string. |
| `wpss_currency_registry` | 1 | `src/functions/money.php:1693` | Filter the canonical currency registry. |
| `wpss_currency_symbols` | 1 | `src/functions/money.php:892` | Filter currency symbols. |
| `wpss_dashboard_asset_shortcodes` | 1 | `src/Frontend/UnifiedDashboard.php:248` | Filters the shortcodes that make a page load the dashboard assets. |
| `wpss_dashboard_default_section` | 2 | `src/Frontend/UnifiedDashboard.php:408` | Filter the dashboard's default landing section. |
| `wpss_dashboard_section_aliases` | 1 | `src/functions/urls.php:307` | Filter the dashboard section alias map. |
| `wpss_dashboard_section_template` | 2 | `src/Frontend/UnifiedDashboard.php:896` *(+1 more)* | Filter the template path for a dashboard section. |
| `wpss_dashboard_section_titles` | 1 | `src/Frontend/UnifiedDashboard.php:845` | Filter dashboard section titles. |
| `wpss_dashboard_sections` | 3 | `src/Frontend/UnifiedDashboard.php:543` | Filter dashboard sections. |
| `wpss_default_page_slugs` | 1 | `src/functions/urls.php:553` | Filter default page slugs. |
| `wpss_default_service_categories` | 1 | `src/PostTypes/ServicePostType.php:89` | Insert the default service categories. Returns the created term IDs. |
| `wpss_delivery_allowed_file_types` | 1 | `src/Services/DeliveryService.php:347` | Filter allowed file types for delivery. |
| `wpss_dispute_reasons` | 1 | `src/functions/moderation.php:107` | Filter the reasons a buyer may give for opening a dispute. |
| `wpss_docs_url` | 1 | `src/Admin/Pages/UpgradePage.php:348` | Filters the documentation URL shown on the upgrade screen. |
| `wpss_ecommerce_adapters` | 1 | `src/CLI/PreflightCommand.php:663` *(+1 more)* |  |
| `wpss_ecommerce_platform_description` | 1 | `src/Admin/Settings.php:3340` | Filter the platform field description. |
| `wpss_ecommerce_platform_status` | 3 | `src/Admin/Settings.php:3295` | Filter the status word shown beside one platform. |
| `wpss_edd_can_access_vendor_dashboard` | 2 | `src/Integrations/EDD/EDDAccountProvider.php:484` **[PRO]** | Filter whether user can access vendor dashboard. |
| `wpss_edd_cart_item_data` | 3 | `src/Integrations/EDD/EDDCheckoutProvider.php:56` **[PRO]** | Filter cart item data for services. |
| `wpss_edd_thankyou_redirect` | 2 | `src/Integrations/EDD/EDDCheckoutProvider.php:250` **[PRO]** | Filter the thank you redirect URL for service orders. |
| `wpss_edd_validate_add_to_cart` | 3 | `src/Integrations/EDD/EDDCheckoutProvider.php:97` **[PRO]** | Filter whether a service can be added to cart. |
| `wpss_email_before_send` | 2 | `src/Services/EmailService.php:2068` | Filter email before sending. |
| `wpss_email_button_text` | 2 | `templates/emails/cancellation-requested.php:126` *(+20 more)* |  |
| `wpss_email_button_url` | 3 | `templates/emails/cancellation-requested.php:125` *(+20 more)* |  |
| `wpss_email_from_name` | 1 | `src/Services/EmailService.php:2048` *(+1 more)* | Filter the email "from" name for white-labelling. |
| `wpss_email_header_vars` | 2 | `src/Services/EmailService.php:2032` | Filter email header/template variables for white-labelling. |
| `wpss_email_preference_categories` | 3 | `src/functions/notifications.php:120` | Filters the email preference categories offered to a user. |
| `wpss_email_providers` | 1 | `src/Core/Plugin.php:2601` | Filter the registered email providers. |
| `wpss_email_subject` | 3 | `src/Services/EmailService.php:2017` *(+1 more)* | Filters the email subject line before sending. |
| `wpss_ensure_pay_order` | 2 | `src/functions/urls.php:915` | Filter the pay URL after the rail has made sure its store order exists. |
| `wpss_foreign_page_map` | 1 | `src/Admin/PageDropdownWalker.php:81` | Filter the pages shown as belonging to another plugin. |
| `wpss_format_price` | 3 | `src/functions/money.php:57` |  |
| `wpss_fullwidth_page_keys` | 1 | `src/Frontend/TemplateLoader.php:316` | Filter which mapped plugin pages render full-width. |
| `wpss_gallery_image_size` | 2 | `templates/partials/service-gallery.php:86` | Filters the gallery image size. |
| `wpss_get_template` | 3 | `src/functions/templates.php:177` | Filter the template file path. |
| `wpss_get_template_part` | 3 | `src/functions/templates.php:126` | Filter the template file path. |
| `wpss_is_vendor` | 2 | `src/functions/vendors.php:148` | Filter whether user is a vendor. |
| `wpss_known_dashboard_sections` | 1 | `src/functions/urls.php:256` | Filter the set of known dashboard section slugs. |
| `wpss_ledger_debit_types` | 1 | `src/functions/money.php:213` | Filter the ledger transaction types treated as debits. |
| `wpss_locate_template` | 3 | `src/Frontend/TemplateLoader.php:545` |  |
| `wpss_locked_billing_fields` | 1 | `src/functions/billing.php:171` | Filters the billing fields that cannot be disabled. |
| `wpss_manual_order_currencies` | 1 | `src/Admin/Pages/ManualOrderPage.php:824` | Filter the currencies available on the Manual Order page dropdown. |
| `wpss_max_order_quantity` | 2 | `src/Frontend/SingleServiceView.php:930` *(+1 more)* | Filters the maximum order quantity for a service. |
| `wpss_max_upload_size` | 1 | `src/functions/misc.php:127` | Filter the max upload size for requirements files. |
| `wpss_member_bypasses_limits` | 2 | `src/functions/vendors.php:722` | Filter whether a member is exempt from vendor selling limits. |
| `wpss_member_display_name` | 3 | `src/functions/vendors.php:596` *(+1 more)* | This filter is documented below. |
| `wpss_message_email_delay_minutes` | 1 | `src/Services/EmailService.php:1219` | Filter the message-email delay. |
| `wpss_messages_per_page` | 2 | `templates/dashboard/sections/messages.php:38` | Filter how many conversations one page of the messages list shows. |
| `wpss_min_service_price` | 1 | `src/Frontend/ServiceWizard.php:1387` *(+2 more)* |  |
| `wpss_no_requests_message` | 1 | `templates/content-no-requests.php:26` | Template: No Requests Found |
| `wpss_no_services_message` | 1 | `templates/content-no-services.php:33` |  |
| `wpss_notification_email_content` | 4 | `src/Services/NotificationService.php:2020` | Filter email content before sending. |
| `wpss_notification_types` | 1 | `src/Admin/Settings.php:3850` | Filter the switchable notification types. |
| `wpss_offline_method_slots` | 1 | `src/Integrations/Gateways/OfflineGateway.php:1668` | Named offline methods. |
| `wpss_offline_methods` | 2 | `src/Integrations/Gateways/OfflineGateway.php:331` | Filter the offline payment methods. |
| `wpss_open_graph_data` | 2 | `src/SEO/SEO.php:271` |  |
| `wpss_order_actions` | 2 | `templates/order/order-view.php:415` | Filter: wpss_order_actions |
| `wpss_order_is_refundable` | 2 | `src/functions/money.php:328` | Filter whether an order may be refunded. |
| `wpss_order_number_prefix` | 1 | `src/Database/Repositories/OrderRepository.php:93` *(+1 more)* | Generate a unique order number. |
| `wpss_order_payment_reference` | 2 | `src/functions/orders.php:1090` | Filter the payment-rail receipt reference shown on an order. |
| `wpss_order_status_groups` | 1 | `src/functions/orders.php:739` | Filter the order status groups used by the dashboard filter chips. |
| `wpss_order_status_label` | 3 | `templates/order/order-view.php:155` | Filter: wpss_order_status_label |
| `wpss_order_status_transitions` | 3 | `src/Services/OrderService.php:710` | Filter allowed status transitions. |
| `wpss_order_statuses` | 1 | `src/functions/orders.php:127` | Filter order statuses. |
| `wpss_organization_schema` | 1 | `src/SEO/SchemaMarkup.php:433` |  |
| `wpss_package_button_text` | 2 | `templates/partials/service-packages.php:240` | Filters the package button text. |
| `wpss_package_id_base` | 2 | `src/functions/services.php:123` |  |
| `wpss_package_price_html` | 3 | `templates/partials/service-packages.php:117` | Filters the package price HTML. |
| `wpss_page_definitions` | 1 | `src/functions/urls.php:469` | Filter the page registry. |
| `wpss_pay_order_url_lookup` | 3 | `src/functions/urls.php:878` | Filter the pay URL with one the rail already knows. |
| `wpss_payment_action_required_message` | 3 | `src/Integrations/Stripe/StripeGateway.php:416` | Filters the message shown when a payment still needs a buyer step (typically 3D Secure authentication). |
| `wpss_payment_declined_message` | 3 | `src/Integrations/Stripe/StripeGateway.php:393` | Filters the message shown to a buyer whose card was declined. |
| `wpss_payment_gateways` | 1 | `src/Core/Plugin.php:2558` *(+4 more)* | Filter the registered payment gateways. |
| `wpss_payout_banner_state` | 4 | `templates/dashboard/sections/earnings.php:86` | Filters the payout banner state shown on the earnings section. |
| `wpss_person_schema` | 2 | `src/SEO/SchemaMarkup.php:355` |  |
| `wpss_platform_name` | 1 | `src/functions/misc.php:37` | Filter the platform name. |
| `wpss_pre_create_order` | 1 | `src/Integrations/Standalone/StandaloneOrderProvider.php:110` | Filters order data before database insertion. |
| `wpss_pre_create_review` | 2 | `src/Services/ReviewService.php:77` | Filters review data before database insertion. |
| `wpss_pre_create_service` | 1 | `src/Services/ServiceManager.php:109` | Filters service data before creation. |
| `wpss_pre_open_dispute` | 2 | `src/Services/DisputeService.php:274` | Filter dispute data before saving to the database. |
| `wpss_pre_order_status_change` | 4 | `src/Services/OrderService.php:442` | Filter whether an order status change should proceed. |
| `wpss_pre_process_gateway_refund` | 4 | `src/Services/OrderWorkflowManager.php:1343` | Short-circuit the gateway refund. |
| `wpss_pre_send_message` | 2 | `src/Services/ConversationService.php:274` | Filter message data before saving to the database. |
| `wpss_pre_submit_delivery` | 2 | `src/Services/DeliveryService.php:101` | Filter delivery data before saving to the database. |
| `wpss_pre_update_service` | 2 | `src/Services/ServiceManager.php:207` | Filters service data before update. |
| `wpss_pre_vendor_register` | 2 | `src/Services/VendorService.php:155` | Filter vendor profile data before creating the vendor profile. |
| `wpss_presence_window` | 2 | `src/functions/notifications.php:162` | Filters the presence window, in seconds. |
| `wpss_pro_beta_rails` | 1 | `wp-sell-services-pro.php:326` **[PRO]** | Filter whether the beta EDD and FluentCart rails are registered. |
| `wpss_pro_fcm_payload` | 3 | `src/Push/FcmProvider.php:208` **[PRO]** | Filter the FCM message body before it is sent. |
| `wpss_pro_pause_license_recheck` | 2 | `src/License/Manager.php:182` **[PRO]** | Filter whether automatic license re-checks are paused. |
| `wpss_pro_push_provider` | 2 | `src/Push/PushNotificationService.php:137` **[PRO]** | Filter the push provider. |
| `wpss_pro_push_should_send` | 5 | `src/Push/PushNotificationService.php:179` **[PRO]** | Filter whether a push is sent for this notification. |
| `wpss_pro_recurring_feature_available` | 1 | `src/RecurringServices/RecurringSettingsRenderer.php:279` **[PRO]** | Whether the recurring-services feature is available in this version. |
| `wpss_pro_upgrade_url` | 1 | `src/Frontend/ServiceWizard.php:873` *(+3 more)* |  |
| `wpss_proposal_order_revisions` | 3 | `src/Services/BuyerRequestService.php:774` |  |
| `wpss_rail_status_map` | 2 | `src/functions/orders.php:1512` | Filters the rail status map. |
| `wpss_rate_limits` | 2 | `src/Core/RateLimiter.php:267` | Filter rate limits for a specific action. |
| `wpss_realtime_settings` | 1 | `src/Services/RealtimeService.php:65` | Filter the realtime (Pusher-protocol) connection settings. |
| `wpss_related_services_args` | 2 | `src/Frontend/SingleServiceView.php:817` | Filter related services query args. |
| `wpss_report_reasons` | 1 | `src/functions/moderation.php:61` | Filter the report reasons offered to members. |
| `wpss_report_target_types` | 1 | `src/functions/moderation.php:139` | Filter what members may report. |
| `wpss_request_card_classes` | 2 | `templates/content-request-card.php:43` |  |
| `wpss_requests_per_page` | 1 | `templates/archive-request.php:73` | Filter: wpss_requests_per_page |
| `wpss_require_service_moderation` | 1 | `src/Services/ModerationService.php:104` | Filter whether new/updated services require moderation. |
| `wpss_requirement_field_label` | 2 | `src/functions/orders.php:299` | Filter the label shown for a submitted requirement field. |
| `wpss_requirements_allowed_file_types` | 1 | `src/Services/RequirementsService.php:503` | Filter allowed file types for requirements. |
| `wpss_requirements_file_inputs` | 2 | `src/Frontend/AjaxHandlers.php:601` |  |
| `wpss_rest_confirm_payment` | 6 | `src/API/PaymentController.php:317` | Filter to handle custom payment gateway confirmation via REST. |
| `wpss_rest_create_payment_intent` | 7 | `src/API/PaymentController.php:268` | Filter to handle custom payment gateway intent creation via REST. |
| `wpss_rest_order_data` | 3 | `src/API/OrdersController.php:1960` | Filters the order data returned in REST API responses. |
| `wpss_rest_review_data` | 3 | `src/API/ReviewsController.php:1070` | Filters the review data returned in REST API responses. |
| `wpss_rest_service_data` | 3 | `src/API/ServicesController.php:1194` | Filter service REST response data. |
| `wpss_rest_vendor_data` | 3 | `src/API/VendorsController.php:913` | Filters the vendor data returned in REST API responses. |
| `wpss_review_window_days` | 1 | `src/Services/ReviewService.php:504` | Filter the review time window in days. |
| `wpss_reviews_per_page` | 2 | `templates/partials/service-reviews.php:54` | Filters the number of reviews to display per page. |
| `wpss_search_categories_limit` | 1 | `src/Blocks/ServiceSearch.php:180` |  |
| `wpss_search_query_args` | 2 | `src/Services/SearchService.php:247` | Filter the WP_Query arguments for service search. |
| `wpss_search_results` | 3 | `src/Services/SearchService.php:121` | Filter search results. |
| `wpss_search_suggestions` | 2 | `src/Services/SearchService.php:523` | Filter search suggestions. |
| `wpss_seller_levels` | 1 | `src/API/SellerLevelsController.php:267` | Filter seller level definitions. |
| `wpss_service_card_classes` | 2 | `templates/content-service-card.php:42` |  |
| `wpss_service_card_thumbnail_size` | 2 | `templates/content-service-card.php:73` | Hook: wpss_before_service_card |
| `wpss_service_category_taxonomy_args` | 1 | `src/Taxonomies/ServiceCategoryTaxonomy.php:118` | Filter service category taxonomy arguments. |
| `wpss_service_limit_error_message` | 1 | `src/Frontend/ServiceWizard.php:231` *(+3 more)* | Filter the error message shown when a vendor cannot create more services. |
| `wpss_service_list_schema` | 1 | `src/SEO/SchemaMarkup.php:243` |  |
| `wpss_service_max_extras` | 1 | `src/functions/services.php:984` | Max service extras (add-ons). |
| `wpss_service_max_faq` | 1 | `src/functions/services.php:994` | Max FAQs. |
| `wpss_service_max_gallery` | 1 | `src/functions/services.php:964` | Max gallery images (additional, not including main). |
| `wpss_service_max_packages` | 1 | `src/functions/services.php:954` | Max pricing packages (tiers). |
| `wpss_service_max_requirements` | 1 | `src/functions/services.php:1004` | Max buyer requirements. |
| `wpss_service_max_videos` | 1 | `src/functions/services.php:974` | Max video URLs. |
| `wpss_service_meta_fields` | 2 | `src/Admin/Metaboxes/ServiceMetabox.php:246` | Filter additional service meta fields rendered in the metabox. |
| `wpss_service_order_slug` | 1 | `src/Core/Plugin.php:612` *(+3 more)* | Filter the service order URL slug. |
| `wpss_service_post_type_args` | 1 | `src/PostTypes/ServicePostType.php:243` | Filter service post type arguments. |
| `wpss_service_schema` | 2 | `src/SEO/SchemaMarkup.php:203` |  |
| `wpss_service_slug` | 1 | `src/PostTypes/ServicePostType.php:321` | Filter the service post type slug. |
| `wpss_service_tag_args` | 1 | `src/PostTypes/ServicePostType.php:305` | Filter service tag taxonomy arguments. |
| `wpss_service_tag_taxonomy_args` | 1 | `src/Taxonomies/ServiceTagTaxonomy.php:103` | Filter service tag taxonomy arguments. |
| `wpss_services_per_page` | 1 | `src/Frontend/ServiceArchiveView.php:657` *(+1 more)* |  |
| `wpss_settings_currencies` | 1 | `src/Admin/Settings.php:3812` | Filter the currencies available in the Settings currency dropdown. |
| `wpss_settings_sections` | 1 | `src/functions/urls.php:986` | Filter the known admin settings sections. |
| `wpss_settings_tabs` | 1 | `src/Admin/Settings.php:227` | Filter the settings tabs. |
| `wpss_should_reverse_vendor_earnings` | 2 | `src/Services/OrderWorkflowManager.php:1111` | Filters whether the vendor's wallet earnings should be reversed. |
| `wpss_show_powered_by` | 1 | `src/Frontend/UnifiedDashboard.php:785` | Filters whether the "Powered by WP Sell Services" footer credit is rendered on the frontend dashboard. |
| `wpss_single_request_layout` | 2 | `templates/single-request.php:126` | Filter: wpss_single_request_layout |
| `wpss_single_service_layout` | 2 | `templates/single-service.php:95` | Filter: wpss_single_service_layout |
| `wpss_sitemap_post_types` | 1 | `src/SEO/SEO.php:335` | Add service post type to sitemap. |
| `wpss_skip_message_email_when_online` | 3 | `src/functions/notifications.php:210` | Filters whether a message email is skipped for an online recipient. |
| `wpss_status_class` | 2 | `src/functions/templates.php:52` | Filter the CSS classes for a status badge. |
| `wpss_sticky_top_offset` | 1 | `src/Frontend/Frontend.php:260` | Extra pixels to add above every sticky WPSS surface. |
| `wpss_storage_providers` | 1 | `src/Core/Plugin.php:2589` *(+3 more)* | Filter the registered storage providers. |
| `wpss_stripe_customer_shipping` | 2 | `src/Integrations/Stripe/StripeGateway.php:222` | Filter the buyer shipping details sent to Stripe. |
| `wpss_stripe_payment_description` | 3 | `src/Integrations/Stripe/StripeGateway.php:263` | Filter the Stripe PaymentIntent description. |
| `wpss_stripe_payment_intent_args` | 3 | `src/Integrations/Stripe/StripeGateway.php:303` | Filter Stripe PaymentIntent parameters before creation. |
| `wpss_stripe_refund_args` | 5 | `src/Integrations/Stripe/StripeGateway.php:598` | Filter the Stripe refund request arguments. |
| `wpss_suppress_theme_title` | 1 | `src/Frontend/ShellHeader.php:178` | Filter whether the active theme's entry-title is suppressed on the current plugin-shell surface. |
| `wpss_template_args` | 2 | `src/functions/templates.php:131` *(+1 more)* |  |
| `wpss_terms_page_slugs` | 1 | `src/Core/Activator.php:592` | Filters the slugs searched when auto-mapping an existing terms page. |
| `wpss_three_decimal_currencies` | 1 | `src/functions/money.php:730` | Filter the list of three-decimal currency codes. |
| `wpss_tip_commission_rate` | 3 | `src/Services/TippingService.php:372` | Filter the commission rate applied to a tip. |
| `wpss_tip_quick_amounts` | 2 | `templates/order/order-view.php:2159` |  |
| `wpss_token_recovery_routes` | 1 | `src/API/AppTokenGuard.php:136` | Filter the routes reachable without a valid token. |
| `wpss_tour_should_enqueue` | 1 | `src/Frontend/Tour.php:217` | Filter whether WPSS tour assets load on the current request. |
| `wpss_tour_steps` | 1 | `src/Frontend/Tour.php:272` | Filter the steps array handed to Shepherd. |
| `wpss_use_fullwidth_template` | 1 | `src/Frontend/TemplateLoader.php:271` *(+1 more)* | Filter whether plugin pages use the full-width template. |
| `wpss_validate_add_to_cart` | 4 | `src/API/CartController.php:204` | Validates whether a service can be added to the cart. |
| `wpss_vendor_benefit_listings_copy` | 2 | `templates/partials/vendor-benefits.php:37` | Filter the service-count promise on the Become a Vendor page. |
| `wpss_vendor_can_create_service` | 2 | `src/Frontend/ServiceWizard.php:219` *(+4 more)* |  |
| `wpss_vendor_is_on_vacation` | 2 | `src/Models/VendorProfile.php:519` | Check if vendor is on vacation. |
| `wpss_vendor_page_schema` | 2 | `src/SEO/SchemaMarkup.php:402` |  |
| `wpss_vendor_pending_email_content` | 3 | `src/Services/NotificationService.php:1577` | Filter vendor pending review email content. |
| `wpss_vendor_pitch_stats` | 1 | `src/functions/vendors.php:824` | Filter the proof points on the Become a Vendor page. |
| `wpss_vendor_pitch_steps` | 1 | `src/Frontend/Shortcodes.php:1364` | Filter the "how it works" steps on the vendor registration page. |
| `wpss_vendor_profile_allowed_fields` | 1 | `src/Services/VendorService.php:541` | Filter the list of allowed vendor profile fields for update. |
| `wpss_vendor_profile_fields` | 2 | `templates/vendor/profile.php:548` | Filter additional vendor profile fields. |
| `wpss_vendor_registration_open` | 1 | `src/API/VendorsController.php:612` |  |
| `wpss_vendor_slug` | 1 | `src/Core/Plugin.php:604` *(+1 more)* | Filter the vendor profile URL slug. |
| `wpss_vendor_status_email_vars` | 3 | `src/Services/VendorService.php:851` | Filters the template variables for a vendor status-change email. |
| `wpss_vendor_welcome_email_content` | 3 | `src/Services/NotificationService.php:1528` | Filter vendor welcome email content. |
| `wpss_vendors_page_id` | 1 | `src/functions/vendors.php:257` | Filter the resolved vendor-directory page ID. |
| `wpss_vendors_url` | 2 | `src/functions/vendors.php:329` | Filter the vendor-directory URL. |
| `wpss_video_thumbnail_cache_ttl` | 2 | `src/functions/services.php:920` | Filter how long a video's poster URL is cached. |
| `wpss_wallet_manager` | 1 | `src/functions/money.php:1736` | Filter the wallet manager instance. |
| `wpss_wallet_providers` | 1 | `src/Core/Plugin.php:2577` *(+2 more)* | Filter the registered wallet providers. |
| `wpss_web_login_lock` | 1 | `src/Core/Plugin.php:2007` | Filter whether the website sign-in form honours the lockout. |
| `wpss_withdrawal_methods` | 1 | `src/API/EarningsController.php:650` *(+1 more)* | Filter available withdrawal methods. |
| `wpss_wizard_sanitize_service_data` | 2 | `src/Frontend/ServiceWizard.php:1855` | Filter the sanitized wizard payload. |
| `wpss_wizard_service_data` | 2 | `src/Frontend/ServiceWizard.php:1245` | Filter the wizard's Alpine seed data for an existing service. |
| `wpss_zero_decimal_currencies` | 1 | `src/functions/money.php:819` | Filter the list of zero-decimal currency codes. |
