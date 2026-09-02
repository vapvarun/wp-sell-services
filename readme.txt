=== WP Sell Services ===
Contributors: wbcomdesigns
Tags: marketplace, freelance, services, standalone, fiverr
Requires at least: 6.4
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 1.7.0
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
* Accept multi-phase milestone contracts, paid one phase at a time in order
* Buyer dashboard for order tracking
* Add services to favorites/wishlist
* Optional tipping for exceptional work
* Complete purchase history

**Milestone Contracts & Paid Extensions (1.1.0)**

* Upwork-style milestone contracts on buyer-request orders - each phase unlocks once the one before it is signed off
* Paid extensions on catalog orders for mid-order add-ons
* Mutual exclusion: a single order surfaces milestones OR extensions, never both
* Ad-hoc milestone additions when scope grows mid-contract
* Auto-complete the parent order once every phase is finished (approved, declined or cancelled)
* Phase, tip and extension payments are supported on Standalone and WooCommerce

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
* Free version includes Stripe, PayPal, and Offline gateways. Pro adds Razorpay gateway plus WooCommerce, EDD and FluentCart checkout integrations.
* Milestone, tip and extension payments are supported on Standalone and WooCommerce

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

* **E-commerce Platforms**: WooCommerce, Easy Digital Downloads, FluentCart
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
* **Expanded Service Limits**: Unlimited gallery images, FAQs, add-ons, and requirements
* **Display Currency**: Show shoppers prices in their own currency while your base currency stays authoritative

= What Makes This Different =

Unlike simple directory plugins, WP Sell Services provides a complete transaction platform with order management, messaging, deliverables, and dispute resolution built-in. You get everything needed to run a professional marketplace from day one.

= Mobile App Ready =

The complete REST API with 23 controllers makes building iOS and Android apps straightforward. The batch endpoint allows mobile apps to execute multiple requests efficiently in a single HTTP call.

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

No. WP Sell Services includes a built-in standalone checkout system with Stripe, PayPal, and Offline payment gateways. Your marketplace is fully functional out of the box — service listings, vendor management, order workflow, messaging, reviews, dispute resolution, and checkout all work without any additional plugins. The Pro version adds Razorpay as an additional gateway, plus WooCommerce, EDD and FluentCart integrations for sites that prefer those platforms.

= Does this work with my WordPress theme? =

Yes. WP Sell Services is designed to work with any well-coded WordPress theme. All frontend templates can be overridden by copying them to your theme's `wp-sell-services/` directory for customization.

= Can I run a multi-vendor marketplace? =

Yes. Any registered WordPress user can apply to become a vendor. Administrators control vendor approval, set commission rates (global or per-vendor), manage service moderation, and oversee the entire marketplace.

= How does the commission system work? =

Set a global commission percentage (0-50%) in settings. The commission is calculated and deducted from the vendor's earnings automatically. On a service order that happens when the order completes; on a tip, milestone phase or paid extension it happens as soon as the buyer pays, so that money reaches the vendor's wallet before delivery rather than being held. Vendors can request withdrawals of their available balance. You can also set custom commission rates for individual vendors.

= Can buyers post job requests? =

Yes. Buyers can post project requests with budget range, description, and deadline. Vendors browse these requests and submit custom proposals with pricing and delivery time. Buyers review proposals and accept the one they prefer.

= What payment gateways are supported? =

The free version includes a standalone checkout with Stripe, PayPal, and Offline payment gateways — no e-commerce plugin required. The Pro version adds Razorpay as an additional gateway, plus WooCommerce, Easy Digital Downloads and FluentCart checkout integrations for sites already using those platforms.

One difference worth knowing: paying a single existing amount (a milestone phase, a tip, a paid extension) is supported on Standalone and WooCommerce. If your marketplace needs milestones, tipping or paid extensions, run it on one of those two.

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

= 1.7.1 - September 2026 =

* Fix      - Refunds made in the PayPal or Razorpay dashboard now update the order and reverse the vendor credit.
* Fix      - A Stripe payment webhook arriving before checkout finishes no longer creates a duplicate order, and cart refunds land on the right order.
* Fix      - Offline and manual gateway refunds are marked as pending manual payment instead of being reported as sent.
* Fix      - Service content limits apply on every save path, and a proposal can only be accepted once.
* Fix      - New orders carry the package's revision count, so buyers can request revisions and vendors see the revision notes.
* Fix      - Deleting a user keeps and anonymises the other party's orders, ledger and reviews instead of deleting them; order files are removed when an order is deleted.
* Improve  - Personal data export now covers disputes, deliveries, requirements, withdrawals, proposals, reports and notifications.
* Fix      - Demo delete removes only demo content, and every writing CLI command asks for confirmation and refuses on production without --force.
* Fix      - Pay buttons for tips, milestones, extensions and proposals are hidden on a store rail that cannot take a single-order payment instead of linking to a page it ignores.
* Security - Message, contact and dispute attachments are stored privately like deliveries, dispute evidence checks ownership, and vendor payout details are encrypted at rest.
* Dev      - Stored files record which storage provider holds them.

= 1.7.0 - August 2026 =

Tax shown at checkout is now actually charged, order files are served through a permission check instead of an unlisted URL, and a partial refund no longer returns the whole order total. Several screens that contradicted themselves now say one thing.

