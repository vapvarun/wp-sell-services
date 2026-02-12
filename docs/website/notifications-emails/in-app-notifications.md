# In-App Notification System

WP Sell Services includes an in-app notification system that displays alerts directly within the WordPress dashboard. Users receive real-time notifications for orders, messages, deliveries, and other events.

## Overview

The in-app notification system stores notifications in the database and displays them in a notification center. Notifications appear regardless of email settings and provide quick access to relevant actions.

### Key Features

- **Notification Center** - Centralized hub for all notifications
- **Unread Badge** - Visual indicator of new notifications
- **Real-Time Updates** - Notifications appear immediately when events occur
- **Persistent Storage** - Notifications saved in database table
- **Auto-Cleanup** - Old notifications automatically deleted after 90 days (read only)

### Database Table

**Table Name:** `wp_wpss_notifications`

**Columns:**
- `id` - Notification ID
- `user_id` - Recipient user ID
- `type` - Notification type constant
- `title` - Notification title
- `message` - Notification message (HTML)
- `data` - Additional data (JSON)
- `is_read` - Read status (0/1)
- `created_at` - Creation timestamp

## Accessing Notifications

### Admin Notification Center

**Location:** WordPress Admin → WP Sell Services → Notifications

**What It Shows:**
- List of all notifications for current user
- Unread count badge
- Notification type icons
- Timestamps
- Mark as read/delete actions

### Notification List Features

**Columns Displayed:**
- **Icon** - Notification type indicator
- **Message** - Notification title and preview
- **Date** - Relative time (e.g., "2 minutes ago")
- **Actions** - View, Mark Read, Delete

**Filtering:**
- All notifications
- Unread only
- By notification type

**Sorting:**
- Newest first (default)
- Oldest first

## Notification Types

The system creates in-app notifications for these event types:

| Type | Constant | Description |
|------|----------|-------------|
| Order Created | `TYPE_ORDER_CREATED` | Vendor receives new order |
| Order Status | `TYPE_ORDER_STATUS` | Order status changes |
| New Message | `TYPE_NEW_MESSAGE` | Message received in conversation |
| Delivery Submitted | `TYPE_DELIVERY_SUBMITTED` | Vendor submits delivery |
| Delivery Accepted | `TYPE_DELIVERY_ACCEPTED` | Buyer accepts delivery |
| Revision Requested | `TYPE_REVISION_REQUESTED` | Buyer requests revision |
| Review Received | `TYPE_REVIEW_RECEIVED` | Vendor receives review |
| Dispute Opened | `TYPE_DISPUTE_OPENED` | Dispute is opened |
| Dispute Resolved | `TYPE_DISPUTE_RESOLVED` | Dispute is resolved |

## Managing Notifications

### Viewing Notifications

1. Go to **WP Sell Services → Notifications** in admin menu
2. View list of notifications
3. Click notification title to view full details
4. Click "View Order" to go to related order

### Marking as Read

**Single Notification:**
1. Hover over notification
2. Click "Mark as Read" link
3. Notification marked as read (badge decreases)

**All Notifications:**
1. Click "Mark All as Read" button at top
2. All notifications for current user marked as read
3. Unread badge clears

### Deleting Notifications

**Single Notification:**
1. Hover over notification
2. Click "Delete" link
3. Notification permanently removed

**Note:** There is no bulk delete function in the current version. Notifications are deleted individually or automatically after 90 days (read notifications only).

## Notification Service Class

**Location:** `src/Services/NotificationService.php`

### Creating Notifications

```php
/**
 * Create notification.
 *
 * @param int    $user_id User to notify.
 * @param string $type    Notification type.
 * @param string $title   Notification title.
 * @param string $message Notification message.
 * @param array  $data    Additional data.
 * @return int|false Notification ID or false on failure.
 */
public function create( $user_id, $type, $title, $message, $data = array() )
```

**Example:**
```php
$notification_service = new NotificationService();
$notification_service->create(
    $vendor_id,
    NotificationService::TYPE_ORDER_CREATED,
    'New Order Received',
    'You have received a new order from Sarah Williams.',
    array(
        'order_id' => 12345,
        'order_number' => 'ORD-12345'
    )
);
```

### Getting Notifications

