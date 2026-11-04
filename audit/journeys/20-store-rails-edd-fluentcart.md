# Journey 20 - EDD and FluentCart rails (Pro, beta)

**Roles:** admin, buyer, vendor
**Rail:** edd, fluentcart (each walked on its own install; a step on another rail is `skipped`, never `fail`)
**Status:** written 2026-11-03; walk pending, low priority (beta rails)
**Docs:** docs/website/payments-checkout/alternative-platforms.md

Both rails load only when the `wpss_pro_beta_rails` filter (or `WPSS_PRO_BETA_RAILS`) is on **and** Settings > General names the rail.

## Steps

### 1. Enable
Turn the beta filter on, install the platform, choose it in Settings > General.

**Expect** without the filter the rail is not offered; with it, our Payment Gateways tab says the platform handles payment and shows no gateway cards.

### 2. Buy a service
As the buyer buy a service through the platform's own checkout.

**Expect** one WPSS order, paid, with the platform's order linked; past orders on other rails are untouched.

### 3. Pay one existing amount (tip, milestone phase, extension)
**Expect** FluentCart resolves a real pay-order through its resolver; EDD has no pay-order rail and the docs say to use Standalone or WooCommerce for these.

### 4. Refund in the platform
Refund the order in the platform's own screen.

**Expect** FluentCart: the WPSS order shows refunded. EDD: a refund does not reach WPSS today (only `complete` is handled) - the docs must say so; our refund screens step aside for both rails (`wpss_order_money_rail`).

Guards: `tests/pro/test-beta-rails.php`, `tests/test-fluentcart-pay-order-lookup-by-key.php`, `tests/test-dispute-closes-on-rail-refund-and-cancel.php`, `tests/test-store-rails-start-orders-with-delivery-terms.php`.