* New      - A submitted milestone phase can be sent back for changes, with your notes reaching the seller and the order conversation.
* New      - A completed milestone contract opens with a Project complete summary: phases approved, total paid, and the date it finished.
* New      - Offline payments can have several named methods, each with its own instructions and its own on/off switch, edited from Settings > Payment Gateways.
* New      - The method a buyer chose is recorded on the order, so renaming or removing a method later never changes what a past order says.
* New      - My Orders has status filter chips. Nothing is hidden by default; the list is ordered so the orders needing the buyer come first.
* New      - A marketplace Create Account page, mapped like the other pages. Register links across the site point at it instead of the WordPress login screen.
* New      - A logged-out buyer reaches checkout with their package and add-ons intact and creates their account there, rather than meeting wp-login.php with their selection lost.
* New      - The owner is warned when payments are live and no Terms page is mapped. Dismissible, and it returns if another gateway is enabled.
* New      - Marketplace data reaches the WordPress privacy tools: orders, messages, reviews, profile and earnings are exported and erased with the account.
* New      - Message emails can be held for a few minutes and re-checked before sending, so a reply read on site does not also arrive by email.
* New      - Pretty URLs for viewing an order and for paying one.
* Improve  - The Become a Vendor page leads with what the marketplace offers, how selling works, and the sign-up form, instead of a narrow card in an empty page.
* Improve  - The buyer's brief reads the same way for the buyer, the seller and the site owner.
* Improve  - Buyer-facing prices carry what a display-currency switcher needs, so a converted hint can appear on request budgets, proposals, cart lines and the order confirmation rather than only on service cards.
* Improve  - Order files are stored outside the web root and served through a link that checks the caller is the buyer, the seller or an admin.
* Improve  - Order files are protected on IIS as well as Apache.
* Improve  - Files uploaded before this release move into the protected store the first time someone opens them, so existing links keep working and stop being public as they are used.
* Improve  - Checkout prefills a signed-in buyer's name and email from their account instead of asking for what the site already knows.
* Improve  - The vendors directory lays out correctly beside a sidebar: avatars are round, metadata lines up across cards, and the column count yields when tiles get too narrow.
* Improve  - A vendor whose balance is below zero is told what it means and what clears it, rather than shown a bare minus figure labelled Available for Withdrawal.
* Improve  - The owner is shown which vendors hold a negative balance, on the Vendors screen.
* Improve  - The Become a Vendor page states the real service limit for the site rather than promising unlimited listings, and no longer promises a Pro analytics dashboard.
* Improve  - The conversation panel on an unpaid order says when messaging opens instead of inviting a message it will refuse.
* Improve  - The Payouts screen describes what the plugin does with the money rather than implying automatic payment.
* Fix      - Tax shown at checkout was never charged: the buyer approved one total and was billed the package price without it.
* Fix      - The order then applied that tax a second time, and commission was taken on the tax as well as on the price.
* Fix      - A milestone contract showed a total of 0.00 on the order summary and on the service line even after every phase was paid.
* Fix      - The buyer's brief was not shown when the service had no requirement questions, leaving the seller nothing to work from.
* Fix      - Paying a tip, a paid extension or a milestone phase sent the buyer to a requirements step that does not apply to those orders.
* Fix      - My Orders counted different orders in its summary cards than in its filter chips, so the two disagreed.
* Fix      - Orders with no platform recorded were left out of a buyer's order counts entirely.
* Fix      - A partial refund returned the buyer the entire order total. The admin screen had no amount field and the refund ran with no amount, which was read as everything.
* Fix      - A withdrawal could ADD to a vendor's balance. One legacy row stored a negative amount and the sign was applied again, overstating one vendor by 100.00.
* Fix      - A disabled payment gateway could still start a payment if a stale checkout page was submitted.
* Fix      - Paying an existing order by an offline method recorded the gateway but not which method, so those orders showed the wrong instructions.
* Fix      - Defining named offline methods left buyers with no payment instructions at all, because the instructions were read from the setting the migration removes.
* Fix      - Service add-ons saved in the admin did not appear on the single service page.
* Fix      - A disputed order had no link to its dispute; it could only be reached from the Disputes menu.
* Fix      - Eleven order actions were vetoed by a status list that had drifted from the actions themselves.
* Fix      - status__not_in on GET /orders is declared in the API schema, on the route that reads it rather than one that ignores it.
* Fix      - Several screens named the same thing two different ways, and a dashboard link named a section it did not open.
* Security - A suspended vendor kept full access to the money REST surface. Reads were login-only while writes were vendor-gated, and the vendor gate never consulted the suspension check.
* Dev      - New filters wpss_order_status_groups, wpss_offline_methods, wpss_offline_method_slots and wpss_vendor_benefit_listings_copy.
* Dev      - New hooks wpss_vendor_pitch_stats, wpss_vendor_pitch_steps, wpss_requirement_field_label and wpss_milestone_revision_requested.
* Dev      - A generated hook reference covering every hook in both plugins, regenerated from source and checked on every commit.
* Dev      - Five tables that no install has created for several releases are named in the schema with a pointer to where their data lives now, and reported by wp wpss preflight.
* Compat   - Requires WP Sell Services Pro 1.7.0. Install both updates together.

= 1.6.0 - August 2026 =

