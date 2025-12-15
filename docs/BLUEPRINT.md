# WP Sell Services - Final Blueprint

## Executive Summary

**WP Sell Services** is a complete **Fiverr-style service marketplace platform** for WordPress that:
- Works **standalone** or integrates with any e-commerce platform
- Supports **direct payments** (Stripe, PayPal, Razorpay)
- Includes **wallet system** integration
- Has **full marketplace** with search, filters, vendor profiles
- Supports **buyer requests** and **custom quotes**
- Includes **dispute resolution** system
- Provides **REST API** for mobile apps
- Scales to **1000+ vendors**

---

## Business Model: Freemium (Free + Pro)

### Complete Feature Comparison

| Feature | FREE | PRO |
|---------|:----:|:---:|
| **E-COMMERCE INTEGRATIONS** | | |
| WooCommerce | ✅ | ✅ |
| Easy Digital Downloads | ❌ | ✅ |
| Fluent Cart | ❌ | ✅ |
| SureCart | ❌ | ✅ |
| Standalone Mode (no e-commerce) | ❌ | ✅ |
| | | |
| **PAYMENT GATEWAYS (Standalone)** | | |
| Stripe Direct Checkout | ❌ | ✅ |
| PayPal Direct Checkout | ❌ | ✅ |
| Razorpay | ❌ | ✅ |
| Custom Gateway Framework | ❌ | ✅ |
| | | |
| **WALLET SYSTEMS** | | |
| Built-in Internal Wallet | ❌ | ✅ |
| TeraWallet Integration | ❌ | ✅ |
| WooWallet Integration | ❌ | ✅ |
| MyCred Integration | ❌ | ✅ |
| | | |
| **SERVICE MANAGEMENT** | | |
| Create Services (CPT) | ✅ | ✅ |
| Service Categories & Tags | ✅ | ✅ |
| Service Packages (Basic/Standard/Premium) | ✅ | ✅ |
| Flexible Tiers (1-5 packages) | ✅ | ✅ |
| Service Add-ons (paid extras) | ✅ | ✅ |
| Custom Quotes | ✅ | ✅ |
| Service Gallery (images/videos) | ✅ | ✅ |
| Service FAQs | ✅ | ✅ |
| Requirement Form Builder | ✅ | ✅ |
| | | |
| **ORDER MANAGEMENT** | | |
| Order Creation & Tracking | ✅ | ✅ |
| Order Status Workflow | ✅ | ✅ |
| Requirement Submissions | ✅ | ✅ |
| Delivery System | ✅ | ✅ |
| Revision Requests | ✅ | ✅ |
| Deadline Extensions | ✅ | ✅ |
| Auto-complete Orders | ✅ | ✅ |
| Order Cancellation (mutual) | ✅ | ✅ |
| | | |
| **COMMUNICATION** | | |
| Order Messaging | ✅ | ✅ |
| Live Message Polling (5-10 sec) | ✅ | ✅ |
| File Attachments in Messages | ✅ | ✅ |
| In-app Notifications | ✅ | ✅ |
| Email Notifications | ✅ | ✅ |
| | | |
| **DISPUTES** | | |
| Dispute Creation | ✅ | ✅ |
| Evidence Submission | ✅ | ✅ |
| Admin Mediation Panel | ✅ | ✅ |
| Resolution Options (refund/complete/cancel) | ✅ | ✅ |
| | | |
| **MARKETPLACE** | | |
| Service Catalog/Archive | ✅ | ✅ |
| Search with Autocomplete | ✅ | ✅ |
| Category Filters | ✅ | ✅ |
| Price Range Filters | ✅ | ✅ |
| Delivery Time Filters | ✅ | ✅ |
| Rating Filters | ✅ | ✅ |
| | | |
| **BUYER REQUESTS** | | |
| Post Buyer Requests | ✅ | ✅ |
| Vendor Proposals | ✅ | ✅ |
| Request → Order Conversion | ✅ | ✅ |
| | | |
| **VENDOR FEATURES** | | |
| Vendor Registration | ✅ | ✅ |
| Vendor Profiles | ✅ | ✅ |
| Portfolio System | ✅ | ✅ |
| Vendor Reviews & Ratings | ✅ | ✅ |
| Verification Tiers (Basic/Verified/Pro) | ✅ | ✅ |
| Vacation Mode | ✅ | ✅ |
| | | |
| **VENDOR DASHBOARD** | | |
| Frontend Dashboard | ✅ | ✅ |
| Orders List & Management | ✅ | ✅ |
| Service Management | ✅ | ✅ |
| Basic Stats (orders, revenue) | ✅ | ✅ |
| Advanced Analytics (charts, trends) | ❌ | ✅ |
| Conversion Tracking | ❌ | ✅ |
| CSV/PDF Exports | ❌ | ✅ |
| | | |
| **CUSTOMER DASHBOARD** | | |
| My Orders | ✅ | ✅ |
| Order Details & Conversation | ✅ | ✅ |
| My Buyer Requests | ✅ | ✅ |
| Notifications | ✅ | ✅ |
| | | |
| **REVIEWS & RATINGS** | | |
| Customer → Vendor Reviews | ✅ | ✅ |
| Vendor → Customer Reviews | ✅ | ✅ |
| Sub-ratings (communication, quality, delivery) | ✅ | ✅ |
| | | |
| **FILE STORAGE** | | |
| WordPress Media Library | ✅ | ✅ |
| Protected Upload Folder | ✅ | ✅ |
| AWS S3 | ❌ | ✅ |
| Google Cloud Storage | ❌ | ✅ |
| DigitalOcean Spaces | ❌ | ✅ |
| | | |
| **EMAIL** | | |
| WordPress wp_mail | ✅ | ✅ |
| Custom Email Templates | ✅ | ✅ |
| SendGrid Integration | ❌ | ✅ |
| Mailgun Integration | ❌ | ✅ |
| Amazon SES Integration | ❌ | ✅ |
| | | |
| **SEO** | | |
| Meta Tags (title, description, OG) | ✅ | ✅ |
| Schema Markup (structured data) | ✅ | ✅ |
| Yoast SEO Integration | ✅ | ✅ |
| Rank Math Integration | ✅ | ✅ |
| | | |
| **DEVELOPER FEATURES** | | |
| Shortcodes | ✅ | ✅ |
| Gutenberg Blocks | ✅ | ✅ |
| Template Overrides | ✅ | ✅ |
| Action/Filter Hooks | ✅ | ✅ |
| REST API | ❌ | ✅ |
| Webhooks | ❌ | ✅ |
| | | |
| **MULTIVENDOR COMPATIBILITY** | | |
| Dokan Integration | ✅ | ✅ |
| WCFM Integration | ✅ | ✅ |
| WC Vendors Integration | ✅ | ✅ |
| | | |
| **SUPPORT** | | |
| WordPress.org Forums | ✅ | ✅ |
| Priority Email Support | ❌ | ✅ |
| | | |
| **UPDATES** | | |
| Security Updates | ✅ | ✅ |
| Feature Updates | ✅ | ✅ |
| Early Access to New Features | ❌ | ✅ |

