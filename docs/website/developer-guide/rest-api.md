# REST API Reference

WP Sell Services provides a comprehensive REST API for building custom integrations, mobile apps, and external applications. Access services, orders, vendors, and marketplace data programmatically.

## Overview

The REST API follows WordPress REST API standards and provides:

- **Base URL**: `/wp-json/wpss/v1/`
- **8 Controllers**: Services, Orders, Vendors, Reviews, Conversations, Disputes, Buyer Requests, Proposals
- **Authentication**: Cookie, Application Password, JWT
- **Standard responses**: JSON format with error handling
- **Pagination**: Standard WordPress pagination
- **Permissions**: WordPress capability-based

## Authentication

### Cookie Authentication

Used for browser-based requests from logged-in users.

**Prerequisites:**
- User logged into WordPress
- Nonce verification required

**Example:**
```javascript
// Get nonce from wp_localize_script
const nonce = wpApiSettings.nonce;

fetch('/wp-json/wpss/v1/services', {
    method: 'GET',
    credentials: 'same-origin',
    headers: {
        'X-WP-Nonce': nonce,
    },
})
.then(response => response.json())
.then(data => console.log(data));
```

### Application Passwords

Recommended for external applications and integrations.

**Setup:**
1. Go to **WordPress Admin → Users → Profile**
2. Scroll to **Application Passwords**
3. Enter application name
4. Click **Add New Application Password**
5. Copy generated password (shown once)

**Example:**
```bash
curl -X GET \
  https://example.com/wp-json/wpss/v1/services \
  -u "username:xxxx xxxx xxxx xxxx xxxx xxxx"
```

```javascript
const username = 'john_doe';
const password = 'xxxx xxxx xxxx xxxx xxxx xxxx';
const auth = btoa(`${username}:${password}`);

fetch('/wp-json/wpss/v1/services', {
    headers: {
        'Authorization': `Basic ${auth}`,
    },
})
.then(response => response.json())
.then(data => console.log(data));
```

### JWT Authentication

**[PRO]** JSON Web Token authentication for mobile apps.

**Setup:**
1. Install JWT authentication plugin
2. Configure secret key in wp-config.php
3. Obtain token via authentication endpoint

**Example:**
```javascript
// Get token
const response = await fetch('/wp-json/jwt-auth/v1/token', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
        username: 'john_doe',
        password: 'password123',
    }),
});

const { token } = await response.json();

// Use token
fetch('/wp-json/wpss/v1/services', {
    headers: {
        'Authorization': `Bearer ${token}`,
    },
});
```

## Services Endpoints

### GET /services

Retrieve list of services.

**Parameters:**

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| page | int | 1 | Page number |
| per_page | int | 10 | Items per page (max: 100) |
| search | string | - | Search term |
| category | int | - | Category ID |
| vendor_id | int | - | Filter by vendor |
| min_price | float | - | Minimum price |
| max_price | float | - | Maximum price |
| rating | float | - | Minimum rating |
| orderby | string | date | Sort by: date, price, rating, popularity |
| order | string | desc | Sort order: asc, desc |

**Response:**
```json
{
  "services": [
    {
      "id": 123,
      "title": "WordPress Website Development",
      "slug": "wordpress-website-development",
      "vendor_id": 45,
      "vendor_name": "John Doe",
      "category_id": 12,
      "category_name": "Web Development",
      "description": "Professional WordPress development...",
      "basic_price": 100.00,
      "standard_price": 200.00,
      "premium_price": 300.00,
      "delivery_time": 7,
      "revisions": 2,
      "rating": 4.8,
      "reviews_count": 42,
      "orders_count": 156,
      "featured_image": "https://example.com/image.jpg",
      "gallery": ["url1", "url2"],
      "created_at": "2026-01-15T10:30:00",
      "url": "https://example.com/service/wordpress-website-development/"
    }
  ],
  "total": 50,
  "pages": 5,
  "current_page": 1,
  "per_page": 10
}
```

