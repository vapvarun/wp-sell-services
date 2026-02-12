# Dispute Process

Understand the complete dispute lifecycle, from initial submission through resolution and closure. Learn what to expect at each stage and how to navigate the process effectively.

## Dispute Lifecycle Overview

Disputes move through defined stages:

```
open → pending_review → (escalated) → resolved → closed
```

Each stage has specific actions and timeframes.

## Dispute Statuses

WP Sell Services uses 5 dispute statuses:

### 1. Open

**What It Means:**
- Dispute just submitted by buyer or vendor
- Initial notification sent to other party
- Waiting for other party's response
- Evidence can still be added

**Who Can View:**
- Buyer (order customer)
- Vendor (order vendor)
- Site administrators

**Duration:** 24-48 hours typical

**Your Actions:**
- Monitor for vendor/buyer response
- Add any additional evidence
- Respond to initial claims

### 2. Pending Review (pending_review)

**What It Means:**
- Both parties have submitted their cases
- Initial evidence collected
- Waiting for admin to begin investigation
- Queued for admin review

**Duration:** 1-3 business days

**Your Actions:**
- Ensure all evidence is uploaded
- No new evidence accepted after this stage
- Wait for admin review to begin
- Check notifications for admin questions

### 3. Escalated

**What It Means:**
- Dispute requires higher-level admin review
- Complex case needs additional investigation
- Standard resolution unclear
- Special circumstances present

**When This Happens:**
- Admin manually escalates complex cases
- High-value orders
- Repeat dispute parties
- Policy interpretation needed

**Duration:** 3-7 business days

**Your Actions:**
- Respond promptly to admin requests
- Provide additional context if asked
- Be patient as thorough review takes time

### 4. Resolved

**What It Means:**
- Final decision made by admin
- Resolution being implemented
- Refunds processed if applicable
- Order status updated accordingly

**Resolution Types:**

| Type | Description |
|------|-------------|
| `full_refund` | Complete refund to buyer, vendor receives nothing |
| `partial_refund` | Split payment between buyer and vendor |
| `favor_vendor` | Vendor receives full payment, no refund |
| `favor_buyer` | Similar to full refund |
| `mutual_agreement` | Both parties agreed to custom solution |

**Duration:** 1-3 days for implementation

**Your Actions:**
- Wait for refund processing
- Confirm receipt of resolution
- Can leave review after closure

### 5. Closed

**What It Means:**
- Dispute completely finalized
- Resolution implemented
- No further action possible
- Order marked appropriately

**Cannot Be Reopened:** Closed disputes are final

**Your Actions:**
- Leave review if you haven't
- Learn from the experience
- Move forward

## Evidence Storage

Evidence is stored as JSON in the `evidence` column of the `wpss_disputes` table.

### Evidence Types

Supported evidence types:

**text:** Written explanations and descriptions
**image:** Attachment ID of uploaded image
**file:** Attachment ID of uploaded document
**link:** URL to external resource

### Evidence Structure

Each evidence item includes:

```json
{
  "id": "ev_uniqueid",
  "user_id": 123,
  "type": "image",
  "content": "456",
  "description": "Screenshot showing the issue",
  "created_at": "2026-02-12 10:30:00"
}
```

### Status Notes

Status change notes are also stored in evidence:

```json
{
  "id": "note_uniqueid",
  "type": "status_note",
  "note": "Admin note explaining status change",
  "status": "pending_review",
  "created_at": "2026-02-12 11:00:00"
}
```

## Resolution Process

### Admin Review Steps

1. **Assessment:** Admin reviews dispute details and evidence
2. **Investigation:** Admin examines order history and communications
3. **Decision:** Admin selects appropriate resolution type
4. **Implementation:** System processes refunds/payments
5. **Notification:** Both parties notified of outcome

### Resolution Data

When dispute is resolved, these fields are set:

- `status` → 'resolved'
- `resolution` → Resolution type (e.g., 'partial_refund')
- `resolution_notes` → Admin explanation
- `resolved_by` → Admin user ID
- `resolved_at` → Timestamp