### Quick Summary

**FREE = Full-featured marketplace with WooCommerce**
- Everything you need to run a service marketplace
- WooCommerce for checkout/payments
- All core features included
- No artificial limits

**PRO = More integrations + Advanced features**
- Alternative e-commerce platforms (EDD, Fluent Cart, SureCart)
- Standalone mode with direct payments (Stripe, PayPal)
- Wallet system support
- Cloud storage options
- Email service integrations
- Advanced analytics & reporting
- REST API for mobile apps

### Upgrade Path
- Free users can upgrade anytime
- All data preserved on upgrade
- Pro features activate immediately
- License-based activation (1 site / 5 sites / unlimited)

### Plugin Distribution Strategy: Free Repo + Pro Download

```
WordPress.org (Free)           Your Website (Pro)
─────────────────────         ─────────────────────
wp-sell-services/             wp-sell-services-pro/
├── Free core features        ├── Extends free version
├── WooCommerce only          ├── All integrations
├── Basic dashboard           ├── Advanced analytics
└── Auto-updates via WP       └── License-based updates
```

**Free Version (WordPress.org)**
- Full-featured free plugin
- WooCommerce integration
- All core marketplace features
- Auto-updates via WordPress

**Pro Version (Your Website)**
- Separate plugin that extends free
- Requires free version installed
- Licensed via EDD/WooCommerce on your site
- Updates via license server (EDD SL / WooCommerce SL)

**Benefits:**
- Full control over Pro distribution
- No WordPress.org review for Pro features
- Can use any licensing system (EDD, Freemius, WooCommerce)
- Pro can update independently of free version

---

## Technical Specifications

| Specification | Decision |
|---------------|----------|
| **PHP Version** | 8.1+ |
| **WordPress Version** | 6.4+ |
| **Code Style** | Modern OOP, PSR-4 autoloading, Namespaces |
| **JavaScript** | Alpine.js (lightweight reactivity) |
| **CSS** | Tailwind CSS (utility-first) |
| **Licensing** | EDD Software Licensing |
| **Blocks** | Gutenberg blocks + Shortcodes |

### Namespace Structure
```php
// FREE PLUGIN (wp-sell-services)
namespace WPSellServices;                      // Core
namespace WPSellServices\Core;                 // Core classes
namespace WPSellServices\Models;               // Data models
namespace WPSellServices\Services;             // Business logic
namespace WPSellServices\Integrations\WooCommerce;  // WC adapter
namespace WPSellServices\API;                  // REST API
namespace WPSellServices\Admin;                // Admin
namespace WPSellServices\Frontend;             // Frontend

// PRO PLUGIN (wp-sell-services-pro)
namespace WPSellServicesPro;                   // Pro core
namespace WPSellServicesPro\Integrations\EDD; // EDD adapter
namespace WPSellServicesPro\Integrations\Stripe;
namespace WPSellServicesPro\Integrations\PayPal;
namespace WPSellServicesPro\Analytics;         // Pro analytics
```

### Two Plugin Structure

```
wp-content/plugins/
├── wp-sell-services/                    # FREE (WordPress.org)
│   ├── wp-sell-services.php
│   ├── composer.json
│   ├── package.json
│   ├── src/
│   │   ├── Core/
│   │   ├── Models/
│   │   ├── Services/
│   │   ├── Integrations/
│   │   │   └── WooCommerce/            # Only WooCommerce in free
│   │   ├── Admin/
│   │   ├── Frontend/
│   │   ├── API/
│   │   └── Blocks/
│   ├── assets/
│   ├── templates/
│   └── languages/
│
└── wp-sell-services-pro/                # PRO (Your website)
    ├── wp-sell-services-pro.php
    ├── composer.json
    ├── src/
    │   ├── Pro.php                      # Main pro loader
    │   ├── License/                     # EDD licensing
    │   ├── Integrations/
    │   │   ├── EDD/
    │   │   ├── FluentCart/
    │   │   ├── SureCart/
    │   │   ├── Standalone/
    │   │   ├── Stripe/
    │   │   ├── PayPal/
    │   │   ├── Razorpay/
    │   │   └── Wallets/
    │   ├── Analytics/                   # Advanced analytics
    │   ├── Storage/                     # Cloud storage
    │   └── Email/                       # Email services
    ├── assets/
    └── templates/
```

### How Pro Extends Free

```php
// In wp-sell-services-pro.php
add_action('wpss_loaded', function() {
    // Check license
    if (!WPSellServicesPro\License\Manager::is_valid()) {
        return;
    }

    // Register pro integrations
    add_filter('wpss_ecommerce_adapters', [ProIntegrations::class, 'register']);
    add_filter('wpss_payment_gateways', [ProPayments::class, 'register']);
    add_filter('wpss_storage_providers', [ProStorage::class, 'register']);

    // Extend analytics
    add_filter('wpss_analytics_widgets', [ProAnalytics::class, 'add_widgets']);
});
```

### Build Tools
```
wp-sell-services/
├── composer.json          # PHP autoloading
├── package.json           # npm for Tailwind/Alpine
├── tailwind.config.js     # Tailwind configuration
├── postcss.config.js      # PostCSS for Tailwind
├── webpack.config.js      # Asset bundling
└── phpcs.xml              # WordPress coding standards

wp-sell-services-pro/
├── composer.json          # Pro autoloading
├── package.json           # Pro assets (if any)
└── phpcs.xml
```

