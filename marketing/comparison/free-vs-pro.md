# WP Sell Services: Free vs Pro Feature Comparison

Complete breakdown of what's included in the free version versus Pro upgrade.

---

## Quick Summary

**Free Version:**
Complete, production-ready marketplace with service management, order workflow, vendor system, reviews, disputes, buyer requests, commission system, and built-in standalone checkout with Stripe, PayPal, and Offline payment gateways. Everything needed to launch and run a professional service marketplace — **no WooCommerce required.**

**Pro Version:**
Everything in Free, plus unlimited service creation limits, four additional e-commerce platforms (WooCommerce, EDD, Fluent Cart, SureCart), Razorpay gateway, wallet integrations, advanced analytics dashboards with data export, cloud storage providers, and REST API extensions.

---

## At a Glance

| Feature Category | Free | Pro |
|-----------------|------|-----|
| **Marketplace Core** | Complete platform | Same |
| **E-commerce Platform** | Standalone (built-in checkout) | +4 alternatives (WC, EDD, FC, SC) |
| **Payment Processing** | Stripe, PayPal, Offline | +Razorpay |
| **Service Creation** | Conservative limits | Unlimited |
| **Commission System** | Global + per-vendor | Same |
| **Analytics** | Basic stats | Advanced dashboards |
| **File Storage** | Local server | +Cloud providers |
| **Wallet System** | Built-in tracking | +4 integrations |
| **REST API** | 20 controllers | +4 controllers |

---

## Core Marketplace Features (Both Versions)

These features are identical in Free and Pro:

### Service Management
- Multi-step creation wizard with live preview
- Three pricing packages per service (Basic, Standard, Premium)
- Custom pricing, delivery time, revisions per package
- Service add-ons and extras for upselling
- Category and tag organization
- Service requirements for buyer information
- Custom FAQ section per service
- Draft and published states
- Service moderation queue (optional)

### Order Workflow
- Complete lifecycle with 11 order statuses
- Requirements collection before work begins
- File delivery system with approval workflow
- Built-in messaging per order with attachments
- Revision request management
- Deadline extension requests
- Order completion and approval process
- Order cancellation with refund support

### Vendor System
- Vendor registration and approval workflow
- Four-tier seller level system (New Seller, Level 1, Level 2, Top Rated)
- Automatic level promotion based on performance
- Unified vendor dashboard with earnings overview
- Portfolio showcase for work samples
- Vacation mode to pause new orders
- Vendor profile with bio, tagline, social links
- Service limit per vendor (configurable, default 20)

### Buyer Features
- Post buyer requests for vendors to bid on
- Browse and compare vendor proposals
- Buyer dashboard for order tracking
- Add services to favorites/wishlist
- Optional tipping for exceptional work
- Complete purchase history

### Reviews and Ratings
- 5-star rating system with written reviews
- Multi-criteria ratings (communication, quality, delivery)
- Review moderation queue (optional)
- Vendor reply to reviews
- Reputation tracking and display

### Dispute Resolution
- Structured workflow (open, in review, resolved)
- Evidence submission with file attachments
- Admin mediation interface
- Multiple resolution types (refund, partial refund, revision, agreement)
- Dedicated messaging thread per dispute

### Commission and Earnings
- Global commission rate (0-50%, default 10%)
- Per-vendor custom commission rates
- Commission-free tipping
- Earnings dashboard with balance tracking
- Withdrawal request system
- Automated withdrawal scheduling (weekly, bi-weekly, monthly)
- Configurable minimum withdrawal amount
- Clearance period configuration

### Notification System
- 11 email notification types for order events
- In-app notification center
- Customizable email templates
- Email preference management per user

### Developer Features
- 6 Gutenberg blocks
- 16 shortcodes
- 20 REST API controllers
- Batch endpoint (25 requests per call)
- Template override system
- 100+ action and filter hooks
- PSR-4 autoloaded architecture
- WP-CLI commands

