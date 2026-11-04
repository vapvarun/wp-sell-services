# Journey 22 - Seller levels

**Roles:** vendor, admin, visitor
**Rail:** standalone, woo
**Status:** written 2026-11-03; walk pending
**Docs:** docs/website/vendor-system/seller-levels.md

Levels: New, Rising (5 orders, 4.0 rating, 3 reviews, 80% on time, 30 days), Top Rated (25, 4.7, 10, 90%, 90 days); Pro is granted by an admin only. In Free a level changes what buyers see, not commission; Pro's Vendor Level commission rule can price by it.

## Steps

### 1. A vendor earns a level
Seed a vendor to just under Rising, then complete one more order and leave a review.

**Expect** the level is recalculated on that completion/review (not only by the weekly job), the vendor gets the promotion email and notification once.

### 2. Where it shows
As a visitor open the vendor's service card, vendor card and profile.

**Expect** a badge on each. A New vendor shows a "New vendor" badge only while their profile is incomplete (no tagline, bio or country), as a hint to buyers; with a complete profile, New shows none. The profile's note says what the next level needs.

### 3. Admin override
Sell Services > Vendors: set the vendor to Pro.

**Expect** the badge shows Pro and the weekly recalculation never moves it back.

### 4. REST
`GET /seller-levels`, `/seller-levels/{level}`, `/vendors/{id}/level` (public), `/vendors/me/level` (vendor).

**Expect** the same level and thresholds as the screens.

Guards: `tests/test-seller-level-override.php`, `tests/test-seller-levels-rest-earnings.php`, `tests/pro/test-seller-level-rule-no-profile.php`.