---

## Complete Requirements Summary

### Core Architecture
| Requirement | Decision |
|-------------|----------|
| Services Data Model | **Separate CPT** (`ss_service`) mapped to e-commerce products |
| Plugin Strategy | **New plugin** with migration from woo-sell-services |
| Multisite | Single site only |
| Scale Target | Medium (100-1000 vendors) |

### E-Commerce Integrations
| Platform | Priority |
|----------|----------|
| WooCommerce | P1 - First |
| Easy Digital Downloads | P2 |
| Fluent Cart | P2 |
| SureCart | P2 |
| Standalone (no e-commerce) | P1 - First |

### Payment Gateways (Standalone Mode)
| Gateway | Priority |
|---------|----------|
| Stripe | P1 |
| PayPal | P1 |
| Razorpay | P2 |
| Custom Gateway Framework | P3 |

### Wallet Systems
| Wallet | Priority |
|--------|----------|
| Built-in Wallet | P2 |
| TeraWallet | P2 |
| WooWallet | P3 |
| MyCred | P3 |

### Service Features
| Feature | Implementation |
|---------|----------------|
| Service Packages | 3 tiers (Basic/Standard/Premium) + flexible 1-5 tiers |
| Add-ons | Paid extras on any package |
| Custom Quotes | Vendors can send custom proposals |
| Revisions | Configurable per service (included + paid extra) |
| Delivery Time | Fixed deadline with extension requests |
| Subscriptions | Recurring services (monthly retainers) |

### Marketplace Features
| Feature | Implementation |
|---------|----------------|
| Service Listing | Full catalog with grid/list view |
| Search | Full-text search with autocomplete |
| Filters | Category, price range, delivery time, rating, location |
| Categories | Hierarchical categories with icons |
| Vendor Profiles | Full profile page with portfolio, reviews, stats |
| Service Gallery | Images + videos per service |
| Vendor Portfolio | Central portfolio page |
| FAQs | Per-service FAQ section |
| Buyer Requests | Buyers post requests, vendors send proposals |

### Vendor System
| Feature | Implementation |
|---------|----------------|
| Registration | Open with verification tiers |
| Verification Tiers | Basic → Verified → Pro (badges, benefits) |
| Dashboard | **Frontend dashboard** (not WP admin) |
| Analytics | Full dashboard with charts, trends, conversion rates |
| Commission | Handled by multivendor plugins (not our scope) |
| Payout | Auto after order completion |

### Order & Delivery
| Feature | Implementation |
|---------|----------------|
| Order Status | Pending → Requirements → In Progress → Delivery → Review → Complete |
| Revisions | Vendor sets limit, customer can request within limit |
| Delivery | Final delivery with files, customer accepts/rejects |
| Disputes | Full dispute system with evidence, admin mediation, escalation |
| Auto-Complete | Configurable auto-complete after X days if no response |

### Communication
| Feature | Implementation |
|---------|----------------|
| Messaging | Async with **live polling (5-10 sec)** |
| Notifications | In-app + email notifications |
| Email Templates | Custom templates, configurable |
| Email Service | wp_mail default + SendGrid/Mailgun/SES optional |

### File Storage
| Option | Support |
|--------|---------|
| WordPress Media Library | Yes (default) |
| Custom Protected Folder | Yes |
| AWS S3 | Yes |
| Google Cloud Storage | Yes |
| DigitalOcean Spaces | Yes |

### Technical
| Feature | Implementation |
|---------|----------------|
| REST API | Full API for all operations |
| SEO | Meta tags + Schema markup + Yoast/RankMath integration |
| Currency | Single currency |
| Caching | Compatible with major caching plugins |

---

## Part 1: Complete Database Schema

### Custom Post Types

```php
// Services CPT
register_post_type('ss_service', [
    'public' => true,
    'has_archive' => true,
    'rewrite' => ['slug' => 'services'],
    'supports' => ['title', 'editor', 'thumbnail', 'excerpt', 'author'],
    'taxonomies' => ['ss_service_category', 'ss_service_tag'],
]);

// Service Orders CPT (for standalone mode)
register_post_type('ss_order', [
    'public' => false,
    'supports' => ['title'],
]);

// Buyer Requests CPT
register_post_type('ss_buyer_request', [
    'public' => true,
    'has_archive' => true,
    'rewrite' => ['slug' => 'requests'],
]);

// Proposals CPT
register_post_type('ss_proposal', [
    'public' => false,
]);

// Disputes CPT
register_post_type('ss_dispute', [
    'public' => false,
]);
```

### Taxonomies

```php
// Service Categories (hierarchical)
register_taxonomy('ss_service_category', 'ss_service', [
    'hierarchical' => true,
    'rewrite' => ['slug' => 'service-category'],
]);

// Service Tags
register_taxonomy('ss_service_tag', 'ss_service', [
    'hierarchical' => false,
    'rewrite' => ['slug' => 'service-tag'],
]);

// Buyer Request Categories
register_taxonomy('ss_request_category', 'ss_buyer_request', [
    'hierarchical' => true,
]);
```

### Database Tables

