# REST API Overview

WP Sell Services provides a comprehensive REST API for building custom integrations, mobile apps, and external applications. The API follows WordPress REST API standards with 23 dedicated controllers plus generic endpoints.

## Overview

**Base URL**: `/wp-json/wpss/v1/`

**Controllers**: 21 specialized controllers handling services, orders, vendors, reviews, conversations, disputes, buyer requests, proposals, notifications, portfolio, earnings, extension requests, milestones, tipping, seller levels, moderation, favorites, media, cart, authentication, and realtime channel authorization.

**Authentication Methods**:
- Cookie authentication (browser-based)
- Application Passwords (WordPress 5.6+)
- JWT tokens **[PRO]** (via third-party plugin)

**Response Format**: JSON with standard WordPress REST API structure

**Pagination**: Standard WordPress pagination with `page` and `per_page` parameters

## Authentication

### Cookie Authentication

Used for same-origin requests from logged-in WordPress users.

**Requirements**:
- User must be logged into WordPress
- Requests must include `X-WP-Nonce` header

**Example**:
```javascript
const nonce = wpApiSettings.nonce; // From wp_localize_script

fetch('/wp-json/wpss/v1/services', {
    credentials: 'same-origin',
    headers: {
        'X-WP-Nonce': nonce
    }
})
.then(response => response.json())
.then(data => console.log(data));
```

### Application Passwords

Recommended for external applications and integrations (WordPress 5.6+).

**Setup**:
1. Navigate to **Users → Profile**
2. Scroll to **Application Passwords** section
3. Enter application name (e.g., "Mobile App")
4. Click **Add New Application Password**
5. Copy the generated password (shown once)

**Example**:
```bash
curl -X GET \
  https://yoursite.com/wp-json/wpss/v1/services \
  -u "username:xxxx xxxx xxxx xxxx"
```

```javascript
const auth = btoa('username:xxxx xxxx xxxx xxxx');

fetch('/wp-json/wpss/v1/services', {
    headers: {
        'Authorization': `Basic ${auth}`
    }
});
```

### App sign-in and sessions

`POST /auth/login` is the entry point for a mobile or desktop client. It takes
the member's **account password** and returns a token to use as Basic auth.

```json
{
  "token": "base64(user_login:app_password)",
  "user":  { "id": 42, "name": "Sofia Rossi", "...": "..." },
  "expires": "2026-11-16T20:13:40+00:00"
}
```

**`expires` is real and enforced.** Before 1.6.0 it was always `null` and the
server enforced nothing, so a stolen token worked forever. A token now dies
**30 days after it was last used** or **90 days after it was issued**, whichever
comes first — a daily user is never interrupted, and an abandoned token is gone
in a month. Both limits are filterable via `wpss_app_token_lifetime`.

Treat `expires` as advisory and the 401 as authoritative: on
`401 wpss_token_expired`, discard the token and sign in again.

**A token cannot mint another token.** `POST /auth/login` refuses a request whose
`password` is an app token rather than the account password, with
`401 wpss_token_cannot_mint`. Without that, whoever stole one token had an
unlimited supply and revoking the original changed nothing.

**Signing in still works with a dead token attached.** WordPress answers 401 for
the *whole request* when an application password fails, so a client that attaches
its stored token to every request would be unable to reach the login route to
replace it. `/auth/login`, `/auth/register` and `/auth/forgot-password` are
therefore reachable with an expired token in the header — they take their
credentials from the body and grant nothing on their own. Every other route
still refuses it.

**Listing and revoking sessions**:

```
GET    /wpss/v1/auth/sessions           # uuid, device, created, last_used, expires, is_current
DELETE /wpss/v1/auth/sessions/{uuid}    # revoke one device
```

`is_current` marks the session making the call, so a client can avoid offering
to sign itself out. A uuid is resolved against the current member, so it cannot
be used to sign anybody else out.

Note this is **`/auth/sessions`**, not `/auth/devices`. The latter exists and
manages **push notification tokens** — revoking one of those must not sign
anyone out.

