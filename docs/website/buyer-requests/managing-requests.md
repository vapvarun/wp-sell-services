# Managing Requests & Converting to Orders

After posting a buyer request, you'll receive vendor proposals. This guide covers evaluating proposals, communicating with vendors, accepting offers, and converting proposals into orders.

## Request Statuses

WP Sell Services uses 5 request statuses:

| Status | Description |
|--------|-------------|
| `open` | Active, accepting proposals |
| `in_review` | Evaluating proposals, may still accept new ones |
| `hired` | Accepted a proposal, order created |
| `expired` | Past expiration date, no longer active |
| `cancelled` | Buyer cancelled the request |

## My Requests Dashboard

Your central hub for managing all buyer requests.

### Accessing the Dashboard

1. Log in to your account
2. Navigate to **Dashboard → My Requests**
3. View all requests and their status

### Request Card Information

Each request displays:

- **Title** - Project name
- **Status** - Current stage
- **Posted Date** - When published
- **Proposal Count** - Number received (from `get_proposal_count()`)
- **Budget** - Your specified amount or range
- **Delivery Days** - Target completion timeframe
- **Expiration** - When request expires
- **Category** - Service category assigned

## Request Metadata

Requests are WordPress custom posts (`wpss_request`) with meta fields:

| Meta Key | Type | Description |
|----------|------|-------------|
| `_wpss_status` | string | Request status |
| `_wpss_budget_type` | string | 'fixed' or 'range' |
| `_wpss_budget_min` | float | Minimum budget |
| `_wpss_budget_max` | float | Maximum budget (range only) |
| `_wpss_delivery_days` | int | Expected delivery timeframe |
| `_wpss_attachments` | array | Attachment IDs |
| `_wpss_skills_required` | array | Required skill tags |
| `_wpss_expires_at` | datetime | Expiration timestamp |
| `_wpss_hired_vendor_id` | int | Vendor ID (when hired) |
| `_wpss_accepted_proposal_id` | int | Proposal ID (when hired) |

## Managing Request Status

### Updating Status

Only certain status transitions are allowed:

```php
// Valid statuses
BuyerRequestService::STATUS_OPEN
BuyerRequestService::STATUS_IN_REVIEW
BuyerRequestService::STATUS_HIRED
BuyerRequestService::STATUS_EXPIRED
BuyerRequestService::STATUS_CANCELLED
```

### Marking as Hired

When you accept a proposal:

```php
$buyer_request_service->mark_hired(
    $request_id,
    $vendor_id,
    $proposal_id
);
```

This automatically:
- Updates status to 'hired'
- Stores vendor ID
- Stores accepted proposal ID

## Viewing Proposals

### Proposal Count

Get total proposals submitted:

```php
$count = $buyer_request_service->get_proposal_count( $request_id );
```

Queries the `wpss_proposals` table.

### Proposal Service

Proposals are managed by `ProposalService`, not `BuyerRequestService`.

**To get proposals:**
```php
$proposal_service = new ProposalService();
$proposals = $proposal_service->get_by_request( $request_id );
```

## Converting to Order

### Acceptance Process

When you accept a proposal, the system creates a service order.

**Method:** `convert_to_order()`

```php
$result = $buyer_request_service->convert_to_order(
    $request_id,
    $proposal_id
);
```

### What Happens Automatically

1. **Validation:**
   - Request exists and is open/in_review
   - Proposal exists and belongs to request
   - Proposal status is pending

2. **Order Creation:**
   - Generates order number (WPSS-XXXXXXXX)
   - Calculates delivery deadline from `proposed_days` or `delivery_days`
   - Creates order in `wpss_orders` table with:
     - `platform` = 'request'
     - `platform_order_id` = request ID
     - `status` = 'pending_payment'
     - `payment_status` = 'pending'

3. **Proposal Updates:**
   - Accepted proposal → status 'accepted'
   - Other proposals → status 'rejected'

4. **Request Updates:**
   - Request status → 'hired'
   - Stores vendor ID and proposal ID

5. **Order Requirements:**
   - Creates requirement record with:
     - Request title and description
     - Proposal cover letter
     - Request attachments

6. **Conversation:**
   - Creates conversation for the order

7. **Notification:**
   - Vendor notified via `NotificationService`