```sql
-- 1. Service Packages (tiers + pricing)
CREATE TABLE {prefix}ss_service_packages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    service_id BIGINT UNSIGNED NOT NULL,         -- Post ID of ss_service

    name VARCHAR(100) NOT NULL,                   -- "Basic", "Standard", "Premium"
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    delivery_days INT NOT NULL,
    revisions INT DEFAULT 0,                      -- -1 = unlimited

    features JSON,                                -- Array of included features
    sort_order INT DEFAULT 0,

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_service (service_id)
);

-- 2. Service Add-ons (paid extras)
CREATE TABLE {prefix}ss_service_addons (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    service_id BIGINT UNSIGNED NOT NULL,

    title VARCHAR(255) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    delivery_days_extra INT DEFAULT 0,            -- Additional days

    is_active TINYINT(1) DEFAULT 1,
    sort_order INT DEFAULT 0,

    INDEX idx_service (service_id)
);

-- 3. Service FAQs
CREATE TABLE {prefix}ss_service_faqs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    service_id BIGINT UNSIGNED NOT NULL,

    question VARCHAR(500) NOT NULL,
    answer TEXT NOT NULL,
    sort_order INT DEFAULT 0,

    INDEX idx_service (service_id)
);

-- 4. Service Requirements (form fields)
CREATE TABLE {prefix}ss_service_requirements (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    service_id BIGINT UNSIGNED NOT NULL,

    field_type VARCHAR(50) NOT NULL,              -- text, textarea, select, file, etc.
    label VARCHAR(255) NOT NULL,
    description TEXT,
    options JSON,                                 -- For select/radio/checkbox
    is_required TINYINT(1) DEFAULT 0,
    sort_order INT DEFAULT 0,

    INDEX idx_service (service_id)
);

-- 5. Orders (platform-agnostic)
CREATE TABLE {prefix}ss_orders (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_number VARCHAR(50) UNIQUE NOT NULL,

    -- Participants
    customer_id BIGINT UNSIGNED NOT NULL,
    vendor_id BIGINT UNSIGNED NOT NULL,

    -- Service Info
    service_id BIGINT UNSIGNED NOT NULL,          -- ss_service post ID
    package_id BIGINT UNSIGNED,                   -- Selected package
    addons JSON,                                  -- Selected add-ons array

    -- Platform Integration
    platform VARCHAR(50) DEFAULT 'standalone',    -- woocommerce, edd, standalone
    platform_order_id BIGINT UNSIGNED,
    platform_item_id BIGINT UNSIGNED,

    -- Pricing
    subtotal DECIMAL(10,2) NOT NULL,
    addons_total DECIMAL(10,2) DEFAULT 0,
    total DECIMAL(10,2) NOT NULL,
    currency VARCHAR(10) DEFAULT 'USD',

    -- Status
    status VARCHAR(50) DEFAULT 'pending_payment',
    delivery_deadline DATETIME,
    original_deadline DATETIME,

    -- Payment
    payment_method VARCHAR(50),
    payment_status VARCHAR(50) DEFAULT 'pending',
    transaction_id VARCHAR(255),
    paid_at DATETIME,

    -- Revisions
    revisions_included INT DEFAULT 0,
    revisions_used INT DEFAULT 0,

    -- Timestamps
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    started_at DATETIME,                          -- When work started
    completed_at DATETIME,

    INDEX idx_customer (customer_id),
    INDEX idx_vendor (vendor_id),
    INDEX idx_status (status),
    INDEX idx_platform (platform, platform_order_id),
    INDEX idx_deadline (delivery_deadline)
);

-- 6. Order Requirements (customer submissions)
CREATE TABLE {prefix}ss_order_requirements (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED NOT NULL,

    field_data JSON NOT NULL,                     -- Structured requirement answers
    attachments JSON,
    submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (order_id) REFERENCES {prefix}ss_orders(id) ON DELETE CASCADE
);

-- 7. Conversations (messages)
CREATE TABLE {prefix}ss_conversations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED NOT NULL,

    sender_id BIGINT UNSIGNED NOT NULL,
    recipient_id BIGINT UNSIGNED NOT NULL,

    message LONGTEXT NOT NULL,
    message_type ENUM('text', 'delivery', 'revision_request', 'extension_request', 'system') DEFAULT 'text',

    attachments JSON,
    is_read TINYINT(1) DEFAULT 0,
    read_at DATETIME,

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_order (order_id),
    INDEX idx_sender (sender_id),
    INDEX idx_unread (recipient_id, is_read),

    FOREIGN KEY (order_id) REFERENCES {prefix}ss_orders(id) ON DELETE CASCADE
);

-- 8. Deliveries
CREATE TABLE {prefix}ss_deliveries (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED NOT NULL,
    vendor_id BIGINT UNSIGNED NOT NULL,

    message TEXT,
    attachments JSON,
    version INT DEFAULT 1,                        -- Revision number

    status ENUM('pending', 'accepted', 'rejected', 'revision_requested') DEFAULT 'pending',
    response_message TEXT,
    responded_at DATETIME,

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_order (order_id),

    FOREIGN KEY (order_id) REFERENCES {prefix}ss_orders(id) ON DELETE CASCADE
);

-- 9. Extension Requests
CREATE TABLE {prefix}ss_extension_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED NOT NULL,

    requested_by BIGINT UNSIGNED NOT NULL,        -- Usually vendor
    extra_days INT NOT NULL,
    reason TEXT NOT NULL,

    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    responded_by BIGINT UNSIGNED,
    response_message TEXT,
    responded_at DATETIME,

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_order (order_id),

    FOREIGN KEY (order_id) REFERENCES {prefix}ss_orders(id) ON DELETE CASCADE
);

-- 10. Reviews
CREATE TABLE {prefix}ss_reviews (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED NOT NULL,

    reviewer_id BIGINT UNSIGNED NOT NULL,
    reviewee_id BIGINT UNSIGNED NOT NULL,
    service_id BIGINT UNSIGNED NOT NULL,

    rating TINYINT UNSIGNED NOT NULL,             -- 1-5
    review TEXT,
    review_type ENUM('customer_to_vendor', 'vendor_to_customer'),

    -- Sub-ratings (optional)
    communication_rating TINYINT UNSIGNED,
    quality_rating TINYINT UNSIGNED,
    delivery_rating TINYINT UNSIGNED,

    is_public TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_order (order_id),
    INDEX idx_reviewee (reviewee_id),
    INDEX idx_service (service_id),

    FOREIGN KEY (order_id) REFERENCES {prefix}ss_orders(id) ON DELETE CASCADE
);

-- 11. Disputes
CREATE TABLE {prefix}ss_disputes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED NOT NULL,

    initiated_by BIGINT UNSIGNED NOT NULL,
    reason VARCHAR(100) NOT NULL,                 -- late_delivery, quality_issue, etc.
    description TEXT NOT NULL,
    evidence JSON,                                -- Attachments/screenshots

    status ENUM('open', 'under_review', 'resolved', 'escalated', 'closed') DEFAULT 'open',
    resolution ENUM('refund_full', 'refund_partial', 'complete_order', 'cancelled', 'dismissed'),
    resolution_notes TEXT,
    resolved_by BIGINT UNSIGNED,
    resolved_at DATETIME,

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_order (order_id),
    INDEX idx_status (status),

    FOREIGN KEY (order_id) REFERENCES {prefix}ss_orders(id) ON DELETE CASCADE
);

-- 12. Dispute Messages (evidence/conversation)
CREATE TABLE {prefix}ss_dispute_messages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    dispute_id BIGINT UNSIGNED NOT NULL,

    sender_id BIGINT UNSIGNED NOT NULL,
    sender_role ENUM('customer', 'vendor', 'admin'),
    message TEXT NOT NULL,
    attachments JSON,

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_dispute (dispute_id),

    FOREIGN KEY (dispute_id) REFERENCES {prefix}ss_disputes(id) ON DELETE CASCADE
);

-- 13. Buyer Requests
CREATE TABLE {prefix}ss_buyer_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    post_id BIGINT UNSIGNED NOT NULL,             -- ss_buyer_request CPT

    buyer_id BIGINT UNSIGNED NOT NULL,
    category_id BIGINT UNSIGNED,

    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    budget_min DECIMAL(10,2),
    budget_max DECIMAL(10,2),
    delivery_days INT,
    attachments JSON,

    status ENUM('open', 'in_progress', 'completed', 'cancelled', 'expired') DEFAULT 'open',
    expires_at DATETIME,

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_buyer (buyer_id),
    INDEX idx_status (status),
    INDEX idx_category (category_id)
);

-- 14. Proposals (vendor bids on buyer requests)
CREATE TABLE {prefix}ss_proposals (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_id BIGINT UNSIGNED NOT NULL,

    vendor_id BIGINT UNSIGNED NOT NULL,
    service_id BIGINT UNSIGNED,                   -- Optional: link to existing service

    cover_letter TEXT NOT NULL,
    proposed_price DECIMAL(10,2) NOT NULL,
    proposed_days INT NOT NULL,
    attachments JSON,

    status ENUM('pending', 'accepted', 'rejected', 'withdrawn') DEFAULT 'pending',

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_request (request_id),
    INDEX idx_vendor (vendor_id),

    FOREIGN KEY (request_id) REFERENCES {prefix}ss_buyer_requests(id) ON DELETE CASCADE
);

-- 15. Vendor Profiles (extended user meta)
CREATE TABLE {prefix}ss_vendor_profiles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL UNIQUE,

    display_name VARCHAR(255),
    tagline VARCHAR(255),
    bio TEXT,
    avatar_id BIGINT UNSIGNED,                    -- Attachment ID
    cover_image_id BIGINT UNSIGNED,

    -- Verification
    verification_tier ENUM('basic', 'verified', 'pro') DEFAULT 'basic',
    verified_at DATETIME,

    -- Location
    country VARCHAR(100),
    city VARCHAR(100),
    timezone VARCHAR(100),

    -- Social/Contact
    website VARCHAR(255),
    social_links JSON,

    -- Stats (cached for performance)
    total_orders INT DEFAULT 0,
    completed_orders INT DEFAULT 0,
    total_earnings DECIMAL(12,2) DEFAULT 0,
    avg_rating DECIMAL(3,2) DEFAULT 0,
    total_reviews INT DEFAULT 0,
    response_time_hours INT,                      -- Average response time
    on_time_delivery_rate DECIMAL(5,2),           -- Percentage

    -- Settings
    is_available TINYINT(1) DEFAULT 1,
    vacation_mode TINYINT(1) DEFAULT 0,
    vacation_message TEXT,

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_user (user_id),
    INDEX idx_tier (verification_tier),
    INDEX idx_rating (avg_rating)
);

-- 16. Portfolio Items
CREATE TABLE {prefix}ss_portfolio_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    vendor_id BIGINT UNSIGNED NOT NULL,
    service_id BIGINT UNSIGNED,                   -- Optional: link to service

    title VARCHAR(255) NOT NULL,
    description TEXT,
    media JSON,                                   -- Array of image/video IDs
    external_url VARCHAR(255),
    tags JSON,

    is_featured TINYINT(1) DEFAULT 0,
    sort_order INT DEFAULT 0,

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_vendor (vendor_id),
    INDEX idx_service (service_id)
);

-- 17. Notifications
CREATE TABLE {prefix}ss_notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,

    type VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT,
    data JSON,                                    -- Context data (order_id, etc.)
    action_url VARCHAR(255),

    is_read TINYINT(1) DEFAULT 0,
    read_at DATETIME,
    is_email_sent TINYINT(1) DEFAULT 0,

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_user_unread (user_id, is_read),
    INDEX idx_type (type)
);

-- 18. Wallet Transactions (internal wallet)
CREATE TABLE {prefix}ss_wallet_transactions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,

    type ENUM('credit', 'debit', 'refund', 'withdrawal', 'deposit', 'fee'),
    amount DECIMAL(10,2) NOT NULL,
    balance_after DECIMAL(10,2) NOT NULL,
    currency VARCHAR(10) DEFAULT 'USD',

    description TEXT,
    reference_type VARCHAR(50),                   -- order, withdrawal, deposit
    reference_id BIGINT UNSIGNED,

    status ENUM('pending', 'completed', 'failed', 'cancelled') DEFAULT 'completed',

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_user (user_id),
    INDEX idx_type (type),
    INDEX idx_reference (reference_type, reference_id)
);

-- 19. Service Platform Mapping
CREATE TABLE {prefix}ss_service_platform_map (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    service_id BIGINT UNSIGNED NOT NULL,          -- ss_service post ID

    platform VARCHAR(50) NOT NULL,                -- woocommerce, edd, etc.
    platform_product_id BIGINT UNSIGNED NOT NULL,

    sync_status ENUM('synced', 'pending', 'error') DEFAULT 'synced',
    last_synced_at DATETIME,

    UNIQUE KEY unique_mapping (service_id, platform),
    INDEX idx_platform (platform, platform_product_id)
);

-- 20. Analytics Events (for dashboard)
CREATE TABLE {prefix}ss_analytics_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    event_type VARCHAR(50) NOT NULL,              -- view, click, order, etc.
    entity_type VARCHAR(50) NOT NULL,             -- service, vendor, category
    entity_id BIGINT UNSIGNED NOT NULL,

    user_id BIGINT UNSIGNED,
    session_id VARCHAR(100),
    ip_hash VARCHAR(64),                          -- Hashed for privacy

    referrer VARCHAR(255),
    user_agent VARCHAR(255),

    event_data JSON,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_event (event_type),
    INDEX idx_date (created_at)
);
```