Only sign-ins this plugin issued are expired or listed. An application password
a member created by hand in their WordPress profile belongs to whatever script
they built with it and is left alone.

**Repeated wrong passwords lock the account.** Five failed sign-ins for one
account within 15 minutes lock it for 15 minutes, answered with
`423 wpss_account_locked`. The counter is per account, not per address - it
does not matter how many IPs the attempts came from - and it sits alongside
the per-IP limiter (`429 rate_limit_exceeded`, 5 attempts per 5 minutes). A
successful sign-in clears the counter. Show the message and stop; retrying
will not help.

**A second factor plugs in after the password.** Once the password and the
lockout have both passed, `wpss_auth_login_challenge` runs before a token is
issued:

```php
add_filter( 'wpss_auth_login_challenge', function ( $challenge, WP_User $user, WP_REST_Request $request ) {
    if ( ! my_2fa_enabled_for( $user ) ) {
        return null; // issue the token as normal
    }
    $code = (string) $request->get_param( 'otp' );
    if ( '' === $code ) {
        return new WP_Error( 'wpss_2fa_required', 'Enter the code from your authenticator app.', array( 'status' => 401 ) );
    }
    return my_2fa_verify( $user, $code ) ? null : new WP_Error( 'wpss_2fa_invalid', 'That code is not valid.', array( 'status' => 401 ) );
}, 10, 3 );
```

A `WP_Error` returned here is sent to the client as-is, so the plugin chooses
the status, the code and any challenge data. The filter never runs for a wrong
password, so it cannot be used to tell whether one was right.

**Password reset takes either identifier.** `POST /auth/forgot-password`
accepts `user_login` or `email` (one is required). The answer is the same
`200` whether or not an account matched, so it cannot be used to enumerate
accounts.

### Order files

A file attached to an order - a delivery, a brief - is private to the buyer,
the vendor and administrators, and lives outside the web root. The web UI
links to it through `admin-post.php`, which authenticates from the session
cookie; an app holding a token has no cookie, so it uses the REST route
instead:

```
GET /wpss/v1/orders/{id}/files/{file}
```

`{file}` is the `id` of an entry in `deliverables[].files[]`, and that entry's
`url` already points here. The gate is the same one the web link runs
(`wpss_can_read_order_files()`): anonymous is `401`, someone with no claim on
the order is `403 wpss_not_owner`, an unknown file is `404 wpss_file_not_found`.

What comes back depends on where the bytes are:

- **Stored on the site**: the file itself, streamed with `Content-Type` and a
  `Content-Disposition: attachment` header. Save the body.
- **Stored in a cloud bucket**: JSON with a signed `url` and `expires_in`
  (seconds, currently 300). Fetch the URL directly; do not attach the token to
  that request. `503 wpss_storage_unavailable` means the bucket's provider
  could not sign a URL - retry later.

Calling the route through `POST /batch` always returns the JSON form, with
`url` null for a locally stored file - a batch cannot carry bytes.

## Payload conventions

Four shapes are the same everywhere in this API. They were not always, and the
inconsistencies were the most-reported problem from client developers: each one
becomes an adapter in the client that never goes away.

### Dates are ISO-8601 with an offset

Every timestamp, on every endpoint, in every nested object:

```json
"created_at": "2026-08-17T07:36:09+00:00"
```

Never a bare `2026-08-17 07:36:09`. A MySQL datetime carries no timezone, so a
client has to guess - and until 1.6.0 roughly half the API guessed differently
from the other half. If you find one, it is a bug; report it with the route.

Dates inside free-form blobs - a notification's `data` object, whose shape the
producing feature owns - are normalised on the way out for keys that name a
date. Everything else is passed through exactly as stored.

### A person is always the same object

```json
"vendor": { "id": 42, "name": "Sofia Rossi", "avatar": "https://...", "deleted": false }
```

Wherever the API describes a member - `vendor`, `customer`, `author`,
`initiated_by`, `other_user`, `sender`, `reviewer` - it is this object. Write one
renderer and use it everywhere.