### Frontend Display
- Service archive with filtering
- Advanced search with autocomplete
- Vendor directory with profiles
- SEO-optimized pages with JSON-LD schema
- Responsive templates
- Compatible with Yoast SEO and RankMath

---

## Service Creation Limits

The key difference in day-to-day service management.

| Feature | Free | Pro |
|---------|------|-----|
| **Pricing Packages** | 3 per service | 3 per service |
| **Gallery Images** | 4 per service | Unlimited |
| **Video Embeds** | 1 per service | 3 per service |
| **Service Add-ons** | 3 per service | Unlimited |
| **FAQ Items** | 5 per service | Unlimited |
| **Buyer Requirements** | 5 per service | Unlimited |
| **Draft Services** | Unlimited | Unlimited |
| **Active Services per Vendor** | Configurable (default 20) | Configurable (default 20) |

### How Limits Work

Free version applies conservative limits through the service creation wizard. When you reach a limit (e.g., 4 gallery images), the wizard displays an upgrade prompt.

Pro version removes most limits by changing filter values to -1 (unlimited) or higher numbers.

**Note:** Both versions support 3 pricing packages. The package count is the same in Free and Pro - this is a core marketplace pattern, not a limitation.

---

## E-Commerce Platform Support

Choose how you handle checkout and payments.

| Platform | Free | Pro |
|----------|------|-----|
| **Standalone Mode** | Included (default) | Included |
| **WooCommerce** | -- | **PRO** |
| **Easy Digital Downloads** | -- | **PRO** |
| **Fluent Cart** | -- | **PRO** |
| **SureCart** | -- | **PRO** |

### Free Version: Standalone Checkout

Built-in checkout system with direct payment gateway integration. **No WooCommerce or any other e-commerce plugin required.** Service orders flow through the plugin's own cart and checkout page with Stripe, PayPal, and Offline payment support.

**Advantages:** Lightweight, zero external dependencies, works on any WordPress site immediately.

### Pro Version: Four E-Commerce Platform Alternatives

**WooCommerce** - Mature e-commerce platform with hundreds of payment gateway extensions. Service orders flow through WooCommerce cart and checkout.

**Easy Digital Downloads** - Lightweight digital commerce platform designed for digital products. Lower overhead than WooCommerce.

**Fluent Cart** - Modern checkout experience with conversion optimization built in.

**SureCart** - Cloud-hosted checkout with built-in PCI compliance.

**Platform Selection:** Choose in Settings > General > E-Commerce Platform. "Auto-detect" mode selects the first active platform automatically.

---

## Payment Gateways

How money flows from buyers to platform to vendors.

| Gateway | Free | Pro |
|---------|------|-----|
| **Stripe Direct Integration** | Included | Included |
| **PayPal Direct Integration** | Included | Included |
| **Offline Payments** | Included (with proof upload) | Included |
| **Razorpay Direct Integration** | -- | **PRO** |
| **All WooCommerce Gateways** | -- | **PRO** (via WC adapter) |

### Free Version

Three payment gateways ship with the free plugin, working with the built-in standalone checkout:

- **StripeGateway** - Direct Stripe API integration with 3D Secure support
- **PayPalGateway** - PayPal Commerce Platform integration
- **OfflineGateway** - Bank transfer, cash, manual payments with proof upload

Each gateway manages its own settings tab, sandbox/live mode, and webhook handling.

### Pro Version

Adds Razorpay gateway (popular in India and Southeast Asia) and access to all WooCommerce-compatible payment gateways when using the WooCommerce adapter.

---

## Wallet System

Automate vendor payouts and balance management.

| Feature | Free | Pro |
|---------|------|-----|
| **Built-in Earnings Tracking** | Included | Included |
| **Withdrawal Requests** | Included | Included |
| **Auto-withdrawal Scheduling** | Included | Included |
| **Internal Wallet Integration** | -- | **PRO** |
| **TeraWallet Integration** | -- | **PRO** |
| **WooWallet Integration** | -- | **PRO** |
| **MyCred Integration** | -- | **PRO** |