---

## Part 2: Complete Plugin Structure

```
wp-sell-services/
├── wp-sell-services.php                         # Main plugin file
├── class-pro-loader.php                         # Pro features loader (license check)
├── uninstall.php
├── index.php
│
├── core/                                        # Core Framework
│   ├── class-sell-services.php
│   ├── class-sell-services-loader.php
│   ├── class-sell-services-activator.php
│   ├── class-sell-services-deactivator.php
│   ├── class-sell-services-i18n.php
│   │
│   ├── Contracts/                               # Interfaces
│   │   ├── interface-ecommerce-adapter.php
│   │   ├── interface-payment-adapter.php
│   │   ├── interface-wallet-adapter.php
│   │   ├── interface-storage-adapter.php
│   │   ├── interface-email-adapter.php
│   │   ├── interface-order-provider.php
│   │   ├── interface-product-provider.php
│   │   └── interface-checkout-provider.php
│   │
│   ├── Models/                                  # Data Models
│   │   ├── class-service.php
│   │   ├── class-service-package.php
│   │   ├── class-service-addon.php
│   │   ├── class-order.php
│   │   ├── class-delivery.php
│   │   ├── class-conversation.php
│   │   ├── class-message.php
│   │   ├── class-review.php
│   │   ├── class-dispute.php
│   │   ├── class-buyer-request.php
│   │   ├── class-proposal.php
│   │   ├── class-vendor-profile.php
│   │   └── class-notification.php
│   │
│   ├── Services/                                # Business Logic
│   │   ├── class-service-manager.php
│   │   ├── class-order-service.php
│   │   ├── class-conversation-service.php
│   │   ├── class-delivery-service.php
│   │   ├── class-revision-service.php
│   │   ├── class-dispute-service.php
│   │   ├── class-review-service.php
│   │   ├── class-notification-service.php
│   │   ├── class-vendor-service.php
│   │   ├── class-buyer-request-service.php
│   │   ├── class-proposal-service.php
│   │   ├── class-analytics-service.php
│   │   └── class-search-service.php
│   │
│   ├── Database/                                # Database Layer
│   │   ├── class-schema-manager.php
│   │   ├── class-migration-manager.php
│   │   ├── Repositories/
│   │   │   ├── class-service-repository.php
│   │   │   ├── class-order-repository.php
│   │   │   ├── class-conversation-repository.php
│   │   │   ├── class-delivery-repository.php
│   │   │   ├── class-review-repository.php
│   │   │   ├── class-dispute-repository.php
│   │   │   ├── class-notification-repository.php
│   │   │   └── class-analytics-repository.php
│   │   └── migrations/
│   │       ├── migration-1.0.0.php
│   │       └── migration-from-wss.php
│   │
│   ├── CustomFields/                            # Requirement Form System
│   │   ├── class-field-manager.php
│   │   ├── class-field-renderer.php
│   │   ├── class-field-validator.php
│   │   └── Fields/
│   │       ├── class-text-field.php
│   │       ├── class-textarea-field.php
│   │       ├── class-wysiwyg-field.php
│   │       ├── class-select-field.php
│   │       ├── class-multiselect-field.php
│   │       ├── class-radio-field.php
│   │       ├── class-checkbox-field.php
│   │       ├── class-upload-field.php
│   │       ├── class-date-field.php
│   │       └── class-number-field.php
│   │
│   ├── PostTypes/                               # CPT Registration
│   │   ├── class-service-post-type.php
│   │   ├── class-order-post-type.php
│   │   ├── class-buyer-request-post-type.php
│   │   └── class-proposal-post-type.php
│   │
│   ├── Taxonomies/                              # Taxonomy Registration
│   │   ├── class-service-category.php
│   │   └── class-service-tag.php
│   │
│   └── Traits/
│       ├── trait-singleton.php
│       ├── trait-hooks.php
│       └── trait-ajax-handler.php
│
├── integrations/                                # All Integrations
│   │
│   ├── ecommerce/                               # E-Commerce Adapters
│   │   ├── class-ecommerce-manager.php
│   │   │
│   │   ├── woocommerce/
│   │   │   ├── class-woocommerce-adapter.php
│   │   │   ├── class-wc-product-sync.php
│   │   │   ├── class-wc-order-handler.php
│   │   │   ├── class-wc-checkout-handler.php
│   │   │   ├── class-wc-account-endpoints.php
│   │   │   ├── class-wc-hpos-compat.php
│   │   │   └── emails/
│   │   │
│   │   ├── edd/
│   │   │   ├── class-edd-adapter.php
│   │   │   ├── class-edd-download-sync.php
│   │   │   └── class-edd-order-handler.php
│   │   │
│   │   ├── fluent-cart/
│   │   │   └── class-fluentcart-adapter.php
│   │   │
│   │   ├── surecart/
│   │   │   └── class-surecart-adapter.php
│   │   │
│   │   └── standalone/
│   │       ├── class-standalone-adapter.php
│   │       ├── class-standalone-checkout.php
│   │       └── class-standalone-account.php
│   │
│   ├── payments/                                # Payment Gateways
│   │   ├── class-payment-manager.php
│   │   │
│   │   ├── stripe/
│   │   │   ├── class-stripe-adapter.php
│   │   │   ├── class-stripe-checkout.php
│   │   │   ├── class-stripe-webhook.php
│   │   │   └── class-stripe-subscription.php
│   │   │
│   │   ├── paypal/
│   │   │   ├── class-paypal-adapter.php
│   │   │   ├── class-paypal-checkout.php
│   │   │   └── class-paypal-webhook.php
│   │   │
│   │   ├── razorpay/
│   │   │   └── class-razorpay-adapter.php
│   │   │
│   │   └── abstract-payment-gateway.php
│   │
│   ├── wallets/                                 # Wallet Systems
│   │   ├── class-wallet-manager.php
│   │   ├── internal/
│   │   │   ├── class-internal-wallet.php
│   │   │   └── class-wallet-transactions.php
│   │   ├── terawallet/
│   │   ├── woowallet/
│   │   └── mycred/
│   │
│   ├── storage/                                 # File Storage
│   │   ├── class-storage-manager.php
│   │   ├── class-local-storage.php
│   │   ├── class-protected-storage.php
│   │   ├── class-s3-storage.php
│   │   └── class-gcs-storage.php
│   │
│   ├── email/                                   # Email Services
│   │   ├── class-email-manager.php
│   │   ├── class-wp-mail-provider.php
│   │   ├── class-sendgrid-provider.php
│   │   └── class-mailgun-provider.php
│   │
│   ├── multivendor/                             # Multivendor Platforms
│   │   ├── dokan/
│   │   ├── wcfm/
│   │   └── wc-vendors/
│   │
│   └── seo/                                     # SEO Integrations
│       ├── class-seo-manager.php
│       ├── class-schema-generator.php
│       ├── class-yoast-integration.php
│       └── class-rankmath-integration.php
│
├── api/                                         # REST API
│   ├── class-rest-api.php
│   ├── endpoints/
│   │   ├── class-services-endpoint.php
│   │   ├── class-orders-endpoint.php
│   │   ├── class-conversations-endpoint.php
│   │   ├── class-deliveries-endpoint.php
│   │   ├── class-reviews-endpoint.php
│   │   ├── class-disputes-endpoint.php
│   │   ├── class-buyer-requests-endpoint.php
│   │   ├── class-proposals-endpoint.php
│   │   ├── class-vendors-endpoint.php
│   │   └── class-notifications-endpoint.php
│   └── class-api-authentication.php
│
├── admin/                                       # Admin Interface
│   ├── class-sell-services-admin.php
│   ├── class-admin-menu.php
│   ├── class-admin-ajax.php
│   ├── class-admin-notices.php
│   ├── metaboxes/
│   │   ├── class-service-metabox.php
│   │   ├── class-packages-metabox.php
│   │   ├── class-requirements-metabox.php
│   │   └── class-order-metabox.php
│   ├── partials/
│   │   ├── dashboard.php
│   │   ├── settings-general.php
│   │   ├── settings-integrations.php
│   │   ├── settings-payments.php
│   │   ├── settings-wallets.php
│   │   ├── settings-storage.php
│   │   ├── settings-emails.php
│   │   ├── settings-notifications.php
│   │   ├── settings-seo.php
│   │   ├── settings-vendors.php
│   │   ├── settings-disputes.php
│   │   ├── orders-list.php
│   │   ├── disputes-list.php
│   │   ├── vendors-list.php
│   │   └── migration.php
│   └── assets/
│
├── public/                                      # Frontend
│   ├── class-sell-services-public.php
│   ├── class-shortcodes.php
│   ├── class-ajax-handlers.php
│   ├── class-template-loader.php
│   │
│   ├── templates/
│   │   ├── archive-service.php                  # Service catalog
│   │   ├── single-service.php                   # Single service page
│   │   ├── taxonomy-service-category.php
│   │   │
│   │   ├── service/
│   │   │   ├── content-service.php
│   │   │   ├── packages.php
│   │   │   ├── addons.php
│   │   │   ├── faqs.php
│   │   │   ├── reviews.php
│   │   │   ├── vendor-card.php
│   │   │   └── gallery.php
│   │   │
│   │   ├── checkout/
│   │   │   ├── checkout.php
│   │   │   ├── payment-methods.php
│   │   │   └── confirmation.php
│   │   │
│   │   ├── order/
│   │   │   ├── order-details.php
│   │   │   ├── conversation.php
│   │   │   ├── requirements-form.php
│   │   │   ├── delivery.php
│   │   │   ├── review-form.php
│   │   │   └── dispute.php
│   │   │
│   │   ├── buyer-requests/
│   │   │   ├── archive-request.php
│   │   │   ├── single-request.php
│   │   │   ├── submit-request.php
│   │   │   └── proposals-list.php
│   │   │
│   │   ├── vendor/
│   │   │   ├── profile.php
│   │   │   ├── portfolio.php
│   │   │   └── reviews.php
│   │   │
│   │   ├── dashboard/                           # Frontend Vendor Dashboard
│   │   │   ├── dashboard.php
│   │   │   ├── orders.php
│   │   │   ├── order-detail.php
│   │   │   ├── services.php
│   │   │   ├── service-edit.php
│   │   │   ├── earnings.php
│   │   │   ├── analytics.php
│   │   │   ├── reviews.php
│   │   │   ├── buyer-requests.php
│   │   │   ├── proposals.php
│   │   │   ├── profile-edit.php
│   │   │   ├── portfolio.php
│   │   │   └── settings.php
│   │   │
│   │   ├── account/                             # Customer Dashboard
│   │   │   ├── orders.php
│   │   │   ├── order-detail.php
│   │   │   ├── buyer-requests.php
│   │   │   └── notifications.php
│   │   │
│   │   └── emails/
│   │       ├── base.php
│   │       ├── new-order.php
│   │       ├── requirements-submitted.php
│   │       ├── new-message.php
│   │       ├── delivery-ready.php
│   │       ├── order-completed.php
│   │       ├── dispute-opened.php
│   │       ├── new-buyer-request.php
│   │       └── new-proposal.php
│   │
│   └── assets/
│       ├── css/
│       │   ├── sell-services.css
│       │   ├── dashboard.css
│       │   ├── checkout.css
│       │   └── rtl/
│       ├── js/
│       │   ├── sell-services.js
│       │   ├── conversation.js
│       │   ├── checkout.js
│       │   ├── dashboard.js
│       │   └── service-editor.js
│       └── images/
│
├── includes/                                    # Utilities
│   ├── class-helpers.php
│   ├── class-capabilities.php
│   ├── class-cron.php
│   └── functions.php
│
├── languages/
│   └── sell-services.pot
│
├── assets/
│   ├── images/
│   └── fonts/
│
└── vendor/                                      # Composer (if needed)
```

