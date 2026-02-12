# REST API Reference

WP Sell Services provides a comprehensive REST API for building custom integrations, mobile apps, and external applications. The API follows WordPress REST API standards with 20 dedicated controllers plus generic endpoints.

## Overview

**Base URL**: `/wp-json/wpss/v1/`

**Controllers**: 20 specialized controllers handling services, orders, vendors, reviews, conversations, disputes, buyer requests, proposals, notifications, portfolio, earnings, extension requests, milestones, tipping, seller levels, moderation, favorites, media, cart, and authentication.

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

## API Controllers

### 1. Services (`/services`)

Manage service listings, packages, and metadata.

**GET /services** - List services
**GET /services/{id}** - Get service details
**POST /services** - Create service (vendor only)
**PUT /services/{id}** - Update service (owner/admin)
**DELETE /services/{id}** - Delete service (owner/admin)

### 2. Orders (`/orders`)

Handle order lifecycle, status changes, and order management.

**GET /orders** - List orders (filtered by user role)
**GET /orders/{id}** - Get order details
**POST /orders/{id}/accept** - Accept order (vendor)
**POST /orders/{id}/start** - Start work (vendor)
**POST /orders/{id}/complete** - Complete order (buyer)
**POST /orders/{id}/cancel** - Cancel order

### 3. Reviews (`/reviews`)

Manage service and vendor reviews.

**GET /reviews** - List reviews
**GET /reviews/{id}** - Get review details
**POST /reviews** - Submit review (buyer, within review window)
**PUT /reviews/{id}** - Update review (owner)
**DELETE /reviews/{id}** - Delete review (owner/admin)

### 4. Vendors (`/vendors`)

Vendor profiles, statistics, and public information.

**GET /vendors** - List vendors
**GET /vendors/{id}** - Get vendor profile
**PUT /vendors/{id}** - Update profile (own profile)
**GET /vendors/{id}/services** - Get vendor's services
**GET /vendors/{id}/stats** - Get vendor statistics

### 5. Conversations (`/conversations`)

Order messaging and communication.

**GET /conversations/{order_id}** - Get messages for order
**POST /conversations/{order_id}/message** - Send message
**PUT /conversations/{message_id}/read** - Mark message as read

### 6. Disputes (`/disputes`)

Dispute management and resolution.

**GET /disputes** - List disputes
**GET /disputes/{id}** - Get dispute details
**POST /disputes** - Open dispute
**POST /disputes/{id}/message** - Add evidence
**POST /disputes/{id}/resolve** - Resolve dispute (admin)

### 7. Buyer Requests (`/buyer-requests`)

Job posting and proposal system.

**GET /buyer-requests** - List requests
**GET /buyer-requests/{id}** - Get request details
**POST /buyer-requests** - Post request (buyer)
**PUT /buyer-requests/{id}** - Update request
**DELETE /buyer-requests/{id}** - Delete request

### 8. Proposals (`/proposals`)

Vendor proposals for buyer requests.

**GET /proposals** - List proposals (filtered by user)
**GET /proposals/{id}** - Get proposal details
**POST /proposals** - Submit proposal (vendor)
**PUT /proposals/{id}** - Update proposal
**POST /proposals/{id}/accept** - Accept proposal (buyer)
**DELETE /proposals/{id}** - Withdraw proposal (vendor)

### 9. Notifications (`/notifications`)

In-app notifications.

**GET /notifications** - List notifications
**GET /notifications/unread** - Get unread count
**PUT /notifications/{id}/read** - Mark as read
**POST /notifications/read-all** - Mark all as read
**DELETE /notifications/{id}** - Delete notification

### 10. Portfolio (`/portfolio`)

Vendor portfolio items.

**GET /portfolio** - List portfolio items
**GET /portfolio/{id}** - Get portfolio item
**POST /portfolio** - Add portfolio item (vendor)
**PUT /portfolio/{id}** - Update portfolio item
**DELETE /portfolio/{id}** - Delete portfolio item

### 11. Earnings (`/earnings`)

Vendor earnings and withdrawals.

**GET /earnings** - Get earnings summary (vendor)
**GET /earnings/history** - Earnings history
**GET /earnings/withdrawals** - Withdrawal history
**POST /earnings/withdraw** - Request withdrawal

### 12. Extension Requests (`/extension-requests`)

Order deadline extensions.

**GET /extension-requests** - List extension requests
**POST /extension-requests** - Request extension (vendor)
**POST /extension-requests/{id}/approve** - Approve (buyer)
**POST /extension-requests/{id}/reject** - Reject (buyer)