### GET /services/{id}

Get single service details.

**Response:**
```json
{
  "id": 123,
  "title": "WordPress Website Development",
  "slug": "wordpress-website-development",
  "vendor": {
    "id": 45,
    "name": "John Doe",
    "username": "johndoe",
    "avatar": "https://example.com/avatar.jpg",
    "rating": 4.9,
    "reviews": 234,
    "member_since": "2024-01-01",
    "response_time": "1 hour"
  },
  "description": "Full service description...",
  "packages": {
    "basic": {
      "price": 100.00,
      "delivery_days": 7,
      "revisions": 2,
      "features": ["5 pages", "Responsive design", "SEO setup"]
    },
    "standard": {
      "price": 200.00,
      "delivery_days": 10,
      "revisions": 5,
      "features": ["10 pages", "Responsive design", "SEO setup", "Contact form"]
    },
    "premium": {
      "price": 300.00,
      "delivery_days": 14,
      "revisions": -1,
      "features": ["Unlimited pages", "Responsive", "SEO", "Forms", "E-commerce"]
    }
  },
  "extras": [
    {
      "id": 1,
      "title": "Extra fast delivery",
      "price": 50.00
    }
  ],
  "faqs": [
    {
      "question": "Do you provide hosting?",
      "answer": "No, hosting is not included."
    }
  ],
  "reviews": {
    "average": 4.8,
    "count": 42,
    "breakdown": {
      "5": 35,
      "4": 5,
      "3": 2,
      "2": 0,
      "1": 0
    }
  },
  "images": {
    "featured": "url",
    "gallery": ["url1", "url2", "url3"]
  },
  "created_at": "2026-01-15T10:30:00",
  "updated_at": "2026-02-01T14:20:00"
}
```

### POST /services

Create new service (vendors only).

**Required Capability:** `wpss_create_services`

**Request Body:**
```json
{
  "title": "My New Service",
  "description": "Service description...",
  "category_id": 12,
  "basic_price": 100,
  "basic_delivery": 7,
  "basic_revisions": 2,
  "basic_features": ["Feature 1", "Feature 2"],
  "standard_price": 200,
  "standard_delivery": 10,
  "standard_revisions": 5,
  "standard_features": ["Feature 1", "Feature 2", "Feature 3"],
  "premium_price": 300,
  "premium_delivery": 14,
  "premium_revisions": -1,
  "premium_features": ["All features", "Priority support"],
  "featured_image_id": 456,
  "gallery_ids": [789, 790]
}
```

**Response:**
```json
{
  "success": true,
  "service_id": 124,
  "status": "pending",
  "message": "Service submitted for review"
}
```

### PUT /services/{id}

Update existing service.

**Required:** Service owner or admin

**Request Body:** Same as POST (partial updates supported)

### DELETE /services/{id}

Delete service.

**Required:** Service owner or admin

**Response:**
```json
{
  "success": true,
  "message": "Service deleted successfully"
}
```

### GET /services/{id}/packages

Get service packages and pricing.

### GET /services/{id}/faqs

Get service FAQs.

### GET /services/{id}/reviews

Get service reviews.

## Orders Endpoints

### GET /orders

Get orders list.

**Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| page | int | Page number |
| per_page | int | Items per page |
| status | string | Order status |
| vendor_id | int | Filter by vendor (vendors see their orders only) |
| buyer_id | int | Filter by buyer (buyers see their orders only) |

**Response:**
```json
{
  "orders": [
    {
      "id": 567,
      "order_number": "WPSS-567",
      "service_id": 123,
      "service_title": "WordPress Website Development",
      "package": "standard",
      "vendor_id": 45,
      "vendor_name": "John Doe",
      "buyer_id": 89,
      "buyer_name": "Jane Smith",
      "status": "in_progress",
      "total": 200.00,
      "created_at": "2026-02-01T10:00:00",
      "delivery_due": "2026-02-11T10:00:00",
      "url": "https://example.com/order/567/"
    }
  ],
  "total": 25,
  "pages": 3
}
```