---

## Part 3: Order Status Workflow

```
┌─────────────────────────────────────────────────────────────────────────┐
│                         ORDER STATUS WORKFLOW                            │
└─────────────────────────────────────────────────────────────────────────┘

[PAYMENT]
    │
    ▼
┌───────────────────┐
│  pending_payment  │◄─── Order created, awaiting payment
└───────────────────┘
    │
    │ Payment successful
    ▼
┌───────────────────┐
│ waiting_requirements │◄─── Customer needs to submit requirements
└───────────────────┘
    │
    │ Requirements submitted
    ▼
┌───────────────────┐
│   in_progress     │◄─── Vendor working on order
└───────────────────┘     (Deadline timer starts)
    │
    │ Vendor submits delivery
    ▼
┌───────────────────┐
│ pending_approval  │◄─── Customer reviews delivery
└───────────────────┘
    │
    ├───── Customer accepts ─────────────────────────────┐
    │                                                     │
    │                                                     ▼
    │                                        ┌───────────────────┐
    │                                        │ waiting_review    │
    │                                        └───────────────────┘
    │                                                     │
    │                                                     │ Customer leaves review
    │                                                     ▼
    │                                        ┌───────────────────┐
    │                                        │    completed      │
    │                                        └───────────────────┘
    │
    │ Customer requests revision (if available)
    ▼
┌───────────────────┐
│ revision_requested│──────► Back to in_progress
└───────────────────┘

[SPECIAL STATUSES]

┌───────────────────┐
│    on_hold        │◄─── Extension requested / Issue reported
└───────────────────┘

┌───────────────────┐
│   in_dispute      │◄─── Dispute opened
└───────────────────┘

┌───────────────────┐
│    cancelled      │◄─── Order cancelled / Refunded
└───────────────────┘

┌───────────────────┐
│     late          │◄─── Past deadline (auto-flagged)
└───────────────────┘
```

