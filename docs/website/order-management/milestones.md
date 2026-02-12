# Project Milestones **[PRO]**

Break large orders into smaller deliverable phases with individual approval steps and payments. Milestones are stored as post meta and managed through the MilestoneService class.

**Note:** This is a **[PRO]** feature available in WP Sell Services Pro.

## What are Milestones?

Milestones divide big projects into manageable chunks. Each milestone has:
- Title and description
- Deliverables list
- Payment amount (portion of total)
- Due date
- Individual approval workflow

**Storage:** Milestones are stored as post meta on the service post, not in a dedicated database table.

**Meta Key Format:** `wpss_milestones_{order_id}`

**Example: Website Design Project**
```
Total Order: $2,000 (30 days)

Milestone 1: Wireframes & Mockups
- Due: Day 7
- Payment: $500 (25%)
- Status: pending

Milestone 2: Homepage Development
- Due: Day 14
- Payment: $600 (30%)

Milestone 3: Inner Pages
- Due: Day 21
- Payment: $600 (30%)

Milestone 4: Final Launch
- Due: Day 30
- Payment: $300 (15%)
```

## Milestone Statuses

Milestones have 5 possible statuses:

| Status | Constant | What It Means | Who Acts Next |
|--------|----------|---------------|---------------|
| Pending | `STATUS_PENDING` | Created, not started | Vendor (start work) |
| In Progress | `STATUS_IN_PROGRESS` | Vendor actively working | Vendor (submit deliverable) |
| Submitted | `STATUS_SUBMITTED` | Deliverable uploaded | Buyer (approve/reject) |
| Approved | `STATUS_APPROVED` | Buyer accepted work | Next milestone or completion |
| Rejected | `STATUS_REJECTED` | Needs changes | Vendor (revise and resubmit) |

## Milestone ID Format

Each milestone gets unique ID: `ms_` + `uniqid()`

Example: `ms_65d3f4b2a1e3c`

## Creating Milestones (Vendors)

### Milestone Data Structure

```php
[
    'id'           => 'ms_' . uniqid(),
    'title'        => 'Homepage Design Mockup',
    'description'  => 'What will be delivered',
    'amount'       => 400.00,
    'due_date'     => '2026-02-20',
    'status'       => 'pending',
    'deliverables' => 'List of specific files/outputs',
    'created_at'   => '2026-02-12 10:30:00',
    'updated_at'   => '2026-02-12 10:30:00',
    'submitted_at' => null,
    'approved_at'  => null,
]
```

### Required Fields

When creating a milestone via `MilestoneService::create()`:

**Required:**
- `title` - Cannot be empty
- `amount` - Must be > 0
- Order must exist

**Optional:**
- `description` - Defaults to empty string
- `due_date` - Defaults to null
- `deliverables` - Defaults to empty string

### Creating Via API

```php
$milestone_service = new MilestoneService();

$result = $milestone_service->create( $order_id, [
    'title'        => 'Homepage Design',
    'description'  => '3 design concepts in PSD format',
    'amount'       => 500.00,
    'due_date'     => '2026-02-20',
    'deliverables' => 'homepage-v1.psd, homepage-v2.psd, homepage-v3.psd',
] );

if ( $result['success'] ) {
    $milestone_id = $result['milestone_id'];
}
```

## Milestone Workflow

### 1. Vendor Creates Milestone

```php
MilestoneService::create( $order_id, $data );
```

**Fires:** `wpss_milestone_created` action

### 2. Vendor Starts Work

Change status to `in_progress`:

```php
// Update milestone status
$milestone_service->update( $order_id, $milestone_id, [
    'status' => 'in_progress'
] );
```

**Note:** Status updates should be done through proper workflow methods, not direct updates.

### 3. Vendor Submits Milestone

```php
$result = $milestone_service->submit(
    $order_id,
    $milestone_id,
    'Milestone completed. Please review attached files.',
    $attachments // Array of file data
);
```

**Requirements:**
- Milestone status: `pending`, `in_progress`, or `rejected`
- Cannot submit `submitted` or `approved` milestones

**What Happens:**
- Status → `submitted`
- `submitted_at` timestamp set
- `submit_message` stored
- Attachments array saved

**Fires:** `wpss_milestone_submitted` action

### 4. Buyer Approves

```php
$result = $milestone_service->approve( $order_id, $milestone_id );
```

**Requirements:**
- Status must be `submitted`

**What Happens:**
- Status → `approved`
- `approved_at` timestamp set
- Payment released to vendor

**Fires:** `wpss_milestone_approved` action with milestone amount

### 5. Buyer Rejects (if needed)

```php
$result = $milestone_service->reject(
    $order_id,
    $milestone_id,
    'Please change header color to navy blue'
);
```

**Requirements:**
- Status must be `submitted`

**What Happens:**
- Status → `rejected`
- `rejection_feedback` stored
- Vendor can revise and resubmit

**Fires:** `wpss_milestone_rejected` action

## Retrieving Milestones

### Get All Milestones for Order

```php
$milestones = $milestone_service->get_order_milestones( $order_id );
```

**Returns:** Array of milestone arrays

**Storage Lookup:**
1. Gets order from database
2. Retrieves service_id from order
3. Gets post meta: `wpss_milestones_{order_id}` from service post
4. Returns array or empty array if none

### Get Single Milestone

```php
$milestone = $milestone_service->get( $order_id, $milestone_id );
```

**Returns:** Milestone array or `null` if not found

### Get Milestone Progress

```php
$progress = $milestone_service->get_progress( $order_id );
```

