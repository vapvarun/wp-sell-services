# Journey 15 - Seller sets up a profile and portfolio, reads earnings and analytics

**Roles:** seller (`wpss_vendor`), admin
**Rail:** standalone, woo
**Status:** written 2026-10-18; walk pending (see .qa-results/walks.json)
**Docs:** docs/website/vendor-system, docs/website/earnings-wallet, docs/website/analytics-reporting

## Steps

### 1. Profile
Dashboard > Profile: display name, tagline, bio, avatar, cover, country, time zone, links, intro video.

**Expect** each saves and shows on the public vendor profile; a vendor with no profile row still renders a profile, never "Vendor not found" (guard `tests/test-vendor-profile-contract.php`).

### 2. Portfolio
Add, edit, feature and remove an item.

**Expect** limits are enforced with a message, removal asks first, the public profile matches.

### 3. Earnings and Payouts
**Expect** available, pending clearance and withdrawn balances add up to the ledger; the period selector changes the figures; the CSV export (Pro) carries the same rows and a summary; money formats follow the site currency.

### 4. Analytics
**Expect** figures agree with Sales Orders for the same period; an empty period shows a designed empty state.

### 5. Vendor plans (Pro)
**Expect** a seller at the plan limit sees the upgrade link and cannot publish past it; Max featured is honoured (carrier product on Woo).

### 6. Portfolio order, featured item, vacation and levels over REST
- `POST /portfolio/reorder` saves the new order and the profile shows it; a seller cannot reorder another seller's items.
- `POST /portfolio/{id}/featured` toggles an item as featured, up to the featured cap; going over the cap is refused with a message, not silently ignored.
- `POST /vendors/me/vacation` turns vacation mode on or off for the caller only, and the services are hidden or restored as the screen does.
- `GET /seller-levels/{level}` returns the level's requirements and the seller's progress toward it.
- The account-page versions of the seller dashboard (`myaccount/vendor-dashboard`, `myaccount/vendor-services`, `myaccount/service-orders`) show the same figures as the dashboard.

**Expect** screen and API agree, and a visitor or another seller gets 403.