### GET /orders/{id}

Get order details.

**Required:** Order buyer, vendor, or admin

**Response:**
```json
{
  "id": 567,
  "order_number": "WPSS-567",
  "status": "in_progress",
  "service": {
    "id": 123,
    "title": "WordPress Website Development",
    "package": "standard"
  },
  "vendor": {
    "id": 45,
    "name": "John Doe",
    "avatar": "url"
  },
  "buyer": {
    "id": 89,
    "name": "Jane Smith",
    "avatar": "url"
  },
  "pricing": {
    "subtotal": 200.00,
    "extras": 50.00,
    "total": 250.00,
    "commission_rate": 20,
    "commission": 50.00,
    "vendor_earnings": 200.00
  },
  "timeline": {
    "ordered": "2026-02-01T10:00:00",
    "accepted": "2026-02-01T11:30:00",
    "delivery_due": "2026-02-11T10:00:00",
    "delivered": null,
    "completed": null
  },
  "requirements_submitted": true,
  "delivery_submitted": false,
  "revisions_used": 0,
  "revisions_allowed": 5
}
```

### PUT /orders/{id}

Update order (limited fields based on role).

### POST /orders/{id}/message

Send message in order conversation.

**Request Body:**
```json
{
  "message": "Hi, I have a question about...",
  "attachments": [123, 124]
}
```

### POST /orders/{id}/requirements

Submit order requirements (buyer only).

**Request Body:**
```json
{
  "requirements": "Here are my requirements...",
  "files": [123, 124, 125]
}
```

### POST /orders/{id}/delivery

Submit delivery (vendor only).

**Request Body:**
```json
{
  "message": "Here is your completed work...",
  "files": [456, 457, 458]
}
```

### POST /orders/{id}/revision-request

Request revision (buyer only).

**Request Body:**
```json
{
  "reason": "Please change the header color to blue..."
}
```

### POST /orders/{id}/accept

Accept order (vendor) or delivery (buyer).

**Response:**
```json
{
  "success": true,
  "new_status": "in_progress",
  "message": "Order accepted"
}
```

### POST /orders/{id}/reject

Reject order (vendor only).

**Request Body:**
```json
{
  "reason": "Cannot complete this within timeframe"
}
```

### POST /orders/{id}/start

Mark order as started (vendor only).

### POST /orders/{id}/complete

Mark order as complete (system, after buyer acceptance).

### POST /orders/{id}/cancel

Cancel order (buyer or vendor with reason).

**Request Body:**
```json
{
  "reason": "No longer needed",
  "refund": true
}
```

### POST /orders/{id}/dispute

Open dispute.

**Request Body:**
```json
{
  "reason": "Service not as described",
  "description": "The delivered work does not match...",
  "evidence": [789, 790]
}
```

## Vendors Endpoints

### GET /vendors

Get vendors list.

**Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| page | int | Page number |
| per_page | int | Items per page |
| search | string | Search term |
| category | int | Filter by category |
| min_rating | float | Minimum rating |
| tier | string | Vendor tier level |
| orderby | string | Sort: rating, orders, revenue, join_date |

**Response:**
```json
{
  "vendors": [
    {
      "id": 45,
      "name": "John Doe",
      "username": "johndoe",
      "avatar": "url",
      "tagline": "Expert WordPress Developer",
      "rating": 4.9,
      "reviews": 234,
      "orders": 567,
      "tier": "top_rated",
      "member_since": "2024-01-01",
      "response_time": "1 hour",
      "url": "https://example.com/vendor/johndoe/"
    }
  ],
  "total": 150
}
```

### GET /vendors/{id}

Get vendor profile.

