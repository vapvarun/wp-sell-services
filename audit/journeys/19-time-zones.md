# Journey 19 - Time zones

**Roles:** buyer, seller, admin
**Rail:** any
**Themes:** Reign, BuddyX, BuddyX Pro, Twenty Twenty-Five
**Status:** written 2026-10-18; walk pending

Walk every step on three sites: Asia/Kolkata, America/New_York and UTC, with an order created and due near local midnight.

## Steps

### 1. A deadline names its zone (decision 15)
Buyer order view, seller order view, admin order screen, My Account service orders, dashboard order row, a buyer request's expiry.

**Expect** "Oct 21, 2026 3:00 pm (UTC+5:30)" everywhere the same moment appears; everyday times (created, messages, timeline) stay plain.

### 2. The same moment reads the same on every screen
One order across the admin list, admin detail, buyer view, seller view, the email, the REST payload.

**Expect** the same day and time on all of them, in the site's zone.

### 3. A vacation return date does not shift
Seller sets Back on Oct 21 on a New York site.

**Expect** the buyer sees Oct 21 on the service page, the card badge and the notice, never Oct 20.

### 4. A typed date or range is read in the display zone
Admin > Orders date filter, Audit Log range, Earnings range, vendor CSV export week/month; edit a buyer request deadline and save without touching it.

**Expect** the range includes the whole local day; the saved deadline does not move after a save.

### 5. Wallet dates are not the browser's
Seller earnings > wallet ledger, browser set to a different zone than the site.

**Expect** the date matches the site, and an entry made at 11 pm local is on that day.

### 6. REST dates are ISO 8601
GET any `wpss/v1` list with dates.

**Expect** `2026-10-21T09:30:00+00:00` style values, including routes written after this release.

### 7. Cancellation emails use the reader's zone
Request an order cancellation as buyer on a site with a member-zone filter.

**Expect** the vendor and the buyer each see the response deadline in their own zone, labelled.
