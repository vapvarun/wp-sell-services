=== WP Sell Services ===
Contributors: wbcomdesigns
Tags: marketplace, freelance, services, standalone, fiverr
Requires at least: 6.4
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 1.1.0
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
* Seller level progression (New Seller, Rising Seller, Top Rated, Pro Seller)
* Unified vendor dashboard with earnings overview
* Portfolio showcase for work samples
* Vacation mode for pausing new orders
* Vendor profile with bio, tagline, and social links
* Commission tracking and withdrawal requests

**Buyer Features**

* Post buyer requests for vendors to bid on
* Browse and compare vendor proposals (Fixed vs Milestone contract types)
* Accept multi-phase milestone contracts with lock-step payments
* Buyer dashboard for order tracking
* Add services to favorites/wishlist
* Optional tipping for exceptional work
* Complete purchase history

**Milestone Contracts & Paid Extensions (1.1.0)**

* Upwork-style milestone contracts on buyer-request orders with lock-step phase payments
* Paid extensions on catalog orders for mid-order add-ons
* Mutual exclusion: a single order surfaces milestones OR extensions, never both
* Ad-hoc milestone additions when scope grows mid-contract
* Auto-complete parent order when every phase is approved

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

**Standalone Checkout**

* Built-in checkout system — no WooCommerce or other e-commerce plugin required
* Offline payment gateway with admin confirmation workflow
* Free version includes Stripe, PayPal, and Offline gateways. Pro adds Razorpay gateway plus WooCommerce, EDD, FluentCart, and SureCart checkout integrations.

**Developer Ready**

* 6 Gutenberg blocks (Service Grid, Search, Categories, Featured Services, Seller Card, Buyer Requests)
* 19 shortcodes for flexible page building
* 21 REST API controllers with 125+ endpoints and full CRUD operations
* 100% REST coverage for all user-facing features — fully mobile-app ready
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

* 26 email notification types for order events
* In-app notification center
* Customizable email templates
* Email notification preferences per user

= Pro Features =