Security fixes for the mobile sign-in, a marketplace that credits vendors on every payment platform, fixes for screens that could white-screen or silently do nothing, and a large cut in the queries the marketplace pages run.

* New      - Buyers can upload proof of an offline payment and an admin can approve or reject it, which marks the order paid and credits the vendor through the same path as a card payment. Off by default.
* New      - A paid order has a printable receipt the buyer can save as a PDF.
* New      - Email preferences are role aware, so a buyer is offered the five categories that apply to them rather than a vendor's eight.
* New      - The plugin does not email someone about a message while they are actively using the site. Off by default, and the window is filterable with wpss_presence_window.
* New      - Owners choose which billing fields checkout collects. Name and email are always collected; address fields suit physical goods and can be switched off for digital services.
* New      - [wpss_my_orders] is paginated. A buyer with more than one page of orders can now reach the rest of them.
* New      - [wpss_seller_card] renders a single seller card, so classic-editor and page-builder sites can place one without the block.
* New      - Buyer request blocks accept budget bounds, matching what the shortcode always supported.
* New      - Catalog prices carry their base amount in the markup, so a display-currency add-on can show an approximate price in the shopper's own currency without changing what is charged.
* New      - The order modal total now carries the same information, so the estimate follows the price as extras and quantity change.
* New      - New action wpss_payable_total_after fires wherever a payable total is shown, on both the cart summary and the checkout, so an add-on can state the charge currency at the last screen before payment.
* New      - Buyers can create their account during checkout instead of meeting a sign-in wall before they can pay. Off by default.
* New      - Members can see every device they are signed in on and revoke any one of them, without signing out everywhere.
* New      - Owners can point the site's header cart link at the marketplace cart, for sites running WooCommerce beside the built-in checkout. Off by default.
* New      - A Vendors directory page is created at install, so the Pages setting is no longer a mapping with nothing behind it.
* Improve  - An order now records the package the buyer actually bought, so editing or reordering a service later never changes what an existing order says was purchased.
* Improve  - Packages carry a stable id that does not shift when packages are reordered, and the REST API publishes it.
* Improve  - Every payment platform reports order status through one shared map, replacing three separate maps that disagreed with each other.
* Improve  - The member dashboard and [wpss_account] are one screen rather than two that had drifted apart, so both show the same sections.
* Improve  - Conversation messages render through one shared renderer everywhere, with clickable links and working attachments.
* Improve  - The setup wizard stops prompting a site that is already configured.
* Improve  - The plugin uses a single accent colour; the second, unrelated green palette has been retired.
* Improve  - Vendor and service grids run far fewer queries. A vendor grid dropped from 49 queries to 9 and a service grid from 70 to 30, with identical output.
* Improve  - Category choosers no longer load every category on the site. The limit is 200 and filterable with wpss_category_terms_limit.
* Improve  - Shortcodes and blocks that show the same thing now share one renderer and one template, so a theme override applies to both and they cannot drift apart again.
* Improve  - Prices are marked up identically for every visitor, so a page cache can never serve one shopper's currency to another.
* Improve  - Saving a vendor profile confirms with the same toast used everywhere else, rather than a banner above the fold the member was not looking at.
* Improve  - Losing bidders on a buyer request are told in the app rather than by email, so deciding a busy request no longer sends a burst of rejections.
* Improve  - The dashboard messages list is paginated, so a member with many conversations no longer loads all of them at once.
* Improve  - SureCart is no longer advertised in Settings or on the Upgrade page, which described an integration Pro does not include.
* Fix      - Vendors could not submit a fixed-price proposal. A hidden milestone field was marked required, so the browser blocked the form and reported nothing the vendor could see.
* Fix      - Orders placed before this release could show the wrong package name and price once a service was edited. Existing orders are repaired on update using what the buyer actually paid.
* Fix      - The category archive listed buyer requests alongside services.
* Fix      - Pages with the same title could not be told apart in the Pages settings dropdowns.
* Fix      - The dashboard Disputes section white-screened for everyone, in both the list and the detail view. It now renders.
* Fix      - An offline order could not be marked as paid from the admin. The control existed only on a screen that was never reachable.
* Fix      - The admin order detail screen fataled for every order.
* Fix      - Cancelling a dispute returned an error and left the order stuck as disputed. The cancel now completes or rolls back as one unit.
* Fix      - [wpss_vendors], [wpss_top_vendors], [wpss_vendor_profile] and the vendor sections of [wpss_account] fataled instead of rendering.
* Fix      - The buyer requests block listed expired requests that the shortcode correctly hid, so sellers could pitch for closed work.
* Fix      - columns="" was ignored by the services and categories grids, which always rendered a single stacked column.
* Fix      - Category cards and the search form rendered differently depending on whether the block or the shortcode was used.
* Fix      - A dispute opened automatically for a late order stored a translated label where a reason code belongs, which left non-English sites with an untranslatable reason.
* Fix      - The disputes table was unreadable on phones, with values pushed to the right against blank labels.
* Fix      - Demo data sized vendor withdrawals against earnings still inside the clearance window, leaving negative available balances, and re-running the seeder credited every order again.
* Fix      - Docblocks across the plugin cited @since versions that were never released; every one now names the version it actually shipped in.
* Fix      - On a site running Easy Digital Downloads, a checkout could complete and mark itself paid before the marketplace had loaded, so no order was created and no vendor was credited.
* Fix      - Accepting a proposal created an order whose service resolved to whichever page happened to be rendering, so the order showed the wrong title.
* Fix      - Paying for an existing order offline never recorded the payment method, leaving an order no admin could confirm and no buyer could send proof for.
* Fix      - Proposals produced no in-app notification at all; the notification types had existed since the first release and nothing ever wrote one.
* Fix      - A dispute conversation was stored in two places, so each screen showed only half of it.
* Fix      - A completed order with an open dispute showed no sign of that dispute on the admin order screen.
* Fix      - A completed order hid the review prompt but never showed the review the buyer had already written.
* Fix      - Vendor links across the site landed on the directory instead of the vendor's own profile.
* Fix      - The Become a Vendor page offered registration to members who were already vendors.
* Fix      - The messages unread count was hidden by a stylesheet rule, so a correct number rendered as a bare dot.
* Fix      - Settings deep links used a query argument the settings screen no longer reads, and five of them pointed at the wrong section.
* Fix      - The Create Service page was published but empty; it now sends the member to the service wizard.
* Fix      - The cart page took the generic /cart/ slug and accumulated orphaned duplicates on sites that already had one.
* Fix      - The admin screens never loaded the design tokens, so the retired green palette survived in every colour fallback.
* Fix      - The single service sidebar did not stick at all when a theme clipped overflow, so the price and Order button scrolled out of reach.
* Fix      - The sticky offset assumed the WordPress admin bar was the only fixed bar on the page and ignored a theme's own sticky header.
* Fix      - Mapped pages rendered two headings, the theme's and the plugin's.
* Fix      - The earnings banner offered a withdrawal to vendors whose balance was negative, because it counted money from orders still in progress.
* Fix      - Ten JavaScript strings were never sent for translation, so every message the favourites feature shows a buyer appeared in English in all locales.
* Fix      - The skip-email-when-online setting could never fire, because the code that records when a member was last active was never hooked to anything.
* Fix      - The cart accepted a stable package id and returned an array position, so a client could not match a cart line back to the package it bought.
* Fix      - The setup wizard rendered an empty browser tab title once setup was complete.
* Security - Mobile app tokens now expire, 30 days after last use or 90 days after they are issued, and are rejected at authentication instead of staying valid forever.
* Security - An app token can no longer be presented in place of the account password to mint further tokens.
* Security - The public vendor search no longer matches WordPress login names, which allowed usernames to be discovered one letter at a time.
* Dev      - GET /orders/{id} answers 404 for an order that does not exist, rather than 403.
* Dev      - An admin-only denial answers wpss_not_admin instead of reusing the ownership code.
* Dev      - The REST error codes are documented, with a committed contract smoke that checks them across anonymous, buyer, vendor and admin callers.
* Dev      - New actions wpss_payment_receipt_submitted, wpss_payment_receipt_verified and wpss_payment_receipt_rejected.
* Dev      - Orders record the paying platform's own order reference, so a platform that uses string ids can be linked back to its order.
* Dev      - Helper functions moved from one 6,187-line file into eleven files grouped by domain. No function was renamed or resignatured, so no call site changes.
* Dev      - wpss_admin_order_actions now fires on the order screen admins actually use.
* Dev      - New wpss_category_card_link and wpss_category_card_classes filters, and a category-card template that themes can override.
* Dev      - Translation templates regenerated for both plugins, and ambiguous translator comments corrected.
* Dev      - Hardcoded English fallbacks removed from JavaScript across both plugins, so every message shown to a user is translatable.
* Dev      - The translation check now fails when the plugin version and the translation template disagree, which is how a template for the previous version shipped before.

