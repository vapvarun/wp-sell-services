# WP Sell Services - Marketing Plan

**Date:** February 2026
**Products:** WP Sell Services (Free) + WP Sell Services Pro

---

## 1. Market Positioning

### The Problem We Solve

WordPress site owners want to build a service marketplace (like Fiverr, Upwork, or 99designs) without:
- Paying $89-329 for a theme-locked solution
- Needing WooCommerce or any e-commerce plugin
- Hiring developers for custom builds
- Giving up control over their platform

### Our Unique Position

**"The only free, standalone WordPress service marketplace plugin with built-in Stripe & PayPal."**

| Differentiator | WPSS Free | Competitors |
|---|---|---|
| **Price** | $0 | $59-329 |
| **Type** | Plugin (works with ANY theme) | Theme (locked design) |
| **WooCommerce required** | No | Yes (most) |
| **Stripe + PayPal included** | Yes (free) | Usually Pro/add-on |
| **REST API (80+ endpoints)** | Yes | Rarely |
| **Mobile-app ready** | Yes | No |

### Competitive Landscape

| Competitor | Price | Type | Weakness |
|---|---|---|---|
| TaskHive | $89/yr | Theme | Theme-locked, no free tier |
| MicrojobEngine | $89-329 | Theme | Expensive, outdated, WC-dependent |
| Developer Starter (CodeCanyon) | $59 | Plugin | Minimal features, no updates |
| Taskbot | $59 | Theme | Theme-locked, Elementor-dependent |
| Jeelo | $49/yr | Plugin | Limited features, weak API |

### Target Audiences

| Segment | Use Case | Size |
|---|---|---|
| **Freelance Marketplace Builders** | Build Fiverr/Upwork clone for a niche | Large |
| **Agency Owners** | Client project: "build me a marketplace" | Medium |
| **Niche Service Platforms** | Tutoring, design, writing, consulting | Large |
| **Existing BuddyPress Communities** | Add marketplace to community site | Medium |
| **WordPress Theme Developers** | Bundle with their theme for added value | Small |
| **SaaS Entrepreneurs** | MVP for service marketplace startup | Medium |

---

## 2. Product Strategy

### Free Plugin - Feature Highlights for Marketing

**Core Marketplace (17 database tables, 20 REST controllers, 80+ endpoints)**

| Category | Key Features |
|---|---|
| **Service Listings** | 6-step creation wizard, 3-tier pricing (Basic/Standard/Premium), service add-ons, gallery + video, FAQs |
| **Order Management** | 10 order statuses, delivery versioning, revision tracking, milestone system, deadline extensions, auto-complete |
| **Payments** | Stripe, PayPal, Offline payments - all free. No WooCommerce needed |
| **Communication** | Per-order conversations with file attachments, in-app notifications, 11 email templates |
| **Vendor System** | Registration (open/approval/closed), Fiverr-style seller levels, portfolio, vacation mode, earnings dashboard |
| **Buyer Requests** | Post jobs, receive vendor proposals, accept/reject proposals |
| **Disputes** | 5-stage dispute resolution, evidence upload, admin intervention |
| **Reviews** | Multi-dimensional ratings (quality, communication, delivery), vendor replies, helpful votes |
| **Requirements** | 9 custom field types for order requirements, timeout handling |
| **Admin** | Orders table, vendor management, service moderation, manual order creation, withdrawals management |
| **Shortcodes** | 16 shortcodes for complete frontend without coding |
| **Blocks** | 6 Gutenberg blocks for modern editors |
| **SEO** | Schema.org markup, Yoast + Rank Math integration |
| **REST API** | 20 controllers, 80+ endpoints, batch requests, CORS, rate limiting |
| **WP-CLI** | Demo content generation, service management, validation |

**Headline Numbers:**
- 17 custom database tables
- 20 REST API controllers
- 80+ API endpoints
- 16 shortcodes
- 6 Gutenberg blocks
- 11 email templates (HTML + plain text)
- 9 custom field types
- 10 order statuses
- 4 seller levels
- 35+ frontend templates (all theme-overridable)
- 46+ AJAX handlers
- 3 payment gateways

### Pro Plugin - Feature Highlights

