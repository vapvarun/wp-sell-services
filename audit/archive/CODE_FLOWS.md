# WP Sell Services — Code Flow Maps

**Generated**: 2026-05-08
**Source**: [`audit/manifest.json`](manifest.json)

---

## Flow: Service order lifecycle

**Entry point**: Buyer clicks "Continue to Checkout" → checkout completes → standalone gateway / WC / EDD adapter creates order

### Code path
1. Checkout completes → adapter (`StandaloneCheckoutProvider` / WC / EDD) inserts row into `{prefix}wpss_orders` with status `pending_requirements`
2. `do_action( 'wpss_order_created', $order_id )` fires → email service notifies vendor
3. Buyer submits requirements → `OrderService::submit_requirements()` → status transitions to `in_progress`
4. Vendor delivers → `OrderService::deliver_order()` → status `delivered`
5. Buyer accepts → `OrderService::accept_delivery()` → status `completed`, vendor wallet credited
6. OR buyer requests revision → `OrderService::request_revision()` (revision counter decrements)
7. OR buyer disputes → `DisputeService::open()` → admin mediation

### Key files
| File | Lines | Role |
|---|---|---|
| `src/Services/OrderService.php` | ~1500 | Order state machine + transitions |
| `src/Database/Repositories/OrderRepository.php` | ~600 | DB persistence |
| `src/Frontend/AjaxHandlers.php:188-450` | order accept/decline/deliver/cancel handlers |
| `src/API/OrdersController.php` | ~400 | REST CRUD |
| `templates/order/order-view.php` | 3183 | Buyer-facing order detail |
| `templates/dashboard/sections/orders.php` | ~150 | Order list (buyer) |
| `templates/dashboard/sections/sales.php` | ~250 | Order list (vendor) |

### AJAX chain (sample: accept order)
| Step | Action | Input | Output |
|---|---|---|---|
| 1 | Page load | `?section=orders&order_id=N` | Order detail HTML |
| 2 | `wpss_accept_order` | `{nonce, order_id}` | `{success, message}` |
| 3 | JS update | success → toast + reload | DOM mutation |

### Permissions
- **Roles**: vendor (sell_services), buyer (subscriber+)
- **Capabilities**: object ownership — `$order->vendor_id === current_user_id` for vendor actions
- **Nonces**: `wpss_order_action` for AJAX

---

## Flow: Buyer request → proposal → conversion to order

### Code path
1. Buyer creates request via `[wpss_dashboard]?section=create-request` → `BuyerRequestService::create()` → `wp_insert_post(wpss_request)`
2. `do_action( 'wpss_buyer_request_created', $post_id, $data )` fires
3. Vendors browse open requests → submit proposals via `ProposalService::submit()`
4. `do_action( 'wpss_proposal_submitted', $proposal_id, $request_id, $vendor_id, $data )`
5. Buyer accepts proposal → `BuyerRequestService::convert_to_order()` → creates order, marks request as `hired`

### Key files
- `src/Services/BuyerRequestService.php`, `src/Services/ProposalService.php`
- `src/API/BuyerRequestsController.php`, `src/API/ProposalsController.php`

### Permissions
- Buyer: `wpss_manage_requests` (auto-granted to subscribers+)
- Vendor: must not have proposed before (dedup via `vendor_has_proposed`)

---

## Flow: Milestone contract lifecycle (1.1.0)

Lock-step phase payments on `wpss_request` orders.

### Code path
1. Vendor proposes milestones at proposal acceptance → `MilestoneService::propose($order_id, $vendor_id, $title, $desc, $amount, $days, $deliverables)`
2. Buyer pays for phase N → sub-order created (`platform=milestone`) → `do_action( 'wpss_order_paid', $sub_id )` → vendor wallet credit
3. Vendor delivers phase → buyer approves → `MilestoneService::approve()` → next phase unlocked
4. All phases approved → parent order auto-completes

Mutual exclusion: a request order has either milestones OR extensions, never both (server-side guard).

---

## Flow: Extension orders on catalog purchases (1.1.0)

### Code path
1. Vendor proposes extension → `ExtensionOrderService::create_extension_request($parent_id, $amount, $days, $vendor_id, $reason)` → sub-order created (`platform=extension`)
2. Buyer accepts → `ExtensionOrderService::accept()` → parent deadline pushes by `$days`
3. OR buyer declines → `ExtensionOrderService::decline()` → sub-order cancelled

### Permissions
- Inline check: `if ( $vendor_id !== (int) $parent->vendor_id ) return error;`

---

## Flow: Tipping (1.1.0)

Buyer can tip after order completion. Sub-order with `platform=tip`. Idempotency key migrated to sub-order ID in 1.1.0 to prevent double-credit.

---

## Flow: Wallet & withdrawal

Vendor wallet credit on `wpss_order_paid`. Withdrawal request via dashboard → admin processes via `WithdrawalsPage`. Auto-withdrawal supported via setting + cron.

### Key files
- `src/Services/EarningsService.php`, `src/Admin/Pages/WithdrawalsPage.php`
- `templates/dashboard/sections/wallet.php` (ledger display, fixed in commit `f69273c` — clickable Reference column)

---

## Flow: Dispute lifecycle

Buyer opens dispute on a `delivered`/`disputed`-able order → `DisputeService::open()` → admin mediates via `Admin/Pages/Disputes` (subset of admin pages).

Dispute deadlines enforced via Action Scheduler.
