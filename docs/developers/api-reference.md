# REST API Reference

WP Sell Services provides a comprehensive REST API for building custom frontends, mobile apps, and integrations.

## Base URL

```
/wp-json/wpss/v1/
```

## Authentication

The API uses WordPress REST API authentication methods:

- **Cookie Authentication** - For logged-in users on the same domain
- **Application Passwords** - WordPress 5.6+ feature for external apps
- **OAuth** - Via plugins like WP OAuth Server

### Cookie Authentication

For JavaScript running on your WordPress site:

```javascript
fetch('/wp-json/wpss/v1/orders', {
    credentials: 'include',
    headers: {
        'X-WP-Nonce': wpssData.nonce
    }
})
```

### Application Passwords

For external applications:

```bash
curl -u "username:xxxx xxxx xxxx xxxx" \
  "https://yoursite.com/wp-json/wpss/v1/orders"
```

## Response Format

All responses return JSON:

```json
{
    "success": true,
    "data": { ... }
}
```

Error responses:

```json
{
    "code": "error_code",
    "message": "Human-readable message",
    "data": {
        "status": 400
    }
}
```

## Pagination

List endpoints support pagination:

| Parameter | Description | Default |
|-----------|-------------|---------|
| `page` | Page number | 1 |
| `per_page` | Items per page | 10 |

Response headers include:
- `X-WP-Total` - Total items
- `X-WP-TotalPages` - Total pages

## Generic Endpoints

### Get Categories

```http
GET /wpss/v1/categories
```

**Parameters:**

| Name | Type | Description |
|------|------|-------------|
| `parent` | integer | Parent category ID (0 for top-level) |
| `hide_empty` | boolean | Hide empty categories |

**Response:**

```json
[
    {
        "id": 1,
        "name": "Web Development",
        "slug": "web-development",
        "description": "Web development services",
        "parent": 0,
        "count": 25,
        "image": "https://..."
    }
]
```

### Get Tags

```http
GET /wpss/v1/tags
```

**Parameters:**

| Name | Type | Description |
|------|------|-------------|
| `search` | string | Search query |

### Get Public Settings

```http
GET /wpss/v1/settings
```

Returns public marketplace configuration:

```json
{
    "platform_name": "My Marketplace",
    "currency": "USD",
    "currency_symbol": "$",
    "reviews_enabled": true
}
```

### Get Current User

```http
GET /wpss/v1/me
```

Requires authentication.

```json
{
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "is_vendor": true,
    "vendor_profile": {
        "rating": 4.8,
        "total_reviews": 45,
        "completed_orders": 120
    }
}
```

### Dashboard Stats

```http
GET /wpss/v1/dashboard
```

Requires authentication. Returns user-specific stats:

```json
{
    "orders": {
        "total": 50,
        "pending": 5,
        "in_progress": 10,
        "completed": 35
    },
    "earnings": {
        "total": 5000.00,
        "pending": 500.00,
        "available": 4500.00
    },
    "services": {
        "total": 8,
        "active": 6
    }
}
```

### Search

```http
GET /wpss/v1/search
```

**Parameters:**

| Name | Type | Description |
|------|------|-------------|
| `q` | string | Search query (required) |
| `type` | string | `all`, `services`, or `vendors` |

## Services

### List Services

```http
GET /wpss/v1/services
```

**Parameters:**

| Name | Type | Description |
|------|------|-------------|
| `page` | integer | Page number |
| `per_page` | integer | Items per page (max 100) |
| `category` | integer | Category ID |
| `tag` | string | Tag slug |
| `vendor` | integer | Vendor user ID |
| `orderby` | string | `date`, `rating`, `price`, `title` |
| `order` | string | `asc` or `desc` |
| `search` | string | Search query |
| `featured` | boolean | Featured services only |
| `min_price` | number | Minimum price |
| `max_price` | number | Maximum price |

**Response:**

```json
[
    {
        "id": 123,
        "title": "Professional Logo Design",
        "slug": "professional-logo-design",
        "description": "...",
        "excerpt": "...",
        "vendor": {
            "id": 5,
            "name": "Design Pro",
            "avatar": "https://..."
        },
        "category": {
            "id": 2,
            "name": "Graphic Design"
        },
        "tags": ["logo", "branding"],
        "packages": [
            {
                "name": "Basic",
                "price": 50.00,
                "delivery_days": 5,
                "revisions": 2
            }
        ],
        "rating": 4.9,
        "review_count": 32,
        "images": ["https://..."],
        "featured_image": "https://...",
        "status": "publish",
        "created_at": "2024-01-15T10:30:00Z"
    }
]
```

### Get Service

```http
GET /wpss/v1/services/{id}
```

Returns full service details including packages, FAQs, and vendor info.

### Create Service

```http
POST /wpss/v1/services
```

Requires vendor authentication.

**Body:**

