# Journey 21 - Vendors pay a plan to sell; commission rules (Pro)

**Roles:** admin, vendor
**Rail:** standalone, woo (plans bill through Stripe on any rail; there is no WooCommerce product for plans)
**Status:** written 2026-11-03; walk pending (paid plan is money)
**Docs:** docs/website/vendor-system/vendor-subscription-plans.md, earnings-wallet/tiered-commission.md

## Steps

### 1. Owner sets up plans
Settings > Vendor Settings > Vendor Subscriptions: enable, create a free plan (0 services means unlimited) and a paid plan with a Stripe Price ID; then tick "Require Subscription to Sell".

**Expect** a paid plan without a Price ID cannot be bought (400 `wpss_plan_not_billable`); the vendor dashboard gains a Subscription section only while the feature is on.

### 2. The gate
As a vendor with no plan open My Services > Create.

**Expect** refused with the required-subscription banner while "Require" is on, allowed when it is off; the site owner (`manage_options`) is never blocked.

### 3. Buy a paid plan
Subscribe, pay on Stripe's hosted page, return.

**Expect** the plan is active only after payment, the service limit is the plan's, and the plan's commission override applies to new orders (it beats tiered rules).

### 4. Cancel keeps the paid period (card fix 2026-11-03)
Cancel from the Subscription section.

**Expect** the badge reads Ending, the row shows Ends On with the period end, there is no Cancel button, and the vendor can still create services until that date; Stripe's `customer.subscription.deleted` (or the date passing) ends it. A free plan ends at once. Guard: `tests/pro/test-subscription-cancel-keeps-paid-period.php`.

### 5. Failed renewal
Send `invoice.payment_failed`.

**Expect** status past_due and creation refused until `invoice.payment_succeeded`.

### 6. Commission rules
Settings > Commission: add a Category rule and a Vendor Level rule with different priorities; place an order in that category from a Top Rated vendor.

**Expect** the first active rule by priority sets the rate, it overrides the vendor's own rate, and the rate is frozen on the order (changing the rule later does not change that order). Sell Services > Dashboard's Pro card shows Tiered Commission active while an active rule exists.

**Known gap:** there is no admin list of vendor subscriptions (the Subscriptions menu is Recurring Services, switched off). An owner cannot see who is on which plan without the database.

Guards: `tests/pro/test-seams-subscriptions-carrier.php`, `tests/test-plan-save-one-rule.php`, `tests/test-tiered-commission-matches-once.php`, Pro `tests/Unit/VendorSubscriptions/*`, Pro `tests/Unit/TieredCommission/*`.