| Category | Key Features |
|---|---|
| **E-commerce Platforms** | WooCommerce, Easy Digital Downloads, Fluent Cart, SureCart adapters |
| **Razorpay Gateway** | Full implementation: orders, capture, refunds, webhooks, 17 currencies |
| **Wallet System** | Internal wallet, TeraWallet, WooWallet, MyCred integrations. Atomic transactions, auto-payout |
| **Cloud Storage** | Amazon S3, Google Cloud Storage, DigitalOcean Spaces. Custom AWS Sig V4 + GCS OAuth2 (no SDK deps) |
| **Analytics** | Chart.js dashboard, 4 data collectors, revenue trends, top services/vendors, CSV/JSON export |
| **Wizard Unlocks** | Unlimited gallery images, extras, FAQs, requirements. 3 video uploads |
| **REST API** | 4 additional controllers: Wallet, Payment routing, Vendor Analytics, Storage |

### Pro Roadmap (Planned Features - Basecamp Suggestions)

These are the features that will make Pro compelling:

| Feature | Why It Matters | Priority |
|---|---|---|
| **Stripe Connect** | Automatic vendor payouts via split payments. Eliminates manual withdrawals | P0 |
| **Vendor Subscription Plans** | Monthly fees for premium vendor tiers. Recurring revenue for platform owners | P0 |
| **Tiered Commission** | Different commission rates by volume, seller level, or category | P1 |
| **Subscription/Recurring Services** | Monthly retainer services (SEO, maintenance, consulting) | P1 |
| **White-Label/Custom Branding** | Remove WPSS branding, custom emails, branded experience | P2 |
| **PayPal Payouts** | Batch vendor payments via PayPal Mass Pay API | P2 |
| **BuddyPress Integration** | Vendor profile tabs, activity stream, BP notifications, group marketplace | P1 |

---

## 3. Launch Sequence

### Phase 1: Foundation (Weeks 1-2)

**Goal:** WordPress.org listing + basic web presence

| Task | Details |
|---|---|
| WordPress.org submission | Plugin directory listing with screenshots, description, FAQ |
| Landing page | On wbcomdesigns.com - feature grid, comparison table, CTA |
| Demo site | BuddyX + WPSS installed, sample services/vendors/orders |
| Demo content importer | One-click import for users to try the full experience |
| Documentation | Getting started guide, shortcode reference, API docs |
| README.txt | WordPress.org readme with proper headers, screenshots, changelog |

### Phase 2: Content Marketing (Weeks 3-6)

**Goal:** Organic discovery through educational content

| Content | Format | Target Keywords |
|---|---|---|
| "How to Build a Fiverr Clone with WordPress" | Blog post + video | fiverr clone wordpress, freelance marketplace plugin |
| "Best WordPress Service Marketplace Plugins 2026" | Comparison post | service marketplace wordpress, microjob plugin |
| "Build a Tutoring Marketplace with WordPress" | Tutorial | tutoring marketplace, online tutoring platform |
| "Freelance Marketplace vs. Job Board: Which to Build?" | Blog post | freelance marketplace, wordpress job board alternative |
| "How to Accept Payments Without WooCommerce" | Blog post | wordpress payments without woocommerce, standalone checkout |
| "BuddyX + WP Sell Services: Community Marketplace" | Blog post | buddypress marketplace, community freelance platform |
| "WordPress REST API Marketplace: Build Mobile Apps" | Developer tutorial | wordpress marketplace api, mobile marketplace app |

### Phase 3: Community & Growth (Weeks 7-12)

**Goal:** User acquisition and social proof

| Channel | Activity |
|---|---|
| **WordPress.org reviews** | In-plugin prompt after 5 orders processed (non-intrusive) |
| **Facebook Groups** | Share in WordPress, BuddyPress, and freelancing groups |
| **Reddit** | r/WordPress, r/webdev, r/Entrepreneur (helpful answers, not spam) |
| **YouTube** | Setup walkthrough, demo video, comparison with competitors |
| **Product Hunt** | Launch day with demo + free tier highlight |
| **BuddyPress community** | Cross-promote with BuddyX ecosystem |
| **Developer outreach** | API-first messaging for developers building marketplace apps |

### Phase 4: Pro Launch (Weeks 8-12)

**Goal:** Convert free users to Pro

| Task | Details |
|---|---|
| Pro landing page | Feature comparison, pricing, testimonials |
| Upgrade prompts (tasteful) | In-plugin "Upgrade to Pro" page showing what Pro adds |
| Email drip | Welcome → Day 3 (tips) → Day 7 (case study) → Day 14 (Pro features) → Day 30 (discount) |
| Stripe Connect announcement | "Automatic vendor payouts — no more manual withdrawals" |
| Launch discount | 30% off first year for early adopters |

