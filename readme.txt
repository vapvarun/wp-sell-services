=== WP Sell Services ===
Contributors: wbcomdesigns
Tags: marketplace, freelance, services, standalone, fiverr
Requires at least: 6.4
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 1.1.1
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

= 1.1.1 =

**Install & reliability**

* Fix: The plugin now activates cleanly from the downloaded zip on every install. Previously, on some hosts the plugin could fatal-error on activation if the install was missing a runtime dependency. The release zip now bundles everything the plugin needs to run.
* Improvement: The release zip and the source repository are now both complete and self-contained — no extra setup step is required after install.

= 1.1.0 - 2026-04-23 =

**Admin UX Consistency**

* Vendors, Withdrawals, and Moderation admin pages now share the same shell — wrapper, heading, stats strip, and filter row — so operators see a consistent surface regardless of which list they open
* Moderation gains a 4-card stats strip (Total / Pending / Approved / Rejected) matching the other two listing pages
* Stats cards now use a single responsive grid that collapses from 5-up on desktop to 2-up on small phones, with no per-page styling required
* Shared status-color palette — green for active/approved/completed, amber for pending, red for suspended/rejected

**First-Time Guide (Admin + Frontend)**

* New: An 8-step admin walkthrough auto-opens on the WP Sell Services dashboard the first time an administrator lands there, covering the dashboard cards, services, vendors, orders, and settings.
* New: A role-aware frontend walkthrough on the `[wpss_dashboard]` shortcode. Active sellers get a 9-step tour covering Orders, Requests, Services, Sales, Earnings, and Messages. Buyers-only see a shorter flow with a "Want to sell too?" prompt highlighting the Start Selling button.
* New: A "Replay tour" button on both the admin and frontend dashboard headers so anyone can re-run the walkthrough on demand. Once finished or skipped, the tour never auto-opens again for that user.
* For developers: New `wpss_tour_steps` filter and `POST /wpss/v1/tour/complete` REST endpoint let Pro (and other extensions) append custom steps and integrate with the completion flag.

**Reliable background jobs**

* Improvement: Every recurring job the plugin runs (order lifecycle sweeps, dispute deadlines, sub-order cleanups, auto-withdrawal, vendor-stat refresh, seller-level recalc) is now powered by the more reliable Action Scheduler library. You can review and replay any background job from Tools &gt; Scheduled Actions. Page loads are no longer slowed down when the dispute cron is due.
* Upgrade path: on the first admin page load after upgrading from a pre-1.1.0 install, the plugin migrates its old scheduled jobs to the new system automatically — no action required from you.

**Empty-state polish**

* Improvement: Empty lists (Orders, Disputes, the buyer-orders tab on the frontend dashboard, and the vendor profile services section) now show a designed empty state with an icon, a one-line explanation, and a call-to-action button — instead of a bare "No X found." sentence.
* Improvement: Orders and Disputes admin screens now use the same card-shell layout as Vendors, Withdrawals, and Moderation, so all six listing screens look and behave consistently.
* New: Help tabs added to the Orders and Disputes admin screens, linking to the plugin docs and workflow guides.

**Database housekeeping**

* Improvement: The plugin's database setup / teardown code is now refactored so that adding or removing a plugin table is a single-line change. Uninstall removes tables in the correct dependency order — no more orphaned data after deactivation + delete.

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

**Money-flow integrity**

* Fix: Sending more than one tip on the same order now credits each one correctly.
* Fix: When the final milestone of a contract is approved, the parent order now completes end-to-end — vendor stats update, the review prompt appears, and the completion email is sent.
* Fix: Converting a buyer request into a milestone contract is now an all-or-nothing operation. Partial conversions no longer leak premature notification emails to vendors.
* Fix: Buyers no longer receive a "Complete your requirements" email when they send a tip, request a paid extension, or pay a milestone. That email is only sent on the original order.
* Improvement: Email rate-limiting now applies only to high-volume notification types. Milestone, extension, tip, and proposal events are never silently dropped.
* Fix: Vendor wallets no longer get credited twice if a payment gateway retries the "order paid" event.

**Architecture**

* Improvement: Tips, paid extensions, and milestone payments now share a common credit / cleanup flow under the hood. Vendor wallets are credited consistently, and abandoned-payment cleanup behaves the same across all three.
* New: Seven new email templates for the milestone (proposed / paid / submitted / approved) and extension (proposed / approved / declined) flows. Every template ships with a plain-text fallback for clients that don't render HTML.
* For developers: New REST endpoints for milestones, extensions, and the Fixed vs Milestone proposal contract type — mobile app developers get full parity with the web frontend.

**Documentation**

* New: Guides for Milestone Contracts, Paid Extensions, Proposal Contracts (Fixed vs Milestone), and the Earnings Ledger & CSV Export on the docs site.
* For developers: New architecture write-up explaining how tips, extensions, and milestones share a common backend pattern (useful for extension authors).

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

= 1.1.1 =
Fixes an install-side fatal error that could occur on some hosts where a runtime dependency was missing from the previous release zip. The 1.1.1 zip now bundles everything the plugin needs to run, so the plugin activates cleanly out of the box. Safe to upgrade — no settings changes required.

= 1.1.0 =
Adds Upwork-style milestone contracts on buyer-request orders, paid extensions on catalog orders, vendor intro video, (Pro) Earnings Ledger with CSV export, a unified admin listing UX, a first-time admin guided tour, and moves all recurring background jobs onto a more reliable scheduler with replay support. Includes money-flow integrity fixes — safe to upgrade; on the first admin page load after upgrade the plugin migrates its existing scheduled jobs to the new system automatically.

= 1.0.0 =
Initial release of WP Sell Services. Transform your WordPress site into a complete service marketplace with vendor management, order workflow, and commission system.