**Returns:**
```php
[
    'total_milestones'    => 4,
    'approved_milestones' => 2,
    'pending_milestones'  => 2,
    'total_amount'        => 2000.00,
    'released_amount'     => 1100.00,
    'pending_amount'      => 900.00,
    'completion_percent'  => 50.0,
]
```

## Updating Milestones

### Editable Fields

```php
$result = $milestone_service->update( $order_id, $milestone_id, [
    'title'        => 'New title',
    'description'  => 'Updated description',
    'amount'       => 450.00,
    'due_date'     => '2026-02-25',
    'deliverables' => 'Updated list',
] );
```

**Allowed Fields:**
- `title` - Sanitized with `sanitize_text_field()`
- `description` - Sanitized with `sanitize_textarea_field()`
- `amount` - Cast to float
- `due_date` - Sanitized with `sanitize_text_field()`
- `deliverables` - Sanitized with `sanitize_textarea_field()`

**Updated Automatically:**
- `updated_at` - Current timestamp

**Cannot Update:**
- `id` - Generated at creation
- `status` - Use workflow methods (submit, approve, reject)
- `created_at` - Set at creation
- `submitted_at`, `approved_at` - Set by workflow

## Deleting Milestones

```php
$result = $milestone_service->delete( $order_id, $milestone_id );
```

**Requirements:**
- Milestone status must be `pending`
- Cannot delete in-progress, submitted, or approved milestones

**What Happens:**
- Milestone removed from array
- Updated array saved to post meta
- No database table cleanup needed

## Payment Release

### When Milestone Approved

Payment is released immediately when buyer approves milestone.

**Hook Usage:**
```php
add_action( 'wpss_milestone_approved', function( $milestone_id, $order_id, $amount ) {
    // Payment logic executed here
    // $amount = milestone payment amount
}, 10, 3 );
```

### Commission Handling

Commission is typically deducted from each milestone payment, but this depends on platform configuration.

## Milestone Limitations

### What Can Be Revised

**Milestone rejections are independent of order revisions:**
- Rejected milestones don't count against order revision limit
- Each milestone can be submitted multiple times
- Status cycles: `rejected` → `in_progress` → `submitted` → `approved`

### Milestone Dependencies

Milestones are independent - you can work on multiple simultaneously or in any order. The system doesn't enforce sequential completion.

## Status Labels

Get human-readable labels:

```php
$labels = MilestoneService::get_status_labels();

// Returns:
[
    'pending'     => 'Pending',
    'in_progress' => 'In Progress',
    'submitted'   => 'Awaiting Approval',
    'approved'    => 'Approved',
    'rejected'    => 'Needs Revision',
]
```

## Action Hooks

```php
// Milestone created
do_action( 'wpss_milestone_created', $milestone_id, $order_id, $milestone );

// Milestone submitted for review
do_action( 'wpss_milestone_submitted', $milestone_id, $order_id );

// Milestone approved by buyer
do_action( 'wpss_milestone_approved', $milestone_id, $order_id, $amount );

// Milestone rejected by buyer
do_action( 'wpss_milestone_rejected', $milestone_id, $order_id, $feedback );
```

## REST API Endpoints

Milestones are managed through the MilestonesController:

**Base:** `/wp-json/wpss/v1/milestones`

**Endpoints:**
- `GET /orders/{order_id}/milestones` - List all milestones
- `POST /orders/{order_id}/milestones` - Create milestone
- `GET /milestones/{milestone_id}` - Get single milestone
- `PUT /milestones/{milestone_id}` - Update milestone
- `DELETE /milestones/{milestone_id}` - Delete milestone
- `POST /milestones/{milestone_id}/submit` - Submit for approval
- `POST /milestones/{milestone_id}/approve` - Approve milestone
- `POST /milestones/{milestone_id}/reject` - Reject milestone

## Best Practices

### For Vendors

✅ **Planning:**
- Create milestones before starting work
- Make each milestone independently valuable
- Front-load important milestones (30-35% first)
- Use 3-5 milestones for most projects

✅ **Submission:**
- Submit when fully complete
- Include all deliverables
- Write clear submission message
- Test files before submitting

### For Buyers

✅ **Reviewing:**
- Review within 2-3 days
- Check all deliverables
- Provide specific feedback if rejecting
- Approve promptly if satisfied

✅ **Feedback:**
- Be specific about changes needed
- Reference milestone requirements
- Don't request out-of-scope changes

### For Admins

✅ **Configuration:**
- Set minimum order value for milestones
- Monitor milestone approval rates
- Step in when milestones are stuck
- Educate users on milestone benefits

## Troubleshooting

### Milestone Not Saving

**Check:**
- Order exists in database
- Service ID valid on order
- Post meta permissions
- Field validation (title required, amount > 0)

### Cannot Submit Milestone

**Verify:**
- Status is `pending`, `in_progress`, or `rejected`
- Not already `submitted` or `approved`
- User has permission

### Payment Not Released

**Check:**
- Milestone status is `approved`
- `wpss_milestone_approved` hook firing
- Payment service integration active
- Vendor wallet accepting payments

## Related Documentation

- [Order Workflow](order-lifecycle.md) - Complete order lifecycle
- [Deliveries & Revisions](deliveries-revisions.md) - Standard delivery workflow
- [Earnings Dashboard](../earnings-wallet/earnings-dashboard.md) - Track milestone payments
- [REST API Reference](../developer-guide/rest-api-endpoints.md) - Milestone endpoints

Milestones make large projects manageable and fair for everyone!
