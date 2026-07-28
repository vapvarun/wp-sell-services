# REST API Controllers Reference

WP Sell Services registers **23 REST controllers** plus a set of generic utility
routes. WP Sell Services Pro adds **10 more**. Everything lives under one
namespace:

```
/wp-json/wpss/v1/
```

Every route below is generated from the 1.3.0 source. Path parameters are shown
as WordPress route regex (`(?P<id>[\d]+)`) so you can match them exactly.

For authentication, pagination, error shapes, and the generic endpoints, see
[REST API Overview](rest-api-overview.md).

> `POST/PUT/PATCH` means the route is registered as `EDITABLE`, so WordPress
> accepts all three verbs on it.

## Free controllers

### Generic utility routes

Registered by the API bootstrap rather than a dedicated controller.

| Method | Route | Purpose |
|--------|-------|---------|
| GET | `/categories` | Service categories |
| GET | `/tags` | Service tags |
| GET | `/settings` | Public marketplace settings |
| GET | `/me` | Current user summary |
| GET | `/dashboard` | Dashboard payload for the current user |
| GET | `/search` | Cross-entity search |
| POST | `/batch` | Batch several reads into one request |

### Services

| Method | Route |
|--------|-------|
| GET, POST | `/services` |
| GET | `/services/grid` |
| GET, POST/PUT/PATCH, DELETE | `/services/(?P<id>[\d]+)` |
| GET | `/services/(?P<id>[\d]+)/packages` |
| GET | `/services/(?P<id>[\d]+)/faqs` |
| GET | `/services/(?P<id>[\d]+)/reviews` |
| GET, POST | `/services/(?P<id>[\d]+)/addons` |
| POST/PUT/PATCH, DELETE | `/services/(?P<id>[\d]+)/addons/(?P<addon_id>[\d]+)` |

`/services/grid` returns the lighter payload used by the catalog grid. Prefer it
over `/services` when you only need cards.

### Orders

| Method | Route |
|--------|-------|
| GET | `/orders` |
| GET, POST/PUT/PATCH | `/orders/(?P<id>[\d]+)` |
| GET, POST | `/orders/(?P<id>[\d]+)/messages` |
| GET, POST | `/orders/(?P<id>[\d]+)/deliverables` |
| POST | `/orders/(?P<id>[\d]+)/(?P<action>…)` |
| GET, POST | `/orders/(?P<id>[\d]+)/requirements` |
| POST | `/orders/(?P<id>[\d]+)/requirements/skip` |
| DELETE | `/orders/(?P<id>[\d]+)/requirements/files/(?P<file_id>[\d]+)` |
| GET | `/orders/(?P<id>[\d]+)/sub-orders` |
| POST | `/orders/(?P<id>[\d]+)/pay` |

Order transitions all go through **one** action route rather than a verb per
transition. `(?P<action>…)` accepts exactly:

```
accept | reject | start | deliver | complete | revision | cancel | dispute
hold | resume | accept-cancellation | reject-cancellation
```

```bash
curl -X POST https://yoursite.com/wp-json/wpss/v1/orders/42/accept \
  -H "X-WP-Nonce: $NONCE"
```

`/sub-orders` lists the milestone, tip, and extension sub-orders attached to a
parent order -- see [Sub-Order Pattern](../../architecture/SUB_ORDER_PATTERN.md).

### Milestones

| Method | Route |
|--------|-------|
| GET, POST | `/orders/(?P<order_id>[\d]+)/milestones` |
| GET, DELETE | `/milestones/(?P<id>[\d]+)` |
| POST | `/milestones/(?P<id>[\d]+)/pay` |
| POST | `/milestones/(?P<id>[\d]+)/submit` |
| POST | `/milestones/(?P<id>[\d]+)/approve` |
| POST | `/milestones/(?P<id>[\d]+)/decline` |

The terminal actions are **approve** and **decline** (not "reject") -- the same
vocabulary as the milestone hooks.

### Extensions and tips

| Method | Route |
|--------|-------|
| GET, POST | `/orders/(?P<order_id>[\d]+)/extensions` |
| POST | `/orders/(?P<order_id>[\d]+)/extension` |
| POST | `/extensions/(?P<id>[\d]+)/decline` |
| GET, POST | `/orders/(?P<order_id>[\d]+)/tip` |
| GET | `/vendors/(?P<vendor_id>[\d]+)/tips` |
| GET | `/vendors/(?P<vendor_id>[\d]+)/tips/total` |

