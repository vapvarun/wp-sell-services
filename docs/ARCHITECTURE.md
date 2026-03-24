# WP Sell Services — Architecture

> Code flow, services, hook registry, bridge pattern, naming conventions.

**Last Updated:** 2026-03-24

---

## Bootstrap Flow

```
plugins_loaded (priority 10)
  wpss_init()
    Plugin::get_instance()->init()
      maybe_upgrade_database()
      register_post_types() — wpss_service, wpss_request
      define_*_hooks() — admin, frontend, AJAX, REST, cron, cascade
      loader->run() — registers all queued hooks
      do_action('wpss_loaded', $plugin) — Pro extends here
```

**Pro Bootstrap** (via `wpss_loaded`):
```
wpss_pro_init($plugin)
  License\Manager::init() — EDD licensing
  Core\Pro::init($is_licensed) — if valid, loads all features
  Register adapters/gateways/wallets/storage via filters
  ProSchemaManager::maybe_upgrade()
  do_action('wpss_pro_loaded')
```

---

## Namespace Structure

### Free Plugin (`WPSellServices\`)
| Namespace | Purpose |
|-----------|---------|
| `Core\` | Bootstrap, activation, deactivation |
| `Models\` | Data models (ServiceOrder, VendorProfile) |
| `Services\` | Business logic (26 service classes) |
| `Database\` | Schema, migrations, repositories |
| `Integrations\` | E-commerce adapters, gateways |
| `Admin\` | Admin pages, metaboxes, settings |
| `Frontend\` | AJAX handlers, templates, dashboard, wizard |
| `API\` | REST controllers (20+ controllers) |
| `Blocks\` | Gutenberg blocks |

### Pro Plugin (`WPSellServicesPro\`)
| Namespace | Purpose |
|-----------|---------|
| `Core\` | Pro orchestrator, abilities registrar |
| `License\` | EDD Software Licensing |
| `Database\` | Pro schema manager |
| `Analytics\` | Admin/vendor analytics, collectors, widgets |
| `TieredCommission\` | Priority-based commission rules |
| `VendorSubscriptions\` | Plan management, billing, enforcement |
| `StripeConnect\` | Express accounts, split payments |
| `RecurringServices\` | Subscription billing for services |
| `PayPalPayouts\` | Batch vendor payouts |
| `WhiteLabel\` | Admin/dashboard/email branding |
| `Features\` | Wizard enhancer (limit removal) |
| `Integrations\` | WooCommerce, EDD, FluentCart, SureCart, Razorpay, Wallets, Storage |

---

## Database Tables

### Free Plugin (17 tables, prefix `wpss_`)

| Table | Key Columns | Purpose |
|-------|-------------|---------|
| `wpss_orders` | order_number, customer_id, vendor_id, service_id, status, total | Core orders |
| `wpss_order_requirements` | order_id, field_data (JSON) | Buyer requirements |
| `wpss_conversations` | order_id, participants (JSON) | Message threads |
| `wpss_messages` | conversation_id, sender_id, content | Individual messages |
| `wpss_deliveries` | order_id, vendor_id, attachments (JSON), version | Delivery submissions |
| `wpss_extension_requests` | order_id, extra_days, status | Deadline extensions |
| `wpss_reviews` | order_id, reviewer_id, rating, review | Ratings |
| `wpss_disputes` | order_id, initiated_by, status, resolution | Dispute cases |
| `wpss_dispute_messages` | dispute_id, sender_id, message | Dispute thread |
| `wpss_proposals` | request_id, vendor_id, proposed_price | Buyer request proposals |
| `wpss_service_packages` | service_id, name, price, delivery_days | Pricing tiers |
| `wpss_service_addons` | service_id, title, price, field_type | Service extras |
| `wpss_vendor_profiles` | user_id (UNIQUE), status, avg_rating, total_earnings | Vendor stats |
| `wpss_portfolio_items` | vendor_id, title, media (JSON) | Vendor portfolio |
| `wpss_notifications` | user_id, type, title, is_read | In-app notifications |
| `wpss_wallet_transactions` | user_id, type, amount, balance_after | Earnings ledger |
| `wpss_withdrawals` | vendor_id, amount, method, status | Withdrawal requests |

### Pro Plugin (7 tables, prefix `wpss_pro_`)

| Table | Purpose |
|-------|---------|
| `wpss_pro_connect_accounts` | Stripe Connect vendor accounts |
| `wpss_pro_subscription_plans` | Admin-defined vendor plans |
| `wpss_pro_vendor_subscriptions` | Vendor-plan assignments |
| `wpss_pro_commission_rules` | Tiered commission rules |
| `wpss_pro_recurring_subscriptions` | Service recurring billing |
| `wpss_pro_paypal_payout_batches` | PayPal batch records |
| `wpss_pro_paypal_payout_items` | Individual payout items |

---

## Order Status State Machine

```
pending_payment → pending_requirements | cancelled
pending_requirements → in_progress | cancelled | on_hold
in_progress → pending_approval | on_hold | cancelled | late | delivered
delivered → pending_approval | in_progress (revision)
pending_approval → completed | revision_requested | disputed
revision_requested → in_progress | cancelled
on_hold → in_progress | cancelled
cancellation_requested → cancelled | in_progress
completed → [terminal]
disputed → [admin resolves]
```

Admin users with `manage_options` can force any transition.

---

## Key Extension Points (Free → Pro)

| Hook | Type | Purpose | Pro Usage |
|------|------|---------|-----------|
| `wpss_loaded` | action | Plugin ready | Pro bootstrap |
| `wpss_ecommerce_adapters` | filter | Register platforms | WooCommerce, EDD, FluentCart, SureCart |
| `wpss_payment_gateways` | filter | Register gateways | Razorpay |
| `wpss_wallet_providers` | filter | Register wallets | Internal, TeraWallet, WooWallet, MyCred |
| `wpss_storage_providers` | filter | Register storage | S3, GCS, DigitalOcean |
| `wpss_api_controllers` | filter | Register REST controllers | 10 Pro controllers |
| `wpss_commission_rate` | filter | Override commission | Tiered commission rules |
| `wpss_vendor_can_create_service` | filter | Gate service creation | Subscription plan enforcement |
| `wpss_stripe_payment_intent_args` | filter | Modify Stripe PI | Connect split payments |
| `wpss_stripe_webhook_received` | action | Stripe events | 3 Pro webhook handlers |
| `wpss_dashboard_sections` | filter | Add dashboard tabs | Wallet, Analytics, Subscription, Connect |
| `wpss_settings_tabs` | filter | Add settings tabs | Branding |
| `wpss_analytics_widgets` | filter | Add analytics widgets | Revenue, Orders, TopServices, TopVendors |

---

## Cron Jobs

| Hook | Schedule | Handler |
|------|----------|---------|
| `wpss_check_late_orders` | hourly | Mark overdue orders as late |
| `wpss_auto_complete_orders` | twice daily | Auto-complete after 72h |
| `wpss_send_deadline_reminders` | daily | Warn vendors of upcoming deadlines |
| `wpss_send_requirements_reminders` | daily | Remind buyers to submit requirements |
| `wpss_check_requirements_timeout` | daily | Auto-start if requirements overdue |
| `wpss_recalculate_seller_levels` | weekly | Update vendor level badges |
| `wpss_process_cancellation_timeouts` | hourly | Auto-cancel after timeout |
| `wpss_process_offline_auto_cancel` | hourly | Cancel unpaid offline orders |
| `wpss_cleanup_expired_requests` | daily | Remove expired buyer requests |
| `wpss_update_vendor_stats` | twice daily | Recalculate vendor metrics |
| `wpss_process_auto_withdrawals` | custom | Process auto-withdrawal threshold |

---

## Commission Calculation Flow

```
Order completed
  → CommissionService::record($order_id)
    1. Idempotency check (existing wallet transaction?)
    2. CommissionService::calculate()
       base = subtotal + addons_total (pre-tax)
       rate = vendor custom_commission_rate
              ?? wpss_commission_rate filter (Pro tiered rules at priority 20)
              ?? global wpss_commission.commission_rate
              ?? 10%
       platform_fee = round(base * rate/100, 2)
       vendor_earnings = round(base - platform_fee, 2)
    3. UPDATE wpss_orders (commission fields)
    4. UPDATE wpss_vendor_profiles (earnings totals)
    5. START TRANSACTION
       SELECT ... FOR UPDATE (wallet row lock)
       INSERT wpss_wallet_transactions
       COMMIT (or ROLLBACK on failure)