---

## Part 4: Implementation Phases

### Phase 1: Core Foundation ✅ COMPLETE
- [x] Plugin bootstrap and structure
- [x] All interfaces defined (6 contracts)
- [x] Database schema and migrations
- [x] CPT and taxonomies registration
- [x] Core models and repositories
- [x] Field system for requirements (10 field types)
- [x] Basic admin settings

### Phase 2: Service Management ✅ COMPLETE
- [x] Service CRUD operations
- [x] Packages and add-ons
- [x] Service gallery (GalleryService)
- [x] FAQs system (FAQService)
- [x] Requirements builder
- [x] Service categories

### Phase 3: Order System (In Progress)
- [x] Order creation and management
- [ ] Status workflow engine
- [ ] Requirements submission flow
- [x] Conversation/messaging system
- [x] Delivery system with revisions
- [ ] Extension requests
- [ ] Auto-complete cron

### Phase 4: WooCommerce Integration (In Progress)
- [x] WooCommerce adapter
- [x] Service ↔ Product sync
- [x] WC checkout integration
- [x] WC account endpoints
- [ ] WC emails integration
- [ ] Migration from woo-sell-services

### Phase 5: Standalone Mode + Payments (PRO - Not Started)
- [ ] Standalone checkout flow
- [ ] Stripe integration
- [ ] PayPal integration
- [ ] Payment webhooks
- [ ] Order confirmation