`deleted` matters more than it looks. Orders and conversations outlive the people
in them, so a client needs to tell *"this member's account is gone"* from *"no
member acted"*. A `sender` of `{ "id": 0, "name": "System" }` is the system
speaking, not a deleted user.

Some endpoints also carry **flat** legacy keys beside the object - `vendor_id`,
`vendor_name`, `vendor_avatar` and the `customer_*` equivalents on order detail.
Those are a compatibility surface for clients that predate the object. Prefer the
object in new code; the flat keys will be retired on a stated version, never
silently.

### A service card is always the same object

`GET /services` and `GET /favorites` return the same keys for a service: `id`,
`title`, `slug`, `description`, `excerpt`, `status`, `link`, `vendor`,
`pricing`, `delivery`, `images`, `categories`, `tags`, `rating`, `created_at`,
`updated_at`.

`/favorites` additionally carries the flat `thumbnail`, `price`, `price_minor`
and `currency` it has always returned. One exception is deliberate and
documented: on `/favorites`, `rating` is a **float** where the canonical shape is
`{ average, count }`. Changing the type of an existing field is a breaking
change, so it waits for a contract bump.

### Money carries minor units

Every money value ships alongside an integer in the currency's minor unit, so a
client never does float arithmetic on a price:

```json
"pricing": { "base_price": 79.99, "base_price_minor": 7999, "currency": "USD" }
```

## Trimming a response

Server-rendered HTML is included on a few endpoints for the plugin's own
progressive-enhancement surfaces - `messages[].html`, `reviews[].review_html`,
`created_human` and friends. A native client does not want them.

Use WordPress core's `_fields`:

```
GET /wpss/v1/reviews?_fields=id,rating,review,created_at
```

Measured on a real install, that takes `/reviews` from 8547 bytes to 1825 (79%
smaller) and `/conversations/{id}/messages` from 26867 to 6829 (75%). The HTML
keys stay in the payload by default because removing a field is a breaking
change, but no client has to receive them.

**`_fields` only works over HTTP.** It is applied in `rest_post_dispatch`, which
`rest_do_request()` does not run - so testing it through `wp eval` shows no
reduction and looks broken.

## The contract version

`GET /settings` returns a `contract_version`. It is bumped when a field changes
**shape or meaning**, never when a value changes, because clients refuse a
contract newer than the one they understand - a spurious bump bricks every build
already shipped.

Adding a key is not a bump. Changing a date's format is not a bump. Changing
`rating` from a number to an object would be.

## Checking the conventions yourself

They are enforced by a committed command rather than by review:

```
wp wpss api:shapes            # every GET route
wp wpss api:shapes --verbose  # also names the routes it could not reach
```

It walks the whole route table, fills parameterised routes from real rows, and
fails on a MySQL date or an actor missing `deleted`. If you add an endpoint, run
it.

## Error codes

Branch on `code`, never on the message — messages are translated and will not
match in another locale.

### Status codes

| Status | Meaning | What a client should do |
|---|---|---|
| `401` | Not authenticated | Refresh the token / prompt sign-in, then retry |
| `403` | Authenticated, not permitted | Do **not** retry with the same credentials - show the reason |
| `404` | No such route or record | Stop; the path or id is wrong |
| `405` | Wrong method on a real route | Fix the verb; the `Allow` header lists what is accepted |
| `409` | Conflict / illegal state | Refresh state and re-decide |
| `501` | Feature disabled on this site | Hide the feature; do not retry |

### Permission codes

A `403` always carries one of these, so the reason is machine-readable:

| Code | Meaning |
|---|---|
| `rest_not_logged_in` | Not signed in (this one is `401`) |
| `wpss_not_vendor` | Signed in, but the account is not a vendor |
| `wpss_vendor_pending` | Vendor account exists but is awaiting approval |
| `wpss_not_owner` | Signed in, but this order / service / file / conversation belongs to someone else |
| `wpss_not_admin` | Requires an administrator |
| `wpss_cannot_create` | Lacks the capability to create this resource |
| `wpss_service_limit_reached` | Permitted, but the account is at its service limit - offer to remove one |

