# Feature catalog

The canonical list of what WP Sell Services does, and which tier it is in. If a
feature is not on this page, treat it as not shipping.

**Version**: 1.7.0-dev · **Last verified**: 2026-08-26 — every row checked against source

Free and Pro documentation both live in this folder — the Free plugin's
`docs/website/` is the single source of truth. There is no separate Pro docs
tree.

Legend: **Yes** ships and is exercised · **Partial** ships with a stated limit ·
**Not yet** built but deliberately off.

---

## Selling and buying

| Feature | Free | Pro |
|---|---|---|
| Service listings with packages and add-ons | Yes | Yes |
| Vendor profiles, portfolios, seller levels | Yes | Yes |
| Buyer requests and vendor proposals | Yes | Yes |
| Order lifecycle with requirements, delivery, revisions | Yes | Yes |
| Order messaging | Yes | Yes |
| Reviews and ratings | Yes | Yes |
| Disputes with frontend messaging | Yes | Yes |
| Favourites | Yes | Yes |
| Services per vendor | Configurable, default 20 (set 0 for unlimited) | Same |

## Money

| Feature | Free | Pro |
|---|---|---|
| Standalone checkout (no other plugin needed) | Yes | Yes |
| Payment gateways | Stripe, PayPal, Offline | + Razorpay |
| Ecommerce integrations | — | WooCommerce, EDD, FluentCart |
| Commission, per-vendor rates | Yes | + tiered rules |
| Vendor subscription plans (charge vendors to sell) | — | Yes, off until you enable it |
| Service limits: gallery images, add-ons, FAQs, buyer requirements | 4 / 3 / 5 / 5 | Unlimited |
| Service limits: pricing packages, video embeds | 3 / 1 | Same — Pro does not raise these |
| Vendor wallet and withdrawals | Yes, built in | Same, plus the option to keep balances in TeraWallet or MyCred instead |
| Manual payouts (mark paid, CSV export) | Yes | Yes |
| Stripe Connect automated payouts | — | Yes |
| PayPal Payouts batches | — | Yes |
| Tips | Yes | Yes |
| Paid extensions on catalog orders | Yes | Yes |
| Milestone contracts on buyer-request orders | Yes | Yes |
| Refunds, including partial | Yes | Yes |
| Display currency (approximate price in the shopper's currency) | — | Yes |

**Paying a single existing amount** — a tip, a milestone phase, a paid
extension — is supported on **Standalone** and **WooCommerce**. If your
marketplace needs those, run it on one of those two.

**Display currency is presentation only.** The shopper's currency is detected
from their timezone and shown as an approximate figure beside the real price;
every order, payout and refund is still settled in your base currency, and the
charge currency is stated at checkout. It is not multi-currency settlement.

## Marketplace surfaces

| Feature | Free | Pro |
|---|---|---|
| Frontend dashboard (buyer + vendor) | Yes | Yes |
| Role-based dashboard sections | Yes | Yes |
| Dark mode and theme integration | Yes | Yes |
| Gutenberg blocks and shortcodes | Yes | Yes |
| Email notifications | Yes | + white-label branding |
| In-app notifications | Yes | Yes |
| Push notifications to phones (Firebase) | — | Yes |
| Realtime messaging (Pusher-protocol) | Yes | Yes |

## Operations

| Feature | Free | Pro |
|---|---|---|
| Admin order, vendor, dispute, withdrawal management | Yes | Yes |
| Moderation queues | Yes | Yes |
| Analytics | Basic stats | Full dashboards; CSV export on the admin screen |
| File storage | Your server | Your server. S3, Google Cloud and DigitalOcean can be configured, but delivery files are **not** sent to them yet -- the bucket is reachable only through the REST API. See the note below. |
| Audit log | Yes | Yes |
| REST API | Yes | Yes |
| WP-CLI | Yes | Yes |

**Cloud storage is not connected to deliveries.** The S3, Google Cloud and
DigitalOcean drivers work and the connection test really does reach your bucket,
but nothing routes a delivery file through them -- deliveries are still stored in
the WordPress media library. Configure it only if you are building against the
REST API. If you want delivery storage offloaded, wait for that to ship.

## Not shipping yet

| Feature | Status |
|---|---|
| Recurring / subscription billing for services | **Not yet.** The code is in Pro but every surface is switched off and there is no setting to turn it on. Do not buy Pro for recurring billing. See [Recurring Services](order-management/recurring-services.md). |

---

## How this page is maintained

Entries are added only after the feature has been exercised, not when the code
lands. A row here is a promise; an owner reads it before buying.

Related: [Free vs Pro](getting-started/free-vs-pro.md) ·
[Capabilities](https://github.com/vapvarun/wp-sell-services/blob/main/CAPABILITIES.md) · `audit/FEATURE_AUDIT.md` for the
developer-side inventory.