### Return Value

Success:
```php
[
    'success' => true,
    'message' => 'Order created successfully. Proceed to payment.',
    'order_id' => 123,
    'order_number' => 'WPSS-ABC12345',
]
```

With warnings:
```php
[
    'success' => true,
    'order_id' => 123,
    'order_number' => 'WPSS-ABC12345',
    'warnings' => [
        'Order requirements could not be saved.'
    ]
]
```

Failure:
```php
[
    'success' => false,
    'message' => 'Error description'
]
```

## Request Queries

### Get Open Requests

```php
$requests = $buyer_request_service->get_open( [
    'posts_per_page' => 20,
    'paged' => 1,
    'category_id' => 0,
    'budget_min' => 0,
    'budget_max' => 0,
    'order_by' => 'date',
    'order' => 'DESC',
] );
```

Returns requests with:
- Status = 'open'
- Not expired (expires_at > now OR no expiration)

### Get User's Requests

```php
$requests = $buyer_request_service->get_by_buyer( $user_id, [
    'posts_per_page' => 20,
    'paged' => 1,
    'status' => '',  // Optional status filter
] );
```

Returns all requests where `post_author` = user ID.

### Search Requests

```php
$requests = $buyer_request_service->search( 'WordPress plugin', [
    'posts_per_page' => 20,
    'paged' => 1,
    'status' => 'open',
] );
```

Searches post title and content.

## Request Expiration

### Default Expiration

When creating a request without expiration:

```php
$default_days = get_option( 'wpss_request_expiry_days', 30 );
$expires_at = current_time('mysql') + $default_days days;
```

Default: 30 days from posting.

### Expiring Old Requests

Background job runs `expire_old_requests()`:

```php
$expired_count = $buyer_request_service->expire_old_requests();
```

Finds requests with:
- Status = 'open'
- `expires_at` < current time

Updates status to 'expired'.

### Extending Expiration

**Not implemented in base service.** You must manually update:

```php
$new_expiry = gmdate( 'Y-m-d H:i:s', strtotime( '+14 days' ) );
update_post_meta( $request_id, '_wpss_expires_at', $new_expiry );
```

## Budget Types

Two budget types supported:

### Fixed Budget

Single price amount:

```php
[
    'budget_type' => BuyerRequestService::BUDGET_FIXED,
    'budget_min' => 500.00,
    'budget_max' => 0, // Not used
]
```

### Range Budget

Flexible price range:

```php
[
    'budget_type' => BuyerRequestService::BUDGET_RANGE,
    'budget_min' => 400.00,
    'budget_max' => 600.00,
]
```

## Filtering Requests

### By Category

Requests are assigned to service categories using WordPress taxonomy:

```php
wp_set_object_terms(
    $request_id,
    [ $category_id ],
    'wpss_service_category'
);
```

Filter by category in queries:

```php
$requests = $buyer_request_service->get_open( [
    'category_id' => 5,
] );
```

### By Budget Range

Filter requests within budget range:

```php
$requests = $buyer_request_service->get_open( [
    'budget_min' => 500,  // Requests with budget_min >= 500
    'budget_max' => 1000, // Requests with budget_max <= 1000
] );
```

## Request Attachments

### Storing Attachments

Attachments are WordPress media library attachments:

```php
[
    'attachments' => [ 123, 456, 789 ], // Attachment IDs
]
```

Stored in `_wpss_attachments` meta as serialized array.

### Accessing Attachments

```php
$request = $buyer_request_service->get( $request_id );
$attachment_ids = $request->attachments; // Array of IDs

foreach ( $attachment_ids as $attachment_id ) {
    $url = wp_get_attachment_url( $attachment_id );
    $title = get_the_title( $attachment_id );
}
```

## Skills Required

Optional skill tags for requests:

```php
[
    'skills_required' => [
        'WordPress',
        'React',
        'PHP 8',
        'REST API',
    ]
]
```

Stored in `_wpss_skills_required` meta.

Vendors can filter by matching skills.

## Request Object Structure

When calling `get()`, you receive:

