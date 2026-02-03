=== WP Sell Services ===
Contributors: developer developer
Tags: marketplace, freelance, services, woocommerce, fiverr
Requires at least: 6.4
Tested up to: 6.7
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Build a Fiverr-style service marketplace on WordPress with WooCommerce integration.

== Description ==

WP Sell Services transforms your WordPress site into a fully functional service marketplace where vendors can list services and buyers can purchase them — just like Fiverr, Upwork, or Freelancer.

**Create a complete freelance marketplace** with service listings, tiered pricing packages, order management, built-in messaging, reviews, dispute resolution, and more — all powered by WooCommerce.

= Key Features =

**Service Creation & Management**

* Multi-step service creation wizard with live preview
* Three-tier pricing packages (Basic, Standard, Premium)
* Service add-ons and extras with custom pricing
* Image gallery and video embeds
* FAQ section per service
* Service categories and tags
* SEO-optimized service pages with JSON-LD schema markup

**Order Lifecycle**

* Complete order workflow with 11 statuses (pending, in progress, revision, completed, disputed, and more)
* Order requirements collection from buyers
* File delivery system with approval workflow
* Revision requests and deadline extensions
* Milestone-based payments
* Tipping support for completed orders

**Communication & Messaging**

* Built-in conversation system per order
* Real-time messaging between buyers and vendors
* File attachments in messages

**Reviews & Ratings**

* 1-5 star rating system
* Review moderation by admins
* Vendor reputation tracking

**Dispute Resolution**

* Structured dispute workflow (open, in review, resolved)
* Multiple resolution types: refund, partial refund, revision, mutual agreement
* Admin mediation interface
* Dispute messaging thread

**Vendor Management**

* Vendor profiles with portfolio and work samples
* Three vendor tiers: New, Rising, Top Rated
* Vendor earnings dashboard with withdrawal requests
* Commission and tax management
* Service moderation and approval queue
* Seller level progression system

**Buyer Features**

* Post buyer requests (job postings) for vendors to bid on
* Receive and compare vendor proposals
* Unified buyer/vendor dashboard
* Order history and tracking

**WooCommerce Integration**

* Seamless checkout through WooCommerce
* WooCommerce order mapping
* HPOS (High Performance Order Storage) compatible
* WooCommerce email notifications
* WooCommerce account page integration

**Developer Friendly**

* 6 Gutenberg blocks (Service Grid, Search, Categories, Featured Services, Seller Card, Buyer Requests)
* 15+ shortcodes for flexible page building
* Full REST API with 8 endpoint controllers
* Template override system (theme/wp-sell-services/)
* WP-CLI commands for service management
* Extensible via hooks and filters
* 17 custom database tables
* PSR-4 autoloading with clean architecture

= Pro Features =

Take your marketplace to the next level with [WP Sell Services Pro](https://developer.developer/wp-sell-services-pro):

* Additional e-commerce platforms: EDD, Fluent Cart, SureCart, or Standalone (no e-commerce plugin needed)
* Payment gateways: Stripe, PayPal, Razorpay, Offline payments
* Wallet system: Internal wallet, TeraWallet, WooWallet, MyCred integration
* Cloud storage: AWS S3, Google Cloud Storage, DigitalOcean Spaces
* Advanced analytics dashboard with charts and CSV/Excel export
* Unlimited service packages, gallery images, videos, FAQs, and add-ons
* Vendor verification and advanced tier management

== Installation ==

1. Upload the `wp-sell-services` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Install and activate **WooCommerce** if you haven't already (required for checkout and payments).
4. Go to **WP Sell Services > Settings** to configure your marketplace.
5. Create the required pages (the plugin will guide you through setup).
6. Start adding services or invite vendors to your marketplace.

= Minimum Requirements =

* WordPress 6.4 or later
* PHP 8.1 or later
* WooCommerce 8.0 or later (for checkout and payment processing)

== Frequently Asked Questions ==

= Is WooCommerce required? =

WooCommerce is required in the free version for checkout and payment processing. The Pro version offers additional e-commerce options including EDD, Fluent Cart, SureCart, and a fully standalone mode that requires no e-commerce plugin.

= Does this work with any WordPress theme? =

Yes. WP Sell Services is designed to work with any well-coded WordPress theme. All frontend templates can be overridden in your theme by placing them in a `wp-sell-services/` directory within your theme folder.

= Can I run a multi-vendor marketplace? =

Yes. Any registered user can apply to become a vendor. Admins can configure vendor approval requirements, set commission rates, and manage vendor tiers from the settings panel.

= How does the commission system work? =

Admins set a commission percentage in the settings. When an order is completed, the commission is automatically calculated and deducted from the vendor's earnings. Vendors can request withdrawals of their available balance.

= Can buyers post job requests? =

Yes. Buyers can post requests describing the work they need. Vendors can browse these requests and submit proposals with custom pricing and delivery timelines.

= Is the plugin translation ready? =

Yes. WP Sell Services is fully translation ready with a complete text domain. You can translate it using any standard WordPress translation method.

= Does it support Gutenberg? =

Yes. The plugin includes 6 native Gutenberg blocks for building marketplace pages, along with 15+ shortcodes for use in the Classic Editor or page builders.

= How are disputes handled? =

Buyers can open a dispute for any active order. The dispute goes through a structured workflow with messaging between parties and admin mediation. Resolutions include full refund, partial refund, additional revision, or mutual agreement.

== Screenshots ==

1. Service listing page with category filters
2. Single service page with packages, gallery, and reviews
3. Service creation wizard
4. Buyer dashboard with order overview
5. Vendor dashboard with earnings summary
6. Order detail page with messaging
7. Buyer requests listing
8. Admin settings panel
9. Admin order management
10. Dispute resolution interface

== Changelog ==

= 1.0.0 =
* Initial release
* Service marketplace with creation wizard and tiered packages
* Complete order lifecycle with 11 statuses
* Built-in messaging and conversation system
* Review and rating system with moderation
* Dispute resolution workflow
* Vendor profiles with portfolio and tier progression
* Buyer request and proposal system
* WooCommerce integration with HPOS support
* 6 Gutenberg blocks and 15+ shortcodes
* Full REST API with 8 controllers
* Admin moderation, commission, and withdrawal management
* SEO schema markup with Yoast and RankMath integration
* Template override system
* WP-CLI commands

== Upgrade Notice ==

= 1.0.0 =
Initial release of WP Sell Services — your complete Fiverr-style marketplace for WordPress.
