# WP Sell Services vs MicrojobEngine: Complete Comparison

Choosing between WP Sell Services and MicrojobEngine for your WordPress micro-job marketplace? This comparison covers architecture, features, pricing, and extensibility.

---

## Quick Comparison

| | WP Sell Services | MicrojobEngine |
|--|-----------------|----------------|
| **Type** | Plugin (works with any theme) | Full theme (replaces your theme) |
| **Price** | Free on WordPress.org | $89-$329 (one-time, 12-month updates) |
| **WooCommerce Required** | No | No (own payment system) |
| **Payment Gateways** | Stripe, PayPal, Offline (free) | PayPal + 2Checkout (free). Stripe, Paystack as paid addons |
| **Order Statuses** | 11 | 6 |
| **Seller Levels** | 4 automatic tiers | Yes (level-based commission) |
| **Multi-Criteria Reviews** | Yes (communication, quality, delivery) | No (single score) |
| **REST API** | 20 controllers + batch endpoint | None |
| **Gutenberg Blocks** | 6 | None (Elementor) |
| **Buyer Requests/Bidding** | Yes | No (custom orders only) |
| **Analytics Dashboard** | Pro (Chart.js) | Yes (built-in) |

---

## Architecture

Both WP Sell Services and MicrojobEngine operate independently without WooCommerce - a significant advantage over Taskbot, Workreap, and HivePress.

**WP Sell Services** is a plugin. It installs alongside your existing theme, adding marketplace functionality to your site. Modern PHP 8.1+ with PSR-4 autoloading, 17 custom database tables, and a RESTful architecture.

**MicrojobEngine** is a full WordPress theme. When you activate it, your entire site becomes MicrojobEngine's design. It was one of the first Fiverr-clone themes (launched ~2015) and has its own payment, wallet, and order system.

### The generational gap

| Aspect | WP Sell Services | MicrojobEngine |
|--------|-----------------|----------------|
| **PHP standard** | PHP 8.1+, PSR-4, typed properties | Older PHP patterns |
| **Database** | 17 custom tables with indexes | Theme-based storage |
| **REST API** | 20 controllers + batch endpoint | None |
| **Block editor** | 6 Gutenberg blocks | No block support |
| **Extension model** | 170+ hooks + Pro plugin | EngineThemes addons only |
| **Code architecture** | Service layer, interfaces, dependency injection | Theme functions |

MicrojobEngine was built in a different era of WordPress development. WP Sell Services is built on modern WordPress patterns - custom database tables, REST API first, Gutenberg blocks, and PHP 8.1+ features.

---

## Order Workflow

| Feature | WP Sell Services | MicrojobEngine |
|---------|-----------------|----------------|
| **Order statuses** | 11 | 6 (Pending, Active, Late, Delivered, Finished, Disputed) |
| **Requirements collection** | Yes - custom forms before work starts | No |
| **Milestone payments** | Yes | No |
| **Deadline extensions** | Yes - request and approve | No |
| **Auto-complete** | Yes - configurable timer via cron | No |
| **Revision management** | Yes - per-package limits | Basic |
| **Tipping** | Yes - commission-free | No |
| **Custom orders** | Via buyer requests | Yes (direct buyer-to-seller) |

WP Sell Services has nearly twice the order statuses and several workflow features MicrojobEngine doesn't offer: requirements collection, milestone payments, deadline extensions, and auto-complete.

---

## Seller Levels

Both platforms have automatic seller levels - a feature most competitors lack entirely.

| Feature | WP Sell Services | MicrojobEngine |
|---------|-----------------|----------------|
| **Automatic progression** | Yes | Yes |
| **Number of tiers** | 4 (New, Level 1, Level 2, Top Rated) | Admin-configurable |
| **Criteria** | Orders, rating, response rate, delivery rate | Sales volume ($$) in recent period |
| **Commission benefit** | Pro (tiered commission) | Yes (higher levels = lower commission) |
| **Badge display** | Yes | Yes |

MicrojobEngine's level system is sales-volume-based (e.g., $100 in sales last month = Level 1). WP Sell Services uses a multi-factor approach including order count, average rating, response rate, and delivery rate - more similar to Fiverr's actual level system.

---

## Buyer Features

| Feature | WP Sell Services | MicrojobEngine |
|---------|-----------------|----------------|
| **Buyer requests (post a job)** | Yes - full system with proposals | No |
| **Proposals/bidding** | Yes - vendors compete for requests | No (custom orders are 1:1) |
| **Favorites/wishlist** | Yes | Limited |
| **Buyer dashboard** | Yes | Yes |

WP Sell Services has a complete buyer requests system where buyers post project needs and vendors submit competitive proposals. MicrojobEngine only supports custom orders (buyer contacts a specific seller directly) - there's no open marketplace for requests.

---

## Reviews

| Feature | WP Sell Services | MicrojobEngine |
|---------|-----------------|----------------|
| **Rating type** | Multi-criteria (communication, quality, delivery) | Single overall score |
| **Vendor reply** | Yes | No |
| **Review moderation** | Yes (admin queue) | Basic |

---

## Developer Experience

