# Journey 12 - A suspended seller finishes paid work and starts nothing new

**Roles:** admin, seller (`wpss_vendor`), buyer
**Rail:** any
**Status:** written 2026-10-18; walk pending

Owner policy (2026-10-18): suspension stops new activity, never paid work in flight. Hold and resume are the site owner's tools only.

## Steps

### 1. Seller cannot pause their own order (card:10399370449)
As the seller, open an in-progress order, then `POST /orders/{id}/hold`.

**Expect** no Hold button anywhere, the route refuses with "Only an administrator can put an order on hold", the status is unchanged. As admin both hold and resume work.

### 2. Suspend a seller with an open paid order (card:10384354109)
Admin sets the seller to Suspended. As the seller open the dashboard.

**Expect** a Selling > Sales Orders item and nothing else under Selling; the open order can be delivered and messaged inside; My Services, Proposals, Portfolio and Earnings are not reachable by URL.

### 3. Suspended seller starts nothing new (card:10384354109)
As the suspended seller submit a proposal (form and `POST /requests/{id}/proposals`), use Contact seller on another seller's service, send a message in a pre-sale thread.

**Expect** each is refused with a plain message ("Your vendor account is suspended" or "Your account is suspended"), nothing is created, no notification reaches the other person.

### 4. Plugin menu is not offered to sellers (card:10379867128)
As a seller who also has `edit_posts`, open wp-admin.

**Expect** no WP Sell Services menu item and no 403 page reachable from the admin bar or menu.