```php
{
    "id": 123,
    "title": "Build WordPress Plugin",
    "description": "Need custom plugin for...",
    "author_id": 456,
    "status": "open",
    "budget_type": "range",
    "budget_min": 500.00,
    "budget_max": 800.00,
    "delivery_days": 14,
    "attachments": [789, 790],
    "skills_required": ["WordPress", "PHP"],
    "expires_at": "2026-03-15 10:30:00",
    "created_at": "2026-02-12 10:30:00",
    "proposal_count": 7,
    "category": {
        "term_id": 5,
        "name": "Web Development",
        "slug": "web-development"
    }
}
```

## WordPress Hooks

### Action: wpss_buyer_request_created

Fires when request is posted:

```php
add_action( 'wpss_buyer_request_created', function( $post_id, $data ) {
    // Notify matching vendors
    // Log new request
}, 10, 2 );
```

### Action: wpss_buyer_request_updated

Fires when request is edited:

```php
add_action( 'wpss_buyer_request_updated', function( $request_id, $data ) {
    // Notify vendors of changes
}, 10, 2 );
```

### Action: wpss_buyer_request_status_changed

Fires on status updates:

```php
add_action( 'wpss_buyer_request_status_changed', function( $request_id, $status, $old_status ) {
    // Track status changes
}, 10, 3 );
```

### Action: wpss_request_converted_to_order

Fires when proposal accepted:

```php
add_action( 'wpss_request_converted_to_order', function( $order_id, $request_id, $proposal_id, $request, $proposal ) {
    // Handle order creation
    // Send notifications
    // Update stats
}, 10, 5 );
```

### Filter: wpss_proposal_order_revisions

Filter revision count for converted orders:

```php
add_filter( 'wpss_proposal_order_revisions', function( $revisions, $proposal, $request ) {
    // Default is 2
    // Increase based on order value
    if ( $proposal->proposed_price > 1000 ) {
        return 5;
    }
    return $revisions;
}, 10, 3 );
```

## Best Practices

### Request Description

Write clear, detailed descriptions:

**Good:**
```
I need a WordPress plugin that integrates with Stripe to process recurring subscriptions.

Requirements:
- Admin panel to manage subscriptions
- Customer portal for self-service
- Email notifications for renewal
- Support for annual and monthly plans
- Compatible with WordPress 6.0+

Deliverables:
- Fully functional plugin
- Source code
- Installation documentation
```

**Poor:**
```
Need Stripe integration ASAP.
```

### Budget Setting

Set realistic budgets:

**Research First:**
- Check similar services on marketplace
- Ask vendors for rough estimates
- Consider project complexity

**Budget Range Benefits:**
- Attracts more proposals
- Shows flexibility
- Vendors can justify pricing

### Proposal Evaluation

Compare proposals on:

1. **Vendor Experience** - Portfolio and reviews
2. **Proposal Quality** - Understanding of requirements
3. **Price** - Value for money, not just cheapest
4. **Timeline** - Realistic delivery estimate
5. **Communication** - Responsiveness and clarity

### After Acceptance

1. **Pay Promptly** - Don't delay payment
2. **Provide Requirements** - Give vendor everything needed
3. **Stay Available** - Answer questions quickly
4. **Be Reasonable** - Don't change scope mid-project

## Troubleshooting

### Request Not Visible

**Check:**
- Request status is 'open'
- Not expired (expires_at in future)
- Category assigned
- Post status is 'publish'

### Can't Accept Proposal

**Verify:**
- Request is 'open' or 'in_review'
- Proposal status is 'pending'
- Proposal belongs to this request
- You are the request author

### Order Creation Failed

**Common Issues:**
- Database insert failed (check permissions)
- Proposal already accepted
- Request already hired
- Invalid vendor or service ID

**Check:** Return value 'message' field for specific error.

## Related Documentation

- [Posting a Request](posting-request.md) - Creating buyer requests
- [Proposal System](../vendor-system/proposals.md) - How proposals work
- [Order Lifecycle](../order-management/order-lifecycle.md) - After order creation
- [Payments & Checkout](../payments-checkout/payment-methods.md) - Completing payment

---

**Key Points:**
- 5 request statuses, most common flow: open → in_review → hired
- Proposals live in separate `wpss_proposals` table
- Converting proposal to order is automatic and comprehensive
- Expiration handled by background cron job
- All metadata stored in post meta with `_wpss_` prefix
