# Journey 11 - Member finds, filters and reads notifications

**Roles:** vendor or buyer with 100+ notifications (seed one), admin
**Rail:** standalone, woo
**Status:** rewritten 2026-10-18; walk pending (see .qa-results/walks.json)

## Steps

### 1. Badge matches the list (card:10403104232)
Open the dashboard. Note the number on **Notifications** in the menu, open the page, click Mark as read on one row, then Mark all.

**Expect** the badge equals Unread only's total, drops by one per read, reaches 0 and disappears; reloading shows the same number. With real-time on, a new notification raises it without a reload.

### 2. Filter keeps its place (card:10403104468)
Choose Unread only + one type, Filter, go to page 2, then Clear filter.

**Expect** the list, the page numbers and the empty message ("No notifications match this filter.") all follow the filter; page 2 keeps it; Clear returns the full list. At 390px both drop-downs and the button fit without sideways scroll and are 44px tall.

### 3. Same-second rows page cleanly (card:10403105211)
Seed 60 notifications with one timestamp, page through three pages of 20.

**Expect** no repeat, no skip.

### 4. Old read notifications go, unread stay (card:10403104873)
Run `wp action-scheduler run` (or wait for the daily job).

**Expect** read notices over 90 days are gone, unread ones and dispute notices under a year remain.


### Registry check
As a member with `order_late`, `refund_pending` and `order_started` rows: every type present appears in the Type dropdown, selecting it returns only those rows, and no row shows the generic bell. Guard: `tests/test-notification-types-registry.php`.

### Retention
Old read notifications are cleaned after 90 days by default; a site changes it with the `wpss_notification_retention_days` filter. **Expect** rows older than the window and already read are removed, unread rows never are, and the badge count is unchanged by the cleanup.

### 5. The account-page notifications tab
Where a cart plugin owns the account page, Notifications renders `myaccount/notifications` with the same list, filter and mark-read as the dashboard.

On WooCommerce the tab is My Account > Service notifications (`/my-account/service-notifications/`), for buyers and vendors; Pro `tests/test-woo-account-notifications-tab.php` guards the menu item, query var and render hook.

**Expect** the type filter lists only the types this member has received, and marking read updates the bell badge.