`wpss_not_vendor` and `wpss_not_owner` used to be a single generic
`rest_forbidden`, so a client could not tell "you need a vendor account" from
"that is not your order" without reading English.

### One caveat worth knowing

WordPress validates required parameters **before** it runs the permission
callback. A request that omits a required argument therefore returns
`400 rest_missing_callback_param` even when unauthenticated - so **do not treat
`400` as an authentication signal**. A well-formed anonymous request always
returns `401`.

## Generic Endpoints

These endpoints are registered directly in `API.php` (not controllers).

### GET /categories

Get service categories with hierarchy.

**Parameters**:
- `parent` (int) - Parent category ID (default: 0)
- `hide_empty` (bool) - Hide empty categories (default: true)

**Response**:
```json
[
  {
    "id": 12,
    "name": "Web Development",
    "slug": "web-development",
    "description": "Website and web application development",
    "count": 145,
    "parent": 0,
    "icon": "dashicons-code",
    "image": "https://example.com/cat-image.jpg"
  }
]
```

### GET /tags

Get service tags.

**Parameters**:
- `search` (string) - Search term

**Response**:
```json
[
  {
    "id": 34,
    "name": "WordPress",
    "slug": "wordpress",
    "count": 89
  }
]
```

### GET /settings

Get public marketplace settings.

**Response**:
```json
{
  "currency": "USD",
  "currency_symbol": "$",
  "currency_position": "before",
  "decimal_places": 2,
  "min_order_amount": 5.00,
  "max_order_amount": 10000.00,
  "vendor_registration": true,
  "service_moderation": false,
  "review_moderation": false,
  "max_file_size": 10485760,
  "allowed_file_types": ["jpg", "jpeg", "png", "pdf", "zip"],
  "pages": {
    "services": 123,
    "vendors": null,
    "dashboard": 125,
    "checkout": 126,
    "cart": 128,
    "become_vendor": 129,
    "terms": null
  },
  "page_urls": {
    "services": "https://yoursite.com/services/",
    "vendors": null,
    "dashboard": "https://yoursite.com/dashboard/",
    "checkout": "https://yoursite.com/service-checkout/",
    "cart": "https://yoursite.com/service-cart/",
    "become_vendor": "https://yoursite.com/become-a-vendor/",
    "terms": null
  },
  "realtime": {
    "enabled": false,
    "key": "",
    "host": "",
    "cluster": "mt1",
    "port": 443,
    "use_tls": true,
    "auth_endpoint": "https://yoursite.com/wp-json/wpss/v1/realtime/auth"
  }
}
```

#### Which pages exist, and which are optional

`pages` gives the post ID, `page_urls` the absolute URL, for the same keys. Both
are always present with the same key set, so a client can read either without
checking for missing keys.

**A value is either a published page or `null` - never `0`.** `null` means the
site has no such page: it was never mapped, or the page it pointed at has since
been unpublished or deleted. Hide the entry rather than linking to it; a `0` was
never a post ID a client could open.

The installer creates these automatically:

| Key | Created on install |
|---|---|
| `services` | Yes |
| `dashboard` | Yes |
| `checkout` | Yes |
| `cart` | Yes |
| `become_vendor` | Yes |

These are **optional** and stay `null` until the site owner maps them in
**WP Sell Services > Settings**:

| Key | Notes |
|---|---|
| `terms` | Mapped to the site's existing terms page - the plugin deliberately does not create a second one. |
| `vendors` | Only set when the owner publishes a vendor-directory page. `page_urls.vendors` may still resolve when the directory is served from an archive rather than a page. |

On a WooCommerce or EDD install, `checkout` and `cart` resolve to that rail's
pages, not the standalone ones - so a client always deep-links to the checkout
the buyer will actually use.