* Dev      - One actor shape across the API: every endpoint describes a person as id, name, avatar and deleted.
* Dev      - One date format across the API: every timestamp is ISO-8601 with an offset, replacing a mix of that and MySQL datetimes carrying no timezone.
* Dev      - One service card shape: /services and /favorites return the same keys from a single shared builder.
* Dev      - Every API change this release is additive, so the REST contract version is unchanged and existing clients keep working.
* Dev      - New GET /auth/sessions and DELETE /auth/sessions/{uuid} list and revoke a member's app sign-ins.
* Dev      - New filters wpss_app_token_lifetime and wpss_token_recovery_routes control how long a token lives and which routes stay reachable with an expired one.
* Dev      - New filter wpss_sticky_top_offset lets a theme declare the height of its own sticky header.
* Dev      - New filter wpss_messages_per_page sets the dashboard conversation page size.
* Dev      - wp wpss preflight now asks Action Scheduler, which the plugin actually uses, instead of reporting every job as unscheduled.
* Dev      - Static analysis and the full test suite run in CI; both had been failing to run at all.
= 1.4.0 - August 2026 =

Payment ownership is now unambiguous, milestone and tip payments work on WooCommerce, and the REST API tells clients the truth.

* New      - Milestone phases, tips and paid extensions can be paid on WooCommerce; the link opens a real WooCommerce order-pay page, so it works from an email with no cart session.
* New      - GET /orders/{id}/timeline returns one merged, chronological event history for an order.
* New      - GET /auth/devices lists a user's registered push devices, and POST /reviews works as an alias for the order review route.
* New      - New filter wpss_pay_order_url is the single seam for "send the buyer somewhere they can pay this order".
* New      - Returning from an off-site card authentication now shows the outcome instead of the checkout form again, so a buyer cannot pay twice for the same order.
* New      - Settings shows whether the Stripe webhook is actually receiving events, so an owner is not left guessing whether setup worked.
* Improve  - When WooCommerce, Easy Digital Downloads, FluentCart or SureCart is active, that platform now owns all payment; the plugin's own gateways and payment routes stay out of the way.
* Improve  - Switching e-commerce platform never rewrites past orders, and webhooks from the gateway that took the payment keep working.
* Improve  - Notification text is now plain, with HTML composed only where it belongs, in email.
* Improve  - Billing details are remembered on every payment method, not only Stripe, so a returning buyer confirms one line instead of retyping their address.
* Improve  - Order numbers are shorter and easier to read out to support, and no longer carry the time the order was placed.
* Improve  - Menu visibility is set one role at a time with plain "visible" toggles, rather than a grid of tick-to-hide boxes that meant the opposite of what it looked like.
* Improve  - Notifications name the order the way the rest of the site does, and a proposed milestone says which phase and how much.
* Improve  - Messages list every conversation by the person it is with, with a preview and what it is about.
* Improve  - Dashboard sections share one card style, so borders and corners match from screen to screen.
* Improve  - Every money value now carries a minor-unit integer alongside the decimal, so zero- and three-decimal currencies stay exact.
* Fix      - Refunding a WooCommerce order now also reverses the tip, milestone phase or paid extension attached to it.
* Fix      - A refund started at the payment gateway is no longer sent back to the gateway a second time, which could return double the money.
* Fix      - A partially refunded order can be completed, and the vendor is credited on what the buyer actually paid rather than the original total.
* Fix      - A vendor can no longer stack withdrawal requests past their available balance; pending requests and funds still clearing are now counted.
* Fix      - A declined card says it was declined and why, instead of asking the buyer to complete an authentication step that does not exist.
* Fix      - The settings API reports an unmapped page as null rather than 0, so an app no longer builds a link to a page that cannot open.
* Fix      - An existing terms page on the site is picked up automatically instead of the setting staying empty.
* Fix      - The REST API answers 401 for a caller with no session and 403 for one who is signed in but not allowed; a client refreshing an expired token no longer reads it as a permanent denial.
* Fix      - A 403 now names its reason so an app can act on it: wpss_not_vendor, wpss_vendor_pending, wpss_not_owner, wpss_not_admin, wpss_cannot_create or wpss_service_limit_reached, instead of one generic code for every refusal.
* Fix      - Submitting a delivery over the REST API now records it and notifies exactly as the dashboard does.
* Dev      - Removed the order actions accept and reject; they had no transition behind them. An order is accepted by being paid.
* Dev      - Removed the hooks wpss_order_accepted, wpss_order_rejected and wpss_order_delivered. Use wpss_order_paid, wpss_order_cancelled, and wpss_delivery_submitted / wpss_delivery_accepted.
* Dev      - The payment routes register only on the standalone rail; on a cart platform they are absent and answer rest_no_route.
* Dev      - Translation catalogs (.mo and .json) are generated in the build, with a CI gate to keep them current.
* Dev      - Every user-facing string in JavaScript is now translatable, including the whole wallet transactions table, which was English in every locale.
* Dev      - An accepted proposal records which order it produced, and existing accepted proposals are linked on upgrade where the match is unambiguous.
* Dev      - The dashboard credit is off by default; owners who want it can switch it on with the wpss_show_powered_by filter.
* Dev      - Documented the pay-order seam, the two REST namespaces, the milestone failure paths, and the three money settings tabs.
* Compat   - Aligned with WP Sell Services Pro 1.4.0. Install both updates together.
* Compat   - Milestone, tip and extension payment links are supported on Standalone and WooCommerce.

