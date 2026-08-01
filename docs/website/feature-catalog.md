# Feature catalog

The canonical list of what WP Sell Services does, and which tier it is in. If a
feature is not on this page, treat it as not shipping.

**Version**: 1.4.0 · **Last verified**: 2026-08-01

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
| Services per vendor | Limited | Unlimited |

## Money

| Feature | Free | Pro |
|---|---|---|
| Standalone checkout (no other plugin needed) | Yes | Yes |
| Payment gateways | Stripe, PayPal, Offline | + Razorpay |
| Ecommerce integrations | — | WooCommerce, EDD, FluentCart, SureCart |
| Commission, per-vendor rates | Yes | + tiered rules |
| Vendor wallet and withdrawals | Yes | + TeraWallet, WooWallet, MyCred |
| Manual payouts (mark paid, CSV export) | Yes | Yes |
| Stripe Connect automated payouts | — | Yes |
| PayPal Payouts batches | — | Yes |
| Tips | Yes | Yes |
| Paid extensions on catalog orders | Yes | Yes |
| Milestone contracts on buyer-request orders | Yes | Yes |
| Refunds, including partial | Yes | Yes |

**Paying a single existing amount** — a tip, a milestone phase, a paid
extension — is supported on **Standalone** and **WooCommerce**. If your
marketplace needs those, run it on one of those two.

## Marketplace surfaces

| Feature | Free | Pro |
|---|---|---|
| Frontend dashboard (buyer + vendor) | Yes | Yes |
| Role-based dashboard sections | Yes | Yes |
| Dark mode and theme integration | Yes | Yes |
| Gutenberg blocks and shortcodes | Yes | Yes |
| Email notifications | Yes | + white-label branding |
| In-app notifications | Yes | Yes |
| Realtime messaging (Pusher-protocol) | Yes | Yes |

## Operations

| Feature | Free | Pro |
|---|---|---|
| Admin order, vendor, dispute, withdrawal management | Yes | Yes |
| Moderation queues | Yes | Yes |
| Analytics | Basic stats | Full dashboards with export |
| File storage | Your server | + S3, Google Cloud, DigitalOcean Spaces |
| Audit log | Yes | Yes |
| REST API | Yes | Yes |
| WP-CLI | Yes | Yes |

## Not shipping yet

| Feature | Status |
|---|---|
| Recurring / subscription billing for services | **Not yet.** Present in Pro behind a default-off flag with its UI hidden in 1.3.x. Do not buy Pro for recurring billing. See [Recurring Services](order-management/recurring-services.md). |

---

## How this page is maintained

Entries are added only after the feature has been exercised, not when the code
lands. A row here is a promise; an owner reads it before buying.

Related: [Free vs Pro](getting-started/free-vs-pro.md) ·
[Capabilities](../../CAPABILITIES.md) · `audit/FEATURE_AUDIT.md` for the
developer-side inventory.