### 13. Milestones (`/milestones`)

Milestone-based payments **[PRO]**.

**GET /milestones/{order_id}** - Get order milestones
**POST /milestones** - Create milestone
**POST /milestones/{id}/submit** - Submit milestone (vendor)
**POST /milestones/{id}/approve** - Approve milestone (buyer)
**POST /milestones/{id}/reject** - Reject milestone (buyer)

### 14. Tipping (`/tips`)

Optional tipping system.

**POST /tips** - Send tip to vendor
**GET /tips/sent** - Tips sent (buyer)
**GET /tips/received** - Tips received (vendor)

### 15. Seller Levels (`/seller-levels`)

Vendor tier system.

**GET /seller-levels** - List level definitions
**GET /seller-levels/{id}** - Get level details
**GET /seller-levels/progress** - Get vendor progress (own profile)

### 16. Moderation (`/moderation`)

Content moderation tools (admin).

**GET /moderation/services** - Services pending approval
**POST /moderation/services/{id}/approve** - Approve service
**POST /moderation/services/{id}/reject** - Reject service
**GET /moderation/reviews** - Reviews pending moderation
**POST /moderation/reviews/{id}/approve** - Approve review

### 17. Favorites (`/favorites`)

Buyer favorites/wishlist.

**GET /favorites** - List favorites (buyer)
**POST /favorites** - Add to favorites
**DELETE /favorites/{service_id}** - Remove from favorites

### 18. Media (`/media`)

File upload and management.

**POST /media/upload** - Upload file
**GET /media/{id}** - Get file info
**DELETE /media/{id}** - Delete file

### 19. Cart (`/cart`)

Shopping cart management.

**GET /cart** - Get cart contents
**POST /cart/add** - Add service to cart
**PUT /cart/update** - Update cart item
**DELETE /cart/remove** - Remove from cart
**POST /cart/clear** - Clear cart

### 20. Auth (`/auth`)

Authentication and session management **[PRO]**.

**POST /auth/login** - Login user
**POST /auth/register** - Register new user
**POST /auth/logout** - Logout
**GET /auth/validate** - Validate token
**POST /auth/refresh** - Refresh token

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
  }
}
```

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
  },
  "pending_orders": [
    {
      "id": 567,
      "order_number": "WPSS-567",
      "service": "WordPress Website Development",
      "total": "$200.00",
      "created_at": "2026-02-01T10:00:00"
    }
  ]
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

**Response**:
```json
{
  "responses": [
    {
      "status": 200,
      "body": { /* services data */ }
    },
    {
      "status": 200,
      "body": { /* vendors data */ }
    },
    {
      "status": 201,
      "body": { "success": true }
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

**Response**:
```json
{
  "query": "wordpress",
  "services": [
    {
      "id": 123,
      "title": "WordPress Development",
      "slug": "wordpress-development",
      "thumbnail": "https://example.com/thumb.jpg",
      "price": "$100.00",
      "rating": 4.8,
      "url": "https://example.com/service/wordpress-development"
    }
  ],
  "vendors": [
    {
      "id": 45,
      "display_name": "John Doe",
      "avatar": "https://example.com/avatar.jpg",
      "tagline": "Expert WordPress Developer",
      "rating": 4.9,
      "url": "https://example.com/vendor/johndoe"
    }
  ]
}
```

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

### Common Error Codes

| Code | Status | Description |
|------|--------|-------------|
| `rest_forbidden` | 401 | Authentication required |
| `rest_forbidden_context` | 403 | Insufficient permissions |
| `invalid_request` | 400 | Invalid or missing parameters |
| `not_found` | 404 | Resource not found |
| `server_error` | 500 | Internal server error |
| `rate_limit_exceeded` | 429 | Too many requests **[PRO]** |

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

**Rate Limit Exceeded Response**:
```json
{
  "code": "rate_limit_exceeded",
  "message": "API rate limit exceeded. Please try again later.",
  "data": {
    "status": 429,
    "retry_after": 3600
  }
}
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

- [Hooks and Filters](hooks-filters.md) - Available action and filter hooks
- [Custom Integrations](custom-integrations.md) - Building custom controllers
- [Theme Integration](theme-integration.md) - Frontend integration

---

**API Version**: v1
**Last Updated**: Compatible with WP Sell Services 1.0.0+
**WordPress Version**: Requires WordPress 6.4+ with REST API enabled
