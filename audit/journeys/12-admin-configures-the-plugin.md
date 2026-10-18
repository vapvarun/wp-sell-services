# Journey 12 - Admin configures the plugin, tab by tab

**Roles:** admin
**Rail:** standalone, woo
**Status:** written 2026-10-18; walk pending (see docs/qa/walks.json)
**Docs:** docs/website/platform-settings, docs/website/getting-started

Seed: a fresh site with the plugin and Pro active, no demo content. Expectation source: what the settings pages promise.

## Steps

### 1. Every screen under Sell Services opens
Dashboard, All Services, All Requests, Categories, Tags, Moderation, Review Moderation, Disputes, Vendors, Orders, Withdrawals, Analytics, Audit Log, My Notifications, Settings, License (Pro), Member Reports.

**Expect** each loads with its own H1, no PHP notice, no console error, a designed empty state when there is no data, and no horizontal scroll at 390px.

### 2. Every Settings tab saves and survives a reload
General, Checkout, Pages, Payment Gateways (standalone only), Commission & Tax, Payouts, Vendor Settings, Orders & Disputes, Emails, Branding, Integrations, System.

**Expect** per tab: change one field, save, reload, the value stays; a negative number in a number field saves as 0 (card:10403454753); an empty required label does not save blank (card:10403205035).

### 3. Pro options are marked and inert without a licence
**Expect** every Pro-only control carries the Pro badge, and with Pro deactivated the control is hidden or disabled, never a working-looking field that does nothing.

### 4. Headings read in order
**Expect** one H1, cards are H2, sub-sections H3, no skipped level on any tab on either rail (card:10412156185; guard `tests/test-settings-heading-outline.php`).

Same check, visual side: every `h2` on every Settings tab is the same size and case (card:10412306674, card:10412156063), and the first heading on Payment Gateways has no empty band above it. Pro cards injected into a tab follow the same rule.

### 5. The Payment Gateways tab follows the rail
Standalone: gateway cards. Woo: one notice that WooCommerce owns payment.

**Expect** switching rails never rewrites past orders, and a gateway saved on one rail is not shown as active on the other.

### Test email and the page template
Settings > Emails > send a test: Mailpit receives `emails/test-email` and the screen reports success only then. Plugin pages render through `wpss-fullwidth-template` on a theme with no native full-width support and stay inside the theme's own template where it has one.