### Free Version

Built-in earnings system tracks vendor revenue, platform commission, available balance, and pending clearance. Vendors request withdrawals when balance meets minimum threshold. Admins approve and process withdrawals manually or through automated scheduling.

### Pro Version

Four wallet provider integrations automate the process. When an order completes, vendor earnings automatically credit to their chosen wallet. Vendors view balance and transaction history through wallet plugin. Withdrawals process through wallet system.

**Wallet Providers:**
- **Internal Wallet** - Built-in wallet system added by Pro
- **TeraWallet** - Popular WooCommerce wallet extension
- **WooWallet** - WordPress wallet plugin
- **MyCred** - Points and rewards system

---

## Commission System (Identical in Both)

Both Free and Pro include the same commission features:

| Feature | Free | Pro |
|---------|------|-----|
| **Global Commission Rate** | 0-50% (default 10%) | Same |
| **Per-Vendor Custom Rates** | Included | Same |
| **Commission on Tips** | Tips are commission-free | Same |
| **Commission Calculation** | Automatic on order completion | Same |

**Important:** Per-vendor commission rates are NOT a Pro-only feature. The free version includes this capability. Admins can override the global rate for individual vendors through vendor profile settings.

---

## Analytics and Reporting

Track marketplace performance and vendor success.

| Feature | Free | Pro |
|---------|------|-----|
| **Vendor Basic Stats** | Order counts, earnings, rating | Same |
| **Admin Basic Stats** | Total orders, revenue overview | Same |
| **Analytics Dashboard** | -- | **PRO** |
| **Revenue Charts** | -- | **PRO** Interactive graphs |
| **Order Analytics** | -- | **PRO** Status distribution |
| **Top Services Report** | -- | **PRO** By revenue and views |
| **Top Vendors Report** | -- | **PRO** By earnings |
| **Data Export** | -- | **PRO** CSV/Excel |
| **Vendor Analytics API** | -- | **PRO** REST endpoint |

### Free Version

Basic statistics display in vendor dashboard (active orders, completed orders, earnings, balance, rating) and admin panel (total orders, revenue, vendor count).

### Pro Version

Full analytics dashboard with visual widgets powered by Chart.js:

- **Revenue Widget** - Interactive graphs over 7/30/90 days or 12 months
- **Orders Widget** - Status distribution, completion rates, volume trends
- **Top Services Widget** - Best performers by revenue and page views
- **Top Vendors Widget** - Leaderboards by earnings and order count

**Data Export:** Export all reports to CSV or Excel format.

**Vendor Analytics API:** REST endpoint provides programmatic access to analytics data for custom integrations.

---

## Cloud Storage (Pro Only)

Store and deliver files through cloud infrastructure.

| Provider | Free | Pro |
|----------|------|-----|
| **Local Server Storage** | Default | Default |
| **Amazon S3** | -- | **PRO** |
| **Google Cloud Storage** | -- | **PRO** |
| **DigitalOcean Spaces** | -- | **PRO** |

### Free Version

All files (service images, delivery attachments, portfolio items) store on WordPress server using standard media library and `/wp-content/uploads/` directory.

### Pro Version

Configure cloud storage provider in settings. Files automatically upload to your chosen cloud service instead of local server. Vendors and buyers upload/download through secure signed URLs.

**Benefits:** Offload storage, handle large files, improve performance, use existing cloud infrastructure.

**Configuration:** Provide credentials, bucket name, and region in settings panel.

---

## REST API

Both versions are API-first with mobile app support.

| Component | Free | Pro |
|-----------|------|-----|
| **Core Controllers** | 20 controllers | 20 controllers |
| **Batch Endpoint** | 25 requests per call | 25 requests per call |
| **Authentication** | Cookies, App Passwords | + JWT (via plugin) |
| **WalletController** | -- | **PRO** |
| **PaymentController** | -- | **PRO** |
| **VendorAnalyticsController** | -- | **PRO** |
| **StorageController** | -- | **PRO** |

