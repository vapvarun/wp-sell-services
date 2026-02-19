# WP Sell Services vs Workreap: Complete Comparison

Choosing between WP Sell Services and Workreap for your WordPress service marketplace? This comparison covers architecture, features, flexibility, and total cost.

---

## Quick Comparison

| | WP Sell Services | Workreap |
|--|-----------------|----------|
| **Type** | Plugin (works with any theme) | Full theme (replaces your theme entirely) |
| **Price** | Free on WordPress.org | $69 on ThemeForest |
| **WooCommerce Required** | No | Yes |
| **Payment Gateways** | Stripe, PayPal, Offline (built-in free) | Only via WooCommerce |
| **Order Statuses** | 11 | ~6 |
| **Seller Levels** | 4 automatic tiers (free) | None (badges addon $29 extra) |
| **Multi-Criteria Reviews** | Yes (communication, quality, delivery) | No (single score only) |
| **REST API** | 20 controllers + batch endpoint | Mobile-app focused |
| **Gutenberg Blocks** | 6 native blocks | None (Elementor only) |
| **Hourly Projects** | Not yet | Yes (built-in) |
| **Real-Time Chat** | AJAX messaging | Yes (Node.js/Pusher) |
| **Mobile App** | API-ready (20 controllers) | 2 apps ($59-$99 extra) |
| **RTL Support** | Yes | Yes |

---

## The Fundamental Difference: Plugin vs Theme

This is the single most important difference between WP Sell Services and Workreap.

**WP Sell Services is a plugin.** It installs on your existing WordPress site alongside your current theme. Your site's design, navigation, header, footer, and other pages remain unchanged. The marketplace features appear on the pages you configure.

**Workreap is a full WordPress theme.** When you activate it, your entire site becomes the Workreap design. Your previous theme, its customizations, and its design are replaced. Every page on your site uses Workreap's templates.

### What this means in practice

| Scenario | WP Sell Services | Workreap |
|----------|-----------------|----------|
| **Existing business site** | Add marketplace to your site | Must rebuild entire site in Workreap |
| **BuddyPress community** | Add alongside your community theme | Cannot use with BuddyPress themes |
| **Custom branded site** | Keep your brand design | Must adapt to Workreap's design |
| **Multiple marketplaces** | Same plugin, different themes per site | Same Workreap design on every site |
| **Future theme change** | Marketplace data untouched | Marketplace breaks if you change theme |
| **White-label for clients** | Template overrides + hooks | Stuck with Workreap branding |

---

## Order Workflow

| Feature | WP Sell Services | Workreap |
|---------|-----------------|----------|
| **Total order statuses** | 11 | ~6 |
| **Requirements collection** | Yes - custom forms before work starts | No |
| **Milestone payments** | Yes | Yes (projects module) |
| **Deadline extensions** | Yes - request and approve | No |
| **Auto-complete** | Yes - configurable timer via cron | No |
| **Revision management** | Yes - per-package limits | Basic |
| **Tipping** | Yes - commission-free | No |

WP Sell Services handles the details that professional marketplaces need: collecting project requirements before work begins, allowing deadline extensions, and automatically completing orders when buyers don't respond. Workreap's workflow is more basic.

---

## Vendor System

| Feature | WP Sell Services | Workreap |
|---------|-----------------|----------|
| **Seller levels** | 4 automatic tiers (free) | None built-in. Badges addon $29 extra |
| **Per-vendor commission** | Yes (free version) | Not confirmed as per-vendor |
| **Tiered commission** | Pro only | Yes (built-in) |
| **Portfolio** | Yes | Yes |
| **Vacation mode** | Yes (account-level) | No |
| **Identity verification** | Not yet | Yes (admin-mediated) |
| **Profile health tracking** | Via seller levels | 6-step system |

Workreap has tiered commission (rates change based on project cost) built-in, which is an advantage. However, WP Sell Services has automatic seller levels in the free version - a feature Workreap only offers through a $29 addon.

---

## Reviews & Ratings

| Feature | WP Sell Services | Workreap |
|---------|-----------------|----------|
| **Rating type** | Multi-criteria (communication, quality, delivery) | Single overall score |
| **Vendor reply** | Yes | Limited |
| **Review moderation** | Yes (admin queue) | Yes |