---

## 4. Pricing Strategy

### Free Plugin

**Price:** $0 forever on WordPress.org

**Includes everything needed to run a complete marketplace:**
- Unlimited services, vendors, orders
- Stripe + PayPal + Offline payments
- Full order lifecycle
- Conversations, reviews, disputes
- Buyer requests + proposals
- 80+ REST API endpoints
- All 16 shortcodes + 6 blocks

### Pro Plugin

**Recommended pricing:** $99/year (single site), $199/year (5 sites), $299/year (unlimited)

**Justification:**
- Competitors charge $59-329 and include far less
- Free plugin alone is more capable than most paid alternatives
- Pro adds enterprise/scale features, not basic functionality

### Why Users Will Pay for Pro

| Pain Point (Free) | Pro Solution |
|---|---|
| "I have to manually pay vendors every week" | Stripe Connect auto-payouts |
| "I want to charge vendors a monthly fee to sell" | Vendor Subscription Plans |
| "Commission should be different for top sellers" | Tiered Commission System |
| "My vendors offer monthly retainers" | Subscription/Recurring Services |
| "I need my own branding, not plugin branding" | White-Label mode |
| "I want WooCommerce checkout for my existing store" | WooCommerce adapter |
| "I need vendor analytics dashboards" | Analytics module + Chart.js |
| "Large file deliveries need cloud storage" | S3 / GCS / DO Spaces |
| "I want to integrate with my BuddyPress community" | BuddyPress integration |

---

## 5. Messaging Framework

### Tagline Options

1. **"Turn any WordPress site into a service marketplace."**
2. **"The free Fiverr for WordPress."**
3. **"Service marketplace. No WooCommerce required."**
4. **"From zero to marketplace in minutes."**

**Recommended:** Option 1 for primary, Option 3 for differentiation

### Elevator Pitch

> WP Sell Services is a free WordPress plugin that turns any site into a service marketplace like Fiverr or Upwork. Built-in Stripe and PayPal payments, no WooCommerce needed. Vendors list services, buyers order and pay, and the platform earns commission — all out of the box. Pro adds WooCommerce, analytics, cloud storage, and automatic vendor payouts via Stripe Connect.

### Key Messages by Audience

**For Marketplace Builders:**
> Build your own Fiverr without code. Service listings with 3-tier pricing, built-in checkout with Stripe & PayPal, order management, reviews, disputes — everything included free. Works with any WordPress theme.

**For Agencies:**
> Stop recommending $300 theme-based solutions to clients. WP Sell Services is a plugin — it works with any theme, any design. Ship marketplace projects faster with 80+ REST API endpoints and 16 ready-made shortcodes.

**For Developers:**
> 20 REST API controllers, 80+ endpoints, batch requests, CORS support, rate limiting. Build native mobile apps or headless frontends on top of a battle-tested WordPress marketplace backend.

**For BuddyPress Users:**
> Already running a community with BuddyX? Add WP Sell Services and your members can sell their skills to each other. Community + marketplace = the complete platform.

---

## 6. WordPress.org Listing Strategy

### Plugin Name
**WP Sell Services — Service Marketplace**

### Short Description (150 chars)
> Turn your WordPress site into a service marketplace like Fiverr. Built-in Stripe & PayPal. No WooCommerce required.

### Description Structure

```
== Description ==

**Build a service marketplace on WordPress — free, standalone, no WooCommerce required.**

WP Sell Services transforms any WordPress site into a full-featured service marketplace
where vendors list services and buyers order, pay, and communicate — all within your site.

= Why WP Sell Services? =

* **Free & complete** — Stripe, PayPal, and offline payments included
* **No WooCommerce needed** — works standalone with built-in checkout
* **Any theme** — plugin, not a theme. Use BuddyX, Astra, GeneratePress, anything
* **Mobile-ready** — 80+ REST API endpoints for native app development
* **Fiverr-style** — 3-tier pricing, seller levels, reviews, disputes, milestones

= Feature Highlights =

* 6-step service creation wizard with packages (Basic/Standard/Premium)
* Service add-ons, FAQs, gallery, and video support
* Complete order lifecycle: requirements → work → delivery → revision → completion
* Buyer request system with vendor proposals
* Multi-dimensional reviews (quality, communication, delivery)
* Dispute resolution with evidence and admin intervention
* Vendor earnings, commission, and withdrawal management
* 11 email notification templates
* 16 shortcodes + 6 Gutenberg blocks
* WP-CLI support for developers
* SEO: Schema.org markup with Yoast & Rank Math integration

= Perfect For =

* Freelance marketplaces (design, writing, development)
* Tutoring and coaching platforms
* Consulting service directories
* Niche service platforms (home services, wellness, legal)
* BuddyPress/BuddyX community marketplaces

= Pro Features =

WP Sell Services Pro extends with:
* WooCommerce, EDD, Fluent Cart, SureCart checkout
* Razorpay payment gateway
* Stripe Connect for automatic vendor payouts
* Wallet system (Internal, TeraWallet, WooWallet, MyCred)
* Cloud storage (Amazon S3, Google Cloud, DigitalOcean)
* Analytics dashboard with charts and exports
* Vendor subscription plans
* BuddyPress integration
```

