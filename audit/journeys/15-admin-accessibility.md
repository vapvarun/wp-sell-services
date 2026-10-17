# Journey 15 - Settings read correctly to a screen reader and in low contrast

**Roles:** admin
**Rail:** any
**Status:** written 2026-10-18; walk pending

## Steps

### 1. Every Settings tab has a heading outline (card:10412306674, card:10412156185, card:10412156063)
Open each tab (General, Checkout, Pages, Payment Gateways, Commission & Tax, Payouts, Vendor, Orders, Emails, Branding, Integrations, Advanced) with the browser's heading list or a screen reader's headings key.

**Expect** each tab starts with a level-2 heading, every card title is a heading (not a paragraph), sub-sections (Add New Rule, Payout Method, Subscription Plans) are level 3 below it, and no level is skipped. Cards look the same as before (small uppercase titles).

### 2. Helper text and sidebar labels are readable (card:10412155855, card:10412155759)
Settings at 1440 and 390, browser zoom 200%.

**Expect** the grey line under the page title, field descriptions and the sidebar group labels (SETUP, MONEY, MARKETPLACE...) read clearly on the grey admin background; run an axe or Lighthouse contrast check and see none on these classes.

### 3. Add-on note on the service page (card:10384448877)
A service with add-ons, light and dark theme.

**Expect** the "N add-ons available from $X" line passes 4.5:1 in both.