```php
/**
 * Get notifications for user.
 *
 * @param int   $user_id User ID.
 * @param array $args    Query args (unread_only, limit, offset).
 * @return array Array of notification objects.
 */
public function get_user_notifications( $user_id, $args = array() )
```

**Example:**
```php
// Get 20 most recent notifications
$notifications = $notification_service->get_user_notifications(
    get_current_user_id(),
    array(
        'limit' => 20,
        'offset' => 0
    )
);

// Get only unread notifications
$unread = $notification_service->get_user_notifications(
    get_current_user_id(),
    array(
        'unread_only' => true
    )
);
```

### Getting Unread Count

```php
/**
 * Get unread count with caching.
 *
 * @param int $user_id User ID.
 * @return int Unread notification count.
 */
public function get_unread_count( $user_id )
```

**Caching:**
- Unread count cached for 1 hour
- Cache invalidated when notification read/deleted
- Cache key: `wpss_unread_notifications_{user_id}`

### Marking as Read

```php
/**
 * Mark notification as read.
 *
 * @param int $notification_id Notification ID.
 * @return bool Success status.
 */
public function mark_as_read( $notification_id )

/**
 * Mark all notifications as read.
 *
 * @param int $user_id User ID.
 * @return bool Success status.
 */
public function mark_all_as_read( $user_id )
```

## Auto-Cleanup

**Default Behavior:**
- **Read Notifications:** Deleted after 90 days
- **Unread Notifications:** Kept indefinitely
- **Cleanup Schedule:** Daily at 2:00 AM (WordPress cron)

**Important Notifications Never Deleted:**
- Dispute notifications
- Order completion notifications
- Any notification less than 90 days old

**Manual Cleanup:**
Admins can manually delete individual notifications at any time through the notification center.

## Developer Hooks

### Action: Notification Created

```php
/**
 * Fires when notification is created.
 *
 * @since 1.0.0
 *
 * @param int    $notification_id Notification ID.
 * @param int    $user_id         User ID.
 * @param string $type            Notification type.
 * @param array  $data            Notification data.
 */
do_action( 'wpss_notification_created', $notification_id, $user_id, $type, $data );
```

**Example Usage:**
```php
add_action( 'wpss_notification_created', function( $notification_id, $user_id, $type, $data ) {
    // Send push notification
    // Log to external system
    // Trigger webhook
}, 10, 4 );
```

## Notification vs Email

**In-App Notifications:**
- Always created for all events
- Stored in database
- Accessible through dashboard
- User must log in to see them
- Can be marked as read/unread

**Email Notifications:**
- Only sent if admin enables the notification type
- Delivered to user's email
- Visible without logging in
- Cannot be marked as read
- Subject to email deliverability issues

**Both Are Independent:**
- Disabling email notification does NOT disable in-app notification
- In-app notifications created even if email fails
- Users can receive both or just in-app (admin controls email)

## Troubleshooting

### Notifications Not Appearing

**Problem:** User doesn't see notifications in notification center

**Solutions:**
1. Check if user has correct WordPress user ID
2. Verify notifications table exists (run activation)
3. Check for database errors in debug log
4. Clear object cache if using caching plugin
5. Verify user has permission to view notifications

### Unread Count Wrong

**Problem:** Badge shows incorrect unread count

**Solutions:**
1. Clear object cache
2. Check `wpss_unread_notifications_{user_id}` cache key
3. Manually mark all as read and test
4. Verify `is_read` column values in database

### Old Notifications Not Deleted

**Problem:** Notifications older than 90 days still present

**Solutions:**
1. Verify WordPress cron is running
2. Check auto-cleanup cron job registered
3. Manually delete old notifications from database
4. Ensure notification is marked as read (unread kept indefinitely)

## Limitations

**Current Version Does Not Include:**
- Browser push notifications
- Desktop notifications
- Sound alerts
- User-configurable notification preferences
- Notification grouping
- SMS notifications
- Mobile app push notifications

These features may be available in the PRO version or future updates.

## Next Steps

- **[Email Types](email-types.md)** - Learn about email notifications
- **[Email Configuration](email-configuration.md)** - Configure email settings
- **[Order Management](../order-management/order-lifecycle.md)** - Understand order workflow

---

*Documentation based on plugin version 1.0.0*
