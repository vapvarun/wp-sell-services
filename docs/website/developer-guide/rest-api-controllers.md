# REST API Controllers Reference

WP Sell Services registers **25 REST controllers** plus a set of generic utility
routes. WP Sell Services Pro adds **10 more**. Everything lives under one
namespace:

```
/wp-json/wpss/v1/
```

Every route below is generated from the 1.4.0 source. Path parameters are shown
as WordPress route regex (`(?P<id>[\d]+)`) so you can match them exactly.

For authentication, pagination, error shapes, and the generic endpoints, see
[REST API Overview](rest-api-overview.md).

> `POST/PUT/PATCH` means the route is registered as `EDITABLE`, so WordPress
> accepts all three verbs on it.

## Two namespaces -- and only one of them is the API

There are two namespaces, and the split is not the one the names suggest.

| Namespace | What is on it |
|---|---|
| `wpss/v1` | **Everything.** All 25 free controllers *and* all 10 Pro controllers. Pro extends the API; it does not run a parallel one. |
| `wpss-pro/v1` | Exactly **four** cart-adapter routes, and only while the matching cart plugin is active. |

The four `wpss-pro/v1` routes are:

| Method | Route | Present when |
|--------|-------|--------------|
| GET | `/wpss-pro/v1/fluentcart/products` | FluentCart is active |
| GET | `/wpss-pro/v1/fluentcart/orders` | FluentCart is active |

Build every client against `wpss/v1`. Prefixing a Pro endpoint with
`wpss-pro/v1` returns `rest_no_route` on every single one.

## Route presence is conditional

Two things change which routes exist on a given site, so enumerate rather than
assume:

- **The active e-commerce rail.** `/payments/*` -- in free *and* Pro -- registers
  only when `wpss_uses_standalone_payments()` is true, i.e. no cart plugin has
  claimed payments. Activate WooCommerce, EDD or FluentCart and those
  routes stop registering entirely; a call to them answers `404 rest_no_route`
  from WordPress core. That is by design: when a cart plugin is enabled it owns
  all payment, and the plugin does not offer a second way in.
- **Pro + a valid license.** The 10 Pro controllers register on `wpss_loaded`.

To see the truth for a site, read the index: `GET /wp-json/wpss/v1`.

## Free controllers

### Generic utility routes

Registered by the API bootstrap rather than a dedicated controller.

| Method | Route | Purpose |
|--------|-------|---------|
| GET | `/` | Namespace index -- the WordPress-generated route list for `wpss/v1`. The authoritative answer to "what exists on this site". |
| GET | `/categories` | Service categories |
| GET | `/tags` | Service tags |
| GET | `/settings` | Public marketplace settings |
| GET | `/me` | Current user summary |
| GET | `/dashboard` | Dashboard payload for the current user |
| GET | `/search` | Cross-entity search |
| POST | `/batch` | Batch several reads into one request |
| POST | `/tour/complete` | Mark the guided onboarding tour finished for the current user. Called by the bundled tour; persists per-user so the tour does not replay. |

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
| GET | `/orders/(?P<id>[\d]+)/receipts` |
| GET | `/orders/(?P<id>[\d]+)/timeline` |
| POST | `/orders/(?P<id>[\d]+)/pay` |

Order transitions all go through **one** action route rather than a verb per
transition. `(?P<action>…)` accepts exactly:

```
start | deliver | complete | revision | cancel | dispute
hold | resume | accept-cancellation | reject-cancellation
```

> **Changed in 1.4.0:** `accept` and `reject` were removed. They had no
> handler behind them and returned a misleading success on some paths, so a
> client built against them was never actually transitioning anything. There is
> no replacement -- an order is accepted by being paid. `deliver` now routes
> through `DeliveryService` rather than writing the status directly, so a
> delivery made over REST produces the same records and notifications as one
> made in the dashboard.

```bash
curl -X POST https://yoursite.com/wp-json/wpss/v1/orders/42/deliver \
  -H "X-WP-Nonce: $NONCE"
```