= 1.3.0 - July 2026 =

Large stability and polish release. Payments hardened across every gateway, full dark-mode support on all frontend surfaces, and a lighter first-run setup.

* New      - Role-based menu visibility so you can show or hide marketplace menu items per user role.
* New      - Frontend dispute messaging: buyer and vendor can message and attach evidence on a dispute without leaving the order.
* Improve  - Full dark-mode support on every frontend surface, following the active theme's toggle (BuddyX, BuddyX Pro, Reign) instead of the OS setting.
* Improve  - The setup wizard no longer forces a payment gateway as the first step; offline payment works out of the box and the wizard guides you to configure a gateway when ready.
* Improve  - Fresh installs are sell-ready: manual/offline payment enabled, default service categories seeded, and new services publish without forced moderation.
* Improve  - Reworked the Upgrade screen with an in-plugin Pro feature tour, and pointed the upgrade calls-to-action at the product site so frontend vendors are not sent to wp-admin.
* Improve  - Added a Log Out link to the frontend dashboard navigation.
* Improve  - Add-to-cart now continues straight to checkout so the "Continue to Checkout" action matches its label.
* Improve  - Accessibility pass: visible keyboard-focus indicators, service metabox field labels, and search input plus category select ARIA labels.
* Fix      - Buyer request cards keep their actions on a single line as icon controls on mobile and tablet instead of overflowing off the card.
* Fix      - Dark mode no longer renders dark text on dark surfaces anywhere in the dashboard or on frontend pages.
* Fix      - PayPal checkout sends the full checkout context, handles multi-cart orders server-side, submits through its own seam, and no longer shows a dead Pay button.
* Fix      - Money precision now respects 0- and 3-decimal currencies, and refunds round to the currency's precision instead of a hardcoded two places.
* Fix      - Stripe pay-order flow for milestone phases and proposal orders creates the payment intent correctly.
* Fix      - Paused services can no longer be ordered from the service call-to-action, the order modal, or the cart API.
* Fix      - The order call-to-action is guarded against zero-price or unpriced packages.
* Fix      - The purchased item is cleared from the cart after a single checkout.
* Fix      - A buyer could get stranded on a pending-requirements order with no way to proceed; the flow now always offers a next step.
* Fix      - A purchase no longer appears under Sales Orders the moment the buyer pays.
* Fix      - Vendor public profiles no longer return 404 when a legacy user meta value is absent.
* Fix      - The service-search shortcode now submits to the services archive with moderation and vacation filtering, not WordPress core search.
* Fix      - Requirement choices use one schema across the wizard, admin, and buyer views, and drag-dropped requirement files now submit.
* Fix      - Admin service save preserves Unlimited (-1) revisions and allows clearing FAQs and requirements.
* Fix      - Confirmation prompts for milestones and order actions use the design-system dialog and are always clickable.
* Fix      - Dead "Upgrade to Pro" links in the service wizard now resolve to the correct destination.
* Fix      - Consolidated duplicated service-grid, empty-state, and card styles so the same component renders consistently on every surface.
* Dev      - Added a gateway-agnostic CheckoutIntent seam (resolve plus settle) so payment gateways plug in uniformly.
* Dev      - Base currency is now authoritative for money, with a display-currency hint provided through a catalog price-html seam.
* Dev      - The free plugin reaches zero wp i18n make-pot warnings, with translator comments added throughout.
* Compat   - Aligned with WP Sell Services Pro 1.3.0. Install both updates together.

