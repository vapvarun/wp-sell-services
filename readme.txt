=== WP Sell Services ===
Contributors: wbcomdesigns
Tags: marketplace, freelance, services, standalone, fiverr
Requires at least: 6.4
Tested up to: 6.7
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Create a complete Fiverr-style service marketplace on WordPress with vendor management, order workflow, and commission system.

== Description ==

WP Sell Services transforms your WordPress site into a production-ready service marketplace where vendors list their services and buyers purchase them through a complete order workflow.

Build a freelance platform, gig marketplace, or service directory with tiered pricing packages, built-in messaging, review system, dispute resolution, and commission-based earnings.

= Complete Marketplace Platform =

**Service Management**

* Multi-step service creation wizard with live preview
* Three-tier pricing packages (Basic, Standard, Premium) with custom pricing and features
* Service add-ons and extras for increased order value
* Image gallery support (up to 4 images in free version)
* Video embeds for service demonstrations
* Custom FAQ section per service
* Service requirements for collecting buyer information
* Category and tag organization

**Order Workflow**

* Complete order lifecycle with 11 distinct statuses
* Requirements collection before work begins
* File delivery system with approval workflow
* Built-in messaging per order with file attachments
* Revision request management
* Deadline extension requests
* Order completion and approval process

**Vendor System**

* Vendor registration and approval workflow
* Four-tier seller level system (New Seller, Level 1, Level 2, Top Rated)
* Unified vendor dashboard with earnings overview
* Portfolio showcase for work samples
* Vacation mode for pausing new orders
* Vendor profile with bio, tagline, and social links
* Commission tracking and withdrawal requests

**Buyer Features**

* Post buyer requests for vendors to bid on
* Browse and compare vendor proposals
* Buyer dashboard for order tracking
* Add services to favorites/wishlist
* Optional tipping for exceptional work
* Complete purchase history

**Reviews and Ratings**

* 5-star rating system with written reviews
* Multi-criteria ratings (communication, quality, delivery)
* Review moderation queue for admin approval
* Vendor reply to reviews
* Reputation tracking and display

**Dispute Resolution**

* Structured dispute workflow (open, in review, resolved)
* Evidence submission with file attachments
* Admin mediation interface
* Multiple resolution types: full refund, partial refund, revision, mutual agreement
* Dedicated messaging thread per dispute

**Commission and Earnings**

* Global commission rate configuration (0-50%)
* Per-vendor custom commission rates
* Commission-free tipping
* Earnings dashboard with balance tracking
* Withdrawal request system with admin approval
* Automated withdrawal scheduling (weekly, bi-weekly, monthly)
* Configurable minimum withdrawal amount and clearance period

**WooCommerce Integration (Optional)**

* Works independently — WooCommerce is not required
* When WooCommerce is active, enables checkout and payment processing
* All WooCommerce payment gateways supported automatically
* HPOS (High Performance Order Storage) compatible
* WooCommerce order mapping for accounting
* Pro version adds Standalone, EDD, FluentCart, and SureCart alternatives

**Developer Ready**

* 6 Gutenberg blocks (Service Grid, Search, Categories, Featured Services, Seller Card, Buyer Requests)
* 16 shortcodes for flexible page building
* 20 REST API controllers with full CRUD operations
* Batch endpoint for mobile apps (up to 25 requests in single call)
* Template override system compatible with any theme
* 100+ action and filter hooks
* 17 custom database tables for optimal performance
* PSR-4 autoloading with clean architecture
* WP-CLI commands for bulk operations

**Frontend Display**

* Service archive with category and tag filtering
* Advanced search with autocomplete
* Vendor directory with ratings and reviews
* SEO-optimized service pages with JSON-LD schema markup
* Responsive templates for all devices
* Compatible with Yoast SEO and RankMath

**Notification System**

* 11 email notification types for order events
* In-app notification center
* Customizable email templates
* Email notification preferences per user