### Phase 6: Vendor System (Partial)
- [ ] Vendor registration
- [ ] Verification tiers
- [ ] Frontend vendor dashboard
- [ ] Earnings tracking
- [ ] Payout system (auto-complete)
- [x] Analytics dashboard (AnalyticsService)

### Phase 7: Marketplace Features (In Progress)
- [x] Service catalog/archive (templates)
- [x] Search and filters (SearchService)
- [x] Vendor profiles (VendorService)
- [ ] Portfolio system
- [x] Review system (ReviewService)

### Phase 8: Buyer Requests (In Progress)
- [x] Buyer request posting (BuyerRequestService)
- [x] Proposal system (ProposalService)
- [ ] Request → Order conversion

### Phase 9: Disputes (In Progress)
- [x] Dispute creation (DisputeService)
- [ ] Evidence submission
- [x] Admin mediation panel (table exists)
- [ ] Resolution workflow

### Phase 10: Additional Integrations (PRO - Not Started)
- [ ] EDD adapter
- [ ] Fluent Cart adapter
- [ ] SureCart adapter
- [ ] Wallet integrations
- [ ] Email service integrations
- [ ] Cloud storage options

### Phase 11: REST API (In Progress)
- [x] All endpoints (Services, Orders, Reviews, Vendors)
- [ ] Authentication
- [ ] Rate limiting
- [ ] Documentation

### Phase 12: SEO & Polish (Not Started)
- [ ] Schema markup
- [ ] Yoast/RankMath integration
- [ ] Performance optimization
- [ ] Testing and QA

---

## Part 5: Migration from Woo Sell Services

### Migration Checklist

```php
class WSS_to_SS_Migration {

    public function run() {
        // 1. Create new tables
        $this->create_tables();

        // 2. Migrate service products → ss_service CPT
        $this->migrate_services();

        // 3. Migrate conversations
        $this->migrate_conversations();

        // 4. Create ss_orders from WC order item meta
        $this->migrate_orders();

        // 5. Migrate vendor assignments
        $this->migrate_vendors();

        // 6. Migrate settings
        $this->migrate_settings();

        // 7. Create platform mappings
        $this->create_wc_mappings();

        // 8. Cleanup
        $this->cleanup_old_data();
    }

    private function migrate_services() {
        // Find all products with _wss_type = 'yes'
        // Create ss_service CPT for each
        // Copy: title, content, featured image, categories
        // Create ss_service_platform_map entry
    }

    private function migrate_orders() {
        // Find all WC orders with service items
        // Create ss_orders entries
        // Link via platform_order_id and platform_item_id
    }
}
```

---

## Summary

This is now a **complete Fiverr-style service marketplace** with:

- **20 database tables** for comprehensive data management
- **Standalone mode** with direct Stripe/PayPal payments
- **4 e-commerce integrations** (WooCommerce, EDD, Fluent Cart, SureCart)
- **Full marketplace** with search, filters, categories
- **Buyer requests** with proposal system
- **Dispute resolution** system
- **Frontend vendor dashboard** with analytics
- **REST API** for mobile apps
- **SEO optimization** with schema markup

**Estimated Total Timeline:** 20-28 weeks for complete implementation

---

Ready to start building when you approve!