### Free Version Controllers

Complete API coverage with 20 controllers:
- Services, Orders, Reviews, Vendors, Conversations
- Disputes, Buyer Requests, Proposals, Notifications
- Portfolio, Earnings, Extensions, Milestones, Tipping
- Seller Levels, Moderation, Favorites, Media, Cart, Auth

### Pro Version

Adds four controllers for Pro-specific features:
- **WalletController** - Balance, transactions, withdrawal operations
- **PaymentController** - Direct gateway integration (Stripe, PayPal, Razorpay)
- **VendorAnalyticsController** - Revenue, orders, services data with export
- **StorageController** - Cloud upload, download URLs, file deletion

---

## Service Wizard Enhancements (Pro)

Beyond removing limits, Pro adds wizard features:

| Feature | Free | Pro |
|---------|------|-----|
| **Multi-step Wizard** | Included | Included |
| **Live Preview** | Included | Included |
| **AI Title Suggestions** | -- | **PRO** |
| **Service Templates** | -- | **PRO** |
| **Bulk Image Upload** | -- | **PRO** |
| **Direct Video Upload** | -- | **PRO** |
| **Custom Package Fields** | -- | **PRO** |
| **Scheduled Publishing** | -- | **PRO** |

Pro wizard features register through `wpss_service_wizard_features` filter, enabling additional UI elements and functionality in the service creation process.

---

## Seller Levels (Identical in Both)

Four-tier reputation system included in both versions:

| Level | Requirements | Free | Pro |
|-------|-------------|------|-----|
| **New Seller** | Default for new vendors | Yes | Yes |
| **Level 1 Seller** | 5+ orders, 4.0+ rating, 80% rates | Yes | Yes |
| **Level 2 Seller** | 25+ orders, 4.5+ rating, 90% rates | Yes | Yes |
| **Top Rated Seller** | 100+ orders, 4.8+ rating, 95% rates | Yes | Yes |

Seller levels calculate automatically based on vendor performance metrics. Level promotions trigger email notifications. The system is part of free version core, not Pro-only.

---

## What Free Includes (Complete List)

Everything needed for a production marketplace:

**Service Features:**
- Multi-step creation wizard with live preview
- Three pricing packages with custom features
- Service add-ons (3 max)
- Image gallery (4 images max)
- Video embed (1 video max)
- FAQ section (5 max)
- Buyer requirements (5 max)
- Category and tag organization

**Order Management:**
- 11 order statuses covering complete lifecycle
- Requirements collection before work starts
- Built-in messaging with file attachments
- Delivery submission and approval
- Revision request workflow
- Deadline extension requests
- Order cancellation with refunds

**Vendor System:**
- Registration and approval workflow
- Four-tier seller level system
- Unified dashboard with earnings overview
- Portfolio showcase
- Vacation mode
- Profile with bio and social links
- Global + per-vendor commission rates

**Buyer Features:**
- Post buyer requests
- Review vendor proposals
- Buyer dashboard
- Favorites/wishlist
- Optional tipping
- Purchase history

**Reviews and Disputes:**
- 5-star rating with written reviews
- Multi-criteria ratings
- Review moderation (optional)
- Vendor reply to reviews
- Complete dispute workflow
- Admin mediation tools

**Financial:**
- Commission system with per-vendor rates
- Earnings tracking
- Withdrawal requests
- Automated withdrawal scheduling
- Clearance period configuration

**Technical:**
- Standalone checkout with Stripe, PayPal, Offline gateways
- 6 Gutenberg blocks
- 16 shortcodes
- 20 REST API controllers
- Batch endpoint
- Template overrides
- 100+ hooks and filters
- WP-CLI commands
- SEO optimization

**Best For:** New marketplaces, lightweight sites that don't need WooCommerce, projects where service creation limits (4 gallery images, 3 add-ons, 5 FAQs, 1 video) are sufficient.

