# WP Sell Services Pro - Architecture

> Technical architecture reference for the Pro plugin. Covers namespaces, bootstrap flow, REST API, database, extension patterns, and settings renderers.

---

## Table of Contents

1. [Namespace Structure](#namespace-structure)
2. [Bootstrap Flow](#bootstrap-flow)
3. [REST API Controllers](#rest-api-controllers)
4. [Database Tables](#database-tables)
5. [Extension Pattern](#extension-pattern)
6. [Settings Renderers](#settings-renderers)

---

## Namespace Structure

```
WPSellServicesPro\
    Core\
        Pro.php                         -- Main plugin class, init and hook registration
        ProAbilitiesRegistrar.php       -- WordPress Abilities API integration (WP 6.9+)

    License\
        Manager.php                     -- EDD Software Licensing validation

    Database\
        ProSchemaManager.php            -- Creates/upgrades 7 Pro tables via dbDelta

    Analytics\
        AnalyticsManager.php            -- Main orchestrator
        Dashboard.php                   -- Admin analytics page
        DataExporter.php                -- CSV/Excel export with cron cleanup
        DataCollectorInterface.php      -- Collector contract
        WidgetInterface.php             -- Widget contract
        Collectors\
            OrdersCollector.php         -- Order statistics
            RevenueCollector.php        -- Revenue data
            ServicesCollector.php       -- Service performance
            VendorsCollector.php        -- Vendor insights
        Widgets\
            RevenueWidget.php           -- Admin dashboard widget
            OrdersWidget.php            -- Admin dashboard widget
            TopServicesWidget.php       -- Admin dashboard widget
            TopVendorsWidget.php        -- Admin dashboard widget

    Integrations\
        Contracts\                      -- Shared interfaces for adapters
        WooCommerce\                    -- WooCommerce adapter (virtual carrier product)
        EDD\                            -- Easy Digital Downloads adapter
        FluentCart\                      -- FluentCart adapter
        SureCart\                        -- SureCart adapter
        Razorpay\
            RazorpayGateway.php         -- Razorpay payment gateway
        Wallets\
            WalletManager.php           -- Singleton, provider management
            WalletProviderInterface.php -- Provider contract
            InternalWalletProvider.php  -- Built-in wallet
            TeraWalletProvider.php      -- TeraWallet integration
            WooWalletProvider.php       -- WooWallet integration
            MyCredProvider.php          -- MyCred integration

    Storage\
        StorageProviderInterface.php    -- Provider contract
        S3Storage.php                   -- Amazon S3
        GCSStorage.php                  -- Google Cloud Storage
        DigitalOceanStorage.php         -- DigitalOcean Spaces

    Features\
        WizardEnhancer.php              -- Removes Free limits on wizard

    API\
        PaymentController.php           -- /payments (~9 endpoints)
        WalletController.php            -- /wallet (5 endpoints)
        VendorAnalyticsController.php   -- /analytics (5 endpoints)
        StorageController.php           -- /storage (4 endpoints)
        CommissionRuleController.php    -- /commission-rules (5 endpoints)
        WhiteLabelController.php        -- /white-label (2 endpoints)
        PayPalPayoutsController.php     -- /paypal-payouts (6 endpoints)
        StripeConnectController.php     -- /stripe-connect (6 endpoints)
        SubscriptionPlanController.php  -- /subscription-plans (7 endpoints)
        RecurringServiceController.php  -- /recurring-services (7 endpoints)

    TieredCommission\
        TieredCommissionManager.php     -- Rule evaluation engine
        CommissionRuleRepository.php    -- CRUD for rules
        CommissionSettingsRenderer.php  -- Admin settings UI
        Contracts\                      -- Interfaces
        Rules\                          -- Rule type implementations

    WhiteLabel\
        WhiteLabelManager.php           -- Central branding logic
        AdminBrandingService.php        -- Admin panel branding
        EmailBrandingService.php        -- Email template branding
        DashboardBrandingService.php    -- Frontend dashboard branding
        WhiteLabelSettingsRenderer.php  -- Settings UI
        Contracts\                      -- Interfaces

    PayPalPayouts\
        PayPalPayoutsManager.php        -- Batch payout orchestration
        PayPalPayoutsApiClient.php      -- PayPal REST API client
        PayoutsBatchService.php         -- Batch creation/processing
        VendorPayoutProfileService.php  -- Vendor PayPal profiles
        PayoutsSettingsRenderer.php     -- Settings UI
        Contracts\                      -- Interfaces

    StripeConnect\
        StripeConnectManager.php        -- Account lifecycle
        ConnectAccountService.php       -- Account CRUD
        ConnectOnboardingHandler.php    -- Express onboarding
        ConnectPaymentProcessor.php     -- Split payments
        ConnectWebhookHandler.php       -- Webhook handling
        ConnectSettingsRenderer.php     -- Settings UI
        Contracts\                      -- Interfaces

    VendorSubscriptions\
        SubscriptionManager.php         -- Plan + subscription lifecycle
        PlanRepository.php              -- CRUD for plans
        PlanEnforcer.php                -- Limit enforcement
        VendorSubscriptionService.php   -- Subscription operations
        SubscriptionBillingHandler.php  -- Stripe billing
        SubscriptionSettingsRenderer.php -- Settings UI
        Contracts\                      -- Interfaces

    RecurringServices\
        RecurringServiceManager.php     -- Subscription lifecycle
        RecurringSubscriptionRepository.php -- CRUD
        RecurringOrderFactory.php       -- Auto-renewal order creation
        StripeRecurringBilling.php      -- Stripe subscription billing
        RecurringWebhookHandler.php     -- Webhook processing
        RecurringSettingsRenderer.php   -- Settings UI
        RecurringSubscriptionsPage.php  -- Admin listing page
        Contracts\                      -- Interfaces
```

---

## Bootstrap Flow

Pro bootstraps via the `wpss_loaded` action fired by the Free plugin. This guarantees the Free plugin's service container, database tables, and hooks are available before Pro initializes.

### Sequence

```
WordPress loads plugins (plugins_loaded, priority 5)
    |
    v
Free plugin: WPSellServices\Plugin::init()
    |
    v
Free plugin: do_action('wpss_loaded', $plugin)
    |
    v
Pro plugin: wpss_pro_init($free_plugin)
    |
    +-- Load Composer autoloader
    +-- ProSchemaManager::maybe_upgrade()  // Creates 7 Pro tables if needed
    +-- License\Manager::is_valid()
    |       |
    |   [invalid] --> Pro::init(licensed: false)
    |       |             Only admin menus register (License page)
    |       |             return
    |   [valid]   --> Pro::init(licensed: true)
    |
    v
Pro::init()
    |
    +-- load_textdomain()
    +-- [admin] add_action('admin_menu', 'add_pro_menu_items')
    +-- [admin] add_action('admin_enqueue_scripts', 'enqueue_admin_assets')
    +-- init_wizard_enhancer()       // Removes Free limits
    +-- init_analytics()             // AnalyticsDashboard
    +-- init_wallet_manager()        // WalletManager singleton
    +-- init_tiered_commission()     // TieredCommissionManager + settings
    +-- init_white_label()           // WhiteLabelManager + settings
    +-- init_paypal_payouts()        // PayPalPayoutsManager + settings
    +-- init_stripe_connect()        // StripeConnectManager
    +-- init_vendor_subscriptions()  // SubscriptionManager + settings
    +-- init_recurring_services()    // RecurringServiceManager + settings
    +-- init_abilities()             // WP 6.9+ Abilities API
    +-- register_hooks()
    |       |
    |       +-- REST API:       rest_api_init -> register_rest_routes()
    |       +-- Controllers:    wpss_api_controllers -> register_api_controllers()
    |       +-- Settings:       wpss_settings_tabs -> add_settings_tabs()
    |       +-- Gateways:       wpss_gateway_accordion_sections -> render_pro_gateway_sections()
    |       +-- Dashboard:      wpss_dashboard_sections -> add_dashboard_sections()
    |       +-- Wallet:         wpss_wallet_manager -> provide_wallet_manager()
    |       +-- Commission:     wpss_commission_recorded -> sync_wallet_balance_on_commission()
    |       +-- Frontend:       wp_enqueue_scripts -> enqueue_frontend_assets()
    |       +-- Cron:           wpss_pro_cleanup_exports (daily)
    |
    v
do_action('wpss_pro_loaded', $pro)
```

### Without a Valid License

When the license is invalid, `Pro::init(licensed: false)` is called. This registers only:
- Admin menu items (License page and Welcome page must remain accessible)
- Admin assets

All Pro features, REST controllers, filters, and hooks are skipped.

---

## REST API Controllers

All 10 controllers are registered via the `wpss_api_controllers` filter in `Pro::register_api_controllers()`. Each extends `WPSellServices\API\RestController` from the Free plugin. All routes use the `wpss/v1` namespace.

### 1. PaymentController

**Base**: `/wpss/v1/payments`

| Method | Route | Purpose |
|--------|-------|---------|
| POST | `/payments/process` | Process a payment |
| POST | `/payments/verify` | Verify payment status |
| POST | `/payments/refund` | Issue a refund |
| GET | `/payments/status/{id}` | Get payment status |
| GET | `/payments/gateways` | List available gateways |
| POST | `/payments/{gateway}/webhook` | Handle gateway webhook |
| GET | `/payments/{gateway}/config` | Get gateway client config |
| POST | `/payments/create-intent` | Create Stripe PaymentIntent |
| GET | `/payments/methods` | List payment methods |

### 2. WalletController

**Base**: `/wpss/v1/wallet`

| Method | Route | Purpose |
|--------|-------|---------|
| GET | `/wallet/balance` | Get user wallet balance |
| GET | `/wallet/transactions` | List transactions |
| POST | `/wallet/withdraw` | Request withdrawal |
| GET | `/wallet/providers` | List wallet providers |
| POST | `/wallet/credit` | Admin credit to wallet |

### 3. VendorAnalyticsController

**Base**: `/wpss/v1/analytics`

| Method | Route | Purpose |
|--------|-------|---------|
| GET | `/analytics/overview` | Dashboard overview stats |
| GET | `/analytics/revenue` | Revenue data with period filter |
| GET | `/analytics/orders` | Order analytics |
| GET | `/analytics/services` | Service performance data |
| GET | `/analytics/export` | Export analytics as CSV/Excel |

### 4. StorageController

**Base**: `/wpss/v1/storage`

| Method | Route | Purpose |
|--------|-------|---------|
| POST | `/storage/upload` | Upload file to cloud |
| GET | `/storage/download-url/{id}` | Get signed download URL |
| DELETE | `/storage/{id}` | Delete file from cloud |
| GET | `/storage/providers` | List available providers |

### 5. CommissionRuleController

**Base**: `/wpss/v1/commission-rules`

| Method | Route | Purpose |
|--------|-------|---------|
| GET | `/commission-rules` | List all rules |
| POST | `/commission-rules` | Create a rule |
| PUT | `/commission-rules/{id}` | Update a rule |
| DELETE | `/commission-rules/{id}` | Delete a rule |
| POST | `/commission-rules/preview` | Preview commission calculation |

### 6. WhiteLabelController

**Base**: `/wpss/v1/white-label`

| Method | Route | Purpose |
|--------|-------|---------|
| GET | `/white-label` | Get branding settings |
| POST | `/white-label` | Update branding settings |

### 7. PayPalPayoutsController

**Base**: `/wpss/v1/paypal-payouts`

| Method | Route | Purpose |
|--------|-------|---------|
| GET | `/paypal-payouts/batches` | List payout batches |
| POST | `/paypal-payouts/batches` | Create a new batch payout |
| GET | `/paypal-payouts/batches/{id}` | Get batch details |
| GET | `/paypal-payouts/pending` | List pending payouts |
| GET | `/paypal-payouts/vendor-profile` | Get vendor's PayPal profile |
| PUT | `/paypal-payouts/vendor-profile` | Update vendor's PayPal profile |

### 8. StripeConnectController

**Base**: `/wpss/v1/stripe-connect`

| Method | Route | Purpose |
|--------|-------|---------|
| POST | `/stripe-connect/onboard` | Start Express onboarding |
| GET | `/stripe-connect/status` | Get account connection status |
| GET | `/stripe-connect/account` | Get full account details |
| DELETE | `/stripe-connect/disconnect` | Disconnect account |
| GET | `/stripe-connect/dashboard-link` | Get Stripe Express dashboard link |
| POST | `/stripe-connect/webhook` | Handle Connect webhooks |

### 9. SubscriptionPlanController

**Base**: `/wpss/v1/subscription-plans`

| Method | Route | Purpose |
|--------|-------|---------|
| GET | `/subscription-plans` | List all plans |
| POST | `/subscription-plans` | Create a plan |
| GET | `/subscription-plans/{id}` | Get plan details |
| PUT | `/subscription-plans/{id}` | Update a plan |
| DELETE | `/subscription-plans/{id}` | Delete a plan |
| POST | `/subscription-plans/{id}/subscribe` | Subscribe vendor to plan |
| POST | `/subscription-plans/{id}/cancel` | Cancel vendor subscription |

### 10. RecurringServiceController

**Base**: `/wpss/v1/recurring-services`

| Method | Route | Purpose |
|--------|-------|---------|
| GET | `/recurring-services` | List customer subscriptions |
| GET | `/recurring-services/{id}` | Get subscription details |
| POST | `/recurring-services/{id}/cancel` | Cancel subscription |
| POST | `/recurring-services/{id}/pause` | Pause subscription |
| POST | `/recurring-services/{id}/resume` | Resume subscription |
| GET | `/recurring-services/vendor` | List vendor's subscribers |
| POST | `/recurring-services/webhook` | Handle renewal webhooks |

---

## Database Tables

Pro owns 7 custom tables, all prefixed with `wpss_pro_`. They are created by `ProSchemaManager::create_tables()` using `dbDelta`.

The `wpss_wallet_transactions` table is owned by the Free plugin. Pro reads/writes to it via `WalletManager` but does not create or drop it.

### Table Summary

| # | Table | Feature Module | Purpose |
|---|-------|---------------|---------|
| 1 | `wpss_pro_connect_accounts` | Stripe Connect | Vendor Stripe Express account records |
| 2 | `wpss_pro_subscription_plans` | Vendor Subscriptions | Plan definitions (price, limits, Stripe price ID) |
| 3 | `wpss_pro_vendor_subscriptions` | Vendor Subscriptions | Active vendor-plan relationships |
| 4 | `wpss_pro_commission_rules` | Tiered Commission | Rule definitions (type, rate, conditions, priority) |
| 5 | `wpss_pro_recurring_subscriptions` | Recurring Services | Customer recurring service subscriptions |
| 6 | `wpss_pro_paypal_payout_batches` | PayPal Payouts | Batch payout records |
| 7 | `wpss_pro_paypal_payout_items` | PayPal Payouts | Individual payout line items within a batch |

### Schema Version

Tracked via `wpss_pro_db_version` option. `ProSchemaManager::maybe_upgrade()` runs on every load and applies `dbDelta` when the stored version is less than the current `SCHEMA_VERSION` constant.

### Uninstall

`ProSchemaManager::drop_tables()` drops all 7 Pro tables and deletes the version option. It explicitly does not touch `wpss_wallet_transactions` (owned by Free).

---

## Extension Pattern

Pro extends Free exclusively through WordPress hooks. The core principle: **never duplicate Free plugin code in Pro**.

### Filters Used by Pro

```php
// In Pro::register_hooks() and Pro::register_api_controllers()

// E-commerce adapters (WooCommerce, EDD, FluentCart, SureCart)
add_filter('wpss_ecommerce_adapters', function(array $adapters): array {
    $adapters['woocommerce'] = new WooCommerceAdapter();
    $adapters['edd']         = new EDDAdapter();
    // ...
    return $adapters;
});

// Payment gateways (only Razorpay -- Stripe and PayPal are in Free)
add_filter('wpss_payment_gateways', function(array $gateways): array {
    $gateways['razorpay'] = new RazorpayGateway();
    return $gateways;
});

// Wallet providers
add_filter('wpss_wallet_providers', function(array $providers): array {
    $providers['internal']    = new InternalWalletProvider();
    $providers['terawallet']  = new TeraWalletProvider();
    // ...
    return $providers;
});

// Cloud storage providers
add_filter('wpss_storage_providers', function(array $providers): array {
    $providers['s3']  = new S3Storage();
    $providers['gcs'] = new GCSStorage();
    // ...
    return $providers;
});

// REST API controllers (10 controllers)
add_filter('wpss_api_controllers', function(array $controllers): array {
    $controllers[] = new WalletController();
    $controllers[] = new PaymentController();
    // ... 8 more
    return $controllers;
});

// Wallet manager (singleton provided to Free)
add_filter('wpss_wallet_manager', function($manager) {
    return WalletManager::get_instance();
});

// Dashboard sections (add wallet, analytics to frontend)
add_filter('wpss_dashboard_sections', function(array $sections, int $user_id, bool $is_vendor): array {
    $sections['wallet']    = [...];
    $sections['analytics'] = [...];
    return $sections;
}, 10, 3);

// Settings tabs (add Branding tab)
add_filter('wpss_settings_tabs', function(array $tabs): array {
    $tabs['branding'] = 'Branding';
    return $tabs;
});

// Gateway accordion (add Razorpay to gateways settings)
add_action('wpss_gateway_accordion_sections', function(): void {
    // Render Razorpay settings section
});

// Service wizard limits (remove Free caps)
add_filter('wpss_service_max_packages', fn() => 999);
add_filter('wpss_service_max_gallery', fn() => 999);
add_filter('wpss_service_max_videos', fn() => 999);
add_filter('wpss_service_max_extras', fn() => 999);
add_filter('wpss_service_max_faq', fn() => 999);
```

### Actions Used by Pro

```php
// Commission -> wallet sync
add_action('wpss_commission_recorded', function(int $order_id, int $vendor_id, float $amount): void {
    WalletManager::get_instance()->credit($vendor_id, $amount, 'Commission for order #' . $order_id);
}, 10, 3);

// Order completed -> auto wallet payout
add_action('wpss_order_status_completed', function(int $order_id): void {
    // Auto-credit vendor wallet
});
```

### Rules for Extension

1. **Check Free first**: Before building anything, verify Free does not already have it.
2. **Each class owns its hooks**: `StripeGateway` owns Stripe settings, `RazorpayGateway` owns Razorpay settings. `Pro.php` only registers License, Analytics, and Branding tabs.
3. **No hook duplication**: Never register the same hook in both Free and Pro.
4. **REST-first**: New features must include REST endpoints. No new AJAX endpoints.
5. **Database ownership**: Pro only creates/drops `wpss_pro_*` tables. Free tables are off-limits.

---

## Settings Renderers

Each Pro feature module has its own `SettingsRenderer` class that registers admin settings UI. Renderers hook into the Free plugin's settings page infrastructure.

### Pattern

```php
class CommissionSettingsRenderer {
    public function init(): void {
        // Hook into Free's settings tabs or sections
        add_action('wpss_settings_section_commissions', [$this, 'render']);
        add_action('wpss_settings_sanitize_commissions', [$this, 'sanitize']);
    }

    public function render(): void {
        // Render HTML for commission settings
    }

    public function sanitize(array $input): array {
        // Sanitize and return settings
    }
}
```

### Renderer Inventory

| Renderer | Feature | Where It Renders |
|----------|---------|-----------------|
| `CommissionSettingsRenderer` | Tiered Commission | Commission settings section |
| `WhiteLabelSettingsRenderer` | White Label | Branding tab (added by Pro) |
| `PayoutsSettingsRenderer` | PayPal Payouts | Payouts settings section |
| `ConnectSettingsRenderer` | Stripe Connect | Stripe Connect settings section |
| `SubscriptionSettingsRenderer` | Vendor Subscriptions | Subscriptions settings section |
| `RecurringSettingsRenderer` | Recurring Services | Recurring settings section |

### Instantiation

Renderers take optional repository parameters but create their own internally. Always instantiate with no arguments unless you need to inject a mock for testing:

```php
// Correct - renderer creates its own repository
$settings = new CommissionSettingsRenderer();
$settings->init();

// Also correct - inject for testing
$settings = new CommissionSettingsRenderer($mock_repo);
$settings->init();

// The WhiteLabelSettingsRenderer takes its manager
$settings = new WhiteLabelSettingsRenderer($white_label_manager);
$settings->init();
```

### Pro.php Only Owns

`Pro.php` directly manages settings for:
- License page (separate admin page, not a tab)
- Analytics page (separate admin submenu page)
- Branding tab (added via `wpss_settings_tabs` filter)
- Razorpay accordion section (added via `wpss_gateway_accordion_sections` action)

Everything else is delegated to the feature module's own `SettingsRenderer`.