### Vendors

| Method | Route |
|--------|-------|
| GET | `/vendors` |
| GET | `/vendors/(?P<id>[\d]+)` |
| GET, POST/PUT/PATCH | `/vendors/me` |
| POST/PUT/PATCH | `/vendors/me/vacation` |
| GET | `/vendors/(?P<id>[\d]+)/services` |
| GET | `/vendors/(?P<id>[\d]+)/reviews` |
| GET | `/vendors/(?P<id>[\d]+)/stats` |
| POST | `/vendors/register` |

### Seller levels

| Method | Route |
|--------|-------|
| GET | `/seller-levels` |
| GET | `/seller-levels/(?P<level>[a-z_]+)` |
| GET | `/vendors/me/level` |
| GET | `/vendors/(?P<vendor_id>[\d]+)/level` |

### Portfolio

| Method | Route |
|--------|-------|
| GET | `/vendors/(?P<vendor_id>[\d]+)/portfolio` |
| POST | `/portfolio` |
| GET, POST/PUT/PATCH, DELETE | `/portfolio/(?P<id>[\d]+)` |
| POST | `/portfolio/(?P<id>[\d]+)/featured` |
| POST | `/portfolio/reorder` |

### Reviews

| Method | Route |
|--------|-------|
| GET | `/reviews` |
| GET, POST/PUT/PATCH, DELETE | `/reviews/(?P<id>[\d]+)` |
| POST | `/orders/(?P<order_id>[\d]+)/review` |
| POST | `/reviews/(?P<id>[\d]+)/reply` |
| POST | `/reviews/(?P<id>[\d]+)/helpful` |
| GET | `/services/(?P<service_id>[\d]+)/reviews/summary` |
| GET | `/vendors/(?P<vendor_id>[\d]+)/reviews/summary` |

### Buyer requests and proposals

| Method | Route |
|--------|-------|
| GET, POST | `/buyer-requests` |
| GET | `/buyer-requests/mine` |
| GET, POST/PUT/PATCH, DELETE | `/buyer-requests/(?P<id>[\d]+)` |
| GET, POST | `/buyer-requests/(?P<id>[\d]+)/proposals` |
| POST | `/buyer-requests/(?P<id>[\d]+)/proposals/(?P<proposal_id>[\d]+)/accept` |
| POST | `/buyer-requests/(?P<id>[\d]+)/proposals/(?P<proposal_id>[\d]+)/reject` |
| GET, POST | `/proposals` |
| GET, POST/PUT/PATCH | `/proposals/(?P<id>[\d]+)` |
| POST | `/proposals/(?P<id>[\d]+)/withdraw` |
| GET | `/proposals/stats` |

### Conversations

| Method | Route |
|--------|-------|
| GET | `/conversations` |
| GET | `/conversations/(?P<id>[\d]+)` |
| GET, POST | `/conversations/(?P<id>[\d]+)/messages` |
| POST | `/conversations/(?P<id>[\d]+)/read` |
| GET | `/conversations/unread-count` |
| GET | `/orders/(?P<order_id>[\d]+)/conversation` |
| POST | `/orders/(?P<order_id>[\d]+)/conversation/messages` |

### Disputes

| Method | Route |
|--------|-------|
| GET | `/disputes` |
| GET | `/disputes/(?P<id>[\d]+)` |
| GET, POST | `/orders/(?P<order_id>[\d]+)/dispute` |
| POST | `/disputes/(?P<id>[\d]+)/respond` |
| GET, POST | `/disputes/(?P<id>[\d]+)/evidence` |
| GET | `/disputes/(?P<id>[\d]+)/timeline` |
| POST | `/disputes/(?P<id>[\d]+)/escalate` |
| POST | `/disputes/(?P<id>[\d]+)/cancel` |
| POST | `/disputes/(?P<id>[\d]+)/resolve` |
| POST | `/disputes/(?P<id>[\d]+)/assign` |
| GET | `/disputes/options` |

`resolve` and `assign` require dispute-management capability. See
[Admin Mediation](../disputes-resolution/admin-dispute-mediation.md).

### Earnings and withdrawals

