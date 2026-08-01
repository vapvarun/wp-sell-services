# REST API Overview

WP Sell Services provides a comprehensive REST API for building custom integrations, mobile apps, and external applications. The API follows WordPress REST API standards with 21 dedicated controllers plus generic endpoints.

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
    "vendors": 124,
    "dashboard": 125,
    "checkout": 126,
    "terms": 127
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
hits, and several were undocumented before 1.3.1.

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

**On 401 vs 403 (changed in 1.3.1):** the plugin now answers these two
correctly. Routes that used a bare boolean permission callback made WordPress
report `rest_forbidden` to an *anonymous* caller -- so a client whose rule is
"401 means refresh the token and retry" read an expired token as a permanent
denial and never recovered. `/me` and `/dashboard`, the first two routes a
cold-starting app calls, both did this. Anonymous is now always `401
rest_not_logged_in`; a logged-in caller who lacks the right is `403`, and the
vendor case has one code, `wpss_not_vendor`, instead of several spellings.

**There is no error code for "a cart plugin owns payments."** When WooCommerce,
EDD, FluentCart or SureCart is enabled, the `/payments/*` routes are simply not
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

## Rate Limiting **[PRO]**

API rate limiting protects against abuse.

**Limits**:
- Authenticated users: 300 requests/hour
- Application passwords: 1000 requests/hour
- Administrators: Unlimited

**Rate Limit Headers**:
```
X-RateLimit-Limit: 300
X-RateLimit-Remaining: 245
X-RateLimit-Reset: 1706785200
```

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
**Documented against**: WP Sell Services 1.3.1 (free) + WP Sell Services Pro 1.3.1
**WordPress Version**: Requires WordPress 6.4+ with REST API enabled

The API has changed since 1.0.0 -- routes were added, `accept`/`reject` order
actions were removed in 1.3.1, and `/payments/*` became conditional on the
active e-commerce rail. Treat this page as describing 1.3.1, not "1.0.0 and
everything after".