= 1.2.2 - July 2026 =

Migrated guest reviews now keep their original reviewer name instead of showing "Anonymous".

* Fix      - Migrated guest reviews with no linked account now show the original reviewer name across service pages, vendor profiles, and review moderation.
* Fix      - Review author name in the REST API and SEO review schema no longer falls back to "Anonymous" for migrated guest reviews.
* Dev      - Added a nullable reviewer_name column to the reviews table and a shared reviewer-name resolver so every review surface renders names consistently.

= 1.2.1 - July 2026 =

Currency now works correctly out of the box and displays consistently across every surface.

* Improve  - Unified WooCommerce-style currency system so prices are formatted correctly out of the box.
* Fix      - Currency-aware price display and inputs across all admin and frontend surfaces.
* Dev      - Resolved all PHPStan analysis errors and repaired the static-analysis tooling.

= 1.2.0 - June 2026 =

Full audit and hardening sprint. Every customer-facing surface rebuilt on the shared design system, all known issues from the 1.1.x cycle resolved.

* New      - Real-time updates (optional): live order messages and notification badges over WebSockets, powered by Pusher.com or a self-hosted Pusher-compatible server such as Soketi. Disabled by default; enable under Settings - Advanced.
* New      - Review Moderation admin page - approve, reject, and audit customer reviews with status filters and a four-card stats strip.
* New      - My Notifications admin page - a read-only mirror of your in-app marketplace notification stream.
* New      - Wallet ledger entries now link straight to the related order, tip, extension, or milestone.
* Improve  - Unified dashboard shell across every frontend template so Services, Dashboard, and profile sections read as one product.
* Improve  - Premium UX uplift on all frontend surfaces - token-driven styling, designed empty states, consistent buttons, badges, and notices at AA contrast.
* Improve  - Lucide line icons throughout, replacing emoji in notifications, single-service highlights, and helpful-vote controls.
* Improve  - Dashboard sections now use pretty permalinks (/dashboard/section/) with a 301 fallback for legacy ?section= URLs.
* Improve  - The earnings area is now "Earnings & Payouts" with manual withdrawal plus extension points so Pro can add PayPal and Stripe payout rails in the same section.
* Improve  - Confirmation prompts and toast messages use a shared design-system dialog instead of the browser native pop-ups.
* Improve  - Marketplace pages (Dashboard, Cart, Checkout, Become a Vendor) now render full-width without the theme blog sidebar, with native layout support for the Reign and BuddyX themes and a sidebar-free fallback for any other theme.
* Improve  - Opening the dashboard lands active vendors on their Sales overview and buyers on My Orders.
* Improve  - Plugin admin screens hide unrelated third-party notices so the marketplace pages stay focused.
* Fix      - Services archive no longer shows visitor-scoped data when set as the site front page.
* Fix      - Services archive and single templates - card borders, category-label overlay, button backgrounds, transparent popups, and sidebar filters.
* Fix      - Favorites now read and write a single canonical meta key, with a one-time lazy merge of the legacy key.
* Fix      - Resubmitting a rejected service re-queues it for moderation and clears the prior rejection reason.
* Fix      - Dashboard section header keeps the title on the left and groups the primary action with the Replay tour control on the right, with a clean wrap on mobile.
* Fix      - The set-up-payouts prompt no longer repeats on every dashboard tab; it stays within the Earnings & Payouts section.
* Fix      - Order conversation messages can be sent with only an image attached and no text.
* Fix      - Saving settings now shows a confirmation message instead of completing silently.
* Fix      - Service add-ons created from the admin service editor or WP-CLI now appear in the cart and checkout, not just the order modal.
* Fix      - Vendor vacation mode is now honored on the single service page - a notice banner shows the seller's message and return date and the order button is disabled, so buyers cannot purchase while the seller is away. Vendors and admins can set an optional return date.
* Dev      - REST status parameter on service update (publish or draft), owner-gated.
* Dev      - Service wizard extension hooks (wpss_wizard_service_data, wpss_wizard_pricing_after, wpss_wizard_sanitize_service_data, wpss_wizard_save_service_meta) let extensions inject fields into the frontend Create Service flow. New filters wpss_use_fullwidth_template, wpss_fullwidth_page_keys, wpss_dashboard_default_section, and wpss_realtime_settings.
* New      - Review moderation can now be switched on under Settings - Vendor; new reviews hold for approval and the API reports the same state.
* New      - Dispute timing is configurable: response window, reminder delay, auto-escalation, and a dedicated dispute notifications email under Settings - Orders.
* New      - Order amount limits, price decimal places, deadline-extension cap, buyer-request expiry, portfolio caps, and audit-log retention all have settings fields instead of fixed defaults.
* New      - Order Confirmation and Terms pages can be mapped under Settings - Pages.
* New      - Wallet provider selection under Settings - Payouts; the free plugin and Pro now resolve the same configured provider.
* Improve  - Vendor profile data (tagline, rating, review count, country, social links) now reads from the canonical vendor profile store everywhere: SEO schema, the vendors API, the Seller Card block, and proposal lists.
* Improve  - Single service pages show the vendor's real last delivery date.
* Fix      - Vendor SEO schema now includes the seller's title and aggregate rating; previously both were always empty.
* Fix      - Vacation mode and other profile changes persist reliably; failed saves now report an error instead of false success, and missing database columns self-heal on update.
* Fix      - Per-member email notification preferences are honored when sending; muted categories no longer email.
* Fix      - Admin service editor shows recurring billing fields and saves them with the same keys as the frontend wizard.
* Fix      - Admin Process Refund button performs the refund through the payment gateway instead of showing a placeholder notice.
* Fix      - Single service sidebar sticks smoothly while scrolling and scrolls internally when taller than the screen.
* Fix      - Delivery time and revision counts are consistent across the wizard, admin editor, REST API, SEO schema, and archive filters.
* Fix      - Vendors approved by an admin can set their PayPal payout email from their profile and it now saves.
* Security - Gateway settings pages no longer print saved secret keys into the page HTML; secret fields are masked and keep the saved value when left blank.
* Dev      - New filters wpss_show_powered_by, wpss_stripe_refund_args, wpss_auto_approve_vendors and wpss_require_service_moderation (now live at the decision points), and action wpss_vendor_profile_saved on both profile save paths.
* Dev      - Static contract audit gate added to the release pipeline; the free/pro pair ships with a clean audit baseline.
* Dev      - audit/manifest.json refreshed; full inventory of REST routes, hooks, tables, shortcodes, and admin pages.