### Tags (max 5)
`marketplace`, `services`, `freelance`, `fiverr`, `stripe`

### Screenshots (recommended 8-10)

1. Service listing page with 3-tier pricing cards
2. Service creation wizard (step 2 - pricing)
3. Order view with conversation thread
4. Vendor dashboard overview
5. Admin orders list table
6. Buyer requests with proposals
7. Dispute resolution view
8. Stripe/PayPal gateway settings
9. Mobile API response (Postman/REST client)
10. BuddyX theme with WPSS marketplace

---

## 7. Demo Site Strategy

### URL
`demo.wbcomdesigns.com/marketplace/` (or similar)

### Stack
- **Theme:** BuddyX (free) — community + marketplace feel
- **Plugin:** WP Sell Services (free) + Pro
- **BuddyPress:** Active with groups + activity

### Demo Content

| Type | Count | Examples |
|---|---|---|
| Services | 20-30 | Logo Design, WordPress Development, SEO Audit, Content Writing, Video Editing, Translation |
| Categories | 8-10 | Design, Development, Writing, Marketing, Video, Music, Business, Lifestyle |
| Vendors | 8-10 | Mix of seller levels (new → top rated), diverse avatars |
| Buyer Requests | 5-8 | "Need a logo for my startup", "Looking for WordPress developer" |
| Reviews | 30-50 | Realistic reviews with varied ratings |
| Orders | 10-15 | Various statuses for demo walkthrough |

### Demo Accounts (auto-login links)

| Role | Username | Purpose |
|---|---|---|
| Admin | `admin` | Full admin experience |
| Top Vendor | `sarah_designer` | Experienced vendor with sales history |
| New Vendor | `mike_developer` | New vendor, level 1 |
| Buyer | `buyer_jane` | Buyer with order history |
| New User | `visitor` | Fresh account, no role |

Each login link: `?autologin=username`

### Demo Tours (guided)

1. **Buyer Journey:** Browse → Select service → Choose package → Add extras → Checkout → Submit requirements → Track order → Accept delivery → Leave review
2. **Vendor Journey:** Register → Create service → Receive order → Communicate → Deliver → Get paid → Track earnings
3. **Admin Journey:** Approve vendors → Moderate services → Manage orders → Process withdrawals → View analytics

---

## 8. Content Marketing Calendar

### Month 1: Launch

| Week | Content | Channel |
|---|---|---|
| 1 | "Introducing WP Sell Services" announcement | Blog, social |
| 1 | WordPress.org submission | Plugin directory |
| 2 | "How to Build a Fiverr Clone" tutorial | Blog, YouTube |
| 3 | "Free vs TaskHive vs MicrojobEngine" comparison | Blog |
| 4 | "5 Niche Marketplace Ideas" listicle | Blog, social |

### Month 2: Education

| Week | Content | Channel |
|---|---|---|
| 5 | "Tutoring Marketplace Tutorial" | Blog, YouTube |
| 6 | "REST API Deep Dive for Developers" | Blog, dev.to |
| 7 | "BuddyX + WPSS = Community Marketplace" | Blog, BuddyPress.org |
| 8 | Pro launch announcement + early bird discount | Blog, email, social |

### Month 3: Growth

| Week | Content | Channel |
|---|---|---|
| 9 | Case study: "How [user] Built [marketplace]" | Blog, social |
| 10 | "WordPress Marketplace SEO Guide" | Blog |
| 11 | "Stripe Connect: Automatic Vendor Payouts" (Pro) | Blog, YouTube |
| 12 | Monthly roundup + roadmap update | Blog, email |

---

## 9. Email Marketing

### Sequences

**Welcome Sequence (Free plugin activation):**

