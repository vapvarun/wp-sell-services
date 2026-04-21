# Sub-Order Pattern

**Audience:** contributors extending the 1.1.x money flow.
**Status:** canonical. Every new "buyer pays the vendor mid-order" feature
must reuse this pattern.
**Introduced:** 1.0.0 (tips) · generalised in 1.1.0 (extensions, milestones).

## Why this pattern exists

Tips, paid deadline extensions, and milestone phases all answer the same
product question: *"How do we charge the buyer more money on top of an
existing order, split the commission correctly, credit the vendor's
wallet, and leave a clean audit trail — without inventing a second order
system?"*

Rather than bolt a new table on for each feature, 1.1.0 consolidated them
into a single **sub-order pattern** that reuses the `wpss_orders` table,
the existing checkout, the existing commission split, and the existing
wallet ledger. A sub-order is a real row in `wpss_orders` whose shape
tells the plugin "this is a phase of another order, not a standalone
purchase."

The pattern survives any future "pay the vendor during an order" feature
(retainers, change orders, etc.) without schema churn.

## Discriminator columns

Every sub-order is identified by two columns on `{prefix}wpss_orders`:

| Column | Value | Meaning |
|---|---|---|
| `platform` | `tip` / `extension` / `milestone` | What *kind* of sub-order this is. Routes checkout, stats filters, email gating, sales-list visibility. |
| `platform_order_id` | parent service order ID | Links the sub-order back to the parent it modifies. Reporting and the vendor dashboard join on this. |

Platform markers are defined as class constants on the owning service:

```php
TippingService::ORDER_TYPE         = 'tip';
ExtensionOrderService::ORDER_TYPE  = 'extension';
MilestoneService::ORDER_TYPE       = 'milestone';
```

No new CPT. No new sibling table. The sub-order is an order row that
happens to carry a platform marker the rest of the plugin already knows
to respect.

## The shared credit path

All three sub-order flows credit the vendor through the **same**
`wpss_order_paid` action. Each service registers a listener that gates on
its own `platform` marker:

```php
add_action( 'wpss_order_paid', [ $this, 'handle_order_paid' ], 20, 1 );

public function handle_order_paid( int $order_id ): void {
    $order = wpss_get_order( $order_id );
    if ( ! $order || self::ORDER_TYPE !== ( $order->platform ?? '' ) ) {
        return;
    }
    $this->credit_*_on_payment_complete( $order_id );
}
```

Priority 20 is deliberate — earlier listeners (audit logs, accounting
export, etc.) see the raw paid sub-order first; the service-specific
credit runs after and flips the sub-order to its terminal state.

`credit_*_on_payment_complete()` is responsible for:

1. Calculating the commission split (uses `CommissionService`).
2. Writing the `wpss_wallet_transactions` row with `type=` the
   service-specific ledger type (see below).
3. Updating the sub-order row's `vendor_earnings` / `platform_commission`
   columns.
4. Flipping the sub-order status to its feature-specific terminal state
   (`completed` for tip, `completed` for extension with `+N days` applied
   to the parent, `in_progress` for milestone so the vendor can submit
   work).
5. Firing the feature-specific event (`wpss_tip_sent`,
   `wpss_extension_approved`, `wpss_milestone_paid`).

## Ledger types

The vendor's wallet-transactions row carries a `type` column that the
LedgerExporter and analytics surfaces use to categorise the income:

| Sub-order | `wallet_transactions.type` | Constant |
|---|---|---|
| Tip | `tip` | `TippingService::TYPE_TIP` |
| Extension | `extension` | `ExtensionOrderService::TYPE_EXTENSION` |
| Milestone phase | `milestone` | `MilestoneService::TYPE_MILESTONE` |
| Base order completion | `order_earning` | (owned by `OrderWorkflowManager`) |
| Vendor withdrawal | `withdrawal` | (owned by `InternalWalletProvider`) |

Adding a new sub-order kind means adding a new type string here and a
matching branch in `LedgerExporter::type_label()`.

## Idempotency key convention

Duplicate-webhook guards key on the **sub-order ID**, not the parent:

```
reference_type = 'order'
reference_id   = <sub_order_id>   // NOT the parent
```

This makes the ledger row self-describing — the reference column points
at the object that carries the money. Reporting, audit, and CSV export
all link correctly by following `reference_id`.

Note: tip currently writes `reference_id = parent_order_id` (tracked in
`docs/qa/1.1.0-dataflow-audit.md` finding C1); new sub-order flows must
follow the sub-order-id convention. When tip is migrated, `has_tipped()`
and `get_order_tip()` need to update together.

