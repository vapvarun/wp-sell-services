# Pro Extension Points [PRO]

WP Sell Services Pro extends the free plugin **only through documented hooks and
services** -- it never modifies free source. This page covers the Pro-specific
seams. For the base marketplace hooks (services, orders, delivery, disputes,
REST), see [Hooks and Filters](hooks-filters.md).

## How Pro relates to Free

- Pro boots **after** Free (later priority on `plugins_loaded`) and requires Free to be active.
- Pro consumes Free's services and seams rather than calling WordPress APIs directly. Money helpers, the ledger debit-type list, and the display-price seam (`wpss_catalog_price_html`) all live in Free; Pro builds on them, so there is one settlement source of truth.
- E-commerce integrations (WooCommerce, EDD, FluentCart) are a **payment rail only** -- they move money. They never supply the marketplace's amounts, currency, or delivery deadline. Those are WP Sell Services-internal and set by the order provider.

That last point matters when you write an integration. If you source a price or a
due date from the cart plugin, you will disagree with the ledger the moment
someone edits a package or a refund lands.

## Commission

| Filter | Parameters | Purpose |
|--------|-----------|---------|
| `wpss_commission_rate` | `float $rate, object $order, int $vendor_id, int $service_id` | Adjust the commission **percentage** before the split is computed. |
| `wpss_commission_fee` | `float $fee, object $order, float $base, float $rate` | Override the final platform **fee amount** for an order. |

Use `wpss_commission_rate` for percentage logic and `wpss_commission_fee` when a
percentage cannot express what you need -- a flat fee, or an absolute per-plan
override.

```php
// Flat $5 platform fee, regardless of order value.
add_filter( 'wpss_commission_fee', function ( $fee, $order, $base, $rate ) {
    return min( 5.00, $base );
}, 10, 4 );
```

The split is computed once via `CommissionService` and **persisted on the
order**; every rail and payout reads the stored value. Return a value from these
filters and it becomes the number of record -- do not try to adjust the fee later
in the flow.

See [Tiered Commission Rules](../earnings-wallet/tiered-commission.md).

## Analytics

| Hook | Parameters | Purpose |
|------|-----------|---------|
| `wpss_analytics_init` | `AnalyticsManager $manager` | Fires when the analytics subsystem boots. |
| `wpss_analytics_widgets` | `array $widgets` | Register additional analytics widgets. |

## Wallet

| Hook | Parameters |
|------|-----------|
| `wpss_wallet_providers` | `array $providers` |
| `wpss_wallet_credited` | `int $user_id, float $amount, string $description, string $provider_id` |
| `wpss_wallet_debited` | `int $user_id, float $amount, string $description, string $provider_id` |
| `wpss_vendor_payout_processed` | `int $order_id, int $vendor_id, float $amount` |

Register `wpss_wallet_providers` to add your own wallet backend alongside the
built-in Internal Wallet, TeraWallet, WooWallet, and MyCred providers.

## Storage

| Hook | Parameters |
|------|-----------|
| `wpss_storage_providers` | `array $providers` |

Add an S3-compatible or bespoke storage backend next to S3, Google Cloud
Storage, and DigitalOcean Spaces.

## Payment gateways

| Hook | Parameters |
|------|-----------|
| `wpss_payment_gateways` | `array $gateways` |
| `wpss_render_secret_field` | (gateway settings rendering) |

## E-commerce adapter hooks

Each integration exposes lifecycle hooks so you can react to rail-specific
events without coupling to the rail.

**EDD**

| Hook | Fires when |
|------|-----------|
| `wpss_edd_adapter_init` | The EDD adapter boots. |
| `wpss_edd_service_purchased` | An EDD purchase of a service completes. |
| `wpss_edd_services_processed` | All services in an EDD order have been processed. |
| `wpss_edd_order_record_created` | The marketplace order is created from an EDD order. |
| `wpss_edd_service_meta_saved` | Service meta is saved on the EDD product. |
| `wpss_edd_service_checkout_processed` | An EDD checkout line has been turned into an order. |

**FluentCart** exposes parallel hooks (`wpss_fluentcart_adapter_init`,
`wpss_fluentcart_order_created`, and so on).

**SureCart was removed in 1.6.0** and fires nothing.

**WooCommerce is the exception.** The WooCommerce adapter does not fire its own
namespaced lifecycle hooks -- it reuses the core `wpss_order_created` and
`wpss_order_status_changed` hooks instead. Code written against those works
identically whether the sale came through WooCommerce or standalone checkout.

> Use adapter hooks to **observe or annotate**, never to source marketplace
> amounts. The order provider owns price, currency, and delivery deadline.

## Payouts and Stripe Connect

| Hook | Parameters |
|------|-----------|
| `wpss_pro_connect_payout_paid` | `string $payout_id, string $account_id, float $amount, string $currency` |
| `wpss_pro_connect_payout_failed` | `string $payout_id, string $account_id, string $failure_code, string $failure_message` |
| `wpss_pro_connect_transfer_created` | `string $transfer_id, string $account_id, float $amount, string $currency` |

Stripe Connect settlement is recorded on the order so the wallet ledger can
offset it; a refund reverses the ledger only when Stripe actually reclaimed the
money. **Build payout tooling against the wallet ledger and the persisted
commission split, not against re-derived amounts.**

## Recurring services (feature-gated)

Recurring services sit behind a **default-off** feature flag in 1.3.0 while the
feature is finished. Opt in on a development site with:

```php
add_filter( 'wpss_pro_recurring_feature_available', '__return_true' );
```

While the flag is off, all recurring-service UI, settings, and the admin
Subscriptions page stay hidden, so the feature never appears before it ships. Its
REST routes register either way. See
[Recurring Services](../order-management/recurring-services.md).

## Related

- [Hooks and Filters](hooks-filters.md) -- the full reference, free and Pro
- [REST API Controllers](rest-api-controllers.md) -- including the 10 Pro controllers
- [Building Custom Integrations](custom-integrations.md)
- [Money Flow](https://github.com/vapvarun/wp-sell-services/blob/main/docs/architecture/MONEY-FLOW.md)
- [Sub-Order Pattern](https://github.com/vapvarun/wp-sell-services/blob/main/docs/architecture/SUB_ORDER_PATTERN.md)
