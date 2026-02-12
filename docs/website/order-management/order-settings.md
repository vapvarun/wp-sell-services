# Order Settings

Configure order policies, delivery deadlines, revisions, requirements timeout, and dispute windows that govern transactions on your marketplace.

**Location:** WP Admin → WP Sell Services → Settings → Orders

## Order Settings Overview

All order settings are stored in WordPress option: `wpss_orders`

Access in Settings.php lines 695-801.

## Auto-Complete Days

Automatically complete orders after delivery if buyer takes no action.

### Configuration

**Field:** `auto_complete_days`

| Setting | Value |
|---------|-------|
| **Default** | 3 days |
| **Minimum** | 0 (disabled) |
| **Maximum** | 30 days |
| **Type** | Number field |

**Description:** "Days after delivery to auto-complete if buyer does not respond. 0 to disable."

### How It Works

**When Enabled (> 0):**
1. Vendor submits delivery
2. Order status → `pending_approval`
3. Buyer has X days to review
4. If no action taken, order auto-completes
5. Vendor receives payment
6. Both can leave reviews

**When Disabled (0):**
- Orders remain in `pending_approval` indefinitely
- Manual buyer action required
- No auto-completion occurs

**Example:**
```
auto_complete_days = 3

Day 0: Vendor delivers
Day 1-3: Buyer review window
Day 4: Auto-complete if no action
```

## Default Revision Limit

Set the default number of revisions included with orders.

### Configuration

**Field:** `revision_limit`

| Setting | Value |
|---------|-------|
| **Default** | 2 revisions |
| **Minimum** | 0 (no revisions) |
| **Maximum** | 10 revisions |
| **Type** | Number field |

**Description:** "Default revisions per order. Can be overridden per service."

### How It Works

**Checking Revisions:**
```php
// Code checks: $order->can_request_revision()
// Returns true if:
// - revisions_included = -1 (unlimited)
// - OR remaining_revisions > 0
```

**Per-Service Override:**
- Vendors can set custom revision counts per package
- Service-level settings override this default
- Example: Basic (1 revision), Standard (2), Premium (unlimited)

**Unlimited Revisions:**
- Set `revision_limit = -1` for unlimited
- Or configure per service package

## Dispute Settings

### Allow Disputes

**Field:** `allow_disputes`

| Setting | Value |
|---------|-------|
| **Default** | Enabled (true) |
| **Type** | Checkbox |
| **Label** | "Allow buyers to open disputes on orders" |

**When Disabled:**
- Buyers cannot open disputes
- Dispute button hidden
- Issues must be resolved via messaging or admin

### Dispute Window Days

**Field:** `dispute_window_days`

| Setting | Value |
|---------|-------|
| **Default** | 14 days |
| **Minimum** | 1 day |
| **Maximum** | 90 days |
| **Type** | Number field |

**Description:** "Days after completion within which disputes can be opened."

**How It Works:**
- Countdown starts when order completes
- Buyer can open dispute within X days
- After window expires, disputes not allowed
- Protects vendors from very old disputes

## Requirements Timeout

Control what happens when buyers don't submit requirements.

### Late Requirements Submission

**Field:** `allow_late_requirements`

| Setting | Value |
|---------|-------|
| **Default** | Disabled (false) |
| **Type** | Checkbox |
| **Label** | "Allow buyers to submit requirements after work has started" |

**Description:** "If enabled, buyers can submit requirements even if the order is already in progress without them."

**When Enabled:**
- Work can start without requirements
- Buyer can add requirements later
- Vendor notified when requirements submitted

**When Disabled:**
- Order stuck in `pending_requirements` until submitted
- Vendor cannot start without requirements

### Requirements Timeout Days

**Field:** `requirements_timeout_days`

| Setting | Value |
|---------|-------|
| **Default** | 0 (disabled) |
| **Minimum** | 0 |
| **Maximum** | 30 days |
| **Type** | Number field |

**Description:** "Days to wait for requirements before taking action. 0 to disable."

**How It Works:**

**When Enabled (> 0):**
1. Order placed
2. Buyer has X days to submit requirements
3. Reminders sent
4. After timeout, automatic action taken (see below)

**When Disabled (0):**
- No timeout enforcement
- Order waits indefinitely for requirements

### Auto-Start on Timeout

**Field:** `auto_start_on_timeout`

| Setting | Value |
|---------|-------|
| **Default** | Enabled (true) |
| **Type** | Checkbox |
| **Label** | "Auto-start order when requirements timeout is reached" |

**Description:** "If enabled, the order starts without requirements. If disabled, the order is cancelled instead."

**When Enabled:**
- Timeout reached → Order status `in_progress`
- Vendor starts work without requirements
- Buyer can still submit requirements if `allow_late_requirements` enabled

**When Disabled:**
- Timeout reached → Order cancelled
- Buyer refunded
- Order doesn't count against vendor metrics

## Complete Settings Table