`/sub-orders` lists the milestone, tip, and extension sub-orders attached to a
parent order -- see [Sub-Order Pattern](https://github.com/vapvarun/wp-sell-services/blob/main/docs/architecture/SUB_ORDER_PATTERN.md).

`GET /orders/{id}/timeline` (**new in 1.4.0**) returns the merged, chronological
event history for one order -- status transitions, deliveries, revisions,
milestone and extension events, and payment events -- as a single list. It is
what an order-detail screen renders instead of stitching four endpoints
together.

### Paying an order or a phase

`POST /orders/{id}/pay` and `POST /milestones/{id}/pay` do **not** take money.
They resolve *where the buyer must go to pay*, and enforce the milestone
lock-step guard while doing so.

**`POST /orders/{id}/pay`** -- 200:

```json
{
  "success": true,
  "order_id": 42,
  "checkout_url": "https://yoursite.com/checkout/order-pay/1042/?key=wc_order_…",
  "platform": "milestone"
}
```

`platform` is the sub-order type when the row is one (`milestone`, `tip`,
`extension`) and an empty string for a normal order.

**`POST /milestones/{id}/pay`** -- 200:

```json
{
  "success": true,
  "milestone_id": 57,
  "checkout_url": "https://yoursite.com/checkout/order-pay/1043/?key=wc_order_…"
}
```

**Errors on both:**

| Status | Code | When |
|---|---|---|
| 404 | `wpss_order_not_found` / `wpss_milestone_not_found` | No such row, or the row is not a milestone |
| 409 | `wpss_order_not_payable` / `wpss_milestone_not_payable` | The row is not in `pending_payment` |
| 409 | `wpss_milestone_locked` | An earlier phase is still open |

> ### `checkout_url` is a BROWSER url, not an API endpoint
>
> This is the single most common way to get this wrong. `checkout_url` is a
> page for a human, resolved through the `wpss_pay_order_url` filter by whatever
> e-commerce rail is active. **Do not fetch it, do not parse it, do not
> reconstruct it.** A native client must open it in a webview (or the system
> browser) and watch for the return URL.
>
> On the **WooCommerce** rail it is a WooCommerce *order-pay* page
> (`/checkout/order-pay/{wc_id}/?key=…`), rendered by WooCommerce with whatever
> gateways the store has enabled. There is no JSON behind it.
>
> **Side effect on Woo: generating this URL creates a real WooCommerce order.**
> Pro's `WCPayOrderResolver` creates (or reuses) an unpaid WC order for the
> amount owed so the link survives an email with no cart session. It is
> idempotent -- the WC order id is stored on the WPSS row as `wc_pay_order_id`
> and reused while the order still needs payment -- but calling `/pay`
> speculatively on a site with Woo active still puts a pending order in the
> store. Call it when the buyer is actually about to pay.
>
> On the **standalone** rail it is `…/checkout/?pay_order={id}` and nothing is
> created.
>
> **EDD and FluentCart have no pay-order rail at all.** They do not
> hook the filter, so `checkout_url` falls back to the standalone
> `?pay_order=N` URL, which those checkouts do not understand -- the buyer
> lands on an empty cart. See
> [WooCommerce Checkout](../payments-checkout/woocommerce-checkout.md#paying-a-milestone-tip-or-extension)
> for the support matrix.

### Milestones

| Method | Route |
|--------|-------|
| GET, POST | `/orders/(?P<order_id>[\d]+)/milestones` |
| GET, DELETE | `/milestones/(?P<id>[\d]+)` |
| POST | `/milestones/(?P<id>[\d]+)/pay` |
| POST | `/milestones/(?P<id>[\d]+)/submit` |
| POST | `/milestones/(?P<id>[\d]+)/approve` |
| POST | `/milestones/(?P<id>[\d]+)/decline` |
| POST | `/milestones/(?P<id>[\d]+)/request-revision` |

The terminal actions are **approve** and **decline** (not "reject") -- the same
vocabulary as the milestone hooks.

`request-revision` is the buyer's third option on a submitted phase: it takes a
`reason`, returns the phase to `revision_requested` so the seller can resubmit,
and posts the reason into the parent contract's conversation. A phase has no
conversation of its own. Only the buyer may call it, and only while the phase
is `pending_approval`.

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
| GET, POST | `/portfolio` |
| GET, POST/PUT/PATCH, DELETE | `/portfolio/(?P<id>[\d]+)` |
| POST | `/portfolio/(?P<id>[\d]+)/featured` |
| POST | `/portfolio/reorder` |

### Reviews

| Method | Route |
|--------|-------|
| GET, POST | `/reviews` |
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

### Payments (free) -- standalone rail only

| Method | Route |
|--------|-------|
| GET | `/payments/methods` |
| POST | `/payments/create-intent` |
| POST | `/payments/confirm` |

These are the **standalone checkout** payment routes shipped in free. Pro
replaces them with a wider, gateway-specific set -- see [Payments (Pro)](#payments-pro).

> **These routes do not exist on every site.** As of 1.4.0 the whole controller
> is skipped unless `wpss_uses_standalone_payments()` is true
> (`src/API/PaymentController.php`). With WooCommerce, EDD, FluentCart or
> FluentCart enabled, that rail owns **all** payment and these routes are never
> registered -- a client calling them gets `404 rest_no_route`. Do not treat
> that as an error to retry: check `GET /wpss/v1` (or `GET /settings`) and send
> the buyer to the rail's own checkout instead.
>
> Switching rails never rewrites past orders -- an order paid through a gateway
> keeps its record, and that gateway's webhooks keep working.

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
| GET, POST | `/auth/devices` |
| DELETE | `/auth/devices/(?P<device_id>[a-zA-Z0-9_-]+)` |
| GET, DELETE | `/auth/sessions` |
| DELETE | `/auth/sessions/(?P<uuid>[a-zA-Z0-9\-]+)` |

`/auth/devices` registers a device for push notifications -- what a mobile
client calls after login.

`/auth/sessions` is a different thing and deliberately a separate route: it
lists and revokes the app tokens a member holds. `DELETE /auth/sessions` ends
them all; the `{uuid}` form ends one. They are not on `/auth/devices` because
that route already means push tokens.

### Favorites, media, notifications, moderation, audit log

| Method | Route |
|--------|-------|
| GET | `/favorites` |
| POST, DELETE | `/favorites/(?P<service_id>[\d]+)` |
| GET | `/services/(?P<service_id>[\d]+)/favorited` |
| GET, POST | `/media` |
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

### Reporting and blocking

Both ship in the **free** plugin. A store submitting a marketplace app needs
them: App Store Guideline 1.2 asks for a way to report content and a way to
block the person who posted it.

| Method | Route |
|--------|-------|
| GET, POST | `/reports` |
| POST | `/reports/(?P<id>[\d]+)/resolve` |
| GET | `/blocks` |
| POST, DELETE | `/blocks/(?P<user_id>[\d]+)` |

`POST /reports` files a report against any target type; the vocabulary of
target types is filterable, so the same controller serves services, orders,
vendors and messages. `GET /reports` is the owner's queue and is admin-only.

Reporting asks the owner to act. **Blocking lets a member act immediately**,
which is the one that actually ends a bad interaction, so the two are separate
routes rather than one moderation surface. Blocks are stored in user meta, and
enforcement lives at the points where two members can reach each other rather
than in the controller.

### Realtime

| Method | Route |
|--------|-------|
| POST | `/realtime/auth` |

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
| POST, DELETE | `/subscription-plans/subscribe` |

### Recurring services

| Method | Route |
|--------|-------|
| GET, POST | `/recurring-services` |
| GET | `/recurring-services/(?P<id>[\d]+)` |
| GET | `/recurring-services/my-subscriptions` |
| GET | `/recurring-services/vendor-subscriptions` |
| POST | `/recurring-services/(?P<id>[\d]+)/cancel` |
| POST | `/recurring-services/(?P<id>[\d]+)/pause` |
| POST | `/recurring-services/(?P<id>[\d]+)/resume` |

Recurring services sit behind a default-off feature flag in 1.3.0. The routes
register, but the feature's UI is hidden until you opt in -- see
[Recurring Services](../order-management/recurring-services.md).

### Analytics

| Method | Route | Scope |
|--------|-------|-------|
| GET | `/analytics/overview` | **Marketplace-wide** -- admin only |
| GET | `/analytics/revenue` | **Marketplace-wide** -- admin only |
| GET | `/analytics/vendor/overview` | The calling vendor |
| GET | `/analytics/vendor/revenue` | The calling vendor |
| GET | `/analytics/vendor/orders` | The calling vendor |
| GET | `/analytics/vendor/services` | The calling vendor |
| POST | `/analytics/export` | Queue a data export |

The two un-prefixed routes (`/analytics/overview`, `/analytics/revenue`) are the
platform-owner numbers and were previously undocumented; the `/vendor/*` ones
are scoped to whoever is calling. `/analytics/export` is **POST**, not GET --
it starts an export rather than returning one.

`/analytics/revenue` takes `period`, one of `7days` | `30days` (default) |
`90days` | `12months`.

Both admin routes register even on an **unlicensed** site, deliberately: an
unlicensed call answers `403 wpss_pro_license_required` rather than
`404 rest_no_route`, so a client can tell "you need a license" apart from "this
build does not have that endpoint". Anonymous callers get `401 rest_not_logged_in`;
a logged-in non-admin gets `403`.

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
| GET, POST/PUT/PATCH | `/white-label` |

Returns the active branding (name, logo, colors) so a headless or mobile client
can render the same identity as the site.

## Related Documentation

- [REST API Overview](rest-api-overview.md) - Authentication, error handling, pagination, CORS
- [Hooks and Filters](hooks-filters.md) - Available action and filter hooks
- [Custom Integrations](custom-integrations.md) - Building custom controllers
- [Money Flow](https://github.com/vapvarun/wp-sell-services/blob/main/docs/architecture/MONEY-FLOW.md) - How the payment routes settle