| Day | Email | Goal |
|---|---|---|
| 0 | Welcome + Quick Start Guide | Activation → first service created |
| 3 | "3 Tips for Your Marketplace" | Engagement |
| 7 | "See What Others Built" (case study) | Inspiration |
| 14 | "Unlock Pro Features" (feature spotlight) | Conversion awareness |
| 30 | "Special Offer: 30% Off Pro" | Conversion |

**Pro Trial/Upsell Sequence:**

| Trigger | Email | Goal |
|---|---|---|
| 10+ orders processed | "You're growing! Time for analytics?" | Pro conversion |
| Vendor withdrawal requested | "Automate payouts with Stripe Connect" | Pro conversion |
| 5+ vendors registered | "Manage vendors better with Pro" | Pro conversion |

### Newsletter (Monthly)

- New features shipped
- Community showcase
- Tips and tutorials
- Upcoming roadmap items

---

## 10. Metrics & Goals

### Phase 1 Goals (3 months)

| Metric | Target |
|---|---|
| WordPress.org active installs | 500+ |
| WordPress.org rating | 4.5+ stars |
| Demo site visits/month | 2,000+ |
| Email subscribers | 300+ |
| Blog posts published | 10+ |
| YouTube videos | 3+ |
| Pro conversions | 20+ |

### Phase 2 Goals (6 months)

| Metric | Target |
|---|---|
| WordPress.org active installs | 2,000+ |
| Pro active licenses | 100+ |
| Monthly recurring revenue | $5,000+ |
| Blog organic traffic/month | 5,000+ |
| Community (Facebook group members) | 200+ |

### Phase 3 Goals (12 months)

| Metric | Target |
|---|---|
| WordPress.org active installs | 10,000+ |
| Pro active licenses | 500+ |
| Monthly recurring revenue | $20,000+ |
| Case studies published | 5+ |
| Third-party reviews/mentions | 10+ |

---

## 11. Channel Strategy

### Owned Channels

| Channel | Content Type | Frequency |
|---|---|---|
| Blog (wbcomdesigns.com) | Tutorials, comparisons, case studies | 2-3/month |
| YouTube | Walkthroughs, feature demos | 2/month |
| Email newsletter | Updates, tips, offers | 1/month |
| Documentation site | Guides, API reference, hooks | Ongoing |

### Social Media

| Platform | Strategy |
|---|---|
| **Twitter/X** | Share tips, engage with WordPress community, reply to "marketplace plugin" queries |
| **Facebook** | WordPress groups (organic), own community group |
| **LinkedIn** | Target agency owners and entrepreneurs |
| **Reddit** | Helpful answers in r/WordPress, r/webdev (no spam) |
| **Product Hunt** | Launch day with compelling demo |

### Partner Channels

| Partner | Activity |
|---|---|
| **BuddyX/BuddyPress community** | Cross-promote, bundle recommendation |
| **WordPress theme authors** | "Recommended plugin" partnerships |
| **WordPress hosting companies** | Marketplace starter package bundles |
| **Starter template providers** | Include WPSS in marketplace templates |

### Paid (if budget allows)

| Channel | Budget | Expected ROI |
|---|---|---|
| Google Ads (keywords) | $500/month | Target "fiverr clone wordpress", "marketplace plugin" |
| Facebook Ads (retargeting) | $300/month | Retarget demo visitors and free users |
| Sponsored WordPress newsletters | $200/post | WP Tavern, Post Status, MasterWP |

---

## 12. The $0 Stack - Bundle Messaging

### The Pitch

> **Build a complete community marketplace for $0.**
>
> BuddyX (free theme) + WP Sell Services (free plugin) + Stripe/PayPal (free accounts) = a fully functional service marketplace with community features, member profiles, and activity feeds.
>
> No WooCommerce. No premium theme. No monthly fees. Just WordPress.

### Comparison Table (for landing page)

| Feature | WPSS + BuddyX ($0) | TaskHive ($89/yr) | MicrojobEngine ($329) |
|---|---|---|---|
| Price | **Free** | $89/year | $329 one-time |
| Theme flexibility | Any theme | Locked | Locked |
| WooCommerce required | No | Yes | Yes |
| Stripe + PayPal | Included | Add-on | Add-on |
| REST API | 80+ endpoints | None | Limited |
| Community features | BuddyPress ready | None | None |
| Seller levels | 4 tiers | 3 tiers | 3 tiers |
| Dispute system | Built-in | Basic | Basic |
| Buyer requests | Built-in | No | Limited |
| Mobile app ready | Yes (API) | No | No |