Upgrade to [WP Sell Services Pro](https://wbcomdesigns.com/downloads/wp-sell-services-pro/) for additional capabilities:

* **E-commerce Platforms**: WooCommerce, Easy Digital Downloads, FluentCart, SureCart
* **Additional Payment Gateway**: Razorpay (UPI, cards, netbanking)
* **Tiered Commission Rules**: Category, volume, and seller-level based commission rates
* **White-Label Branding**: Rebrand the entire marketplace with custom name and styling
* **PayPal Mass Payouts**: Automated batch vendor payouts via PayPal
* **Stripe Connect**: Direct vendor payments with Express onboarding
* **Vendor Subscription Plans**: Paid vendor tiers with service limits and feature gating
* **Recurring Services**: Subscription billing for services with automated renewals
* **Wallet Integrations**: Internal wallet, TeraWallet, WooWallet, MyCred
* **Cloud Storage**: Amazon S3, Google Cloud Storage, DigitalOcean Spaces for file storage
* **Advanced Analytics**: Revenue charts, order analytics, service performance, vendor statistics with CSV/Excel export
* **Expanded Service Limits**: Unlimited gallery images, FAQs, add-ons, and requirements; 3 video embeds (free: 1)
* **Wizard Enhancements**: AI title suggestions, service templates, bulk uploads, scheduled publishing

= What Makes This Different =

Unlike simple directory plugins, WP Sell Services provides a complete transaction platform with order management, messaging, deliverables, and dispute resolution built-in. You get everything needed to run a professional marketplace from day one.

= Mobile App Ready =

The complete REST API with 21 controllers makes building iOS and Android apps straightforward. The batch endpoint allows mobile apps to execute multiple requests efficiently in a single HTTP call.

= Documentation =

Full documentation at [wbcomdesigns.com/docs/wp-sell-services](https://wbcomdesigns.com/docs/wp-sell-services/) covering every feature, with guides for vendors, buyers, administrators, and developers.

== Installation ==

= Minimum Requirements =

* WordPress 6.4 or higher
* PHP 8.1 or higher
* MySQL 5.7 or higher
* No additional plugins required (standalone checkout included)

= Installation =

1. Download the plugin ZIP from [wbcomdesigns.com](https://wbcomdesigns.com/downloads/wp-sell-services/)
2. Go to **Plugins > Add New > Upload Plugin** and upload the ZIP
3. Click **Install Now** and then **Activate**
4. Complete the **Setup Wizard** to create pages and configure your marketplace

= Manual Installation =

1. Download and extract the plugin ZIP
2. Upload the `wp-sell-services` folder to `/wp-content/plugins/`
3. Activate through the **Plugins** menu in WordPress
4. Complete the **Setup Wizard** to create pages and configure your marketplace

= After Activation =

1. Complete the Setup Wizard (creates pages, configures currency, and imports demo content)
2. Configure commission rates and vendor settings under **WP Sell Services > Settings**
3. Set up service categories
4. Configure email notifications
5. Enable vendor registration or manually create vendor accounts
6. Create your first test service to verify setup

== Frequently Asked Questions ==

= Is WooCommerce required? =

No. WP Sell Services includes a built-in standalone checkout system with an offline payment gateway. Your marketplace is fully functional out of the box — service listings, vendor management, order workflow, messaging, reviews, dispute resolution, and checkout all work without any additional plugins. The Pro version adds direct Stripe, PayPal, and Razorpay payment gateways, plus WooCommerce, EDD, FluentCart, and SureCart integrations for sites that prefer those platforms.

= Does this work with my WordPress theme? =

Yes. WP Sell Services is designed to work with any well-coded WordPress theme. All frontend templates can be overridden by copying them to your theme's `wp-sell-services/` directory for customization.

= Can I run a multi-vendor marketplace? =

Yes. Any registered WordPress user can apply to become a vendor. Administrators control vendor approval, set commission rates (global or per-vendor), manage service moderation, and oversee the entire marketplace.

= How does the commission system work? =

Set a global commission percentage (0-50%) in settings. When an order completes, the commission is automatically calculated and deducted from the vendor's earnings. Vendors can request withdrawals of their available balance. You can also set custom commission rates for individual vendors.

= Can buyers post job requests? =

Yes. Buyers can post project requests with budget range, description, and deadline. Vendors browse these requests and submit custom proposals with pricing and delivery time. Buyers review proposals and accept the one they prefer.

= What payment gateways are supported? =

The free version includes a standalone checkout with Stripe, PayPal, and Offline payment gateways — no e-commerce plugin required. The Pro version adds Razorpay as an additional gateway, plus WooCommerce, Easy Digital Downloads, FluentCart, and SureCart checkout integrations for sites already using those platforms.

= How are disputes handled? =

Buyers can open a dispute on any active order. Both parties submit evidence and messages. Administrators review the dispute details and can enforce resolutions including full refund, partial refund, additional revision, or note mutual agreement reached by parties.

= Can vendors pause their services temporarily? =

Yes. Vendors can enable "Vacation Mode" which automatically pauses all their services from accepting new orders while keeping them published. A custom vacation message displays on their profile.

= Does it support multiple currencies? =

Yes. The plugin supports 10 currencies: USD, EUR, GBP, AUD, CAD, INR, JPY, BRL, MXN, and ZAR. You configure one primary currency for your marketplace in settings.

= Is it translation ready? =

Yes. All plugin text uses the `wp-sell-services` text domain and can be translated using standard WordPress translation methods, WPML, Polylang, or translation plugins.

= How does the REST API work? =

The plugin provides 21 REST API controllers under `/wp-json/wpss/v1/` covering all marketplace functionality. Authentication works via WordPress cookies, Application Passwords, or JWT tokens. Perfect for building mobile apps or custom integrations.

= Can I customize the email templates? =

Yes. Email templates are located in `templates/emails/` and can be overridden in your theme. The plugin includes 26 email notification types for different order events, and administrators can customize subject lines and content.

= What seller levels are included? =

Three auto-calculated levels plus one admin-granted: New Seller (default), Rising Seller (5+ orders, 4.0+ rating), Top Rated (25+ orders, 4.7+ rating), and Pro Seller (admin-granted). Levels automatically update based on vendor performance metrics.

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

= 1.1.0 - 2026-04-21 =

**Milestone Contracts (Upwork-style)**

* Vendors choose Fixed or Milestone contract type when submitting a proposal
* Milestone proposals carry a phase repeater: title, description, amount, days
* Buyers compare proposals with a phase-count badge and see the full breakdown before accepting
* Acceptance pre-creates every phase on the order timeline — no upfront parent checkout
* Lock-step payment enforced on the server: phase N only unlocks after every earlier phase is approved or cancelled
* Ad-hoc milestones can still be proposed during a contract for legitimate scope changes
* Parent order auto-completes when every phase is terminal — standard completion email and review prompt fire
* Cancellation rules: paid phases stand, unpaid phases auto-cancel, paid-but-open phases route through dispute

**Paid Extensions (catalog orders)**

* Vendors on in-progress catalog orders can quote extra work with a price and extra days
* Buyers accept and pay, or decline with one click
* Commission is split at payment time; vendor wallet credited immediately
* Parent order deadline extends by the quoted days on acceptance
* Extensions are mutually exclusive with milestone contracts — a single order only ever shows one flow

**Vendor Intro Video**

* Vendors can add an Introduction section with a short intro video to their public profile
* Supported sources: MP4 upload or YouTube embed
* Renders above the vendor's tagline and bio on the profile page

**Earnings Ledger & CSV Export (Pro)**

* Wallet dashboard surfaces a dated ledger of every transaction — Earning, Tip, Extension, Milestone, Withdrawal, Credit, Debit, Dispute Refund
* Period selector: Last 30 Days, This Month, Last Month, This Year, All Time
* CSV export streams the same rows plus a summary block (Total Credits, Total Debits, Net, Tips, Total Withdrawn)
* Row columns: Date, Type, Description, Reference (linkable), Currency, Amount (signed), Balance After
* Compatible with QuickBooks, Xero, Wave, and spreadsheet tools

**Money-Flow Integrity**

* Tip idempotency key standardised to the tip sub-order ID so repeated tips on the same parent credit correctly
* Milestone-contract parent auto-completion now routes through `OrderService::update_status()` so vendor stats, review prompt, and the full completion hook chain fire
* Buyer-request conversion wraps the bulk milestone insert in a transaction and defers `wpss_milestone_proposed` hooks until after commit — partial failures no longer leak emails
* Email rate-limit scoped to spam-prone types only; milestone / extension / tip / proposal events are never silently dropped
* `mark_as_paid` skips the `pending_requirements` transition on tip / extension / milestone sub-orders so buyers don't receive "Complete your requirements" emails on a tip they just sent
* Pro wallet provider no longer double-credits on `wpss_order_paid` retries

**Architecture**

* Sub-order pattern generalised across tips, extensions, and milestones (shared `platform` marker, shared `wpss_order_paid` credit handler, shared 48-hour abandon-cron contract with carve-out for contract milestones) — full write-up in `docs/architecture/SUB_ORDER_PATTERN.md`
* 7 new email templates: 4 milestone (proposed / paid / submitted / approved) + 3 extension (proposed / approved / declined), each with plain-text fallback
* New REST endpoints for milestones, extensions, and proposal contract type — full mobile-app parity

**Documentation**

* New: Milestone Contracts, Paid Extensions, Proposal Contracts (Fixed vs Milestone), Earnings Ledger & CSV Export
* New developer doc: Sub-Order Pattern (explains the shared architecture for contributors)

= 1.0.0 - 2026-04-02 =

**Marketplace Core**

* Complete Fiverr-style service marketplace with standalone checkout
* Multi-step service creation wizard with live preview
* Three-tier pricing packages (Basic, Standard, Premium) with custom features
* Service add-ons and extras for upselling
* Image gallery, video embeds, FAQs, and requirements per service
* Category and tag organization with drag-and-drop ordering

**Order Workflow**

* Complete order lifecycle with 11 distinct statuses
* Requirements collection before work begins
* File delivery system with buyer approval workflow
* Built-in messaging per order with file attachments
* Revision request and deadline extension management
* Buyer-initiated order cancellation with vendor response flow

**Vendor System**

* Vendor registration with open, approval, or closed modes
* Seller level progression with automatic and admin-granted tiers
* Unified vendor dashboard with earnings, orders, and analytics
* Portfolio showcase, vacation mode, and profile customization
* Commission tracking and withdrawal requests with admin approval

**Buyer Features**

* Post buyer requests for vendors to bid on
* Browse and compare vendor proposals
* Favorites/wishlist, optional tipping, and complete purchase history

**Reviews, Disputes, and Notifications**

* 5-star multi-criteria rating system with moderation queue
* Structured dispute workflow with admin mediation and multiple resolution types
* 26 configurable email notification types with template overrides
* In-app notification center

**Payments and Earnings**

* Standalone checkout with offline gateway (no WooCommerce required)
* Global and per-vendor commission rates (0-50%)
* Earnings dashboard with automated withdrawal scheduling

**Developer Features**

* 21 REST API controllers with 125+ endpoints and batch endpoint for mobile apps
* 100% REST coverage for all user-facing features
* 6 Gutenberg blocks and 19 shortcodes
* Template override system compatible with any theme
* SEO schema markup with Yoast and RankMath integration
* 9 extension hooks for Pro plugin integration
* WP-CLI commands for bulk operations
* 17 custom database tables with PSR-4 autoloaded architecture
* 100+ action and filter hooks for extensibility
* Post-activation setup wizard with demo content importer
* WP 6.7+ compatible (lazy-loaded translations)

== Upgrade Notice ==

= 1.1.0 =
Adds Upwork-style milestone contracts on buyer-request orders, paid extensions on catalog orders, vendor intro video, and (Pro) an Earnings Ledger with CSV export. Includes money-flow integrity fixes — safe to upgrade.

= 1.0.0 =
Initial release of WP Sell Services. Transform your WordPress site into a complete service marketplace with vendor management, order workflow, and commission system.