**Response:**
```json
{
  "id": 45,
  "name": "John Doe",
  "username": "johndoe",
  "avatar": "url",
  "cover_image": "url",
  "tagline": "Expert WordPress Developer",
  "description": "Full bio...",
  "rating": 4.9,
  "reviews": 234,
  "orders": 567,
  "completion_rate": 98.5,
  "tier": "top_rated",
  "member_since": "2024-01-01",
  "response_time": "1 hour",
  "languages": ["English", "Spanish"],
  "skills": ["WordPress", "PHP", "JavaScript"],
  "certifications": ["WordPress Certified Developer"],
  "vacation_mode": false,
  "services_count": 12,
  "portfolio_items": 25
}
```

### PUT /vendors/{id}

Update vendor profile (own profile only).

**Request Body:**
```json
{
  "tagline": "New tagline",
  "description": "Updated bio",
  "languages": ["English", "Spanish", "French"],
  "skills": ["WordPress", "PHP"]
}
```

### GET /vendors/{id}/services

Get vendor's services.

### GET /vendors/{id}/portfolio

Get vendor's portfolio items.

## Reviews Endpoints

### GET /reviews

Get reviews list.

**Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| service_id | int | Filter by service |
| vendor_id | int | Filter by vendor |
| rating | int | Filter by rating (1-5) |
| page | int | Page number |
| per_page | int | Items per page |

**Response:**
```json
{
  "reviews": [
    {
      "id": 789,
      "order_id": 567,
      "service_id": 123,
      "vendor_id": 45,
      "buyer": {
        "id": 89,
        "name": "Jane Smith",
        "avatar": "url"
      },
      "rating": 5,
      "comment": "Excellent work, highly recommended!",
      "created_at": "2026-02-01T15:00:00",
      "vendor_reply": "Thank you for your kind words!",
      "reply_date": "2026-02-01T16:30:00"
    }
  ],
  "total": 42
}
```

### POST /reviews

Create review (order buyers only, within review window).

**Request Body:**
```json
{
  "order_id": 567,
  "rating": 5,
  "comment": "Excellent work!",
  "private_feedback": "Delivered early"
}
```

### GET /reviews/{id}

Get single review.

## Conversations Endpoints

### GET /conversations/{order_id}

Get order conversation messages.

**Required:** Order buyer, vendor, or admin

**Response:**
```json
{
  "order_id": 567,
  "messages": [
    {
      "id": 123,
      "sender_id": 89,
      "sender_name": "Jane Smith",
      "sender_type": "buyer",
      "message": "Hi, I have a question...",
      "attachments": [
        {
          "id": 456,
          "filename": "reference.pdf",
          "url": "url"
        }
      ],
      "created_at": "2026-02-01T10:15:00",
      "read": true
    }
  ],
  "unread_count": 2
}
```

### POST /conversations/{order_id}/message

Send message (covered in Orders endpoints).

## Disputes Endpoints

### GET /disputes

Get disputes list (admins see all, users see their own).

**Response:**
```json
{
  "disputes": [
    {
      "id": 234,
      "order_id": 567,
      "opened_by": 89,
      "opened_by_type": "buyer",
      "reason": "Service not as described",
      "status": "open",
      "opened_at": "2026-02-01T14:00:00"
    }
  ]
}
```

### GET /disputes/{id}

Get dispute details.

**Response:**
```json
{
  "id": 234,
  "order_id": 567,
  "opened_by": 89,
  "opened_by_type": "buyer",
  "reason": "Service not as described",
  "description": "Full description...",
  "status": "open",
  "evidence": [
    {
      "submitted_by": 89,
      "submitted_by_type": "buyer",
      "message": "Here is proof...",
      "files": ["url1", "url2"],
      "submitted_at": "2026-02-01T14:00:00"
    }
  ],
  "admin_notes": [],
  "opened_at": "2026-02-01T14:00:00",
  "resolved_at": null,
  "resolution": null
}
```

### POST /disputes/{id}/message

Add evidence or message to dispute.

