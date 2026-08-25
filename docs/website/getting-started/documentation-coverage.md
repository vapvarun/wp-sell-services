# Documentation Coverage

**As of WP Sell Services 1.4.0 and WP Sell Services Pro 1.4.0.**

This page exists so you never have to guess whether something is missing or you
just cannot find it. It states what is documented, and -- more usefully -- what
is not.

Both plugins' documentation lives in **one place**, the free plugin's
`docs/website/` tree, in the GitHub repository. Pro features are marked
**[PRO]** inline rather than split into a second manual, because almost every
Pro feature is an extension of a free one and splitting them meant a reader had
to hold two documents open. The Pro plugin's own docs folder is retired and
publishes nothing.

---

## What is documented

Every page below exists, is listed in `docs_config.json`, and is checked on each
run of `bin/docs-audit.py`.

| Area | Covered |
|---|---|
| **Getting started** | Introduction, installation and requirements, quick setup, free vs Pro comparison, Pro overview, dashboard tour, license activation **[PRO]**, launch checklist, What's New |
| **Buying** | Browsing and purchasing, choosing a package, order tracking, buyer dashboard, favorites, buyer FAQ, buyer tips |
| **Selling** | Service wizard, pricing and packages, add-ons, media, requirements and FAQs, editing and pausing, publishing and moderation |
| **Buyer requests** | Posting a request, submitting proposals, managing requests, fixed vs milestone proposal contracts |
| **Orders** | The 11-status lifecycle, requirements collection, messaging, deliveries and revisions, **milestone contracts**, **paid extensions**, tipping, recurring services, order settings |
| **Payments and checkout** | Standalone mode, Stripe, **WooCommerce (including the pay-order handoff)** **[PRO]**, alternative platforms (EDD / FluentCart / SureCart) **[PRO]**, other gateways, Stripe Connect **[PRO]**, currency and tax, display currency |
| **Earnings and payouts** | Wallet, earnings dashboard, commission system, tiered commission **[PRO]**, withdrawals, vendor payouts, automated payouts **[PRO]**, the ledger and CSV export |
| **Vendors** | Becoming a vendor, vendor dashboard, profile and portfolio, seller levels, vacation mode, vendor settings, vendor subscription plans **[PRO]** |
| **Reviews** | Review system, reputation and moderation |
| **Disputes** | Opening a dispute, the dispute process, admin mediation |
| **Notifications** | Email configuration, email types, in-app notifications, realtime (WebSocket) updates |
| **Display and SEO** | Shortcodes, Gutenberg blocks, search and filters, template overrides, JSON-LD schema |
| **Analytics** | Vendor analytics, admin analytics **[PRO]**, data export **[PRO]** |
| **Cloud storage** **[PRO]** | Overview and setup (S3, Google Cloud Storage, DigitalOcean Spaces) |
| **Admin tools** | Service moderation, vendor management, withdrawal approvals, manual orders, the guided tour |
| **Platform settings** | General, Pages, **Payment Gateways**, **Commission & Tax**, **Payouts**, White Label **[PRO]**, Advanced |
| **Developer guide** | REST API overview, REST controller reference, hooks and filters, capabilities, database schema, template and theme integration, email customization, Action Scheduler, WP-CLI, Abilities API, Pro extension points, custom integrations |

### Recently closed gaps

