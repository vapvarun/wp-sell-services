# Journey 04 — Finding a vendor and their work

**Role:** logged-out visitor, then buyer
**Status:** passing as of commit `e29809c`

Guards card 10208183009.

## Why this one matters

This journey is one click long and it was broken for **every vendor link on the
site**. `wpss_get_vendor_url()` points at `{vendors page}?vendor={nicename}`
whenever a vendors page exists, and `[wpss_vendors]` never read that parameter —
so following any vendor link re-rendered the directory.

It was dormant until the installer started seeding the vendors page: before
that, the resolver fell through to its `/provider/{nicename}` branch. A fix in
one place turned a latent gap into a site-wide one, which is the general lesson
— when a resolver has a "preferred" branch nothing has been using, check the
branch works before making it preferred.

## Steps

### 1. Directory renders
Visit `/vendors/`.

**Expect**
- Vendor cards render; no raw `[wpss_vendors]` text.
- Breadcrumb reads Home › Vendors.
- 390px: cards stack, `scrollWidth === innerWidth` (no horizontal overflow).

### 2. Vendor link goes to the vendor
Click any vendor card.

**Expect**
- Their **profile** renders — not the directory again.
- URL is `/vendors/?vendor={nicename}`.
- Zero `.wpss-vendors-grid .wpss-vendor-card` elements on the page: if the grid
  is still there, the profile did not take over.

### 3. A vendor with no services
Open a vendor who has published nothing.

**Expect** the profile with its empty states ("hasn't published any services",
"No reviews yet") — **not** "Vendor not found", and not the directory.

### 4. Stale or invented slug
Hand-edit to `?vendor=does-not-exist-xyz`.

**Expect** the directory, with no error text. A dead link should show the list,
not a wall.

### 5. A non-vendor's slug
Use a plain subscriber's nicename.

**Expect** the directory — guarded by `wpss_is_vendor()`, so a buyer's slug
cannot render an empty vendor profile.

### 6. Other entry points
Vendor names link from service pages, seller cards and order screens.

**Expect** all of them land on the profile. They share
`wpss_get_vendor_url()`, so they break together and fix together — worth
spot-checking two of them after any change to that resolver.

### 7. Through to the work
From the profile, open one of their services.

**Expect** the single-service page, with that vendor attributed.
