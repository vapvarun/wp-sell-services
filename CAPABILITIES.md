# What WP Sell Services can and cannot do

Buyer-level truth for the store listing and for anyone deciding whether this
plugin fits. Written to be read before purchase, not after.

**Version**: 1.3.1 · **Last verified**: 2026-08-01

`YES` — ships and is exercised · `PARTIAL` — ships with a stated limit ·
`NO` — does not ship, whatever a roadmap says.

---

## Can I run a services marketplace without any other plugin?

**YES.** Free includes standalone checkout with Stripe, PayPal and Offline
gateways, the full order lifecycle, messaging, reviews, disputes and vendor
payouts. WooCommerce is optional, not required.

## Can I use my existing ecommerce plugin?

**YES (Pro)** for WooCommerce, Easy Digital Downloads, FluentCart and SureCart.
One rail is active at a time and it owns payment entirely — the plugin never
offers its own gateways alongside your platform's, because two checkouts for one
button confuses buyers and splits your reporting.

## Can vendors be paid automatically?

**PARTIAL.** Manual payouts are in Free: mark paid, and export what is owed as
CSV for a bank or PayPal bulk upload. Automated payouts are Pro — Stripe Connect
and PayPal Payouts. **An owner can always pay every vendor with zero
integrations**; automation is opt-in.

## Can buyers pay in stages?

**YES**, on Standalone and WooCommerce. Milestone contracts split a
buyer-request order into phases that unlock one at a time, and tips and paid
extensions work the same way. Run your marketplace on one of those two rails if
you need this.

## Can I charge subscriptions for a service?

**NO.** Recurring billing exists in the Pro codebase but ships behind a
default-off flag with its UI hidden. Do not buy Pro for recurring billing.

## Can I take a commission?

**YES.** A global rate, per-vendor overrides, and tiered rules in Pro.
Commission comes off vendor earnings automatically — on completion for a service
order, and at payment time for tips, milestone phases and extensions so the
money reaches the vendor's wallet before delivery.

## Can I moderate what vendors publish?

**YES.** Service moderation and review moderation, both optional.

## Is there an API?

**YES.** A REST API under `wpss/v1` covering the marketplace, with Application
Password and cookie authentication. See
[REST API overview](docs/website/developer-guide/rest-api-overview.md).

## Can it be translated?

**YES**, fully translation-ready — every user-facing string is extractable and
loadable, in both plugins. No locales are bundled; you or a translator supply
them.

## Will it work with my theme?

**YES** in the general case. The frontend is token-driven with dark-mode and RTL
support, and does not require one of our themes.

---

Fuller detail: [Feature catalog](docs/website/feature-catalog.md) ·
[Free vs Pro](docs/website/getting-started/free-vs-pro.md). Developer-side
capability and role reference lives in `audit/CAPABILITIES.md`.