| Was missing | Now at |
|---|---|
| The three money settings tabs created by the July 2026 regroup | [Payment Gateways](../platform-settings/payment-gateways-settings.md), [Commission & Tax](../platform-settings/commission-tax-settings.md), [Payouts](../platform-settings/payouts-settings.md) |
| The WooCommerce pay-order handoff, and which platforms support it | [WooCommerce Checkout](../payments-checkout/woocommerce-checkout.md#paying-a-milestone-tip-or-extension) |
| `wpss_pay_order_url`, the payment-handoff seam | [Hooks and Filters](../developer-guide/hooks-filters.md#wpss_pay_order_url----the-payment-handoff-seam) |
| Milestone failure paths, the 48-hour abandon sweep, and where lock-step is *not* enforced | [Milestone Contracts](../order-management/milestones-wpss.md) |
| Six live REST routes, the two-namespace split, and the real error codes | [REST Controllers](../developer-guide/rest-api-controllers.md), [REST Overview](../developer-guide/rest-api-overview.md) |

---

## What is NOT documented

Stated plainly, because a gap you know about costs less than one you discover.

### Deliberately not documented

| Not covered | Why |
|---|---|
| **Recurring billing end to end** | The feature sits behind a default-off flag and is deferred. [Recurring Services](../order-management/recurring-services.md) describes what exists; it does not walk a full subscription lifecycle, because that lifecycle is not finished. Do not plan a launch around it. |
| **Per-jurisdiction tax** | The plugin has one tax rate, full stop. There is no VAT MOSS, no per-country table, no digital-services handling, so there is nothing to document. Run checkout on WooCommerce and use its tax tables if you need this. |
| **Third-party gateway configuration** | We document what to paste where, not how to obtain a Stripe restricted key or a PayPal live app. Those belong to the gateway and change on their schedule, not ours. |

### Known gaps, not yet written

| Not covered | Status |
|---|---|
| **A "What's New in 1.4.0" page** | The newest What's New page is 1.3.0. The 1.4.0 changes are in both plugins' `readme.txt` changelogs and in the pages they affect, but there is no single narrative page for the release yet. |
| **A dedicated Audit Log page** | **Sell Services > Audit Log** is mentioned where it is relevant, and `GET /audit-log` is in the REST reference, but there is no page explaining what is recorded, retention, or how to read an entry. |
| **The Vendors, Orders & Disputes, and Emails settings tabs** | These are documented by *feature*, not by *tab*: see [Vendor Settings](../vendor-system/vendor-settings.md), [Order Settings](../order-management/order-settings.md) and [Email Configuration](../notifications-emails/email-configuration.md). The Platform Settings section does not yet have a page per tab for these three, so a reader looking tab-by-tab will not find them where they expect. |
| **Migration between e-commerce rails** | Switching rails is safe (past orders are never rewritten, old gateway webhooks keep working) and that is stated in the REST and WooCommerce pages, but there is no step-by-step migration guide. |
| **Scale and performance guidance** | No page on running the marketplace at thousands of services, vendors or orders: no indexing notes, no caching guidance, no benchmark figures. |
| **Multisite** | Neither documented nor claimed. Assume it is unsupported until it is tested. |

### Known product gaps that ARE documented

These are limitations of the software, not of the writing. They are called out
where a reader will hit them:

- **Milestone, tip and extension payments work on Standalone and WooCommerce
  only.** EDD and FluentCart have no pay-order flow, so those links
  are a dead end there. See
  [WooCommerce Checkout](../payments-checkout/woocommerce-checkout.md#platform-support----read-this-before-promising-it).
- **Lock-step milestone payment is a workflow rail, not a security control.**
  It is enforced on the standalone checkout and the REST pay endpoints, but not
  on the WooCommerce order-pay URL. See
  [Milestone Contracts](../order-management/milestones-wpss.md#the-lock-step-rule-and-where-it-actually-holds).
- **Tips, milestone phases and paid extensions are not escrowed.** They credit
  the vendor at payment, not at delivery. See
  [Money Flow](https://github.com/vapvarun/wp-sell-services/blob/main/docs/architecture/MONEY-FLOW.md).
- **Stripe Connect bypasses the clearance window** by paying at charge time.
  See [Payouts Settings](../platform-settings/payouts-settings.md).

---

## How this stays true

`bin/docs-audit.py` runs as a gate and fails the build on any of:

- a page on disk that is not published, or published but missing
- a broken image, a broken internal link, or a link that escapes the published tree
- a hook documented in a reference table but never fired in the source
- a `Settings > X` tab or `Sell Services > X` menu path that does not exist,
  across `docs/website/`, `docs/architecture/`, `docs/qa/` and `docs/decisions/`
- a UI label the plugin does not render

It does not, and cannot, check whether prose is *true*. That is what code
citations and the per-release resync pass are for. If you find a page that
contradicts the plugin, that is a bug worth reporting -- the last full
source-to-docs resync was **2026-08-01, against 1.4.0**.
