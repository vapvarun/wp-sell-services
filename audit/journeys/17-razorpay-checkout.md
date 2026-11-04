# Journey 17 - Buyer pays with Razorpay (Pro)

**Roles:** admin, buyer (`subscriber`)
**Rail:** standalone (Razorpay registers only there; on a store rail the Payment Gateways tab says the store handles payment)
**Status:** written 2026-11-03; walk pending, money (run after the sandbox is wired, see `docs/qa/` money-cards-last)
**Docs:** docs/website/payments-checkout/other-gateways.md, platform-settings/payment-gateways-settings.md

## Steps

### 1. Owner turns Razorpay on
Settings > Payment Gateways > Razorpay: enter test Key ID, Key Secret and Webhook Secret, tick Enabled, save.

**Expect** the card saves and re-renders with the secrets masked; with the keys empty the gateway does not offer itself at checkout even when ticked.

### 2. Buyer pays for a service
As the buyer, add a service with an add-on, open checkout, choose Razorpay, pay with a Razorpay test card.

**Expect** the amount Razorpay shows equals the checkout total (add-on and tax included, priced server-side); the order is created once, paid, and moves to requirements; the buyer lands on the order.

### 3. Signature and capture are what settle it
Replay the verify call with a changed `razorpay_signature`, then with a payment id from another site's order.

**Expect** both refused ("Payment verification failed"), no order created, no money settled. A payment left `authorized` is captured before settling.

### 4. Webhook
Send `order.paid` and `refund.created` to `/wpss-payment/razorpay/callback/` with a valid `X-Razorpay-Signature`; send one twice; send one with a bad signature; send one with no webhook secret saved.

**Expect** a valid event settles or records once, the repeat changes nothing, a bad signature gets 400, no secret gets 500; `payment.captured` is ignored by design.

### 5. Refund
As admin, refund the order from Sell Services > Orders.

**Expect** Razorpay receives one refund for the right amount, the vendor's earnings reverse once, and a `refund.created` echo for the same refund adds nothing.

### 6. Owner turns it off
Untick Enabled.

**Expect** checkout no longer offers it; a create-order call answers 403 `wpss_gateway_disabled`; past orders still refund and their webhooks still land.

Guards: `tests/pro/test-razorpay-settle-path.php`, `tests/pro/test-razorpay-webhook-dedup-key.php`, `tests/test-razorpay-secret-constants.php`, `tests/test-refund-dedupe-contract.php`.
