# WP Sell Services — Role Matrix

**Generated**: 2026-05-08
**Source**: [`audit/manifest.json`](manifest.json)

Plugin uses standard WordPress capabilities + role-based ownership comparisons (no custom capability slugs).

## Roles in play

| Role | Source | Created when |
|---|---|---|
| `administrator` | WP core | Always |
| `editor` | WP core | Always |
| `author` | WP core | Always |
| `contributor` | WP core | Always |
| `subscriber` | WP core | Always (default registration) |
| `wpss_vendor` | Plugin (custom) | On plugin activation OR when buyer becomes vendor via `[wpss_become_vendor]` |

## Capability matrix — admin features

| Feature | Admin | Editor | Vendor | Subscriber |
|---|---|---|---|---|
| Plugin Settings | ✅ manage_options | ❌ | ❌ | ❌ |
| Vendors page | ✅ | ❌ | ❌ | ❌ |
| Withdrawals page | ✅ | ❌ | ❌ | ❌ |
| Disputes page | ✅ | ❌ | ❌ | ❌ |
| Service Moderation | ✅ | ✅ (edit_posts) | ❌ | ❌ |
| All Services | ✅ | ✅ | ❌ | ❌ |
| All Buyer Requests | ✅ | ✅ | ❌ | ❌ |
| Orders | ✅ | ✅ | ❌ | ❌ |
| Analytics | ✅ | ❌ | ❌ | ❌ |

## Capability matrix — frontend dashboard

| Section | Admin (also vendor) | Vendor | Subscriber/Buyer |
|---|---|---|---|
| Buying — My Orders | R (own) | R (own) | R (own) |
| Buying — Favorites | RUD | RUD | RUD |
| Buying — Buyer Requests | CRUD (own) | CRUD (own) | CRUD (own) |
| Selling — My Services | CRUD | CRUD (own) | ❌ |
| Selling — Sales Orders | R (own as vendor) | R (own as vendor) | ❌ |
| Selling — Earnings | R (own) | R (own) | ❌ |
| Selling — Wallet | R (own) | R (own) | ❌ |
| Selling — Analytics | R (own) | R (own) | ❌ |
| Selling — Portfolio | CRUD (own) | CRUD (own) | ❌ |
| Selling — Create Service | C | C | ❌ |
| Selling — Create Request (alt) | C | C | C |
| Account — Messages | R (own conversations) | R (own conversations) | R (own conversations) |
| Account — Profile | RU (own) | RU (own) | RU (own) |

Legend: C=Create, R=Read, U=Update, D=Delete, ❌=No access. "(own)" indicates ownership is enforced at the data layer.

## Object ownership rules

These checks are enforced at the AJAX/REST handler level, NOT via WordPress capabilities:

| Object | Owner field | Checked in |
|---|---|---|
| Order (vendor side) | `vendor_id` | `OrderService` accept/decline/deliver, AjaxHandlers `accept_order`/`decline_order` lines 188-280 |
| Order (buyer side) | `customer_id` | `OrderService` accept_delivery/cancel, dispute open |
| Service | `post_author` | `ServicesController::edit_item`, ServiceMetabox |
| Buyer Request | `post_author` | `BuyerRequestsController` |
| Proposal | `vendor_id` | `ProposalService::reject/withdraw` |
| Withdrawal | `vendor_id` | `WHERE vendor_id = $current_user_id` in `cancel_withdrawal` line 3527 |
| Notification | `user_id` | `WHERE user_id = $current_user_id` in `mark_notification_read` line 2716 |
| Conversation | `participants` | `MessageService::send`, `ConversationsController` |
| Dispute | `customer_id`/`vendor_id` | `DisputeService` |
| Wallet transaction | `user_id` | Read-only, scoped by `WHERE user_id = X` |

## Public (nopriv) endpoints

These work without login (5 AJAX + several REST):

| Endpoint | Type | Action | Risk mitigation |
|---|---|---|---|
| `wpss_load_reviews` | AJAX | List reviews for a service | Read-only public data |
| `wpss_mark_review_helpful` | AJAX | Vote a review helpful | RateLimiter + IP-scoped vote dedup |
| `wpss_live_search` | AJAX | Service search | Read-only public catalog |
| `wpss_add_service_to_cart` | AJAX | Add to cart (guest) | Cart stored in user meta on login |
| `wpss_load_services` | AJAX | Block-driven service list | Read-only published services |
| `/wpss/v1/services` | REST GET | Service list | Public listings |
| `/wpss/v1/auth/login` | REST POST | Login | Standard WP authentication |
| `/wpss/v1/auth/register` | REST POST | Register | Email validation, role gating |

## Audit findings — capability gaps

Per `audit/wppqa-baseline-2026-05-08/SUMMARY.md`:

**Real gaps (likely real, deferred to future sprint):**
- `Admin.php:1266`, `Admin.php:1319` — admin actions with nonce but no `current_user_can()`
- `ServiceModerationPage.php:1211` — moderation action with nonce but no cap check

**Verified valid (false positives by wppqa's narrow regex):**
- ~13 AjaxHandlers.php "nonce-no-cap" findings — all use object-ownership comparison after the nonce, which is equivalent or stronger than `current_user_can`
- WHERE-clause scoping in `mark_notification_read`, `mark_all_notifications_read`, `cancel_withdrawal` — implicit ownership via SQL constraint

## Recommendations

1. **Real gaps (3-5 sites)** — add `current_user_can( 'manage_options' )` immediately after nonce check in `Admin.php` and `ServiceModerationPage.php`
2. **Future**: Author a `wppqa-config.json` exemption for ownership-checked handlers so wppqa stops false-positive flagging them
3. **Capability cleanup** — currently no plugin-specific custom caps. If multi-staff workflow becomes important (e.g., some admins manage orders, others manage withdrawals), introduce `wpss_manage_orders`, `wpss_manage_withdrawals` etc. and replace `manage_options` checks