| Setting | Field Name | Default | Min | Max | Type | Description |
|---------|-----------|---------|-----|-----|------|-------------|
| Auto-Complete Days | `auto_complete_days` | 3 | 0 | 30 | Number | Days after delivery to auto-complete |
| Default Revision Limit | `revision_limit` | 2 | 0 | 10 | Number | Default revisions per order |
| Allow Disputes | `allow_disputes` | true | - | - | Checkbox | Enable dispute system |
| Dispute Window | `dispute_window_days` | 14 | 1 | 90 | Number | Days after completion to open dispute |
| Late Requirements | `allow_late_requirements` | false | - | - | Checkbox | Submit requirements after start |
| Requirements Timeout | `requirements_timeout_days` | 0 | 0 | 30 | Number | Days to wait for requirements |
| Auto-Start on Timeout | `auto_start_on_timeout` | true | - | - | Checkbox | Start order or cancel on timeout |

## Accessing Settings

### In PHP Code

```php
// Get all order settings
$order_settings = get_option( 'wpss_orders', [] );

// Get specific setting with default fallback
$auto_complete_days = $order_settings['auto_complete_days'] ?? 3;
$revision_limit = $order_settings['revision_limit'] ?? 2;
$dispute_window = $order_settings['dispute_window_days'] ?? 14;
$timeout_days = $order_settings['requirements_timeout_days'] ?? 0;
$auto_start = $order_settings['auto_start_on_timeout'] ?? true;
```

### Via REST API

Settings are exposed through public settings endpoint for logged-in users.

## Configuration Examples

### Conservative Marketplace

```
Auto-Complete Days: 7
Revision Limit: 1
Allow Disputes: Yes
Dispute Window: 30 days
Requirements Timeout: 3 days
Auto-Start on Timeout: No (cancel instead)
```

**Effect:** Longer review periods, fewer revisions, longer dispute window, strict requirements.

### Fast-Paced Marketplace

```
Auto-Complete Days: 1
Revision Limit: 5
Allow Disputes: Yes
Dispute Window: 7 days
Requirements Timeout: 1 day
Auto-Start on Timeout: Yes
```

**Effect:** Quick turnaround, generous revisions, shorter dispute window, flexible requirements.

### Quality-Focused Marketplace

```
Auto-Complete Days: 0 (disabled)
Revision Limit: -1 (unlimited)
Allow Disputes: Yes
Dispute Window: 60 days
Requirements Timeout: 7 days
Auto-Start on Timeout: No
```

**Effect:** Manual approval required, unlimited revisions, extended buyer protection, strict requirements.

## Workflow Impact

### Requirements Timeout Workflow

```
Order Placed
    ↓
Pending Requirements Status
    ↓
Timer Starts (requirements_timeout_days)
    ↓
Buyer Has X Days
    ↓
    ├─→ Requirements Submitted → In Progress
    │
    └─→ Timeout Reached
           ↓
           ├─→ auto_start_on_timeout = true → In Progress
           └─→ auto_start_on_timeout = false → Cancelled
```

### Auto-Complete Workflow

```
Delivery Submitted
    ↓
Pending Approval Status
    ↓
Timer Starts (auto_complete_days)
    ↓
Buyer Has X Days
    ↓
    ├─→ Buyer Accepts → Completed
    ├─→ Buyer Requests Revision → Revision Requested
    └─→ No Action (timeout) → Auto-Complete → Completed
```

## Best Practices

### For Most Marketplaces

✅ **Recommended Settings:**
- Auto-Complete: 3-5 days
- Revisions: 2-3 default
- Dispute Window: 14-30 days
- Requirements Timeout: 2-3 days
- Auto-Start: Enabled

### For High-Value Services

✅ **Recommended Settings:**
- Auto-Complete: 5-7 days
- Revisions: 3-5 or unlimited
- Dispute Window: 30-60 days
- Requirements Timeout: 5-7 days
- Auto-Start: Disabled (require requirements)

### For Quick Turnaround

✅ **Recommended Settings:**
- Auto-Complete: 1-2 days
- Revisions: 1-2
- Dispute Window: 7-14 days
- Requirements Timeout: 1 day
- Auto-Start: Enabled

## Troubleshooting

### Orders Not Auto-Completing

**Check:**
1. `auto_complete_days` > 0
2. Order actually delivered (status `pending_approval`)
3. WP Cron running properly
4. Correct number of days passed
5. Check debug log for cron errors

**Test WP Cron:**
```bash
wp cron event list
wp cron test
```

### Requirements Not Timing Out

**Check:**
1. `requirements_timeout_days` > 0
2. Order in `pending_requirements` status
3. WP Cron running
4. Timeout period actually elapsed

### Disputes Not Available

**Check:**
1. `allow_disputes` enabled
2. Order status `completed`
3. Within `dispute_window_days` period
4. User is buyer (not vendor)

## Related Documentation

- [Order Workflow](order-lifecycle.md) - Complete order statuses
- [Deliveries & Revisions](deliveries-revisions.md) - How settings affect delivery workflow
- [Requirements Submission](order-requirements.md) - Requirements timeout details
- [Dispute Resolution](../disputes-resolution/opening-disputes.md) - Dispute window usage

Configure settings to match your marketplace's pace and quality standards!
