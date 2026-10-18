# Journey 15 - Seller sets up a profile and portfolio, reads earnings and analytics

**Roles:** seller (`wpss_vendor`), admin
**Rail:** standalone, woo
**Status:** written 2026-10-18; walk pending (see docs/qa/walks.json)
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