### Refund Information

If refund is involved, refund amount is stored in evidence:

```json
{
  "id": "refund_uniqueid",
  "type": "refund_info",
  "refund_amount": 75.00,
  "created_at": "2026-02-12 14:00:00"
}
```

## Order Status Updates

Dispute resolution automatically updates order status:

**Full Refund or Favor Buyer:**
- Order status → 'refunded'

**Partial Refund:**
- Order status → 'partially_refunded'

**Favor Vendor:**
- Order status → 'completed'

**Mutual Agreement:**
- Order status → 'completed'

## Timeline and Response Requirements

### Party Response Times

| Stage | Response Required | Deadline |
|-------|------------------|----------|
| Initial Claim | Other party response | 48 hours |
| Evidence Submission | Additional evidence | Before pending_review |
| Admin Questions | Answer clarifications | 48 hours |
| Resolution Proposed | Accept or appeal | 7 days |

### Admin Response Times

- Initial review: 1-3 business days
- Investigation: 3-7 business days
- Resolution implementation: 1-3 days

**Note:** Times may vary based on dispute complexity and admin workload.

## What You Can Do at Each Stage

### As Buyer or Vendor

**During Open:**
- ✓ Add evidence (text, images, files, links)
- ✓ View all submitted evidence
- ✓ Communicate with other party
- ✓ Wait for admin review

**During Pending Review:**
- ✓ View evidence (no new submissions)
- ✓ Wait for admin
- ✗ Cannot add evidence
- ✗ Cannot change claims

**During Investigation:**
- ✓ Respond to admin questions
- ✓ Provide additional context if requested
- ✗ Cannot modify evidence
- ✗ Cannot close dispute

**After Resolution:**
- ✓ View resolution details
- ✓ Confirm receipt
- ✓ Leave review
- ✗ Cannot reopen

### As Administrator

Admins can at any stage:
- Update dispute status
- Add evidence
- Add status notes
- Resolve dispute
- Implement refunds

## Database Schema Reference

Disputes are stored in the `wpss_disputes` table with these key fields:

| Column | Type | Purpose |
|--------|------|---------|
| `id` | int | Dispute ID |
| `order_id` | int | Associated order |
| `initiated_by` | int | User who opened dispute |
| `reason` | varchar | Short reason category |
| `description` | text | Detailed explanation |
| `status` | varchar | Current status (5 options) |
| `evidence` | longtext | JSON array of evidence |
| `resolution` | varchar | Resolution type when resolved |
| `resolution_notes` | text | Admin explanation |
| `resolved_by` | int | Admin user ID |
| `resolved_at` | datetime | Resolution timestamp |
| `created_at` | datetime | Dispute open time |
| `updated_at` | datetime | Last update time |

## Important Notes

**No Time Windows:** The system does not automatically enforce dispute filing windows. Buyers and vendors can open disputes at any time after order creation.

**One Dispute Per Order:** Each order can have only one dispute. Attempting to open a second dispute returns false.

**Both Parties Can Open:** Either buyer or vendor can initiate a dispute. The `initiated_by` field tracks who opened it.

**Evidence Cannot Be Deleted:** Once evidence is added, it becomes part of the permanent dispute record.

**No Auto-Resolution:** Disputes never auto-resolve. Admin action is always required for resolution.

## WordPress Hooks

### Actions

**wpss_dispute_opened** - Fires when dispute is created
**wpss_dispute_evidence_added** - Fires when evidence is submitted
**wpss_dispute_status_changed** - Fires on status updates
**wpss_dispute_resolved** - Fires when dispute is resolved

### Filters

Use these hooks to extend dispute functionality or add notifications.

## Related Documentation

- [Opening a Dispute](opening-a-dispute.md) - How to file a dispute
- [Admin Dispute Mediation](admin-dispute-mediation.md) - Admin dispute management
- [Order Lifecycle](../order-management/order-lifecycle.md) - Order status reference
- [Refund Process](../payments-checkout/refund-policy.md) - How refunds work