= 1.1.1 =

**Install & reliability**

* Fix: The plugin now activates cleanly from the downloaded zip on every install. Previously, on some hosts the plugin could fatal-error on activation if the install was missing a runtime dependency. The release zip now bundles everything the plugin needs to run.
* Improvement: The release zip and the source repository are now both complete and self-contained - no extra setup step is required after install.

= 1.1.0 - 2026-04-23 =

**Admin UX Consistency**

* Vendors, Withdrawals, and Moderation admin pages now share the same shell - wrapper, heading, stats strip, and filter row - so operators see a consistent surface regardless of which list they open
* Moderation gains a 4-card stats strip (Total / Pending / Approved / Rejected) matching the other two listing pages
* Stats cards now use a single responsive grid that collapses from 5-up on desktop to 2-up on small phones, with no per-page styling required
* Shared status-color palette - green for active/approved/completed, amber for pending, red for suspended/rejected

**First-Time Guide (Admin + Frontend)**

* New: An 8-step admin walkthrough auto-opens on the WP Sell Services dashboard the first time an administrator lands there, covering the dashboard cards, services, vendors, orders, and settings.
* New: A role-aware frontend walkthrough on the `[wpss_dashboard]` shortcode. Active sellers get a 9-step tour covering Orders, Requests, Services, Sales, Earnings, and Messages. Buyers-only see a shorter flow with a "Want to sell too?" prompt highlighting the Start Selling button.
* New: A "Replay tour" button on both the admin and frontend dashboard headers so anyone can re-run the walkthrough on demand. Once finished or skipped, the tour never auto-opens again for that user.
* For developers: New `wpss_tour_steps` filter and `POST /wpss/v1/tour/complete` REST endpoint let Pro (and other extensions) append custom steps and integrate with the completion flag.

**Reliable background jobs**

* Improvement: Every recurring job the plugin runs (order lifecycle sweeps, dispute deadlines, sub-order cleanups, auto-withdrawal, vendor-stat refresh, seller-level recalc) is now powered by the more reliable Action Scheduler library. You can review and replay any background job from Tools &gt; Scheduled Actions. Page loads are no longer slowed down when the dispute cron is due.
* Upgrade path: on the first admin page load after upgrading from a pre-1.1.0 install, the plugin migrates its old scheduled jobs to the new system automatically - no action required from you.

