# WP Sell Services vs Taskbot: Complete Comparison

Choosing between WP Sell Services and Taskbot for your WordPress service marketplace? This comparison covers every feature, requirement, and trade-off.

---

## Quick Comparison

| | WP Sell Services | Taskbot |
|--|-----------------|---------|
| **Type** | Plugin (works with any theme) | Plugin (needs Taskup companion theme for design) |
| **Price** | Free on WordPress.org | $69 on CodeCanyon |
| **WooCommerce Required** | No | Yes |
| **Payment Gateways** | Stripe, PayPal, Offline (built-in) | Only via WooCommerce |
| **Order Statuses** | 11 | ~6 |
| **Seller Levels** | 4 automatic tiers | None |
| **Multi-Criteria Reviews** | Yes (communication, quality, delivery) | No (single score only) |
| **REST API** | 20 controllers + batch endpoint | Mobile-app only (limited) |
| **Gutenberg Blocks** | 6 native blocks | None (Elementor only) |
| **Hourly Projects** | Not yet | Paid addon ($29) |
| **Mobile App** | API-ready (20 controllers) | Tasklay app ($99 extra) |

---

## Architecture

**WP Sell Services** is a WordPress plugin with PSR-4 autoloading, 17 custom database tables, and standalone checkout. It works with any WordPress theme. Install it on an existing site and your marketplace is ready.

**Taskbot** is also a plugin, but it requires WooCommerce for all checkout and payment processing. For a polished look, you'll also need AmentoTech's Taskup companion theme. Without it, the frontend is barebones.

### Why this matters
- Adding WooCommerce to a site means 50+ database tables, additional JS/CSS assets, and a heavier admin panel - even if you only need it for service checkout.
- WP Sell Services ships Stripe, PayPal, and Offline gateways in its free version. No WooCommerce overhead.
- Taskbot creates WooCommerce products behind the scenes for each service. WP Sell Services uses its own clean database schema (17 purpose-built tables).

---

## Order Workflow

| Feature | WP Sell Services | Taskbot |
|---------|-----------------|---------|
| **Total order statuses** | 11 (pending_payment, pending_requirements, in_progress, pending_approval, completed, revision_requested, disputed, late, on_hold, cancelled) | ~6 (pending, active, delivered, completed, cancelled, disputed) |
| **Requirements collection** | Yes - custom forms before work starts | No |
| **Milestone payments** | Yes | Yes (projects module only) |
| **Deadline extensions** | Yes - request and approve | No |
| **Auto-complete** | Yes - configurable timer via cron | No |
| **Revision management** | Yes - per-package revision limits | Basic |
| **Tipping** | Yes - commission-free | No |
| **Order messaging** | Yes - per-order with file attachments | Yes |

WP Sell Services has the most complete order workflow of any WordPress marketplace solution. Requirements collection before work begins, deadline extension requests, and automatic completion via cron are features you'll find on Fiverr but not in Taskbot.

---

## Vendor System

| Feature | WP Sell Services | Taskbot |
|---------|-----------------|---------|
| **Seller levels** | 4 automatic tiers (New, Level 1, Level 2, Top Rated) | None |
| **Level criteria** | Orders completed, rating, response rate, delivery rate | N/A |
| **Per-vendor commission** | Yes (free version) | No (global rate only) |
| **Portfolio** | Yes | Not available |
| **Vacation mode** | Yes (account-level) | Not available |
| **Vendor approval workflow** | Yes | Yes |

Taskbot has no seller level system. No way for vendors to earn a "Top Rated" badge through performance. No per-vendor commission rates. No portfolio section. No vacation mode.

---

## Buyer Features

| Feature | WP Sell Services | Taskbot |
|---------|-----------------|---------|
| **Buyer requests** | Yes - post requests, receive proposals | Yes - via Projects module |
| **Proposals system** | Yes - vendors bid with pricing and timeline | Yes - credit-based bidding |
| **Favorites/wishlist** | Yes | Limited |
| **Tipping** | Yes | No |

Both platforms support buyer requests and vendor proposals. Taskbot uses a credit system where sellers spend credits to bid on projects. WP Sell Services has a simpler, direct proposal system.

---

## Reviews

| Feature | WP Sell Services | Taskbot |
|---------|-----------------|---------|
| **Rating system** | 5-star with multi-criteria | 5-star single score |
| **Criteria** | Communication, Quality, Delivery (rated separately) | Overall rating only |
| **Vendor reply** | Yes | Basic |
| **Review moderation** | Yes (admin queue) | Yes |