= Pro Features =

Upgrade to [WP Sell Services Pro](https://wbcomdesigns.com/downloads/wp-sell-services-pro) for additional capabilities:

* **E-commerce Platforms**: Easy Digital Downloads, FluentCart, SureCart, or Standalone mode (no e-commerce plugin required)
* **Direct Payment Gateways**: Stripe, PayPal, Razorpay, and Offline payments with proof upload
* **Wallet Integrations**: Internal wallet, TeraWallet, WooWallet, MyCred
* **Cloud Storage**: Amazon S3, Google Cloud Storage, DigitalOcean Spaces for file storage
* **Advanced Analytics**: Revenue charts, order analytics, service performance, vendor statistics with CSV/Excel export
* **Expanded Service Limits**: Unlimited gallery images, FAQs, add-ons, and requirements; 3 video embeds (free: 1)
* **Wizard Enhancements**: AI title suggestions, service templates, bulk uploads, scheduled publishing

= What Makes This Different =

Unlike simple directory plugins, WP Sell Services provides a complete transaction platform with order management, messaging, deliverables, and dispute resolution built-in. You get everything needed to run a professional marketplace from day one.

= Mobile App Ready =

The complete REST API with 20 controllers makes building iOS and Android apps straightforward. The batch endpoint allows mobile apps to execute multiple requests efficiently in a single HTTP call.

= Documentation =

Comprehensive documentation included covering every feature, with guides for vendors, buyers, administrators, and developers.

== Installation ==

= Minimum Requirements =

* WordPress 6.4 or higher
* PHP 8.1 or higher
* MySQL 5.7 or higher
* WooCommerce 8.0 or higher (optional — enables checkout and payment processing)

= Automatic Installation =

1. Log in to your WordPress dashboard
2. Navigate to **Plugins > Add New**
3. Search for "WP Sell Services"
4. Click **Install Now** and then **Activate**
5. Go to **WP Sell Services > Settings** to configure your marketplace
6. Optionally install **WooCommerce** to enable checkout and payment processing

= Manual Installation =

1. Download the plugin ZIP file
2. Upload to `/wp-content/plugins/` directory
3. Unzip the file
4. Activate through the **Plugins** menu in WordPress
5. Visit **WP Sell Services > Settings** for configuration
6. Optionally install **WooCommerce** for checkout and payment capabilities

= After Activation =

1. Configure general settings (currency, commission rate, policies)
2. Create required pages (services, vendors, dashboard, buyer requests)
3. Set up service categories
4. Configure email notifications
5. Enable vendor registration or manually create vendor accounts
6. Create your first test service to verify setup

== Frequently Asked Questions ==

= Is WooCommerce required? =

No. WP Sell Services works independently without WooCommerce. Your marketplace is fully functional for service listings, vendor management, order workflow, messaging, reviews, and dispute resolution. When WooCommerce is installed and active, the plugin automatically enables checkout and payment processing with access to hundreds of WooCommerce-compatible payment gateways. The Pro version adds more alternatives including EDD, FluentCart, SureCart, and fully Standalone mode with direct Stripe/PayPal/Razorpay integration.

= Does this work with my WordPress theme? =

Yes. WP Sell Services is designed to work with any well-coded WordPress theme. All frontend templates can be overridden by copying them to your theme's `wp-sell-services/` directory for customization.

= Can I run a multi-vendor marketplace? =

Yes. Any registered WordPress user can apply to become a vendor. Administrators control vendor approval, set commission rates (global or per-vendor), manage service moderation, and oversee the entire marketplace.

= How does the commission system work? =

Set a global commission percentage (0-50%) in settings. When an order completes, the commission is automatically calculated and deducted from the vendor's earnings. Vendors can request withdrawals of their available balance. You can also set custom commission rates for individual vendors.

= Can buyers post job requests? =

Yes. Buyers can post project requests with budget range, description, and deadline. Vendors browse these requests and submit custom proposals with pricing and delivery time. Buyers review proposals and accept the one they prefer.

= What payment gateways are supported? =

When WooCommerce is active, the free version works with all WooCommerce payment gateways including Stripe, PayPal, Square, bank transfer, and hundreds more through WooCommerce extensions. The Pro version adds direct integrations for Stripe, PayPal, Razorpay, and offline payments — these work independently without WooCommerce in Standalone mode.

= How are disputes handled? =

Buyers can open a dispute on any active order. Both parties submit evidence and messages. Administrators review the dispute details and can enforce resolutions including full refund, partial refund, additional revision, or note mutual agreement reached by parties.

= Can vendors pause their services temporarily? =

Yes. Vendors can enable "Vacation Mode" which automatically pauses all their services from accepting new orders while keeping them published. A custom vacation message displays on their profile.

= Does it support multiple currencies? =

Yes. The plugin supports 10 currencies: USD, EUR, GBP, AUD, CAD, INR, JPY, BRL, MXN, and ZAR. You configure one primary currency for your marketplace in settings.

= Is it translation ready? =

Yes. All plugin text uses the `wp-sell-services` text domain and can be translated using standard WordPress translation methods, WPML, Polylang, or translation plugins.

= How does the REST API work? =

The plugin provides 20 REST API controllers under `/wp-json/wpss/v1/` covering all marketplace functionality. Authentication works via WordPress cookies, Application Passwords, or JWT tokens. Perfect for building mobile apps or custom integrations.

= Can I customize the email templates? =

Yes. Email templates are located in `templates/emails/` and can be overridden in your theme. The plugin includes 11 email notification types for different order events, and administrators can customize subject lines and content.

= What seller levels are included? =

Four levels: New Seller (default), Level 1 Seller (5+ orders, 4.0+ rating), Level 2 Seller (25+ orders, 4.5+ rating), and Top Rated Seller (100+ orders, 4.8+ rating). Levels automatically update based on vendor performance metrics.

== Screenshots ==

1. Service listing page with category filters and search
2. Single service page showing packages, gallery, and reviews
3. Multi-step service creation wizard with live preview
4. Buyer dashboard with active orders and statistics
5. Vendor dashboard showing earnings, orders, and performance
6. Order detail page with messaging and delivery management
7. Buyer requests listing where vendors can submit proposals
8. Admin settings panel for marketplace configuration
9. Admin order management with status transitions and moderation
10. Dispute resolution interface with evidence and admin mediation

== Changelog ==

= 1.0.0 =
* Initial release
* Service marketplace with multi-step creation wizard
* Three-tier pricing packages with custom features
* Service add-ons and extras for upselling
* Complete order lifecycle with 11 statuses
* Built-in messaging system per order with file attachments
* Requirements collection and delivery management
* Revision request workflow
* Deadline extension requests
* Review and rating system with multi-criteria ratings
* Dispute resolution with admin mediation
* Vendor profiles with portfolio showcase
* Four-tier seller level system with automatic progression
* Buyer request system with vendor proposals
* Commission system with per-vendor custom rates
* Earnings tracking and withdrawal management
* Automated withdrawal scheduling
* 11 configurable email notification types
* In-app notification system
* Vacation mode for vendors
* Optional tipping system
* Favorites/wishlist functionality
* WooCommerce integration with HPOS support
* 6 Gutenberg blocks for page building
* 16 shortcodes for flexible display
* 20 REST API controllers with batch endpoint
* Template override system for theme customization
* SEO schema markup with Yoast and RankMath integration
* WP-CLI commands for bulk operations
* 17 custom database tables for optimal performance
* PSR-4 autoloaded architecture
* 100+ action and filter hooks for extensibility

== Upgrade Notice ==

= 1.0.0 =
Initial release of WP Sell Services. Transform your WordPress site into a complete service marketplace with vendor management, order workflow, and commission system.