| Method | Route |
|--------|-------|
| GET | `/earnings/summary` |
| GET | `/earnings/history` |
| GET | `/wallet/transactions` |
| GET, POST | `/withdrawals` |
| POST/PUT/PATCH | `/withdrawals/(?P<id>[\d]+)` |
| GET | `/withdrawals/methods` |

### Payments (free)

| Method | Route |
|--------|-------|
| GET | `/payments/methods` |
| POST | `/payments/create-intent` |
| POST | `/payments/confirm` |

These are the **standalone checkout** payment routes shipped in free. Pro
replaces them with a wider, gateway-specific set -- see [Payments (Pro)](#payments-pro).

### Cart

| Method | Route |
|--------|-------|
| GET | `/cart` |
| POST | `/cart/add` |
| DELETE | `/cart/(?P<item_key>[a-z0-9]+)` |
| POST | `/cart/checkout` |

Cart items are addressed by **`item_key`**, not by service id -- one service can
appear more than once with different packages and add-ons.

### Authentication

Authentication ships in the **free** plugin, not Pro.

| Method | Route |
|--------|-------|
| POST | `/auth/login` |
| POST | `/auth/register` |
| POST | `/auth/logout` |
| GET | `/auth/me` |
| POST | `/auth/forgot-password` |
| POST | `/auth/change-password` |
| POST | `/auth/devices` |
| DELETE | `/auth/devices/(?P<device_id>[a-zA-Z0-9_-]+)` |

`/auth/devices` registers a device for push notifications -- what a mobile
client calls after login.

### Favorites, media, notifications, moderation, audit log

| Method | Route |
|--------|-------|
| GET | `/favorites` |
| POST, DELETE | `/favorites/(?P<service_id>[\d]+)` |
| GET | `/services/(?P<service_id>[\d]+)/favorited` |
| POST | `/media` |
| GET, DELETE | `/media/(?P<id>[\d]+)` |
| GET | `/notifications` |
| GET | `/notifications/unread-count` |
| POST | `/notifications/(?P<id>[\d]+)/read` |
| POST | `/notifications/read-all` |
| DELETE | `/notifications/(?P<id>[\d]+)` |
| GET | `/moderation/pending` |
| GET | `/moderation/count` |
| GET | `/moderation/(?P<service_id>[\d]+)` |
| POST | `/moderation/(?P<service_id>[\d]+)/approve` |
| POST | `/moderation/(?P<service_id>[\d]+)/reject` |
| GET | `/audit-log` |

### Realtime

Private-channel authorization for the realtime (WebSocket) layer. The plugin speaks the Pusher protocol, so this works with Pusher.com or any self-hosted Pusher-compatible server (e.g. Soketi). Client connection settings (key, host, cluster, port, TLS) are exposed under the `realtime` key of `GET /settings`; the app secret never leaves the server.

### POST /realtime/auth

Authorize a private-channel subscription per the Pusher auth contract. Called automatically by the bundled `wpss-realtime.js` client (with the `X-WP-Nonce` header); external clients can call it with any supported authentication method.

**Authentication Required**: Yes (logged-in user)

**Parameters**:
- `socket_id` (string, required) - Pusher socket ID of the connecting client (format `123.456`)
- `channel_name` (string, required) - Private channel to subscribe to

**Allowed channels**:
- `private-wpss-user-{ID}` - Only the user themself
- `private-wpss-order-{ID}` - The order's customer, its vendor, or administrators

**Response** (200):
```json
{
  "auth": "app_key:hmac_sha256_signature"
}
```

**Errors**:
- `401 rest_not_logged_in` - Not authenticated
- `403 wpss_realtime_forbidden` - Channel not owned by the current user (any channel outside the two shapes above is also refused)
- `404 wpss_realtime_disabled` - Realtime is not enabled/configured on this site

**Example**:
```bash
curl -X POST \
  https://yoursite.com/wp-json/wpss/v1/realtime/auth \
  -u "username:xxxx xxxx xxxx xxxx" \
  -d "socket_id=1234.5678" \
  -d "channel_name=private-wpss-user-45"
```

**Events published by the plugin**:
- `notification.created` on `private-wpss-user-{ID}` - payload `{ id, type }`
- `message.created` on `private-wpss-order-{ID}` and the recipient's `private-wpss-user-{ID}` - payload `{ order_id, sender_id, message_id, excerpt }`

## Pro controllers **[PRO]**

Available only when WP Sell Services Pro is active with a valid license.

### Payments (Pro)

| Method | Route |
|--------|-------|
| GET | `/payments/methods` |
| POST | `/payments/stripe/create-intent` |
| POST | `/payments/stripe/confirm` |
| POST | `/payments/paypal/create-order` |
| POST | `/payments/paypal/capture` |
| POST | `/payments/razorpay/create-order` |
| POST | `/payments/razorpay/verify` |
| POST | `/payments/offline/submit` |
| GET | `/payments/(?P<order_id>[\d]+)/status` |

### Wallet

| Method | Route |
|--------|-------|
| GET | `/wallet/balance` |
| GET | `/wallet/transactions` |
| POST | `/wallet/withdraw` |
| GET | `/wallet/withdrawals` |
| GET | `/wallet/providers` |

### Stripe Connect

| Method | Route |
|--------|-------|
| POST | `/stripe-connect/onboard` |
| GET | `/stripe-connect/status` |
| POST | `/stripe-connect/disconnect` |
| GET | `/stripe-connect/accounts` |
| GET | `/stripe-connect/accounts/(?P<vendor_id>[\d]+)` |
| GET, POST/PUT/PATCH | `/stripe-connect/settings` |

### PayPal mass payouts

| Method | Route |
|--------|-------|
| GET, POST | `/paypal-payouts/batches` |
| GET | `/paypal-payouts/batches/(?P<id>[\d]+)` |
| POST | `/paypal-payouts/batches/(?P<id>[\d]+)/sync` |
| GET | `/paypal-payouts/pending` |
| GET, POST/PUT/PATCH | `/paypal-payouts/profile` |

`/pending` is what an owner exports to pay vendors manually; `sync` reconciles a
submitted batch back against the wallet ledger.

### Commission rules

| Method | Route |
|--------|-------|
| GET, POST | `/commission-rules` |
| GET | `/commission-rules/preview` |
| GET, POST/PUT/PATCH, DELETE | `/commission-rules/(?P<id>[\d]+)` |

`preview` resolves which rule *would* apply to a given vendor/category/amount
without persisting anything -- use it to explain a rate in your own UI.

### Vendor subscription plans

| Method | Route |
|--------|-------|
| GET, POST | `/subscription-plans` |
| GET, POST/PUT/PATCH, DELETE | `/subscription-plans/(?P<id>[\d]+)` |
| GET | `/subscription-plans/my-subscription` |
| POST | `/subscription-plans/subscribe` |

### Recurring services

| Method | Route |
|--------|-------|
| GET, POST | `/recurring-services` |
| GET, DELETE | `/recurring-services/(?P<id>[\d]+)` |
| GET | `/recurring-services/my-subscriptions` |
| GET | `/recurring-services/vendor-subscriptions` |
| POST | `/recurring-services/(?P<id>[\d]+)/cancel` |
| POST | `/recurring-services/(?P<id>[\d]+)/pause` |
| POST | `/recurring-services/(?P<id>[\d]+)/resume` |

Recurring services sit behind a default-off feature flag in 1.3.0. The routes
register, but the feature's UI is hidden until you opt in -- see
[Recurring Services](../order-management/recurring-services.md).

### Analytics

| Method | Route |
|--------|-------|
| GET | `/analytics/vendor/overview` |
| GET | `/analytics/vendor/revenue` |
| GET | `/analytics/vendor/orders` |
| GET | `/analytics/vendor/services` |
| GET | `/analytics/export` |

### Cloud storage

| Method | Route |
|--------|-------|
| POST | `/storage/upload` |
| GET | `/storage/(?P<file_id>[\d]+)/url` |
| DELETE | `/storage/(?P<file_id>[\d]+)` |
| GET | `/storage/providers` |

`/storage/{file_id}/url` returns a time-limited signed URL. Do not cache it past
its expiry.

### White label

| Method | Route |
|--------|-------|
| GET | `/white-label` |

Returns the active branding (name, logo, colors) so a headless or mobile client
can render the same identity as the site.

## Related Documentation

- [REST API Overview](rest-api-overview.md) - Authentication, error handling, pagination, CORS
- [Hooks and Filters](hooks-filters.md) - Available action and filter hooks
- [Custom Integrations](custom-integrations.md) - Building custom controllers
- [Money Flow](../../architecture/MONEY-FLOW.md) - How the payment routes settle