```json
{
    "title": "My New Service",
    "description": "Full description...",
    "category": 2,
    "tags": ["tag1", "tag2"],
    "packages": [
        {
            "name": "Basic",
            "description": "Basic package",
            "price": 50.00,
            "delivery_days": 5,
            "revisions": 1
        }
    ],
    "faqs": [
        {
            "question": "What do I need to provide?",
            "answer": "Your requirements and assets."
        }
    ]
}
```

### Update Service

```http
PUT /wpss/v1/services/{id}
PATCH /wpss/v1/services/{id}
```

Requires service owner authentication.

### Delete Service

```http
DELETE /wpss/v1/services/{id}
```

Requires service owner or admin authentication.

### Get Service Packages

```http
GET /wpss/v1/services/{id}/packages
```

### Get Service FAQs

```http
GET /wpss/v1/services/{id}/faqs
```

### Get Service Reviews

```http
GET /wpss/v1/services/{id}/reviews
```

## Orders

### List Orders

```http
GET /wpss/v1/orders
```

Requires authentication. Returns orders for current user (as buyer or vendor).

**Parameters:**

| Name | Type | Description |
|------|------|-------------|
| `status` | string | Order status filter |
| `role` | string | `buyer` or `vendor` |

### Get Order

```http
GET /wpss/v1/orders/{id}
```

### Create Order

```http
POST /wpss/v1/orders
```

**Body:**

```json
{
    "service_id": 123,
    "package_id": 1,
    "addons": [0, 2],
    "custom_requirements": "Additional notes..."
}
```

### Update Order

```http
PUT /wpss/v1/orders/{id}
```

### Submit Requirements

```http
POST /wpss/v1/orders/{id}/requirements
```

**Body:**

```json
{
    "answers": {
        "field_1": "Answer to question 1",
        "field_2": "Answer to question 2"
    },
    "files": [
        {
            "name": "brief.pdf",
            "url": "https://..."
        }
    ]
}
```

### Submit Delivery

```http
POST /wpss/v1/orders/{id}/deliver
```

Vendor only.

**Body:**

```json
{
    "message": "Here's your completed work!",
    "files": [
        {
            "name": "final-logo.zip",
            "url": "https://..."
        }
    ]
}
```

### Accept Delivery

```http
POST /wpss/v1/orders/{id}/accept
```

Buyer only.

### Request Revision

```http
POST /wpss/v1/orders/{id}/revision
```

Buyer only.

**Body:**

```json
{
    "reason": "Please adjust the colors..."
}
```

### Cancel Order

```http
POST /wpss/v1/orders/{id}/cancel
```

**Body:**

```json
{
    "reason": "Cancellation reason..."
}
```

## Vendors

### List Vendors

```http
GET /wpss/v1/vendors
```

**Parameters:**

| Name | Type | Description |
|------|------|-------------|
| `orderby` | string | `rating`, `orders`, `date` |
| `category` | integer | Category specialization |
| `min_rating` | number | Minimum rating |

### Get Vendor

```http
GET /wpss/v1/vendors/{id}
```

### Get Vendor Services

```http
GET /wpss/v1/vendors/{id}/services
```

### Get Vendor Reviews

```http
GET /wpss/v1/vendors/{id}/reviews
```

### Register as Vendor

```http
POST /wpss/v1/vendors
```

**Body:**

```json
{
    "display_name": "Pro Designer",
    "bio": "Professional designer with 10 years experience...",
    "skills": ["logo-design", "branding", "illustration"]
}
```

### Update Vendor Profile

```http
PUT /wpss/v1/vendors/{id}
```

## Reviews

### List Reviews

```http
GET /wpss/v1/reviews
```

**Parameters:**

| Name | Type | Description |
|------|------|-------------|
| `service_id` | integer | Filter by service |
| `vendor_id` | integer | Filter by vendor |
| `rating` | integer | Filter by rating |

### Get Review

```http
GET /wpss/v1/reviews/{id}
```

### Create Review

```http
POST /wpss/v1/reviews
```

Buyer only, after order completion.

**Body:**

```json
{
    "order_id": 456,
    "rating": 5,
    "rating_communication": 5,
    "rating_quality": 5,
    "rating_value": 5,
    "content": "Excellent work! Highly recommended."
}
```

### Add Vendor Response

```http
POST /wpss/v1/reviews/{id}/response
```

Vendor only.

**Body:**

```json
{
    "response": "Thank you for your kind words!"
}
```

## Conversations

### List Conversations

```http
GET /wpss/v1/conversations
```

Returns user's conversations.

### Get Conversation

```http
GET /wpss/v1/conversations/{id}
```

### Get Messages

```http
GET /wpss/v1/conversations/{id}/messages
```

### Send Message

```http
POST /wpss/v1/conversations/{id}/messages
```

**Body:**

```json
{
    "message": "Your message content...",
    "attachments": []
}
```

## Buyer Requests

### List Requests

```http
GET /wpss/v1/buyer-requests
```