```

---

## Design Patterns

| Pattern | Where Used |
|---------|-----------|
| **Orchestrator** | Pro feature managers (SubscriptionManager, StripeConnectManager, etc.) |
| **Repository** | All DB tables (AbstractRepository base, typed repositories) |
| **Provider/Adapter** | Wallets, storage, e-commerce platforms |
| **State Machine** | OrderService::can_transition() |
| **Singleton** | WalletManager, AnalyticsManager |
| **Hook-based Extension** | Free provides hooks, Pro extends via filters |
| **Template Override** | Theme can override any template in wp-sell-services/ |

---

## Naming Conventions

| Type | Convention | Example |
|------|-----------|---------|
| PHP Namespace | `WPSellServices\` / `WPSellServicesPro\` | `WPSellServices\Services\OrderService` |
| Global functions | `wpss_` prefix | `wpss_get_order()`, `wpss_is_vendor()` |
| Hooks (actions) | `wpss_` prefix | `wpss_order_status_changed` |
| Hooks (filters) | `wpss_` prefix | `wpss_commission_rate` |
| DB tables | `{prefix}wpss_` / `{prefix}wpss_pro_` | `wp_wpss_orders` |
| Options | `wpss_` / `wpss_pro_` | `wpss_commission`, `wpss_pro_license_key` |
| User meta | `_wpss_` prefix | `_wpss_is_vendor`, `_wpss_views` |
| AJAX actions | `wpss_` prefix | `wpss_accept_order` |
| REST namespace | `wpss/v1` | `/wp-json/wpss/v1/orders` |
| Text domain | `wp-sell-services` / `wp-sell-services-pro` | |
| CSS classes | `wpss-` prefix | `.wpss-dashboard`, `.wpss-order-card` |
| JS objects | `wpssData` / `wpssAnalytics` | Localized script data |