WP Sell Services is the only WordPress marketplace solution offering multi-criteria reviews. Buyers rate communication, quality, and delivery separately - the same approach Fiverr uses. This gives vendors actionable feedback and buyers more nuanced information.

---

## Dispute Resolution

| Feature | WP Sell Services | Taskbot |
|---------|-----------------|---------|
| **Resolution types** | 5 (full refund, partial refund, favor buyer, favor vendor, mutual agreement) | ~2 (refund or dismiss) |
| **Evidence submission** | Yes (file attachments) | Basic |
| **Escalation workflow** | Yes (open, in review, resolved) | Basic admin mediation |
| **Dedicated dispute thread** | Yes | No |

---

## Developer Experience

| Feature | WP Sell Services | Taskbot |
|---------|-----------------|---------|
| **REST API controllers** | 20 (services, orders, reviews, vendors, conversations, disputes, buyer requests, proposals, notifications, portfolio, earnings, extensions, milestones, tipping, seller levels, moderation, favorites, media, cart, auth) | Mobile-app focused (limited documentation) |
| **Batch endpoint** | Yes (25 sub-requests per call) | No |
| **Action/filter hooks** | 170+ documented | No public reference |
| **Template overrides** | Yes (theme/wp-sell-services/) | Yes (child theme) |
| **Gutenberg blocks** | 6 native blocks | None (Elementor shortcodes only) |
| **Shortcodes** | 16 | Elementor-based |
| **PSR-4 autoloading** | Yes | No |
| **WP-CLI commands** | Yes | No |

If you're a developer or agency, WP Sell Services gives you dramatically more control. 20 REST API controllers with a batch endpoint means you can build a mobile app or custom frontend. 170+ hooks means you can customize every aspect of the marketplace. Taskbot's API is limited to powering their mobile app.

---

## SEO

| Feature | WP Sell Services | Taskbot |
|---------|-----------------|---------|
| **JSON-LD schema markup** | Yes (Service, Review schemas) | No |
| **Yoast SEO integration** | Yes | Basic WordPress SEO |
| **Rank Math integration** | Yes | Basic |
| **Breadcrumbs** | Yes | Yes |

WP Sell Services outputs JSON-LD structured data for services and reviews, helping search engines understand your marketplace content. Taskbot relies on whatever your SEO plugin can infer from the page.

---

## What Taskbot Has That WP Sell Services Doesn't

| Feature | Status | Notes |
|---------|--------|-------|
| **Hourly project billing** | Not in WPSS | Taskbot offers this as a $29 addon |
| **Mobile app (Tasklay)** | Not in WPSS (API-ready) | Taskbot sells Tasklay for $99. WPSS has 20 REST API controllers ready for custom app development |
| **Custom offer system** | Not in WPSS | Taskbot sells this as a $29 addon. WPSS handles this through buyer requests |
| **Elementor shortcodes** | Not in WPSS | WPSS uses native Gutenberg blocks instead |

---

## What WP Sell Services Has That Taskbot Doesn't

- No WooCommerce requirement (Stripe + PayPal + Offline built-in)
- 11 order statuses vs ~6
- Requirements collection before work begins
- Deadline extension requests
- Auto-complete via cron
- Multi-criteria reviews (communication, quality, delivery)
- 4 automatic seller levels
- Per-vendor commission rates
- Portfolio for vendors
- Vacation mode
- 5 dispute resolution types
- 20 REST API controllers + batch endpoint
- 6 Gutenberg blocks
- 16 shortcodes
- JSON-LD schema markup
- WP-CLI commands
- 170+ action/filter hooks
- PSR-4 modern PHP architecture
- Commission-free tipping

---

## Pricing

| | WP Sell Services | Taskbot |
|--|-----------------|---------|
| **Core plugin** | Free | $69 |
| **All features above** | Free | $69 + $29 hourly addon + $29 custom offers addon = $127 |
| **Mobile app** | Build your own with our 20-controller REST API | $99 (Tasklay) |
| **Total cost for full setup** | $0 (free version) | $127-$226 |

---

## Bottom Line

**Choose WP Sell Services if:** You want a lightweight, modern marketplace plugin that works with any theme, doesn't require WooCommerce, and gives you the most complete order workflow and developer API available.

**Choose Taskbot if:** You specifically need hourly project billing today and want a ready-made mobile app without building one.

---

**Try WP Sell Services:** Free on [WordPress.org](https://wordpress.org/plugins/wp-sell-services)