**Parameters:**

| Name | Type | Description |
|------|------|-------------|
| `status` | string | `open`, `in_progress`, `completed`, `cancelled` |
| `category` | integer | Category ID |

### Get Request

```http
GET /wpss/v1/buyer-requests/{id}
```

### Create Request

```http
POST /wpss/v1/buyer-requests
```

**Body:**

```json
{
    "title": "Need a WordPress developer",
    "description": "Looking for someone to customize my theme...",
    "category": 5,
    "budget_min": 100,
    "budget_max": 500,
    "deadline": "2024-02-15"
}
```

### Update Request

```http
PUT /wpss/v1/buyer-requests/{id}
```

### Delete Request

```http
DELETE /wpss/v1/buyer-requests/{id}
```

## Proposals

### List Proposals

```http
GET /wpss/v1/proposals
```

**Parameters:**

| Name | Type | Description |
|------|------|-------------|
| `request_id` | integer | Filter by request |
| `status` | string | Proposal status |

### Get Proposal

```http
GET /wpss/v1/proposals/{id}
```

### Submit Proposal

```http
POST /wpss/v1/proposals
```

Vendor only.

**Body:**

```json
{
    "request_id": 789,
    "price": 300.00,
    "delivery_days": 7,
    "message": "I'd love to work on this project..."
}
```

### Accept Proposal

```http
POST /wpss/v1/proposals/{id}/accept
```

Buyer only.

### Reject Proposal

```http
POST /wpss/v1/proposals/{id}/reject
```

Buyer only.

### Withdraw Proposal

```http
POST /wpss/v1/proposals/{id}/withdraw
```

Vendor only.

## Disputes

### List Disputes

```http
GET /wpss/v1/disputes
```

### Get Dispute

```http
GET /wpss/v1/disputes/{id}
```

### Open Dispute

```http
POST /wpss/v1/disputes
```

**Body:**

```json
{
    "order_id": 456,
    "reason": "work_not_delivered",
    "description": "Detailed description of the issue..."
}
```

### Add Evidence

```http
POST /wpss/v1/disputes/{id}/evidence
```

**Body:**

```json
{
    "description": "Additional evidence...",
    "files": []
}
```

### Respond to Dispute

```http
POST /wpss/v1/disputes/{id}/respond
```

## Webhooks

### Webhook Endpoints

Configure webhooks for payment gateways:

| Gateway | Endpoint |
|---------|----------|
| Stripe | `/wp-json/wpss/v1/webhooks/stripe` |
| PayPal | `/wp-json/wpss/v1/webhooks/paypal` |
| Razorpay | `/wp-json/wpss/v1/webhooks/razorpay` |

## Error Codes

| Code | Description |
|------|-------------|
| `rest_forbidden` | Authentication required |
| `rest_not_found` | Resource not found |
| `rest_invalid_param` | Invalid parameter |
| `wpss_not_vendor` | User is not a vendor |
| `wpss_not_order_owner` | Not authorized for this order |
| `wpss_invalid_status` | Invalid status transition |
| `wpss_rate_limited` | Too many requests |

## Rate Limiting

API requests are rate limited:

| Endpoint Type | Limit |
|---------------|-------|
| Public | 60 requests/minute |
| Authenticated | 120 requests/minute |

Rate limit headers:
- `X-RateLimit-Limit` - Max requests
- `X-RateLimit-Remaining` - Remaining requests
- `X-RateLimit-Reset` - Reset timestamp

## CORS

The API supports CORS for frontend apps. Configure allowed origins via filter:

```php
add_filter( 'wpss_api_cors_origins', function( $origins ) {
    $origins[] = 'https://myapp.com';
    return $origins;
});
```

## Examples

### JavaScript (Fetch)

```javascript
async function getServices() {
    const response = await fetch('/wp-json/wpss/v1/services', {
        headers: {
            'Content-Type': 'application/json'
        }
    });
    return response.json();
}

async function createOrder(serviceId, packageId) {
    const response = await fetch('/wp-json/wpss/v1/orders', {
        method: 'POST',
        credentials: 'include',
        headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': wpssData.nonce
        },
        body: JSON.stringify({
            service_id: serviceId,
            package_id: packageId
        })
    });
    return response.json();
}
```

### PHP (wp_remote_*)

```php
$response = wp_remote_get(
    rest_url( 'wpss/v1/services' ),
    array(
        'headers' => array(
            'Content-Type' => 'application/json',
        ),
    )
);

$services = json_decode( wp_remote_retrieve_body( $response ), true );
```

### cURL

```bash
# Get services
curl "https://yoursite.com/wp-json/wpss/v1/services"

# Create order (authenticated)
curl -X POST "https://yoursite.com/wp-json/wpss/v1/orders" \
  -u "username:app-password" \
  -H "Content-Type: application/json" \
  -d '{"service_id": 123, "package_id": 0}'
```