---

## What Pro Adds

Premium features for growing marketplaces:

**Removed Limits:**
- Unlimited gallery images (free: 4)
- 3 video embeds (free: 1)
- Unlimited service add-ons (free: 3)
- Unlimited FAQs (free: 5)
- Unlimited requirements (free: 5)

**E-commerce Flexibility:**
- WooCommerce adapter (access hundreds of WC gateways)
- Easy Digital Downloads adapter
- Fluent Cart adapter
- SureCart adapter

**Additional Payment Gateways:**
- Razorpay direct integration (popular in India and Southeast Asia)

**Wallet Integrations:**
- Internal Wallet system
- TeraWallet integration
- WooWallet integration
- MyCred integration

**Advanced Analytics:**
- Revenue charts and graphs
- Order analytics dashboard
- Top services report
- Top vendors report
- CSV/Excel data export
- Vendor analytics REST API

**Cloud Storage:**
- Amazon S3 integration
- Google Cloud Storage integration
- DigitalOcean Spaces integration

**Wizard Enhancements:**
- AI title suggestions
- Service templates
- Bulk image upload
- Direct video upload
- Custom package fields
- Scheduled publishing

**API Extensions:**
- WalletController
- PaymentController
- VendorAnalyticsController
- StorageController

**Best For:** Growing marketplaces needing WooCommerce/EDD/FluentCart/SureCart integration, Razorpay payments, automated wallet payouts, detailed analytics, or vendors requiring unlimited service media and add-ons.

---

## Upgrade Path

Upgrading from Free to Pro preserves all data:

1. Keep free version installed and active
2. Install and activate Pro plugin
3. Enter license key in Settings > License
4. Pro features activate immediately

No migration required. Both plugins run simultaneously - Pro extends Free through WordPress hooks.

---

## Pricing

**Free Version:** $0 - No monthly fees, no transaction limits, unlimited sites

**Pro Version:** Available at [wbcomdesigns.com/downloads/wp-sell-services-pro](https://wbcomdesigns.com/downloads/wp-sell-services-pro)

---

## Frequently Asked Questions

**Can I upgrade from Free to Pro later?**
Yes. Install Pro alongside Free at any time. All services, orders, vendors, and settings are preserved.

**Do I need WooCommerce?**
No. The free version includes a built-in standalone checkout with Stripe, PayPal, and Offline gateways. WooCommerce is optional and available as a Pro adapter alongside EDD, Fluent Cart, and SureCart.

**Is per-vendor commission a Pro feature?**
No. Per-vendor commission rates are available in the free version.

**What happens if my Pro license expires?**
Your site continues working with all Pro features. You won't receive updates or support until you renew.

**What are the actual free version limits?**
4 gallery images, 1 video, 3 add-ons, 5 FAQs, 5 buyer requirements per service. Three pricing packages in both versions.

---

## Make Your Choice

**Choose Free If:**
- Launching a new marketplace
- Want a lightweight setup without WooCommerce
- Stripe + PayPal + Offline payments are sufficient
- Service creation limits are sufficient
- Basic analytics meet your needs
- Local file storage works fine

**Choose Pro If:**
- Need WooCommerce, EDD, FluentCart, or SureCart integration
- Need Razorpay gateway
- Require unlimited service media and add-ons
- Need advanced analytics dashboards
- Want automated wallet-based vendor payouts
- Plan to use cloud storage for files
- Building mobile apps with extended API

---

**Get Started:**
- Download Free: [wordpress.org/plugins/wp-sell-services](https://wordpress.org/plugins/wp-sell-services)
- Upgrade to Pro: [wbcomdesigns.com/downloads/wp-sell-services-pro](https://wbcomdesigns.com/downloads/wp-sell-services-pro)
- Documentation: Available in plugin dashboard
- Support: WordPress.org forums (Free) or priority support (Pro)
