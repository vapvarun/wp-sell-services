# Journey 17 - Layout per surface

**Roles:** buyer, seller, admin
**Rail:** standalone and WooCommerce
**Themes:** Reign, BuddyX, BuddyX Pro (light and dark), Twenty Twenty-Five
**Status:** written 2026-10-18; walk pending

## Steps

### 1. Checkout sidebar cards do not slide under the summary (card:10411948180)
Standalone checkout at 1440 wide, scroll to the bottom.

**Expect** "About the Seller" and "What happens next?" stay fully readable; the Order Summary never paints over them.

### 2. Buyer-request breadcrumb is one line (card:10384469084)
Open a single buyer request.

**Expect** Home / Requests / title on one row, separators between items.

### 3. Report dialog does not flash (card:10398652904)
Reload a service page and watch the first two seconds on a slow connection.

**Expect** no dialog appears until Report is clicked.

### 4. Deliver dialog clears a sticky header (card:10384248570)
Seller order view, click Deliver on a theme with a sticky header, at 1440 and 390.

**Expect** the whole dialog, including its title and close X, sits below the header.

### 5. Milestone Days input stays in its box (card:10379783717)
Propose with milestones at 390.

**Expect** Amount and Days sit side by side inside the row, no horizontal scroll.

### 6. Empty-state categories are centred (card:10380369422)
New buyer with no orders, Dashboard empty state.

**Expect** the categories block is centred under the message.

### 7. Vacation fields line up (card:10384895523)
Admin > Vendors > a vendor > Vacation Mode.

**Expect** each label sits above its control, same left edge.

### 8. Review search shows its placeholder (card:10384766482)
Admin > Review Moderation.

**Expect** "Search by reviewer or text..." is fully visible at 1440; the field fills the row at 390.

### 9. Keyboard focus is visible on every field (UX rule 14)
Tab through the contact form, the request requirements form, checkout form groups, the block category select and the tip presets.

**Expect** each focused control shows a 3px primary ring, not only a border-colour change. check: tests/test-focus-indicator-css.php
