# Product-type inventory (read-only, 1.9.0)

Goal: WP Sell Services is the commerce core for services today and for physical goods later. This
inventory lists, layer by layer, what is already generic and where generic code assumes "the thing sold is
a service". **No behaviour changes in 1.9.0.** It is the starting list for a product-type seam.

Method: grep of service-only terms (`service_id`, `wpss_service`, package, revision, delivery, requirement)
across the generic layers, plus a read of the schema, the checkout intent and the gateway contract.
Date: 2026-10-18.

## 1. Already generic (reuse as is for another product type)

| Layer | Why it is generic |
|---|---|
| `PaymentGatewayInterface` | `create_payment( amount, currency, metadata )`, refund, webhook. Knows nothing about what is bought. |
| Refund state, ledger rows, commission base (`functions/ledger.php`) | Works on an order total and a vendor share. |
| Wallet and withdrawals (`WalletProviderInterface`, `EarningsService` payout side) | Money only. |
| Notifications, audit log, reports, reviews, conversations | Keyed on an order or user, not on a service. |
| E-commerce rails (`EcommerceAdapterInterface`) | Rail-level (Woo, EDD, Fluent, standalone). |
| Storage and SEO seams | Provider contracts, no product assumption. |

## 2. Service-only (belongs to the "service" product type)

| Concept | Where it lives today |
|---|---|
| Packages, add-ons, express delivery | `service_packages` table, `functions/packages.php`, `Service` model |
| Requirements form and answers | `order_requirements` table, `functions/requirements.php` |
| Delivery, revisions, extension requests | `deliveries`, `extension_requests` tables, `revisions_included/used` columns |
| Deadline lifecycle (late, auto-complete, reminders) | `OrderWorkflowManager` cron jobs, `delivery_deadline`/`original_deadline` columns |
| Service statuses | `pending_requirements`, `requirements_submitted`, `in_progress`, `delivered`, `revision_requested`, `late` in `ServiceOrder` |
| Buyer requests and proposals, portfolio | `proposals`, `portfolio_items` tables |
| Service limits and publish thresholds | `functions/services.php` |

## 3. Coupling points (generic code that assumes a service)

Each row is a place a physical-goods type would have to work around today. Ordered by how deep it goes.

| # | Coupling | Evidence | Effect on a new type |
|---|---|---|---|
| 1 | `wpss_orders` is service-shaped: `service_id NOT NULL`, `package_id`, `addons`, `revisions_*`, deadlines are columns. **1.9.0 added `product_type` (default `service`) and `product_id` (mirrors `service_id`) with an index and a batched backfill; `service_id` stays authoritative.** | `SchemaManager::get_orders_table` | The service-only columns still move to type data when the product-type registry lands. |
| 2 | `CheckoutIntent` carries `service_id`, `package_id`, `addons`, `addons_total` as first-class fields | `src/Checkout/CheckoutIntent.php:104-128`. **1.9.0: the intent carries its own receipt `description` in its metadata; `service_id`/`package_id` stay for the service type.** | Intent needs `items[]` of `{product_id, type, qty, options}`; the service fields become one item's options. |
| 3 | `PaymentController` requires `service_id` and `package_id` on create and confirm | `src/API/PaymentController.php:82-143,202`. **1.9.0: routes accept `product_id` + `product_type`; `service_id` stays an alias and any type but `service` is refused.** | Options stay service-shaped until the type registry lands. |
| 4 | Stripe and PayPal read `metadata['service_id']`, title via `get_the_title( $service_id )`, vendor via the service's `post_author` | `StripeGateway.php:236,1034-1043`, `PayPalGateway.php:694,740`. **1.9.0: Stripe and PayPal print the intent's description and no longer look up the service; the legacy `create_payment_intent()` still reads the service author.** | Gateways must receive a neutral description and payee from the intent, not look up a service. |
| 5 | Contract methods named for services (renamed in 1.9.0: `cart_has_sellable_items`, `get_cart_sellable_items`, `has_sellable_items`, `get_sellable_items`, `is_sellable_product`). `ProductProviderInterface::get_service(): ?Service` still returns the service model | `CheckoutProviderInterface`, `OrderProviderInterface`, `ProductProviderInterface` | `get_service()` stays in 1.9.0: renaming it needs a neutral product model to return, which belongs with the item-based intent. |
| 6 | One status list for every order | `ServiceOrder::STATUS_*` (18 constants) mixes generic (`pending_payment`, `completed`, `cancelled`, `refunded`) with service-only states | Each type declares its states and transitions; generic ones stay in core. |
| 7 | Order workflow crons are service lifecycle | `OrderWorkflowManager` (late, auto-complete, requirements reminders) | Register per type, not for every order. |
| 8 | `wpss_calculate_tax()` takes `$service_id` | `functions/money.php:234`; filter `wpss_checkout_tax_rate` | Pass a product id and type. Filter signature changes (Compat). |
| 9 | Earnings screens resolve a service title from the order | `EarningsService.php:612-623` | Ask the type for a line label. |
| 10 | Standalone adapter is wired to services (149 hits) | `src/Integrations/Standalone` | Becomes the "services" implementation of a neutral standalone rail. |

## 4. Seam (partly built in 1.9.0)

**Built in 1.9.0:** `ProductTypeInterface` (`get_id`, `get_label`, `owns`, `get_title`, `get_vendor_id`), `ServiceProductType`, the `wpss_product_types` registry (`wpss()->get_product_types()` / `get_product_type()`), the REST payment type check and the earnings line label. **Not built:** `statuses()`, `checkout_options()`, `fulfillment()` and `register_cron()` below.

Proposed full contract:

```
ProductTypeInterface
  get_id(), get_label()
  statuses(): array            // type-specific states and allowed transitions
  line_label( $order ): string // what an order line is called on receipts and earnings
  checkout_options( $request ) // validated options (package/add-ons, or size/colour)
  fulfillment(): FulfillmentInterface   // delivery now, shipping later
  register_cron(): void        // lifecycle jobs only for this type
```

Registry `wpss_product_types` (id => instance) read through `wpss_validate_registry()`. "service" is the
first registered type; core never branches on `'service'`.

## 5. Order of work for a later release

1. Rename the contract methods in section 3 row 5 and the filter arguments in row 8 (Compat list).
2. Add `product_type` (default `service`) and `product_id` to `wpss_orders`, backfill from `service_id`.
3. Make `CheckoutIntent` item-based; adapt `PaymentController` and both gateways (after the sandbox is wired).
4. Move service-only columns and statuses behind `ProductTypeInterface`.
5. Build the goods type as a separate plugin, registered only through the filter, as the proof.

Out of scope here: inventory/stock, shipping rates and tax classes for goods. They arrive with the goods type.
