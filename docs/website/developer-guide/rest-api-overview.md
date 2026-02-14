# REST API Overview

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

- [REST API Controllers Reference](rest-api-controllers.md) - All 20 controller endpoints
- [Hooks and Filters](hooks-filters.md) - Available action and filter hooks
- [Custom Integrations](custom-integrations.md) - Building custom controllers
- [Theme Integration](theme-integration.md) - Frontend integration

---

**API Version**: v1
**Last Updated**: Compatible with WP Sell Services 1.0.0+
**WordPress Version**: Requires WordPress 6.4+ with REST API enabled