The `realtime` key carries the non-sensitive client config for the realtime (WebSocket) layer - see [Realtime controller](rest-api-controllers.md#21-realtime-realtime). The app secret is never included.

### GET /me

Get current user info and capabilities.

**Authentication Required**: Yes

**Response**:
```json
{
  "id": 45,
  "email": "john@example.com",
  "display_name": "John Doe",
  "avatar": "https://example.com/avatar.jpg",
  "is_vendor": true,
  "is_admin": false,
  "capabilities": {
    "can_create_services": true,
    "can_manage_orders": false
  },
  "vendor_status": "approved",
  "rating": 4.8,
  "review_count": 156
}
```

### GET /dashboard

Get dashboard statistics for current user.

**Authentication Required**: Yes

**Response**:
```json
{
  "user_id": 45,
  "is_vendor": true,
  "as_customer": {
    "total_orders": 12,
    "active_orders": 3,
    "completed_orders": 9
  },
  "as_vendor": {
    "services_count": 8,
    "total_orders": 234,
    "pending_orders": 5,
    "active_orders": 12,
    "completed_orders": 217,
    "total_earnings": 45620.00,
    "rating": 4.8,
    "review_count": 156
  }
}
```

### POST /batch

Execute multiple API requests in single HTTP call (mobile efficiency).

**Authentication Required**: Yes

**Maximum Requests**: 25 (filtered via `wpss_batch_max_requests`)

**Request Body**:
```json
{
  "requests": [
    {
      "method": "GET",
      "path": "/wpss/v1/services?per_page=5"
    },
    {
      "method": "GET",
      "path": "/wpss/v1/vendors?per_page=5"
    },
    {
      "method": "POST",
      "path": "/wpss/v1/favorites",
      "body": {
        "service_id": 123
      }
    }
  ]
}
```

**Notes**:
- All sub-requests must be within `/wpss/v1/` namespace
- Authentication inherited from parent request
- Each sub-request processed independently
- Failed requests don't stop batch processing

### GET /search

Global search across services and vendors.

**Parameters**:
- `q` (string, required) - Search query
- `type` (string) - Search type: `all`, `services`, `vendors` (default: `all`)

## Error Handling

### Standard Error Format

All errors follow WordPress REST API error format:

```json
{
  "code": "invalid_request",
  "message": "Missing required parameter: service_id",
  "data": {
    "status": 400,
    "params": {
      "service_id": "required"
    }
  }
}
```

### Error codes

The plugin returns **78 distinct error codes**. Branch on `code`, never on
`message` -- messages are translated and will not match on a non-English site.

Codes are grouped by the status they return. Anything not listed here comes from
WordPress core (`rest_no_route`, `rest_cookie_invalid_nonce`, and friends).

#### The codes you will actually branch on

If you implement nothing else, implement these. They are the ones a real client
hits, and several were undocumented before 1.4.0.

| Status | Code | Means | What to do |
|---|---|---|---|
| 401 | `rest_not_logged_in` | No usable session or credentials | Refresh the token / re-auth, then retry once |
| 403 | `wpss_forbidden` | Logged in, but not a party to this object (not the buyer, not the vendor) | Do not retry. Surface it |
| 403 | `wpss_not_vendor` | Logged in, but the account is not a vendor | Offer the "become a vendor" flow |
| 403 | `wpss_pro_license_required` | A Pro endpoint on a site with no active license | Hide the feature; do not retry |
| 400 | `wpss_category_required` | Publishing a service with no category, on a site that requires one | Fix the payload |
| 404 | `wpss_milestone_not_found` | No such milestone, or the id is not a milestone sub-order | Re-fetch the order |
| 409 | `wpss_milestone_not_payable` | The phase is not in `pending_payment` | Re-fetch; someone already paid or cancelled it |
| 409 | `wpss_milestone_locked` | An earlier phase is still open | Show the "pay the previous phase first" hint |
| 409 | `wpss_milestone_not_declinable` | The phase is not awaiting approval | Re-fetch |
| 409 | `wpss_milestone_not_cancellable` | The phase has moved past the cancellable window | Re-fetch |
| 409 | `wpss_order_not_payable` | The order is not awaiting payment | Re-fetch |

**On 401 vs 403 (changed in 1.4.0):** the plugin now answers these two
correctly. Routes that used a bare boolean permission callback made WordPress
report `rest_forbidden` to an *anonymous* caller -- so a client whose rule is
"401 means refresh the token and retry" read an expired token as a permanent
denial and never recovered. `/me` and `/dashboard`, the first two routes a
cold-starting app calls, both did this. Anonymous is now always `401
rest_not_logged_in`; a logged-in caller who lacks the right is `403`, and the
vendor case has one code, `wpss_not_vendor`, instead of several spellings.

**There is no error code for "a cart plugin owns payments."** When WooCommerce,
EDD or FluentCart is enabled, the `/payments/*` routes are simply not
registered, so the answer is WordPress core's `404 rest_no_route`. Detect the
rail from `GET /settings` rather than probing a payment route and interpreting
the 404.

#### 401 -- not authenticated

`rest_not_logged_in` · `invalid_credentials`

#### 403 -- authenticated but not allowed

`rest_forbidden` (admin-only action) · `wpss_forbidden` · `wpss_not_vendor` ·
`not_vendor` (legacy spelling, still emitted by some vendor routes) ·
`wpss_pro_license_required` · `wpss_realtime_forbidden` · `disputes_disabled` ·
`registration_disabled`

#### 404 -- not found

`rest_order_not_found` · `wpss_order_not_found` · `rest_vendor_not_found` ·
`rest_review_not_found` · `request_not_found` · `proposal_not_found` ·
`dispute_not_found` · `conversation_not_found` · `addon_not_found` ·
`invalid_service` · `invalid_package` · `rest_file_not_found` ·
`rest_not_vendor` · `not_found`

#### 409 -- conflicting state

`wpss_order_not_payable` -- the order is not awaiting payment
`wpss_milestone_not_payable` -- the phase is not awaiting payment
`wpss_milestone_not_declinable` -- the phase is past the point where it can be declined
`wpss_milestone_not_cancellable` -- the phase is past the point where it can be cancelled
`wpss_milestone_locked` -- an earlier phase is still open

These are the ones worth handling explicitly: they mean "your request was
valid, but the object has moved on." Re-fetch the order rather than retrying.

**`wpss_milestone_locked` is narrower than it sounds.** A phase unlocks when
every earlier phase has reached `completed` **or** `cancelled` -- and `cancelled`
covers a buyer declining it, a vendor deleting it while unpaid, and the 48-hour
abandon sweep. *Paying* an earlier phase does **not** unlock the next one: a paid
phase is `in_progress`, which still blocks. See
[Milestone Contracts](../order-management/milestones-wpss.md#the-lock-step-rule-and-where-it-actually-holds).

There is also a second, differently-spelled code for the same condition:
`wpss_phase_locked`, returned by `CheckoutIntentService` on the Stripe and
Razorpay standalone paths. Branch on both.

#### 429 -- rate limited

`rate_limit_exceeded` · `rate_limited`

Both ship in **free** (login and registration are rate limited). Back off and
retry; do not loop.

#### 500 / 501 -- server side

`order_failed` · `create_failed` · `update_failed` · `delete_failed` ·
`upload_failed` · `addon_create_failed` · `rest_review_failed` ·
`rest_message_failed` · `rest_conversation_failed` · `rest_deliverable_failed` ·
`rest_vacation_update_failed` · `wpss_profile_update_failed` ·
`conversation_unavailable` · `user_lookup_failed` · `no_provider` ·
`app_passwords_unavailable` · `checkout_unavailable` (501)

`no_provider` and `checkout_unavailable` mean the marketplace is misconfigured
(no e-commerce adapter or gateway available), not that the request was wrong.

#### 400 -- bad request or rejected business rule

Most codes fall here. The ones you are most likely to handle:

| Code | Means |
|------|-------|
| `rest_validation_failed` | Generic parameter validation failure |
| `rest_invalid_rating` | Rating outside 1-5 |
| `rest_invalid_message`, `message_empty` | Empty message body |
| `rest_already_reviewed`, `rest_already_replied`, `rest_already_voted` | Duplicate action |
| `rest_review_window_expired` | Past the review window (default 30 days) |
| `rest_order_not_completed` | Reviewing an order that is not complete |
| `rest_action_failed` | The order status transition is not allowed from here |
| `rest_amount_mismatch` | Paid amount does not match the order total |
| `own_service` | Buying your own service |
| `service_paused` | Vendor paused the service or is on vacation |
| `empty_cart`, `not_found` | Cart empty, or item key not in cart |
| `insufficient_balance`, `below_minimum`, `pending_exists` | Withdrawal rejected |
| `invalid_amount` | Amount must be greater than zero |
| `rest_registration_closed`, `rest_already_vendor`, `rest_pending_application` | Vendor registration rejected |
| `username_exists`, `email_exists`, `weak_password`, `incorrect_password` | Account problems |
| `invalid_gateway`, `unsupported_gateway` | Gateway not enabled, or does not support REST confirmation |
| `stripe_error`, `stripe_confirm_error`, `paypal_error`, `paypal_confirm_error` | Gateway declined or errored |
| `file_too_large`, `invalid_type`, `no_file` | Upload rejected |

`rest_action_failed` is the one that most often looks like a bug and is not: it
means the transition you asked for is not legal from the order's current status.
Check the status first -- see [Order Lifecycle](../order-management/order-lifecycle.md).

## Pagination

### Pagination Parameters

All list endpoints support pagination:

**Parameters**:
- `page` (int) - Current page number (default: 1)
- `per_page` (int) - Items per page (default: 10, max: 100)

### Pagination Headers

Responses include pagination headers:

```
X-WP-Total: 50
X-WP-TotalPages: 5
Link: <url?page=2>; rel="next", <url?page=5>; rel="last"
```

### Pagination Response Body

```json
{
  "items": [...],
  "total": 50,
  "pages": 5,
  "current_page": 1,
  "per_page": 10
}
```

## CORS Support

CORS headers are automatically added for requests to `/wp-json/wpss/` namespace.

**Allowed Origins**: Configurable via `wpss_api_cors_origins` filter (default: site home URL)

**Allowed Methods**: GET, POST, PUT, PATCH, DELETE, OPTIONS

**Allowed Headers**: Authorization, Content-Type, X-WP-Nonce

**Example Filter**:
```php
add_filter( 'wpss_api_cors_origins', function( $origins ) {
    $origins[] = 'https://mobile-app.example.com';
    return $origins;
} );
```

## Rate Limiting

Rate limiting ships in the **free** plugin. It is applied per action, not per
user per hour, and it guards specific write paths -- opening a dispute, marking a
review helpful, and similar -- rather than the API as a whole.

Budgets live in `Core\RateLimiter` and are filterable:

```php
add_filter( 'wpss_rate_limits', function ( array $limits, string $action ) {
    if ( 'dispute' === $action ) {
        $limits['max'] = 10; // allow 10 in the window instead of the default
    }
    return $limits;
}, 10, 2 );
```

A refused request returns HTTP **429**. There are **no** `X-RateLimit-*`
response headers -- do not build a client that waits on them.

## Extending the API

### Adding Custom Endpoints

Register custom controllers via `wpss_api_controllers` filter:

```php
add_filter( 'wpss_api_controllers', function( $controllers ) {
    $controllers[] = new My_Custom_Controller();
    return $controllers;
} );
```

See [Custom Integrations](custom-integrations.md) for detailed examples.

## Related Documentation

- [REST API Controllers Reference](rest-api-controllers.md) - Every route, by controller
- [Hooks and Filters](hooks-filters.md) - Available action and filter hooks
- [Custom Integrations](custom-integrations.md) - Building custom controllers
- [Theme Integration](theme-integration.md) - Frontend integration

---

**API Version**: v1
**Documented against**: WP Sell Services 1.4.0 (free) + WP Sell Services Pro 1.4.0
**WordPress Version**: Requires WordPress 6.4+ with REST API enabled

The API has changed since 1.0.0 -- routes were added, `accept`/`reject` order
actions were removed in 1.4.0, and `/payments/*` became conditional on the
active e-commerce rail. Treat this page as describing 1.4.0, not "1.0.0 and
everything after".