### Upgrade Path

```
$0 Stack (Free)
├── BuddyX Theme
├── WP Sell Services
├── Stripe + PayPal
└── Everything you need to launch

$99/yr Stack (Pro)
├── Everything in Free
├── WooCommerce / EDD checkout
├── Razorpay gateway
├── Analytics dashboard
├── Cloud storage (S3/GCS)
├── Wallet system
└── Scale your marketplace

$199/yr Stack (Pro + Stripe Connect) [Future]
├── Everything in Pro
├── Automatic vendor payouts
├── Vendor subscription plans
├── Tiered commission
├── Recurring services
└── Run it like a real business
```

---

## 13. In-Plugin Marketing

### Activation Experience

1. **Welcome screen** after activation with quick-start checklist
2. **"Recommended: BuddyX Theme"** notice (dismissible, shown once)
3. **One-click page creation** in Settings → Pages tab
4. **Demo content importer** for instant marketplace preview

### Upgrade Touchpoints (Tasteful)

| Location | Message | Trigger |
|---|---|---|
| Admin menu | "Upgrade to Pro" submenu item | Always visible |
| Settings → E-commerce | "Pro unlocks WooCommerce, EDD" | When viewing platform settings |
| Settings → Gateways | Razorpay accordion (locked, "Pro" badge) | When viewing gateways |
| Settings → Advanced | Analytics section locked | When viewing advanced settings |
| Dashboard → Earnings | "Auto-payout with Pro" teaser | When vendor views earnings |
| After 10 orders | Admin notice: "Growing? See Pro analytics" | One-time, dismissible |
| Withdrawal page | "Automate with Stripe Connect (Pro)" | When processing withdrawals |

### What NOT to Do

- No nag screens
- No feature-gating that breaks existing free workflows
- No "Pro required" on anything that works today
- No more than 1 admin notice at a time
- All notices are dismissible and remembered

---

## 14. SEO Strategy

### Target Keywords

| Keyword | Monthly Volume (est.) | Difficulty | Content |
|---|---|---|---|
| fiverr clone wordpress | 1,200 | Medium | Tutorial blog post |
| wordpress marketplace plugin | 2,400 | High | Comparison post |
| service marketplace wordpress | 800 | Medium | Landing page |
| freelance marketplace plugin | 600 | Low | Comparison post |
| wordpress service booking | 1,800 | High | Tutorial |
| microjob wordpress | 400 | Low | Comparison post |
| sell services online wordpress | 500 | Low | Tutorial |
| wordpress fiverr theme | 1,000 | Medium | Comparison post |
| upwork clone wordpress | 400 | Low | Tutorial |
| buddypress marketplace | 300 | Low | Integration guide |

### On-Page SEO

- Landing page optimized for "service marketplace wordpress"
- Blog posts targeting long-tail keywords
- Schema.org markup on all pages
- Plugin page on WordPress.org optimized for discovery

### Link Building

- Guest posts on WordPress blogs
- BuddyPress.org mention/listing
- WordPress developer community engagement
- GitHub repo with API examples (drives developer links)

---

## 15. Risks & Mitigations

| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| WordPress.org rejection | Low | High | Follow guidelines strictly, no external calls without consent |
| Slow adoption | Medium | Medium | Strong content marketing, demo site, BuddyX bundle |
| Pro conversion too low | Medium | High | Ensure Pro has must-have features (Stripe Connect, Subscriptions) |
| Support burden | High | Medium | Good docs, FAQ, community forum before 1-on-1 support |
| Competitor copies approach | Low | Low | Execute faster, build community moat |
| Negative reviews from bugs | Medium | High | Thorough testing, fast response to support requests |

---

## Appendix: Quick Reference

### Free Plugin by the Numbers

- **17** custom database tables
- **20** REST API controllers
- **80+** API endpoints
- **16** shortcodes
- **6** Gutenberg blocks
- **11** email templates
- **9** custom field types
- **10** order statuses
- **4** seller levels
- **3** payment gateways
- **35+** templates
- **46+** AJAX handlers
- **2** CPTs + 2 taxonomies

### Pro Plugin by the Numbers

- **4** e-commerce adapters
- **1** additional gateway (Razorpay)
- **4** wallet providers
- **3** cloud storage providers
- **4** REST API controllers
- **4** analytics collectors + 4 widgets
- **1** license system