Multi-criteria reviews are a significant differentiator. When a buyer rates communication, quality, and delivery separately, vendors get actionable feedback and buyers get nuanced information. No competitor offers this.

---

## Chat & Communication

| Feature | WP Sell Services | Workreap |
|---------|-----------------|----------|
| **Messaging type** | AJAX-based (per-order) | Real-time (Node.js/Pusher via WP Guppy) |
| **File attachments** | Yes | Yes |
| **Pre-order chat** | Via conversations | Yes (dedicated messenger page) |
| **Unread counts** | Yes | Yes |

Workreap wins here with real-time chat powered by Node.js/Pusher. WP Sell Services uses WordPress AJAX for messaging, which works reliably but isn't instant. For most service marketplaces, AJAX messaging is perfectly adequate - vendors don't need Slack-like real-time for delivering design work or consulting services.

---

## Developer Experience

| Feature | WP Sell Services | Workreap |
|---------|-----------------|----------|
| **REST API** | 20 controllers + batch endpoint | Mobile-app focused (limited docs) |
| **Action/filter hooks** | 170+ documented | Available but no public reference |
| **Template overrides** | Yes (theme/wp-sell-services/) | Child theme only |
| **Gutenberg blocks** | 6 native blocks | None (Elementor only) |
| **PSR-4 autoloading** | Yes | No |
| **WP-CLI commands** | Yes | No |
| **Shortcodes** | 16 | Elementor-based |

For agencies and developers, WP Sell Services is dramatically more extensible. 20 REST API controllers mean you can build custom interfaces. 170+ hooks mean you can customize every behavior. Being a plugin means you can pair it with any theme framework.

---

## What Workreap Has That WP Sell Services Doesn't

| Feature | Notes |
|---------|-------|
| **15+ homepage designs** | Workreap ships beautiful pre-built layouts. WPSS relies on your theme for design |
| **Real-time chat** | Node.js/Pusher integration via WP Guppy |
| **Hourly project billing** | Built into Workreap core |
| **Mobile apps** | Workreap React Native ($99) + Workfleet ($59, MVP) |
| **Identity verification** | Admin-mediated document verification |
| **Tiered commission** | Rates adjust based on project cost thresholds |
| **Social login addon** | Facebook/LinkedIn ($29 addon) |

---

## What WP Sell Services Has That Workreap Doesn't

- Works with any theme (plugin, not a theme)
- No WooCommerce requirement
- Built-in Stripe + PayPal + Offline gateways (free)
- 11 order statuses vs ~6
- Requirements collection before work begins
- Deadline extension requests
- Auto-complete via cron
- Multi-criteria reviews
- 4 automatic seller levels (no addon needed)
- Per-vendor commission rates (free)
- Vacation mode
- 5 dispute resolution types
- 20 REST API controllers + batch endpoint
- 6 Gutenberg blocks
- JSON-LD schema markup
- WP-CLI commands
- 170+ documented hooks
- PSR-4 modern PHP architecture
- Commission-free tipping
- 17 custom database tables (faster than WC post meta)

---

## Total Cost Comparison

| Component | WP Sell Services | Workreap |
|-----------|-----------------|----------|
| **Core** | $0 (free) | $69 (theme) |
| **WooCommerce** | Not needed | Required (free but adds overhead) |
| **Seller levels/badges** | Included free | $29 addon |
| **Social login** | Standard WP plugins | $29 addon |
| **Mobile app** | Build with REST API | $59-$99 |
| **Real-time chat** | N/A | Included (needs Node.js server) |
| **Hourly billing** | Not yet available | Included |
| **Total for comparable features** | **$0** | **$127-$226** |

---

## Bottom Line

**Choose WP Sell Services if:** You have an existing WordPress site, don't want to replace your theme, want a lightweight standalone checkout (no WooCommerce), need deep customization through hooks and API, or are building for non-English markets with RTL support.

**Choose Workreap if:** You're starting from scratch with no existing site, want beautiful pre-built designs out of the box, need hourly project billing and real-time chat today, or want a ready-made mobile app.

**The structural difference:** Workreap locks you into its design and WooCommerce. WP Sell Services gives you freedom - pair it with any theme, skip WooCommerce entirely, and customize through 170+ hooks and 20 REST API controllers.

---

**Try WP Sell Services:** Free on [WordPress.org](https://wordpress.org/plugins/wp-sell-services)
