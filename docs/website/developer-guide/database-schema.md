# Database Schema

WP Sell Services stores marketplace data in **17 custom tables**, and Pro adds
**8 more**. Services and buyer requests are custom post types; everything else
lives in these tables.

All names are shown without the site's table prefix. Use `$wpdb->prefix` in code.

> **Read before you query.** Money columns are authoritative and are written
> once at settlement. Do not recompute a fee or an earnings figure from a rate --
> read the stored value. See [Money Flow](https://github.com/vapvarun/wp-sell-services/blob/main/docs/architecture/MONEY-FLOW.md).

## Where things live

| Entity | Stored as |
|--------|-----------|
| Service | `wpss_service` post type + `wpss_service_packages` / `wpss_service_addons` |
| Buyer request | `wpss_request` post type |
| Order | `wpss_orders` (custom table, **not** a post type) |
| Everything else | Custom tables below |

Because orders are not posts, `WP_Query` will not find them. Go through the
order service or query the table directly.

## Core tables

### `wpss_orders`

The centre of the data model. One row per order **and** per sub-order.

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint | PK |
| `order_number` | varchar(50) | Unique, human-facing (`WPSS-…`) |
| `customer_id`, `vendor_id`, `service_id` | bigint | Indexed |
| `package_id`, `addons` | bigint / longtext | Selected package and add-on JSON |
| `platform` | varchar(50) | Which rail created it, and the **sub-order type** discriminator |
| `platform_order_id` | bigint | For a sub-order, the **parent** order id |
| `platform_item_id` | bigint | Line item on the external rail |
| `subtotal`, `addons_total`, `total` | decimal(11,3) | Buyer-facing amounts |
| `currency` | varchar(10) | Base currency at time of sale |
| `commission_rate` | decimal(5,2) | Rate resolved **at settlement** |
| `platform_fee` | decimal(11,3) | What the platform kept |
| `vendor_earnings` | decimal(11,3) | What the vendor is owed |
| `refunded_amount` | decimal(11,3) | Running total refunded |
| `connect_transfer_id` | varchar(255) | Stripe Connect transfer, when used |
| `status` | varchar(50) | Indexed. See [Order Lifecycle](../order-management/order-lifecycle.md) |
| `delivery_deadline`, `original_deadline` | datetime | Second changes when an extension is approved |
| `payment_method`, `payment_status`, `transaction_id`, `paid_at` | | Payment record |
| `revisions_included`, `revisions_used` | int | Copied from the package at purchase |
| `billing_address`, `meta` | longtext | JSON |
| `created_at`, `updated_at`, `started_at`, `completed_at` | datetime | |

**Sub-orders.** Tips, paid extensions, and milestone phases are rows in this
same table, linked to the parent through `platform_order_id`. A query that
forgets to filter on `platform` will count a tip as an order and double-count
revenue. See
[Sub-Order Pattern](https://github.com/vapvarun/wp-sell-services/blob/main/docs/architecture/SUB_ORDER_PATTERN.md).

`commission_rate`, `platform_fee`, and `vendor_earnings` are **persisted, not
derived**. Changing your commission rate does not alter orders already settled --
that is deliberate, and re-deriving them in a report will disagree with the
ledger.

### `wpss_vendor_profiles`

One row per vendor, keyed uniquely on `user_id`.

Identity: `display_name`, `tagline`, `bio`, `avatar_id`, `cover_image_id`,
`country`, `city`, `timezone`, `website`, `intro_video_url`, `social_links`.

Status: `status`, `verification_tier`, `verified_at`.

Availability: `is_available`, `vacation_mode`, `vacation_message`,
`vacation_return_date`.

**Denormalised counters** — `total_orders`, `completed_orders`,
`total_earnings`, `net_earnings`, `total_commission`, `avg_rating`,
`total_reviews`, `response_time_hours`, `on_time_delivery_rate`. These are
maintained by the plugin and drive [seller levels](../vendor-system/seller-levels.md).
Treat them as read-only; writing them directly puts them out of step with the
rows they summarise.

`custom_commission_rate` is the free per-vendor commission override. `NULL`
means "use the global rate".

### `wpss_wallet_transactions`

The append-only earnings ledger. Every credit and debit, whatever the rail.

| Column | Notes |
|--------|-------|
| `user_id`, `type`, `amount` | `type` is indexed; credits and debits share the table |
| `balance_after` | Running balance snapshot at write time |
| `currency`, `description`, `status` | |
| `reference_type`, `reference_id` | Indexed pair pointing at the order, withdrawal, or payout that caused the row |

**A vendor's balance is the ledger, not a column.** Derive it here rather than
from `vendor_profiles.total_earnings`, which is a display counter.

### `wpss_withdrawals`

`vendor_id`, `amount`, `method`, `details` (JSON), `status`, `is_auto`,
`admin_note`, `processed_at`, `processed_by`, `created_at`.

`is_auto` distinguishes scheduled auto-withdrawals from vendor-initiated ones --
that is what the "Auto" badge in the admin list reads.

## Orders and fulfilment

| Table | Purpose | Key columns |
|-------|---------|-------------|
| `wpss_service_packages` | Package tiers per service | `service_id`, `name`, `price`, `delivery_days`, `revisions`, `features`, `sort_order` |
| `wpss_service_addons` | Add-ons and extras | `service_id`, `title`, `field_type`, `price`, `price_type`, `min_quantity`, `max_quantity`, `is_required`, `options`, `delivery_days_extra`, `applies_to` |
| `wpss_order_requirements` | Buyer's answers | `order_id`, `field_data`, `attachments`, `submitted_at` |
| `wpss_deliveries` | Delivery submissions | `order_id`, `vendor_id`, `message`, `attachments`, `version`, `status`, `response_message`, `responded_at` |
| `wpss_extension_requests` | Paid extensions | `order_id`, `requested_by`, `extra_days`, `amount`, `pay_order_id`, `status`, `original_due_date`, `new_due_date` |

`wpss_deliveries.version` increments per revision round, so the delivery history
is preserved rather than overwritten. `extension_requests.pay_order_id` points at
the sub-order in `wpss_orders` that carries the payment.

## Messaging and notifications

| Table | Purpose | Key columns |
|-------|---------|-------------|
| `wpss_conversations` | One thread per order | `order_id`, `service_id`, `participants`, `message_count`, `unread_counts`, `is_closed`, `last_message_at` |
| `wpss_messages` | Messages in a thread | `conversation_id`, `sender_id`, `type`, `content`, `attachments`, `metadata`, `read_by`, `is_edited` |
| `wpss_notifications` | In-app notifications | `user_id`, `type`, `title`, `message`, `data`, `action_url`, `is_read`, `read_at`, `is_email_sent` |

`participants`, `unread_counts`, and `read_by` are JSON. Unread state is
per-participant, so do not treat `is_read` on a message as global.

## Reviews, disputes, portfolio, proposals

| Table | Purpose | Key columns |
|-------|---------|-------------|
| `wpss_reviews` | Ratings and replies | `order_id`, `reviewer_id`, `reviewee_id`, `rating`, `communication_rating`, `quality_rating`, `delivery_rating`, `vendor_reply`, `status`, `is_public`, `helpful_count` |
| `wpss_disputes` | Dispute records | `dispute_number`, `order_id`, `initiated_by`, `respondent_id`, `reason`, `evidence`, `status`, `response_deadline`, `resolution`, `refund_amount`, `resolved_by`, `assigned_admin` |
| `wpss_dispute_messages` | Dispute thread | `dispute_id`, `sender_id`, `sender_role`, `message`, `attachments` |
| `wpss_proposals` | Bids on buyer requests | `request_id`, `vendor_id`, `cover_letter`, `proposed_price`, `proposed_days`, `contract_type`, `milestones`, `status`, `order_id` |
| `wpss_portfolio_items` | Vendor portfolio | `vendor_id`, `service_id`, `title`, `media`, `external_url`, `tags`, `is_featured`, `sort_order` |
| `wpss_audit_log` | Admin action trail | `actor_id`, `actor_role`, `event_type`, `object_type`, `object_id`, `action`, `from_value`, `to_value`, `is_forced`, `context` |

`proposals.contract_type` selects fixed-price or phased; when phased, the
`milestones` JSON is what becomes milestone sub-orders on acceptance.
`proposals.order_id` is populated once accepted.

## Pro tables

Created by Pro's schema manager. They persist when Pro is deactivated or its
license lapses, so nothing is lost on reactivation.

| Table | Purpose | Key columns |
|-------|---------|-------------|
| `wpss_pro_commission_rules` | Tiered commission | `rule_type`, `rate`, `rate_type`, `conditions`, `priority`, `is_active` |
| `wpss_pro_connect_accounts` | Stripe Connect | `vendor_id`, `stripe_account_id`, `status`, `payouts_enabled`, `charges_enabled`, `country`, `default_currency`, `onboarding_completed` |
| `wpss_pro_paypal_payout_batches` | Mass payout batches | `batch_id`, `payout_batch_id`, `status`, `total_amount`, `total_items`, `initiated_by` |
| `wpss_pro_paypal_payout_items` | Payouts in a batch | `batch_id`, `vendor_id`, `paypal_email`, `amount`, `payout_item_id`, `transaction_id`, `status`, `error_message` |
| `wpss_pro_subscription_plans` | Vendor plans | `name`, `slug`, `price`, `billing_period`, `max_services`, `max_featured`, `commission_override`, `stripe_price_id`, `is_active` |
| `wpss_pro_vendor_subscriptions` | Who is on a plan | `vendor_id`, `plan_id`, `stripe_subscription_id`, `status`, `current_period_start`, `current_period_end`, `cancelled_at` |
| `wpss_pro_recurring_subscriptions` | Recurring services | `customer_id`, `vendor_id`, `service_id`, `original_order_id`, `stripe_subscription_id`, `billing_interval`, `amount`, `status`, `next_billing_date` |

`commission_rules.priority` is ascending -- lowest number is evaluated first and
the first match wins. See [Tiered Commission](../earnings-wallet/tiered-commission.md).

## Querying safely

- **Filter sub-orders.** Any revenue or order-count query over `wpss_orders` must account for `platform` / `platform_order_id`, or tips and milestone phases inflate the numbers.
- **Use the stored split.** `platform_fee` and `vendor_earnings` are the record. Never recompute from `commission_rate`.
- **Balance comes from the ledger.** Sum `wpss_wallet_transactions`; the profile counters are for display.
- **Prepare everything.** These are direct `$wpdb` tables with no `WP_Query` layer -- use `$wpdb->prepare()` on every interpolated value.
- **Do not ALTER these tables.** Schema is managed by the plugin's installer and migrations will overwrite you. Use `meta` JSON columns or your own table.
- **Test at scale.** `wp wpss scale seed` builds a production-shape dataset and `wp wpss scale bench` times hot-path queries against a budget. See [WP-CLI Commands](wp-cli-commands.md).

## Uninstall

Tables are **kept** on plugin deletion unless the site owner enables
*Delete data on uninstall* in **Sell Services > Settings > Advanced**.

## Related

- [Money Flow](https://github.com/vapvarun/wp-sell-services/blob/main/docs/architecture/MONEY-FLOW.md)
- [Sub-Order Pattern](https://github.com/vapvarun/wp-sell-services/blob/main/docs/architecture/SUB_ORDER_PATTERN.md)
- [Capabilities & Roles](capabilities.md)
- [REST API Controllers](rest-api-controllers.md)