**Request Body:**
```json
{
  "message": "Additional information...",
  "files": [123, 124]
}
```

### POST /disputes/{id}/escalate

Escalate dispute to admin (vendor or buyer).

### POST /disputes/{id}/resolve

Resolve dispute (admins only).

**Request Body:**
```json
{
  "resolution": "refund_buyer",
  "notes": "Refund issued to buyer",
  "refund_amount": 250.00
}
```

## Buyer Requests Endpoints

### GET /buyer-requests

Get buyer requests list.

**Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| category | int | Filter by category |
| min_budget | float | Minimum budget |
| max_budget | float | Maximum budget |
| status | string | open, closed, in_progress |

**Response:**
```json
{
  "requests": [
    {
      "id": 345,
      "title": "Need WordPress Plugin Development",
      "category_id": 12,
      "category_name": "Web Development",
      "budget": 500.00,
      "delivery_days": 14,
      "description": "I need a custom plugin...",
      "buyer": {
        "id": 89,
        "name": "Jane Smith"
      },
      "proposals_count": 12,
      "status": "open",
      "created_at": "2026-02-01T09:00:00",
      "expires_at": "2026-02-08T09:00:00"
    }
  ]
}
```

### POST /buyer-requests

Create buyer request (buyers only).

**Request Body:**
```json
{
  "title": "Need WordPress Plugin",
  "description": "Detailed description...",
  "category_id": 12,
  "budget": 500,
  "delivery_days": 14,
  "attachments": [123, 124]
}
```

### GET /buyer-requests/{id}

Get request details.

### GET /buyer-requests/{id}/proposals

Get proposals for request.

## Proposals Endpoints

### POST /proposals

Submit proposal (vendors only).

**Request Body:**
```json
{
  "request_id": 345,
  "message": "I can help you with this project...",
  "price": 450,
  "delivery_days": 12,
  "attachments": [456]
}
```

### PUT /proposals/{id}

Update proposal (before acceptance).

### POST /proposals/{id}/accept

Accept proposal (buyer only, creates order).

### DELETE /proposals/{id}

Withdraw proposal (vendor only).

## Generic Endpoints

### GET /categories

Get service categories.

**Response:**
```json
{
  "categories": [
    {
      "id": 12,
      "name": "Web Development",
      "slug": "web-development",
      "parent_id": 0,
      "count": 145,
      "image": "url"
    }
  ]
}
```

### GET /search

Global search across services and vendors.

**Parameters:**
- `q`: Search query
- `type`: services, vendors, or both

## Error Handling

### Error Response Format

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
| `invalid_request` | 400 | Invalid parameters |
| `not_found` | 404 | Resource not found |
| `server_error` | 500 | Internal server error |

## Rate Limiting

**[PRO]** API rate limiting protects server resources:

- **Authenticated users**: 300 requests/hour
- **Application passwords**: 1000 requests/hour
- **Admins**: Unlimited

**Rate limit headers:**
```
X-RateLimit-Limit: 300
X-RateLimit-Remaining: 245
X-RateLimit-Reset: 1706785200
```

When exceeded:
```json
{
  "code": "rate_limit_exceeded",
  "message": "API rate limit exceeded",
  "data": {
    "status": 429,
    "retry_after": 3600
  }
}
```

## Pagination

### Pagination Parameters

- `page`: Current page (default: 1)
- `per_page`: Items per page (default: 10, max: 100)

### Pagination Headers

```
X-WP-Total: 50
X-WP-TotalPages: 5
Link: <url?page=2>; rel="next", <url?page=5>; rel="last"
```

### Pagination Response

```json
{
  "items": [...],
  "total": 50,
  "pages": 5,
  "current_page": 1,
  "per_page": 10
}
```

## Related Documentation

- [Hooks and Filters](hooks-filters.md) - Extending API functionality
- [Custom Integrations](custom-integrations.md) - Building custom controllers
- [Authentication Setup](../settings/security-settings.md) - Security configuration