**Empty-state polish**

* Improvement: Empty lists (Orders, Disputes, the buyer-orders tab on the frontend dashboard, and the vendor profile services section) now show a designed empty state with an icon, a one-line explanation, and a call-to-action button - instead of a bare "No X found." sentence.
* Improvement: Orders and Disputes admin screens now use the same card-shell layout as Vendors, Withdrawals, and Moderation, so all six listing screens look and behave consistently.
* New: Help tabs added to the Orders and Disputes admin screens, linking to the plugin docs and workflow guides.

**Database housekeeping**

* Improvement: The plugin's database setup / teardown code is now refactored so that adding or removing a plugin table is a single-line change. Uninstall removes tables in the correct dependency order - no more orphaned data after deactivation + delete.

**Milestone Contracts (Upwork-style)**

* Vendors choose Fixed or Milestone contract type when submitting a proposal
* Milestone proposals carry a phase repeater: title, description, amount, days
* Buyers compare proposals with a phase-count badge and see the full breakdown before accepting
* Acceptance pre-creates every phase on the order timeline - no upfront parent checkout
* Lock-step payment enforced on the server: phase N only unlocks after every earlier phase is approved or cancelled
* Ad-hoc milestones can still be proposed during a contract for legitimate scope changes
* Parent order auto-completes when every phase is terminal - standard completion email and review prompt fire
* Cancellation rules: paid phases stand, unpaid phases auto-cancel, paid-but-open phases route through dispute

**Paid Extensions (catalog orders)**

* Vendors on in-progress catalog orders can quote extra work with a price and extra days
* Buyers accept and pay, or decline with one click
* Commission is split at payment time; vendor wallet credited immediately
* Parent order deadline extends by the quoted days on acceptance
* Extensions are mutually exclusive with milestone contracts - a single order only ever shows one flow

**Vendor Intro Video**

* Vendors can add an Introduction section with a short intro video to their public profile
* Supported sources: MP4 upload or YouTube embed
* Renders above the vendor's tagline and bio on the profile page

**Earnings Ledger & CSV Export (Pro)**

* Wallet dashboard surfaces a dated ledger of every transaction - Earning, Tip, Extension, Milestone, Withdrawal, Credit, Debit, Dispute Refund
* Period selector: Last 30 Days, This Month, Last Month, This Year, All Time
* CSV export streams the same rows plus a summary block (Total Credits, Total Debits, Net, Tips, Total Withdrawn)
* Row columns: Date, Type, Description, Reference (linkable), Currency, Amount (signed), Balance After
* Compatible with QuickBooks, Xero, Wave, and spreadsheet tools

**Money-flow integrity**

* Fix: Sending more than one tip on the same order now credits each one correctly.
* Fix: When the final milestone of a contract is approved, the parent order now completes end-to-end - vendor stats update, the review prompt appears, and the completion email is sent.
* Fix: Converting a buyer request into a milestone contract is now an all-or-nothing operation. Partial conversions no longer leak premature notification emails to vendors.
* Fix: Buyers no longer receive a "Complete your requirements" email when they send a tip, request a paid extension, or pay a milestone. That email is only sent on the original order.
* Improvement: Email rate-limiting now applies only to high-volume notification types. Milestone, extension, tip, and proposal events are never silently dropped.
* Fix: Vendor wallets no longer get credited twice if a payment gateway retries the "order paid" event.

**Architecture**

* Improvement: Tips, paid extensions, and milestone payments now share a common credit / cleanup flow under the hood. Vendor wallets are credited consistently, and abandoned-payment cleanup behaves the same across all three.
* New: Seven new email templates for the milestone (proposed / paid / submitted / approved) and extension (proposed / approved / declined) flows. Every template ships with a plain-text fallback for clients that don't render HTML.
* For developers: New REST endpoints for milestones, extensions, and the Fixed vs Milestone proposal contract type - mobile app developers get full parity with the web frontend.

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

= 1.4.0 =
WooCommerce sites can now take milestone, tip and extension payments. A cart plugin, when active, owns all payment. The order actions accept and reject, and the hooks wpss_order_accepted / wpss_order_rejected / wpss_order_delivered, have been removed.

= 1.3.0 =
Hardens payments across every gateway, adds full dark-mode support on all frontend surfaces, and makes fresh installs sell-ready with offline payment and seeded categories. Safe to upgrade - no settings changes required.

= 1.1.1 =
Fixes an install-side fatal error that could occur on some hosts where a runtime dependency was missing from the previous release zip. The 1.1.1 zip now bundles everything the plugin needs to run, so the plugin activates cleanly out of the box. Safe to upgrade - no settings changes required.

= 1.1.0 =
Adds Upwork-style milestone contracts on buyer-request orders, paid extensions on catalog orders, vendor intro video, (Pro) Earnings Ledger with CSV export, a unified admin listing UX, a first-time admin guided tour, and moves all recurring background jobs onto a more reliable scheduler with replay support. Includes money-flow integrity fixes - safe to upgrade; on the first admin page load after upgrade the plugin migrates its existing scheduled jobs to the new system automatically.

= 1.0.0 =
Initial release of WP Sell Services. Transform your WordPress site into a complete service marketplace with vendor management, order workflow, and commission system.