| Feature | WP Sell Services | MicrojobEngine |
|---------|-----------------|----------------|
| **REST API** | 20 controllers + batch endpoint | None |
| **Action/filter hooks** | 170+ documented | No public reference |
| **Template overrides** | Yes (theme/wp-sell-services/) | Limited |
| **Gutenberg blocks** | 6 | None |
| **Third-party extensions** | Possible via hooks | Only EngineThemes addons |
| **PSR-4 autoloading** | Yes | No |
| **WP-CLI** | Yes | No |
| **Mobile app development** | 20 REST API controllers ready | No API available |

This is the biggest gap. MicrojobEngine has **no REST API**. You cannot build a mobile app, custom frontend, or third-party integration. WP Sell Services has 20 REST API controllers covering every aspect of the marketplace, plus a batch endpoint for efficient mobile communication.

---

## Payment & Payouts

| Feature | WP Sell Services | MicrojobEngine |
|---------|-----------------|----------------|
| **Standalone checkout** | Yes (free) | Yes |
| **Stripe** | Yes (free, direct) | Paid addon |
| **PayPal** | Yes (free, direct) | Yes (built-in) |
| **Offline payments** | Yes (free) | Yes |
| **Virtual credit system** | Pro (wallet integrations) | Yes (built-in credits) |
| **Auto-withdrawal schedule** | Yes (free) | Addon |
| **Manual withdrawals** | Yes | Yes |
| **Clearance period** | Yes | No |

Both platforms avoid the WooCommerce dependency. WP Sell Services includes Stripe in the free version, while MicrojobEngine charges extra for it.

---

## SEO

| Feature | WP Sell Services | MicrojobEngine |
|---------|-----------------|----------------|
| **JSON-LD schema** | Yes (built-in) | No |
| **Yoast integration** | Yes | Basic |
| **Rank Math integration** | Yes | Basic |
| **i18n strings** | 3,344 translatable strings | Translation tool + WPML |
| **RTL support** | Yes | Not confirmed |

---

## What MicrojobEngine Has That WP Sell Services Doesn't

| Feature | Notes |
|---------|-------|
| **Level-based commission discounts** | Higher seller levels automatically get lower commission (WPSS Pro has tiered commission) |
| **Virtual credit system** | Buyers top up wallet balance with credits (WPSS Pro has wallet integrations) |
| **Built-in analytics** | Seller revenue/order analytics in dashboard (WPSS Pro has Chart.js analytics) |
| **AJAX advanced filtering** | Added in v1.3.3 (WPSS uses page-reload filtering) |
| **Job verification** | Sellers pay to verify listings as quality signal |
| **Established since ~2015** | Longer market presence |

---

## What WP Sell Services Has That MicrojobEngine Doesn't

- Plugin architecture (works with any theme)
- 20 REST API controllers + batch endpoint (MJE has zero)
- 6 Gutenberg blocks (MJE has none)
- Multi-criteria reviews (communication, quality, delivery)
- Multi-factor seller levels (not just sales volume)
- Buyer requests with competitive proposals/bidding
- Requirements collection before work begins
- Milestone payments
- Deadline extension requests
- Auto-complete via cron
- 5 dispute resolution types
- Commission-free tipping
- Account-level vacation mode
- Per-vendor commission rates
- 170+ documented action/filter hooks
- PSR-4 PHP 8.1+ architecture
- WP-CLI commands
- JSON-LD schema markup
- RTL support
- Template override system
- Auto-withdrawal scheduling (free)
- Clearance periods

---

## Pricing

| Plan | WP Sell Services | MicrojobEngine |
|------|-----------------|----------------|
| **Basic marketplace** | $0 (free) | $89 (12-month updates only) |
| **+ Stripe gateway** | Included free | +addon cost |
| **+ Discount system** | Not yet | +addon cost |
| **+ Featured listings** | Standard | +addon cost |
| **+ Withdrawal automation** | Included free | +addon cost |
| **Full-featured** | $0 free / Pro for extras | $329 (Pro package) |
| **Ongoing updates** | Unlimited (WordPress.org) | Requires annual renewal |

WP Sell Services is free on WordPress.org with unlimited updates. MicrojobEngine's $89 basic plan only includes 12 months of updates - after that, you pay again. The $329 Pro plan is required for most real-world features.

---

## Bottom Line

**Choose WP Sell Services if:** You want a modern, extensible marketplace plugin with a REST API, Gutenberg blocks, and a future-proof architecture. If you ever plan to build a mobile app or custom frontend, WPSS is the only option with a complete API.

**Choose MicrojobEngine if:** You want an established, all-in-one Fiverr clone theme with built-in analytics and a virtual credit system, and you don't need a REST API, Gutenberg blocks, or custom theme flexibility.

**The generational difference:** MicrojobEngine was built in the theme-based WordPress era (~2015). WP Sell Services is built for modern WordPress - plugin architecture, REST API first, Gutenberg blocks, PHP 8.1+, and 17 custom database tables. If you're starting a new marketplace in 2026, the architectural choice matters for the next 5 years.

---

**Try WP Sell Services:** Free on [WordPress.org](https://wordpress.org/plugins/wp-sell-services)