## Abandon-cron convention

Unpaid sub-orders expire through a per-service WP-Cron sweep. Default
timeout is 48 hours (`ABANDON_AFTER_HOURS = 48` on each service). This is
long enough that a buyer pausing checkout for a day never races the
cleanup, short enough that stale proposals don't clutter the vendor's
planning surface forever.

Hooks:

```php
TippingService::CLEANUP_HOOK         = 'wpss_cleanup_abandoned_tips';
ExtensionOrderService::CLEANUP_HOOK  = 'wpss_cleanup_abandoned_extensions';
MilestoneService::CLEANUP_HOOK       = 'wpss_cleanup_abandoned_milestones';
```

**Carve-out for milestone contracts.** Milestones proposed as part of an
Upwork-style contract (`is_contract_milestone=true` in the sub-order's
meta JSON) are **excluded** from the cleanup sweep. A buyer may legitimately
hold a later phase in `pending_payment` for weeks while earlier phases run —
expiring phase 5 just because the buyer hasn't paid for it yet would
corrupt the contract. Ad-hoc milestones proposed after order creation do
expire on the 48-hour rule like the other sub-order kinds.

## Lock-step convention (milestone-contract only)

Milestones that belong to an Upwork-style contract expose an `is_locked`
computed field on the API / template payload. A phase is locked when any
earlier-sort-order sibling is not yet terminal (`completed` or
`cancelled`). The Pay button on a locked phase is a disabled pill; the
`?pay_order=<locked_id>` checkout handler also refuses the request
server-side (see `MilestoneService::is_locked()` and the standalone
checkout provider's pay_order backstop).

The client-side gate is UX; the server-side gate is the security
contract. Both have to stay in sync — if you change the lock-step rule
(e.g. to permit out-of-order payment), update both.

## Parent lifecycle

| Sub-order action | Parent effect |
|---|---|
| Tip paid | Parent unchanged (tip runs after the order completed). |
| Extension paid | Parent's `delivery_date` pushes out by the quoted days; parent status unchanged. |
| Milestone paid (first one on a milestone contract) | Parent is already `in_progress` (set at proposal acceptance); no status change. |
| Milestone approved (last one on the contract) | Parent auto-flips to `completed` via `OrderService::update_status()` so the full completion hook chain fires. |
| Parent cancelled | Pending-payment milestones cascade to `cancelled` via a direct `UPDATE`; paid-and-open milestones keep their state and the parties route through the dispute flow for those phases. |

## Files

| File | Owns |
|---|---|
| `src/Services/TippingService.php` | Tip sub-order flow. |
| `src/Services/ExtensionOrderService.php` | Extension sub-order flow. |
| `src/Services/MilestoneService.php` | Milestone sub-order flow (includes lock-step + contract carve-outs). |
| `src/Services/ExtensionRequestService.php` | The `wpss_extension_requests` row that pairs with the extension sub-order. |
| `src/Core/Plugin.php` | Listener wiring (cascade cancel, auto-complete parent, notification + email dispatch). |
| `src/Integrations/Standalone/StandaloneOrderProvider.php` | `mark_as_paid` that fires `wpss_order_paid` after the gateway confirms. |
| `src/Integrations/Standalone/StandaloneCheckoutProvider.php` | `pay_order` checkout handler + the server-side lock-step backstop. |
| `src/Services/CommissionService.php` | Commission split applied at sub-order payment. |
| `wp-sell-services-pro/src/Integrations/Wallets/InternalWalletProvider.php` | `credit()` that writes the `wpss_wallet_transactions` row. |
| `wp-sell-services-pro/src/Services/LedgerExporter.php` | CSV export — `type_label()` maps each sub-order type to a human label. |

## When to add a new sub-order kind

1. Pick a platform marker (lowercase, short, noun — `retainer`,
   `change_order`, etc.).
2. Add an `ORDER_TYPE` and `TYPE_*` constant on your new service class.
3. Register a `wpss_order_paid` listener at priority 20 that gates on
   your marker.
4. Write the pending-payment creation method and the
   `credit_*_on_payment_complete()` method.
5. Register an abandon-cron hook and schedule it in the plugin's cron
   registrar.
6. Extend `LedgerExporter::type_label()` with a human label.
7. Decide whether your sub-order should be hidden from the parent's
   sales list (most are — tips, extensions, milestones all hide from
   the "service orders" UI so the list stays readable).

Never write directly to `wpss_wallet_transactions` — always go through
the wallet provider's `credit()` method so the ledger row, the
provider's running balance, and the idempotency guard stay consistent.
