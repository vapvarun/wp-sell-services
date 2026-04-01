# WP Sell Services - Project Scope & Architecture

> **Purpose**: This document defines the complete scope of FREE and PRO plugins, architecture flows, and roadmap. Use this as the single source of truth when developing features.

---

## Table of Contents

1. [Project Overview](#project-overview)
2. [Free vs Pro Feature Matrix](#free-vs-pro-feature-matrix)
3. [Architecture Diagrams](#architecture-diagrams)
4. [Module Specifications](#module-specifications)
5. [Pro Feature Modules](#pro-feature-modules)
6. [REST API](#rest-api)
7. [Database Schema](#database-schema)
8. [CI/CD Pipeline](#cicd-pipeline)
9. [Developer Onboarding](#developer-onboarding)
10. [Roadmap](#roadmap)
11. [Development Guidelines](#development-guidelines)

---

## Project Overview

**WP Sell Services** is a Fiverr-style service marketplace platform for WordPress.

| Aspect | Description |
|--------|-------------|
| **Target Users** | Freelancers, agencies, service providers |
| **Business Model** | Free core + Pro add-on |
| **Free Plugin** | Full marketplace with Standalone checkout, Stripe, PayPal, and Offline gateways |
| **Pro Plugin** | WooCommerce/EDD/FluentCart/SureCart adapters, Razorpay, wallets, analytics, cloud storage, and 6 feature modules |

### Plugin Relationship

```
+-----------------------------------------------------------------------+
|                         WORDPRESS                                     |
+-----------------------------------------------------------------------+
|                                                                       |
|   +---------------------------------------------------------------+   |
|   |              WP SELL SERVICES (FREE)                          |   |
|   |                                                               |   |
|   |  Core marketplace functionality:                              |   |
|   |  - Service CPT & Taxonomies                                   |   |
|   |  - Vendor System                                              |   |
|   |  - Order Management                                           |   |
|   |  - Messaging & Review Systems                                 |   |
|   |  - Standalone Checkout (no WooCommerce needed)                |   |
|   |  - Stripe, PayPal, and Offline payment gateways               |   |
|   |  - Unified Dashboard                                          |   |
|   |  - Service Wizard (Frontend Creation)                         |   |
|   |  - REST API (base controllers)                                |   |
|   |                                                               |   |
|   |  Extension Points (Filters):                                  |   |
|   |  +-- wpss_ecommerce_adapters                                  |   |
|   |  +-- wpss_payment_gateways                                    |   |
|   |  +-- wpss_wallet_providers                                    |   |
|   |  +-- wpss_storage_providers                                   |   |
|   |  +-- wpss_dashboard_sections                                  |   |
|   |  +-- wpss_settings_tabs                                       |   |
|   |  +-- wpss_analytics_widgets                                   |   |
|   |  +-- wpss_api_controllers                                     |   |
|   |  +-- wpss_gateway_accordion_sections                          |   |
|   +---------------------------------------------------------------+   |
|                              |                                        |
|                              | Hooks into via wpss_loaded             |
|                              v                                        |
|   +---------------------------------------------------------------+   |
|   |              WP SELL SERVICES PRO                              |   |
|   |                                                               |   |
|   |  Premium features:                                            |   |
|   |  - WooCommerce, EDD, FluentCart, SureCart adapters             |   |
|   |  - Razorpay payment gateway                                   |   |
|   |  - Wallet system (Internal, TeraWallet, WooWallet, MyCred)    |   |
|   |  - Advanced analytics with charts & export                    |   |
|   |  - Cloud storage (S3, GCS, DigitalOcean Spaces)               |   |
|   |  - Tiered Commission rules                                    |   |
|   |  - White Label branding                                       |   |
|   |  - PayPal Payouts (batch vendor payouts)                      |   |
|   |  - Stripe Connect (Express onboarding, split payments)        |   |
|   |  - Vendor Subscriptions (plans with Stripe billing)           |   |
|   |  - Recurring Services (auto-renewal subscriptions)            |   |
|   |  - 10 REST API controllers (~55 endpoints)                    |   |
|   +---------------------------------------------------------------+   |
|                                                                       |
+-----------------------------------------------------------------------+
```

---

## Free vs Pro Feature Matrix

### Core Marketplace Features

| Feature | FREE | PRO | Notes |
|---------|------|-----|-------|
| Service CPT | Yes | Yes | Custom post type for services |
| Categories & Tags | Yes | Yes | Taxonomies for organization |
| Vendor Registration | Yes | Yes | Users become vendors |
| Auto-Vendor Admins | Yes | Yes | Admins are auto-vendors |
| Vendor Profiles | Yes | Yes | Public vendor pages |
| Seller Levels | Yes | Yes | New, Level 1, Level 2, Top Rated |

### Service Creation (Frontend Wizard)

| Feature | FREE | PRO | Filter Hook |
|---------|------|-----|-------------|
| Basic Service Creation | Yes | Yes | Core functionality |
| Title & Description | Yes | Yes | - |
| Pricing Packages | 3 max | Unlimited | `wpss_service_max_packages` |
| Gallery Images | 4 max | Unlimited | `wpss_service_max_gallery` |
| Videos | 1 max | Unlimited | `wpss_service_max_videos` |
| Service Extras/Add-ons | 3 max | Unlimited | `wpss_service_max_extras` |
| FAQ Items | 5 max | Unlimited | `wpss_service_max_faq` |
| Requirements | Yes | Yes | - |
| Category & Tags | Yes | Yes | - |
| Service Duplication | No | Yes | Pro feature |
| Bulk Management | No | Yes | Pro feature |
| Service Scheduling | No | Yes | Pro feature |
| Advanced SEO Fields | No | Yes | Pro feature |
| Service Templates | No | Yes | Pro feature |
| AI-Assisted Descriptions | No | Yes | Pro feature |
| Per-Service Analytics | No | Yes | Pro feature |

### Order Management

| Feature | FREE | PRO | Notes |
|---------|------|-----|-------|
| Order Workflow | Yes | Yes | Full order lifecycle |
| Order Messaging | Yes | Yes | Buyer-vendor chat |
| File Delivery | Yes | Yes | Deliver work files |
| Requirements Submission | Yes | Yes | Buyer provides details |
| Revisions | Yes | Yes | Request changes |
| Order Extensions | Yes | Yes | Extend deadline |
| Dispute System | Yes | Yes | Handle conflicts |
| Review System | Yes | Yes | Ratings & reviews |

### Dashboard

| Feature | FREE | PRO | Notes |
|---------|------|-----|-------|
| Unified Dashboard | Yes | Yes | Single dashboard for all |
| My Orders (Buyer) | Yes | Yes | Orders as buyer |
| Buyer Requests | Yes | Yes | Post job requests |
| My Services (Vendor) | Yes | Yes | Vendor's services |
| Sales Orders (Vendor) | Yes | Yes | Orders as vendor |
| Earnings Section | Yes | - | Basic earnings view |
| Wallet & Earnings | - | Yes | Enhanced with wallet |
| Analytics Section | - | Yes | Detailed analytics |
| Messages | Yes | Yes | All conversations |
| Profile | Yes | Yes | Account settings |

### E-commerce & Checkout

| Platform | FREE | PRO | Notes |
|----------|------|-----|-------|
| Standalone Checkout | Yes | Yes | Built-in, no WooCommerce needed |
| WooCommerce | No | Yes | Full adapter, HPOS compatible, multi-service cart |
| Easy Digital Downloads | No | Yes | Variable pricing and subscription support |
| Fluent Cart | No | Yes | Modern checkout experience |
| SureCart | No | Yes | Cloud-hosted PCI-compliant checkout |

### Payment Gateways

| Gateway | FREE | PRO | Notes |
|---------|------|-----|-------|
| Offline (bank transfer, cash) | Yes | Yes | Built-in to Standalone adapter |
| Stripe | Yes | Yes | Native integration in Free |
| PayPal | Yes | Yes | Native integration in Free |
| Razorpay | No | Yes | UPI, cards, netbanking for Indian markets |
| Via WooCommerce | No | Yes | All WC gateways (when WC adapter active) |

### Wallet & Payouts

| Feature | FREE | PRO | Notes |
|---------|------|-----|-------|
| Manual Earnings Tracking | Yes | Yes | Basic tracking |
| Internal Wallet | No | Yes | Built-in wallet |
| TeraWallet Integration | No | Yes | Third-party wallet |
| WooWallet Integration | No | Yes | Third-party wallet |
| MyCred Integration | No | Yes | Points system |
| Auto Vendor Payouts | No | Yes | On order complete |
| Withdrawal Requests | Yes | Yes | Full workflow |
| Transaction History | No | Yes | Detailed log |
| PayPal Batch Payouts | No | Yes | Automated batch vendor payouts |
| Stripe Connect Payouts | No | Yes | Direct split payments to vendors |

### Analytics & Reporting

| Feature | FREE | PRO | Notes |
|---------|------|-----|-------|
| Basic Order Stats | Yes | Yes | Count, totals |
| Revenue Analytics | No | Yes | Charts & trends |
| Orders Analytics | No | Yes | Detailed metrics |
| Services Performance | No | Yes | Top performers |
| Vendor Analytics | No | Yes | Vendor insights |
| Period Filters | No | Yes | 7d/30d/90d/12m |
| Data Export (CSV/Excel) | No | Yes | Full export |
| Admin Dashboard Widgets | No | Yes | 4 widgets |

### Cloud Storage (Deliveries)

| Provider | FREE | PRO | Notes |
|----------|------|-----|-------|
| Local WordPress | Yes | Yes | Default |
| Amazon S3 | No | Yes | Signature V4 authentication |
| Google Cloud Storage | No | Yes | Service account auth |
| DigitalOcean Spaces | No | Yes | S3-compatible |

### Pro Feature Modules

| Module | FREE | PRO | Notes |
|--------|------|-----|-------|
| Tiered Commission | No | Yes | Category, volume, and seller-level commission rules |
| White Label | No | Yes | Custom branding for admin, emails, and dashboard |
| PayPal Payouts | No | Yes | Automated batch vendor payouts via PayPal |
| Stripe Connect | No | Yes | Direct vendor payments with Express onboarding |
| Vendor Subscriptions | No | Yes | Subscription plans with Stripe billing and enforcement |
| Recurring Services | No | Yes | Auto-renewal subscriptions for services |

### Email Delivery

Email delivery uses WordPress's native `wp_mail()` function. For SMTP configuration, use dedicated plugins like:
- WP Mail SMTP
- FluentSMTP
- Post SMTP

---

## Architecture Diagrams

### Overall System Architecture

```
+-----------------------------------------------------------------------------+
|                            USER INTERFACES                                  |
+-----------------------------------------------------------------------------+
|                                                                             |
|  +--------------+  +--------------+  +--------------+  +--------------+     |
|  |   Services   |  |  Dashboard   |  |    Admin     |  |   REST API   |     |
|  |    Page      |  |   (Unified)  |  |    Panel     |  |   Endpoints  |     |
|  +--------------+  +--------------+  +--------------+  +--------------+     |
|                                                                             |
+-----------------------------------------------------------------------------+
                                      |
                                      v
+-----------------------------------------------------------------------------+
|                          BUSINESS LOGIC LAYER                               |
+-----------------------------------------------------------------------------+
|                                                                             |
|  +-------------------------------------------------------------------+     |
|  |                        FREE PLUGIN SERVICES                        |     |
|  +-------------------------------------------------------------------+     |
|  |                                                                    |     |
|  |  +-----------+ +-----------+ +-----------+ +-----------+           |     |
|  |  |  Service  | |   Order   | |  Vendor   | |  Review   |           |     |
|  |  |  Manager  | |  Service  | |  Service  | |  Service  |           |     |
|  |  +-----------+ +-----------+ +-----------+ +-----------+           |     |
|  |                                                                    |     |
|  |  +-----------+ +-----------+ +-----------+ +-----------+           |     |
|  |  | Converse  | | Delivery  | |  Dispute  | | Earnings  |           |     |
|  |  |  Service  | |  Service  | |  Service  | |  Service  |           |     |
|  |  +-----------+ +-----------+ +-----------+ +-----------+           |     |
|  |                                                                    |     |
|  |  +-----------+ +-----------+ +-----------+                         |     |
|  |  |Standalone | |  Stripe   | |  PayPal   |                         |     |
|  |  | Adapter   | | Gateway   | | Gateway   |                         |     |
|  |  +-----------+ +-----------+ +-----------+                         |     |
|  +-------------------------------------------------------------------+     |
|                                      |                                      |
|                                      | Filters & Actions                    |
|                                      v                                      |
|  +-------------------------------------------------------------------+     |
|  |                        PRO PLUGIN SERVICES                         |     |
|  +-------------------------------------------------------------------+     |
|  |                                                                    |     |
|  |  +-----------+ +-----------+ +-----------+ +-----------+           |     |
|  |  | Analytics | |  Wallet   | | Razorpay  | |  Storage  |           |     |
|  |  |  Manager  | |  Manager  | | Gateway   | | Providers |           |     |
|  |  +-----------+ +-----------+ +-----------+ +-----------+           |     |
|  |                                                                    |     |
|  |  +-----------+ +-----------+ +-----------+ +-----------+           |     |
|  |  |WooCommerce| |   EDD     | |  Fluent   | | SureCart  |           |     |
|  |  |  Adapter  | |  Adapter  | |   Cart    | |  Adapter  |           |     |
|  |  +-----------+ +-----------+ +-----------+ +-----------+           |     |
|  |                                                                    |     |
|  |  +-----------+ +-----------+ +-----------+                         |     |
|  |  |  Tiered   | |  White    | |  PayPal   |                         |     |
|  |  |Commission | |  Label    | |  Payouts  |                         |     |
|  |  +-----------+ +-----------+ +-----------+                         |     |
|  |                                                                    |     |
|  |  +-----------+ +-----------+ +-----------+                         |     |
|  |  |  Stripe   | |  Vendor   | | Recurring |                         |     |
|  |  |  Connect  | |  Subs     | | Services  |                         |     |
|  |  +-----------+ +-----------+ +-----------+                         |     |
|  +-------------------------------------------------------------------+     |
|                                                                             |
+-----------------------------------------------------------------------------+
                                      |
                                      v
+-----------------------------------------------------------------------------+
|                            DATA LAYER                                       |
+-----------------------------------------------------------------------------+
|                                                                             |
|  +-----------------------------------------------------------------------+  |
|  |                         WORDPRESS DATABASE                            |  |
|  +-----------------------------------------------------------------------+  |
|  |                                                                       |  |
|  |  WordPress Core Tables        Free Custom Tables (wpss_*)            |  |
|  |  ---------------------        --------------------------              |  |
|  |  wp_posts (services)          wpss_orders                             |  |
|  |  wp_postmeta                  wpss_conversations                      |  |
|  |  wp_users                     wpss_messages                           |  |
|  |  wp_usermeta                  wpss_deliveries                         |  |
|  |  wp_terms                     wpss_reviews                            |  |
|  |  wp_term_taxonomy             wpss_disputes                           |  |
|  |  wp_options                   wpss_service_packages                   |  |
|  |                               wpss_wallet_transactions                |  |
|  |                               wpss_order_requirements                 |  |
|  |                                                                       |  |
|  |  Pro Custom Tables (wpss_pro_*)                                       |  |
|  |  ----------------------------------                                   |  |
|  |  wpss_pro_connect_accounts                                            |  |
|  |  wpss_pro_subscription_plans                                          |  |
|  |  wpss_pro_vendor_subscriptions                                        |  |
|  |  wpss_pro_commission_rules                                            |  |
|  |  wpss_pro_recurring_subscriptions                                     |  |
|  |  wpss_pro_paypal_payout_batches                                       |  |
|  |  wpss_pro_paypal_payout_items                                         |  |
|  +-----------------------------------------------------------------------+  |
|                                                                             |
+-----------------------------------------------------------------------------+
```

### Order Workflow

```
+-----------------------------------------------------------------------------+
|                           ORDER LIFECYCLE                                   |
+-----------------------------------------------------------------------------+

    BUYER                          SYSTEM                         VENDOR
      |                              |                              |
      |  1. Purchase Service         |                              |
      +----------------------------->|                              |
      |                              |                              |
      |                              |  Order Created               |
      |                              |  Status: pending_requirements|
      |                              |                              |
      |  2. Submit Requirements      |                              |
      +----------------------------->|                              |
      |                              |                              |
      |                              |  3. Notify Vendor            |
      |                              +----------------------------->|
      |                              |  Status: in_progress         |
      |                              |                              |
      |                              |  4. Work on Order            |
      |                              |<-----------------------------+
      |                              |                              |
      |                              |  5. Deliver Work             |
      |  6. Review Delivery          |<-----------------------------+
      |<-----------------------------|  Status: delivered           |
      |                              |                              |
      |                              |                              |
      |  +---------------------------------------------------+     |
      |  |                    DECISION POINT                  |     |
      |  +---------------------------------------------------+     |
      |         |                    |                    |         |
      |         v                    v                    v         |
      |    +---------+         +---------+         +---------+     |
      |    | Accept  |         |Revision |         | Dispute |     |
      |    +---------+         +---------+         +---------+     |
      |         |                    |                    |         |
      |         v                    |                    |         |
      |  Status: completed           |                    |         |
      |         |                    |                    |         |
      |         v                    |                    |         |
      |  +-------------+            |                    |         |
      |  |Leave Review |            |                    |         |
      |  +-------------+            |                    |         |
      |         |                    |                    |         |
      |         v                    v                    v         |
      |  +---------------------------------------------------+     |
      |  |              VENDOR PAYOUT (Pro Wallet)           |     |
      |  |  - Auto-credit to wallet on completion            |     |
      |  |  - Withdrawal request workflow                    |     |
      |  |  - PayPal batch payouts or Stripe Connect splits  |     |
      |  +---------------------------------------------------+     |
      |                                                             |

ORDER STATUSES:
  - pending_payment          : Awaiting payment
  - pending_requirements     : Paid, awaiting buyer requirements
  - in_progress              : Vendor working on order
  - delivered                : Work delivered, awaiting acceptance
  - pending_approval         : Delivery submitted, awaiting buyer approval
  - revision_requested       : Buyer requested changes
  - completed                : Order finished successfully
  - cancellation_requested   : Cancellation requested, awaiting confirmation
  - cancelled                : Order cancelled
  - disputed                 : Under dispute resolution
  - late                     : Past due date
  - on_hold                  : Paused
  - refunded                 : Order refunded
```

### Pro Plugin Initialization Flow

```
+-----------------------------------------------------------------------------+
|                     PRO PLUGIN INITIALIZATION                               |
+-----------------------------------------------------------------------------+

    plugins_loaded (Priority 5)
           |
           v
    +---------------------------------------------+
    |   Check Free Plugin Version                 |
    |   wpss_pro_check_free_version()             |
    +---------------------------------------------+
           |
           | Free version OK?
           |
     No <--+--> Yes
     |          |
     v          v
  Show       Hook into wpss_loaded
  Notice     add_action('wpss_loaded', 'wpss_pro_init')
     |          |
     |          v
     |    +---------------------------------------------+
     |    |   wpss_pro_init($plugin)                    |
     |    +---------------------------------------------+
     |          |
     |          v
     |    +---------------------------------------------+
     |    |   Load Composer Autoloader                  |
     |    +---------------------------------------------+
     |          |
     |          v
     |    +---------------------------------------------+
     |    |   ProSchemaManager::maybe_upgrade()         |
     |    |   (creates 7 Pro tables if needed)          |
     |    +---------------------------------------------+
     |          |
     |          v
     |    +---------------------------------------------+
     |    |   License\Manager::is_valid()               |
     |    +---------------------------------------------+
     |          |
     |    Invalid <--+--> Valid
     |       |              |
     |       v              v
     |    Show           Register Filters:
     |    License        +-- wpss_ecommerce_adapters
     |    Notice         +-- wpss_payment_gateways
     |                   +-- wpss_wallet_providers
     |                   +-- wpss_storage_providers
     |                         |
     |                         v
     |                   +---------------------------------------------+
     |                   |   Core\Pro::init()                          |
     |                   +---------------------------------------------+
     |                   |   - load_textdomain()                       |
     |                   |   - init_wizard_enhancer()                  |
     |                   |   - init_analytics()                        |
     |                   |   - init_wallet_manager()                   |
     |                   |   - init_tiered_commission()                |
     |                   |   - init_white_label()                      |
     |                   |   - init_paypal_payouts()                   |
     |                   |   - init_stripe_connect()                   |
     |                   |   - init_vendor_subscriptions()             |
     |                   |   - init_recurring_services()               |
     |                   |   - init_abilities()                        |
     |                   |   - register_hooks()                        |
     |                   +---------------------------------------------+
     |                         |
     |                         v
     |                   do_action('wpss_pro_loaded', $pro)
     |
     +---------------------------------------------------------------------
```

---

## Module Specifications

### 1. Analytics Module

**Location**: `src/Analytics/`

**Components**:

| File | Purpose |
|------|---------|
| `AnalyticsManager.php` | Main orchestrator, AJAX handlers |
| `Dashboard.php` | Admin analytics page |
| `DataExporter.php` | CSV/Excel export |
| `DataCollectorInterface.php` | Collector contract |
| `WidgetInterface.php` | Widget contract |
| `Collectors/OrdersCollector.php` | Order statistics |
| `Collectors/RevenueCollector.php` | Revenue data |
| `Collectors/ServicesCollector.php` | Service performance |
| `Collectors/VendorsCollector.php` | Vendor insights |
| `Widgets/RevenueWidget.php` | Admin dashboard widget |
| `Widgets/OrdersWidget.php` | Admin dashboard widget |
| `Widgets/TopServicesWidget.php` | Admin dashboard widget |
| `Widgets/TopVendorsWidget.php` | Admin dashboard widget |

**Data Flow**:
```
User Request (Dashboard/Admin)
       |
       v
AnalyticsManager
       |
       +-- Period: 7d / 30d / 90d / 12m
       |
       v
DataCollectors (parallel)
       |
       +-- OrdersCollector   -> Order counts, status breakdown
       +-- RevenueCollector  -> Totals, trends, charts
       +-- ServicesCollector -> Top performers, views
       +-- VendorsCollector  -> Top vendors, earnings
       |
       v
Aggregated Data -> Render or Export
```

### 2. Wallet Module

**Location**: `src/Integrations/Wallets/`

**Components**:

| File | Purpose |
|------|---------|
| `WalletManager.php` | Provider management, operations |
| `WalletProviderInterface.php` | Provider contract |
| `InternalWalletProvider.php` | Built-in wallet |
| `TeraWalletProvider.php` | TeraWallet integration |
| `WooWalletProvider.php` | WooWallet integration |
| `MyCredProvider.php` | MyCred integration |

**Operations**:
```
WalletManager
    |
    +-- get_balance(user_id)                      -> float
    +-- credit(user_id, amount, description)      -> bool
    +-- debit(user_id, amount, description)       -> bool
    +-- get_transactions(user_id, limit)          -> array
    +-- process_withdrawal(user_id, amount)       -> bool
```

**Auto-Payout Flow**:
```
Order Status -> completed
       |
       v
wpss_order_status_completed hook
       |
       v
WalletManager::process_vendor_payout()
       |
       +-- Check auto_payout_to_wallet setting
       +-- Calculate vendor_earnings
       +-- Credit to vendor wallet
       +-- Update order meta (payout processed)
```

### 3. Payment Gateways

**Free Plugin Gateways** (in `wp-sell-services/src/Integrations/`):

| File | Purpose |
|------|---------|
| `Stripe/StripeGateway.php` | Stripe Checkout/Elements |
| `PayPal/PayPalGateway.php` | PayPal Commerce Platform |
| `Standalone/OfflineGateway.php` | Bank transfer, cash payments |

**Pro Plugin Gateways** (in `wp-sell-services-pro/src/Integrations/`):

| File | Purpose |
|------|---------|
| `Razorpay/RazorpayGateway.php` | UPI, cards, netbanking (India) |

All gateways implement `PaymentGatewayInterface`:
```php
interface PaymentGatewayInterface {
    public function get_id(): string;
    public function get_name(): string;
    public function is_available(): bool;
    public function process_payment(array $order_data): array;
    public function handle_webhook(array $payload): void;
    public function get_settings_fields(): array;
}
```

### 4. E-commerce Adapters

**Free Plugin**: Standalone adapter with Offline, Stripe, and PayPal gateways (no WooCommerce dependency).

**Pro Plugin** (in `src/Integrations/`):

| Adapter | Location | Notes |
|---------|----------|-------|
| WooCommerce | `WooCommerce/` | HPOS compatible, virtual carrier product, multi-service cart |
| EDD | `EDD/` | Variable pricing and subscription support |
| FluentCart | `FluentCart/` | Modern checkout experience |
| SureCart | `SureCart/` | Cloud-hosted PCI-compliant checkout |

Each adapter provides:

| Provider | Purpose |
|----------|---------|
| `*Adapter.php` | Main adapter class |
| `*ProductProvider.php` | Create/sync products |
| `*OrderProvider.php` | Process orders |
| `*CheckoutProvider.php` | Checkout flow |
| `*AccountProvider.php` | Customer account |

### 4a. WooCommerce Virtual Carrier Product (PRO)

**Location**: `wp-sell-services-pro/src/Integrations/WooCommerce/`

WooCommerce uses a **Virtual Carrier Product** approach instead of 1:1 product-service mapping:

```
+-----------------------------------------------------------------------------+
|              VIRTUAL CARRIER PRODUCT APPROACH                               |
+-----------------------------------------------------------------------------+
|                                                                             |
|  OLD APPROACH (Not Used):                                                   |
|    Service A -> WC Product A   (Cluttered catalog)                          |
|    Service B -> WC Product B   (Sync issues)                                |
|    Service C -> WC Product C   (Manual setup per service)                   |
|                                                                             |
|  CURRENT APPROACH (Implemented):                                            |
|    All Services --> Single Hidden Carrier --> Cart --> Checkout              |
|                              |                                              |
|                  +-----------------------+                                  |
|                  | Cart Item Meta:       |                                  |
|                  | - wpss_service_id     |                                  |
|                  | - wpss_package_id     |                                  |
|                  | - wpss_addons         |                                  |
|                  | - wpss_vendor_id      |                                  |
|                  +-----------------------+                                  |
|                                                                             |
+-----------------------------------------------------------------------------+
```

| Step | Action | Details |
|------|--------|---------|
| 1 | Plugin Activation | Hidden "Service Order" product auto-created |
| 2 | User clicks "Order Now" | AJAX adds carrier to cart with service meta |
| 3 | Cart Display | Shows service name + package (not "Service Order") |
| 4 | Price Calculation | Dynamic from selected package + addons |
| 5 | Checkout | Standard WC checkout flow |
| 6 | WC Order Created | Service meta saved to order line item |
| 7 | Payment Complete | WPSS service order created from WC order |

### 5. Storage Module

**Location**: `src/Storage/`

| File | Purpose |
|------|---------|
| `StorageProviderInterface.php` | Provider contract |
| `S3Storage.php` | Amazon S3 (Signature V4) |
| `GCSStorage.php` | Google Cloud Storage (service account auth) |
| `DigitalOceanStorage.php` | DigitalOcean Spaces (S3-compatible) |

---

## Pro Feature Modules

### 1. Tiered Commission

**Location**: `src/TieredCommission/`

| File | Purpose |
|------|---------|
| `TieredCommissionManager.php` | Rule evaluation and commission calculation |
| `CommissionRuleRepository.php` | CRUD for commission rules |
| `CommissionSettingsRenderer.php` | Admin settings UI |
| `Contracts/` | Interfaces |
| `Rules/` | Rule type implementations |

Supports three rule types:
- **Category-based**: Different commission rates per service category
- **Volume-based**: Tiered rates based on order count or revenue thresholds
- **Seller-level**: Rates tied to vendor level (New, Level 1, Level 2, Top Rated)

**DB Table**: `wpss_pro_commission_rules`

### 2. White Label

**Location**: `src/WhiteLabel/`

| File | Purpose |
|------|---------|
| `WhiteLabelManager.php` | Central branding logic |
| `AdminBrandingService.php` | Admin panel branding |
| `EmailBrandingService.php` | Email template branding |
| `DashboardBrandingService.php` | Frontend dashboard branding |
| `WhiteLabelSettingsRenderer.php` | Settings UI |
| `Contracts/` | Interfaces |

Covers: custom platform name, logo, colors, email headers/footers, and dashboard branding.

### 3. PayPal Payouts

**Location**: `src/PayPalPayouts/`

| File | Purpose |
|------|---------|
| `PayPalPayoutsManager.php` | Batch payout orchestration |
| `PayPalPayoutsApiClient.php` | PayPal REST API integration |
| `PayoutsBatchService.php` | Batch creation and processing |
| `VendorPayoutProfileService.php` | Vendor PayPal profile management |
| `PayoutsSettingsRenderer.php` | Admin settings UI |
| `Contracts/` | Interfaces |

**DB Tables**: `wpss_pro_paypal_payout_batches`, `wpss_pro_paypal_payout_items`

### 4. Stripe Connect

**Location**: `src/StripeConnect/`

| File | Purpose |
|------|---------|
| `StripeConnectManager.php` | Connect account lifecycle |
| `ConnectAccountService.php` | Account CRUD and status |
| `ConnectOnboardingHandler.php` | Express onboarding flow |
| `ConnectPaymentProcessor.php` | Split payment processing |
| `ConnectWebhookHandler.php` | Stripe webhook handling |
| `ConnectSettingsRenderer.php` | Admin settings UI |
| `Contracts/` | Interfaces |

**DB Table**: `wpss_pro_connect_accounts`

### 5. Vendor Subscriptions

**Location**: `src/VendorSubscriptions/`

| File | Purpose |
|------|---------|
| `SubscriptionManager.php` | Plan and subscription lifecycle |
| `PlanRepository.php` | CRUD for subscription plans |
| `PlanEnforcer.php` | Limit enforcement (max services, etc.) |
| `VendorSubscriptionService.php` | Subscription operations |
| `SubscriptionBillingHandler.php` | Stripe billing integration |
| `SubscriptionSettingsRenderer.php` | Admin settings UI |
| `Contracts/` | Interfaces |

**DB Tables**: `wpss_pro_subscription_plans`, `wpss_pro_vendor_subscriptions`

### 6. Recurring Services

**Location**: `src/RecurringServices/`

| File | Purpose |
|------|---------|
| `RecurringServiceManager.php` | Subscription lifecycle |
| `RecurringSubscriptionRepository.php` | CRUD for customer subscriptions |
| `RecurringOrderFactory.php` | Auto-creates renewal orders |
| `StripeRecurringBilling.php` | Stripe subscription billing |
| `RecurringWebhookHandler.php` | Webhook processing |
| `RecurringSettingsRenderer.php` | Admin settings UI |
| `RecurringSubscriptionsPage.php` | Admin listing page |
| `Contracts/` | Interfaces |

**DB Table**: `wpss_pro_recurring_subscriptions`

---

## REST API

### Pro REST API Controllers (10 controllers, ~55 endpoints)

All controllers register via the `wpss_api_controllers` filter and extend `WPSellServices\API\RestController`. Base namespace: `wpss/v1`.

| Controller | Base Route | Key Endpoints |
|-----------|-----------|---------------|
| `PaymentController` | `/payments` | `POST /process`, `POST /verify`, `POST /refund`, `GET /status/{id}`, `GET /gateways`, `POST /{gateway}/webhook`, `GET /{gateway}/config`, `POST /create-intent`, `GET /methods` |
| `WalletController` | `/wallet` | `GET /balance`, `GET /transactions`, `POST /withdraw`, `GET /providers`, `POST /credit` |
| `VendorAnalyticsController` | `/analytics` | `GET /overview`, `GET /revenue`, `GET /orders`, `GET /services`, `GET /export` |
| `StorageController` | `/storage` | `POST /upload`, `GET /download-url/{id}`, `DELETE /{id}`, `GET /providers` |
| `CommissionRuleController` | `/commission-rules` | `GET /`, `POST /`, `PUT /{id}`, `DELETE /{id}`, `POST /preview` |
| `WhiteLabelController` | `/white-label` | `GET /`, `POST /` (get/update settings) |
| `PayPalPayoutsController` | `/paypal-payouts` | `GET /batches`, `POST /batches`, `GET /batches/{id}`, `GET /pending`, `GET /vendor-profile`, `PUT /vendor-profile` |
| `StripeConnectController` | `/stripe-connect` | `POST /onboard`, `GET /status`, `GET /account`, `DELETE /disconnect`, `GET /dashboard-link`, `POST /webhook` |
| `SubscriptionPlanController` | `/subscription-plans` | `GET /`, `POST /`, `GET /{id}`, `PUT /{id}`, `DELETE /{id}`, `POST /{id}/subscribe`, `POST /{id}/cancel` |
| `RecurringServiceController` | `/recurring-services` | `GET /`, `GET /{id}`, `POST /{id}/cancel`, `POST /{id}/pause`, `POST /{id}/resume`, `GET /vendor`, `POST /webhook` |

### Adding a New Pro REST Controller

1. Create in `src/API/` extending `WPSellServices\API\RestController`
2. Register in `Pro::register_api_controllers()` method
3. Use permission patterns from base class (`wpss_vendor` capability or `manage_options`)
4. REST-first only -- no new AJAX endpoints

---

## Database Schema

### Free Plugin Tables

```sql
-- Service Orders
CREATE TABLE {prefix}wpss_orders (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_number VARCHAR(50) NOT NULL,
    service_id BIGINT UNSIGNED NOT NULL,
    package_id BIGINT UNSIGNED,
    buyer_id BIGINT UNSIGNED NOT NULL,
    vendor_id BIGINT UNSIGNED NOT NULL,
    platform_order_id BIGINT UNSIGNED,
    status VARCHAR(50) DEFAULT 'pending_requirements',
    total DECIMAL(10,2) DEFAULT 0,
    vendor_earnings DECIMAL(10,2) DEFAULT 0,
    platform_fee DECIMAL(10,2) DEFAULT 0,
    delivery_date DATETIME,
    completed_at DATETIME,
    meta JSON,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Conversations
CREATE TABLE {prefix}wpss_conversations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED NOT NULL,
    participants JSON NOT NULL,
    last_message_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Messages
CREATE TABLE {prefix}wpss_messages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    conversation_id BIGINT UNSIGNED NOT NULL,
    sender_id BIGINT UNSIGNED NOT NULL,
    message TEXT NOT NULL,
    attachments JSON,
    is_read TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Deliveries
CREATE TABLE {prefix}wpss_deliveries (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED NOT NULL,
    vendor_id BIGINT UNSIGNED NOT NULL,
    message TEXT,
    files JSON,
    revision_number INT DEFAULT 1,
    status VARCHAR(50) DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Reviews
CREATE TABLE {prefix}wpss_reviews (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED NOT NULL,
    service_id BIGINT UNSIGNED NOT NULL,
    reviewer_id BIGINT UNSIGNED NOT NULL,
    vendor_id BIGINT UNSIGNED NOT NULL,
    rating DECIMAL(2,1) NOT NULL,
    review TEXT,
    response TEXT,
    status VARCHAR(50) DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Disputes
CREATE TABLE {prefix}wpss_disputes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    dispute_number VARCHAR(50) NOT NULL,
    order_id BIGINT UNSIGNED NOT NULL,
    initiated_by BIGINT UNSIGNED NOT NULL,
    reason VARCHAR(100) NOT NULL,
    description TEXT,
    status VARCHAR(50) DEFAULT 'open',
    resolution TEXT,
    resolved_by BIGINT UNSIGNED,
    resolved_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Service Packages
CREATE TABLE {prefix}wpss_service_packages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    service_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    delivery_days INT NOT NULL,
    revisions INT DEFAULT 0,
    features JSON,
    sort_order INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Order Requirements
CREATE TABLE {prefix}wpss_order_requirements (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED NOT NULL,
    ...
);

-- Wallet Transactions (owned by Free plugin)
CREATE TABLE {prefix}wpss_wallet_transactions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    type ENUM('credit', 'debit') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    balance_after DECIMAL(10,2) NOT NULL,
    description VARCHAR(255),
    reference_type VARCHAR(50),
    reference_id BIGINT UNSIGNED,
    meta JSON,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at)
);
```

### Pro Plugin Tables (7 tables)

All managed by `ProSchemaManager`. Created via `dbDelta` on activation or version upgrade.

```sql
-- 1. Stripe Connect vendor accounts
CREATE TABLE {prefix}wpss_pro_connect_accounts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    vendor_id BIGINT UNSIGNED NOT NULL,
    stripe_account_id VARCHAR(255) NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'pending',
    payouts_enabled TINYINT(1) NOT NULL DEFAULT 0,
    charges_enabled TINYINT(1) NOT NULL DEFAULT 0,
    country VARCHAR(2) DEFAULT NULL,
    default_currency VARCHAR(3) DEFAULT NULL,
    onboarding_completed TINYINT(1) NOT NULL DEFAULT 0,
    metadata LONGTEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY vendor_id (vendor_id),
    KEY stripe_account_id (stripe_account_id),
    KEY status (status)
);

-- 2. Vendor subscription plans
CREATE TABLE {prefix}wpss_pro_subscription_plans (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    billing_period VARCHAR(20) NOT NULL DEFAULT 'monthly',
    max_services INT NOT NULL DEFAULT -1,
    max_featured INT NOT NULL DEFAULT 0,
    commission_override DECIMAL(5,2) DEFAULT NULL,
    features LONGTEXT DEFAULT NULL,
    stripe_price_id VARCHAR(255) DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY slug (slug),
    KEY is_active (is_active)
);

-- 3. Active vendor subscriptions
CREATE TABLE {prefix}wpss_pro_vendor_subscriptions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    vendor_id BIGINT UNSIGNED NOT NULL,
    plan_id BIGINT UNSIGNED NOT NULL,
    stripe_subscription_id VARCHAR(255) DEFAULT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'active',
    current_period_start DATETIME DEFAULT NULL,
    current_period_end DATETIME DEFAULT NULL,
    cancelled_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY vendor_id (vendor_id),
    KEY plan_id (plan_id),
    KEY status (status)
);

-- 4. Tiered commission rules
CREATE TABLE {prefix}wpss_pro_commission_rules (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rule_type VARCHAR(50) NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    rate DECIMAL(5,2) NOT NULL,
    rate_type VARCHAR(20) NOT NULL DEFAULT 'percentage',
    conditions LONGTEXT DEFAULT NULL,
    priority INT NOT NULL DEFAULT 10,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY rule_type (rule_type),
    KEY priority (priority),
    KEY is_active (is_active)
);

-- 5. Customer recurring subscriptions
CREATE TABLE {prefix}wpss_pro_recurring_subscriptions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id BIGINT UNSIGNED NOT NULL,
    vendor_id BIGINT UNSIGNED NOT NULL,
    service_id BIGINT UNSIGNED NOT NULL,
    package_id INT DEFAULT NULL,
    original_order_id BIGINT UNSIGNED NOT NULL,
    stripe_subscription_id VARCHAR(255) DEFAULT NULL,
    billing_interval VARCHAR(20) NOT NULL DEFAULT 'monthly',
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) NOT NULL DEFAULT 'USD',
    status VARCHAR(50) NOT NULL DEFAULT 'active',
    next_billing_date DATETIME DEFAULT NULL,
    cancelled_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY customer_id (customer_id),
    KEY vendor_id (vendor_id),
    KEY service_id (service_id),
    KEY stripe_subscription_id (stripe_subscription_id),
    KEY status (status)
);

-- 6. PayPal payout batches
CREATE TABLE {prefix}wpss_pro_paypal_payout_batches (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    batch_id VARCHAR(255) DEFAULT NULL,
    payout_batch_id VARCHAR(255) DEFAULT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'pending',
    total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total_items INT NOT NULL DEFAULT 0,
    currency VARCHAR(3) NOT NULL DEFAULT 'USD',
    initiated_by BIGINT UNSIGNED NOT NULL,
    metadata LONGTEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY batch_id (batch_id),
    KEY payout_batch_id (payout_batch_id),
    KEY status (status)
);

-- 7. PayPal payout items
CREATE TABLE {prefix}wpss_pro_paypal_payout_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    batch_id BIGINT UNSIGNED NOT NULL,
    vendor_id BIGINT UNSIGNED NOT NULL,
    paypal_email VARCHAR(255) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) NOT NULL DEFAULT 'USD',
    payout_item_id VARCHAR(255) DEFAULT NULL,
    transaction_id VARCHAR(255) DEFAULT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'pending',
    error_message TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY batch_id (batch_id),
    KEY vendor_id (vendor_id),
    KEY status (status)
);
```

---

## CI/CD Pipeline

GitHub Actions runs on every push and PR to `main`.

| Check | Description | Blocking |
|-------|-------------|----------|
| PHP Lint | Syntax check on PHP 8.1, 8.2, 8.3, 8.4 | Yes |
| WPCS | WordPress Coding Standards (0 errors required, warnings allowed) | Yes |
| PHPStan | Static analysis level 5 (auto-checks out free plugin for type resolution) | Yes |
| PHPUnit | Unit tests across PHP/WP matrix | No |

### Branch Protection

- All blocking checks must pass before merge
- Pull requests require 1 approval
- Direct pushes to `main` are blocked

### CI Workflow

```
1. Create feature branch from main
2. Push commits -> CI runs automatically
3. Open PR -> CI runs again
4. All checks green + 1 approval -> merge
```

---

## Developer Onboarding

### How Pro Extends Free

Pro **never** duplicates Free plugin code. Instead, it hooks into Free via WordPress filters and actions:

```php
// Pro hooks into the Free plugin's loaded action
add_action('wpss_loaded', function($plugin) {
    // Verify license
    if (!License\Manager::is_valid()) {
        return;
    }

    // Register Pro adapters via filters
    add_filter('wpss_ecommerce_adapters', ...);  // WooCommerce, EDD, etc.
    add_filter('wpss_payment_gateways', ...);     // Razorpay
    add_filter('wpss_wallet_providers', ...);     // Internal, TeraWallet, etc.
    add_filter('wpss_storage_providers', ...);    // S3, GCS, DO Spaces
    add_filter('wpss_api_controllers', ...);      // 10 REST controllers
    add_filter('wpss_settings_tabs', ...);        // Branding tab
    add_filter('wpss_dashboard_sections', ...);   // Wallet, analytics sections
});
```

Key principle: **If you find yourself copying code from Free into Pro, you are doing it wrong.** Use hooks to extend, never to duplicate.

### Local Development Setup

```bash
# 1. Clone the Pro plugin into your Local WP site's plugins directory
cd /path/to/local-site/app/public/wp-content/plugins/
git clone <pro-repo-url> wp-sell-services-pro

# 2. The Free plugin must also be installed and active
git clone <free-repo-url> wp-sell-services

# 3. Install Pro dependencies
cd wp-sell-services-pro
composer install
npm install

# 4. Build assets
npm run build      # Production
npm run dev        # Watch mode

# 5. Activate both plugins in WP Admin (Free first, then Pro)
# 6. Enter your license key at WP Sell Services > License
```

### Running Tests, Lint, and PHPStan

```bash
# WPCS linting (check)
composer phpcs

# WPCS auto-fix
composer phpcbf

# PHPStan static analysis (requires Free plugin at ../wp-sell-services/)
vendor/bin/phpstan analyse --memory-limit=1G

# PHPUnit tests
composer test
vendor/bin/phpunit --testsuite unit
```

### CI Workflow (Day-to-Day)

```
1. git checkout -b feature/your-feature-name
2. Make changes
3. Run locally:  composer phpcs && vendor/bin/phpstan analyse --memory-limit=1G
4. git push -u origin feature/your-feature-name
5. Open PR on GitHub -> CI checks run automatically
6. Fix any CI failures, push again
7. Get 1 approval from a reviewer
8. Merge (all blocking checks must be green)
```

### Key Extension Points (Free Plugin Provides)

| Hook | Purpose |
|------|---------|
| `wpss_ecommerce_adapters` | Register e-commerce adapters (WooCommerce, EDD, etc.) |
| `wpss_payment_gateways` | Register payment gateways (Razorpay) |
| `wpss_wallet_providers` | Register wallet providers |
| `wpss_storage_providers` | Register cloud storage providers |
| `wpss_dashboard_sections` | Add dashboard sections |
| `wpss_settings_tabs` | Add settings tabs |
| `wpss_analytics_widgets` | Add analytics dashboard widgets |
| `wpss_wallet_manager` | Provide wallet manager |
| `wpss_api_controllers` | Register Pro REST API controllers |
| `wpss_gateway_accordion_sections` | Add gateway sections to settings |
| `wpss_service_max_packages` | Override max packages (default 3) |
| `wpss_service_max_gallery` | Override max gallery images (default 5) |
| `wpss_service_max_videos` | Override max videos (default 1) |
| `wpss_service_max_extras` | Override max extras (default 3) |
| `wpss_service_max_faq` | Override max FAQ items (default 5) |
| `wpss_commission_recorded` | Fired when commission is recorded |
| `wpss_order_status_completed` | Fired when order completes |

### Action Hooks

| Hook | When Fired | Use Case |
|------|------------|----------|
| `wpss_loaded` | Free plugin fully loaded | Pro initialization |
| `wpss_pro_loaded` | Pro plugin fully loaded | Third-party extensions |
| `wpss_order_status_completed` | Order marked complete | Auto wallet payout |
| `wpss_wallet_credited` | Wallet credited | Notifications |
| `wpss_wallet_debited` | Wallet debited | Notifications |
| `wpss_commission_recorded` | Commission recorded | Wallet balance sync |

---

## Roadmap

### Completed

- [x] Core marketplace stabilization (Free)
- [x] WooCommerce moved from Free to Pro
- [x] Standalone checkout with Stripe, PayPal, Offline gateways in Free
- [x] WooCommerce Virtual Carrier Product integration (Pro)
- [x] Email templates for order lifecycle
- [x] 6 Pro feature modules (Tiered Commission, White Label, PayPal Payouts, Stripe Connect, Vendor Subscriptions, Recurring Services)
- [x] 10 REST API controllers (~55 endpoints)
- [x] 7 Pro database tables
- [x] CI/CD pipeline (GitHub Actions)
- [x] PHPStan level 5 with baseline
- [x] Pro extension hooks in Free plugin

### In Progress

- [ ] Service Wizard enhancement hooks (Pro limits removal)
- [ ] Complete EDD adapter testing
- [ ] Complete FluentCart adapter testing
- [ ] Complete SureCart adapter testing

### Planned

- [ ] AI-assisted descriptions (Pro)
- [ ] Bulk service management (Pro)
- [ ] Advanced SEO fields (Pro)
- [ ] Per-service analytics (Pro)
- [ ] Service bundles
- [ ] Promotional pricing

---

## Development Guidelines

### Adding New Features

1. **Check Scope First**
   - Is this Free or Pro?
   - Does it fit existing architecture?
   - Update this document if adding new scope

2. **Use Filter Pattern for Pro Features**
   ```php
   // In Free plugin
   $max_packages = apply_filters('wpss_service_max_packages', 3);

   // In Pro plugin
   add_filter('wpss_service_max_packages', function($limit) {
       return 999; // Unlimited
   });
   ```

3. **Follow Existing Patterns**
   - Use interfaces for providers
   - Use managers for complex logic
   - Use repositories for data access
   - Use SettingsRenderers for admin settings (they create their own repos internally)

4. **Test Integration**
   - Verify Pro works without changes to Free
   - Verify Free works without Pro
   - Test filter/action hooks

### File Naming

| Type | Location | Convention |
|------|----------|------------|
| Classes | `src/` | PascalCase (PSR-4) |
| Interfaces | `src/*/Contracts/` | `*Interface.php` |
| Templates | `templates/` | `kebab-case.php` |
| Assets | `assets/` | `kebab-case.{css,js}` |

### Coding Standards

- WordPress Coding Standards (WPCS)
- PHP 8.1+ features allowed
- Strict types enabled
- PSR-4 autoloading
- Text domain: `wp-sell-services-pro`
- Global prefix: `wpss_pro_` or `WPSellServicesPro`

---

## Version History

| Version | Date | Changes |
|---------|------|---------|
| 1.0.0 | 2024-01 | Initial scope document |
| 2.0.0 | 2026-04 | Major rewrite: WooCommerce moved to Pro, Stripe/PayPal moved to Free, added 6 Pro feature modules, 10 REST controllers, 7 Pro DB tables, CI/CD pipeline, developer onboarding |

---

**Last Updated**: 2026-04-01
