# WP Sell Services — Mobile App Plan

**Date:** 2026-07-31
**Audited against:** Free + Pro on branch `1.3.1` (headers still read `1.3.0`; bump happens at tag time), and the live site `wp-sell-services.local` driven with `curl`.
**Method:** three parallel audits — REST surface from source, product/money flows from source, and an empirical probe of the running API. Where source and live disagree, live wins and it is called out.

> **Move this file to the app repo (`vapvarun/wpss-app`) once it exists.** It lives here for now because Phase 0 is entirely plugin-side work.

---

## 0. The verdict in one paragraph

**This plugin is closer to app-ready than any sibling in the portfolio, and further from App-Store-ready than any of them.** The API is large (128 live routes), correctly owner-scoped, properly paginated on 27 of its collection endpoints, and it already ships a *purpose-built mobile auth handshake* that mints an Application Password and returns a ready `Authorization: Basic` value. There is a `/batch` endpoint whose comment literally says "Batch endpoint for mobile apps." Someone already thought about this. But: **all 57 Pro routes serve 404 on this site**, three core endpoints return uncaught PHP fatals, every real vendor's `/me` reports `is_vendor: false`, and there is no account deletion, no user blocking, and no push sender — three independent Apple-review blockers. The app is buildable. It is not submittable until Phase 0 lands.

---

## 1. What the product actually is

A freelance-services marketplace (Fiverr-shaped). Sellers publish `wpss_service` listings with up to 3 packages; buyers order, submit requirements, receive a delivery, accept or request a revision, then review. There is also a reverse market: buyers post `wpss_request` briefs and sellers submit proposals.

**WPSS does not require WooCommerce.** Woo, EDD, SureCart and FluentCart are optional Pro adapters; the standalone rail is primary and is what runs on this site (Woo is installed but **inactive**). Stripe and PayPal gateways ship in **Free** — `Plugin.php:1644`, `:1649`.

### Actors

| Actor | How it is defined | App relevance |
|---|---|---|
| Guest | no role | **Full anonymous browse works** — catalogue, vendor profiles, portfolios, reviews, categories, buyer requests, settings all 200 without auth. Browse-before-login is fully supported. |
| Buyer | **any logged-in user; there is no `wpss_customer` role** (`Activator.php:100-132`) | Everyone is a buyer by default |
| Vendor | role `wpss_vendor` **and** `wpss_vendor_profiles.status = 'active'` (`VendorService.php:325-331`) | Seller surfaces gate on `is_active_vendor()`, not on the role alone |
| Admin | `manage_options` | Moderation, disputes, withdrawals, vendor approval — all wp-admin only, **none reachable over REST** |

### The order lifecycle the app must implement

There are **two** state machines over one `status` column (`OrderService.php:369-450`). Build against **Branch A**, the live web flow:

```
pending_payment → pending_requirements → in_progress → pending_approval → completed
                                    ↘ revision_requested ↗
        also: cancellation_requested · disputed · on_hold · late · cancelled
```

**Branch B (`pending → accepted → rejected → requirements_submitted → delivered`) is a trap.** REST `accept`/`reject` both require `$order->status === 'pending'` (`OrdersController.php:706,719`) and **nothing in either plugin ever writes `'pending'` as an order status**. There is no vendor-accept step on the standalone rail — the buyer submitting requirements auto-starts the work (`RequirementsService.php:107`). **Do not build an "Accept order" screen.**

Two more traps in the same area:
- `POST /orders/{id}/deliver` only flips the status; it writes no delivery row, posts nothing to the conversation, and permanently blocks auto-complete (which requires a `wpss_deliveries` row, `OrderWorkflowManager.php:224-238`). **Use `POST /orders/{id}/deliverables`** (`OrdersController.php:613`), which calls `DeliveryService::submit()`.
- Package identity is **positional**. Real packages live in `_wpss_packages` post meta as an index-keyed array; `package_id` on an order is an **array index**, not a PK (`ServiceOrder.php:546,940-942`). The `wpss_service_packages` and `wpss_service_addons` tables are empty and dead. Index `0` is valid — never `empty()`-check it.

---

## 2. What is already built for mobile — the good news

| Seam | State | Evidence |
|---|---|---|
| **Token auth** | **Done, and purpose-built.** `AuthController` docblock: *"Provides token-based auth for mobile apps using WordPress Application Passwords."* `POST /auth/login` returns `base64(user_login:app_password)` — a ready `Basic` value. Caps at 5 passwords/user, evicting the oldest. Logout revokes every `WPSS*` password. | `AuthController.php:26,46,283,535-570,392-396` |
| **Writes without a nonce** | **Proven live.** `POST /favorites/206` with HTTP Basic and **no `X-WP-Nonce`** → `201`. Undone with `DELETE`, verified in the DB. This is the single most important green light. | live probe |
| **Login rate limiting** | Works — 3 failures then `429 rate_limit_exceeded` | live probe |
| **Batch** | `POST /batch`, 25 sub-requests, inherits the parent `Authorization` header. Comment reads *"Batch endpoint for mobile apps"*. Solves the detail-screen round-trip problem. | `API.php:189,607,649` |
| **Device registration** | `POST /auth/devices` accepts `token`, `platform` (`ios\|android\|web`), `device_id`; `DELETE /auth/devices/{id}` revokes | `AuthController.php:180-210` |
| **Realtime** | `POST /realtime/auth` signs Pusher private channels; client config exposed in `/settings` | `RealtimeController.php:50` |
| **Owner scoping** | **No IDOR found.** Orders, conversations, proposals and media all correctly 403 for non-parties across three probed roles | live probe |
| **Pagination** | 27 collection endpoints do a real `COUNT(*)` + `LIMIT/OFFSET` and emit `X-WP-Total`/`X-WP-TotalPages` | `RestController.php:150-176` |
| **`available_actions`** | Orders return a per-role action list — exactly what a native action bar needs. Verified role-correct on two live orders. | live probe |
| **`/settings`** | Anonymous; gives currency, symbol, decimals, min/max order, upload limits, allowed file types up front | `API.php:150,344-377` |

---

## 3. Phase 0 — plugin blockers. No app code starts before these.

### 0.1 Three uncaught fatals and a split-brain (all small, all load-bearing)

| # | Defect | Evidence |
|---|---|---|
| **B1** | **`GET /disputes/{id}` returns a 500 for every authenticated role**, and takes `/evidence` and `/timeline` with it. `$dispute->order_id` arrives from `$wpdb` as the string `'14'` and is passed to an `int`-typed parameter — **inside the permission callback**, so it fires for everyone. All 12 disputes affected. Emits `text/html` with absolute server paths and a full stack trace. | `DisputesController.php:377` → `RestController.php:114` |
| **B2** | **`GET /buyer-requests/{id}` returns 500 when authenticated and 200 when anonymous** — the exact inverse of what you'd expect. Line 487 sits in a logged-in-only branch and indexes a `stdClass` as an array. Breaks the buyer-request detail screen for the only people who can act on it. | `BuyerRequestsController.php:487` |
| **B3** | **Vendor identity has three competing sources of truth, and `/me` picks the wrong one.** `is_vendor` in `/me` and `/dashboard` reads the `_wpss_is_vendor` user meta. A real vendor (role `wpss_vendor`, profile `active`, 2 services, 10 orders) has an empty meta value → `/me` returns `is_vendor: false` and `can_create_services: false`, and `/dashboard` returns all zeros. Meanwhile `/vendors/me`, `/orders` and `/earnings/*` all correctly treat her as a vendor. The admin, who is not a seller, *does* have the meta and reports `is_vendor: true`. **`/me` is the natural bootstrap call and it is the one that lies.** | live probe; `VendorService.php:266-280` vs `:325-331` |

**Fix B3 by making `is_vendor()` authoritative** — role-or-profile, not meta — and having `/me`, `/dashboard` and `/vendors/me` all call it. Then backfill the meta or drop it.

### 0.2 All 57 Pro routes serve 404

Pro is active. None of `wallet`, `stripe-connect`, `analytics`, `subscription-plans`, `commission-rules`, `white-label`, `paypal-payouts`, `storage`, `recurring-services` appear in the live index. Proven at runtime:

```
wp eval 'echo var_export(has_filter("wpss_api_controllers"),true);'   → false
wp eval '$c=apply_filters("wpss_api_controllers",[]); echo count($c);' → 0
```

Cause chain: `wp-sell-services-pro.php:188` `$is_licensed = $license->is_valid()` → `Pro::init($licensed)` → `Pro.php:173` `if ( ! $licensed ) { return; }` — which short-circuits **before** `Pro.php:417` registers the REST controllers. `is_valid()` returns false here despite `wpss_pro_license_status = valid`.

Two things to fix, and the second matters more than the first:

1. Why `is_valid()` disagrees with the stored status on this site (note `wpss_pro_version` option is `1.2.2` while installed Pro is `1.3.0` — a migration has not run).
2. **The failure mode.** An unlicensed site returns bare `rest_no_route` **404** for every Pro route. A client cannot distinguish "Pro not installed", "Pro unlicensed", and "I typo'd the path". Pro routes should register unconditionally and return a **403 with a distinct code** (`wpss_pro_license_required`) when unlicensed — the same lesson Listora learned with its `app_enabled` gate.

Also worth fixing while in there: `wp-sell-services-pro.php:531-532` claims *"License state authorises update downloads only — it never gates functionality."* **That comment is false** and directly contradicts `Pro.php:172-193`.

### 0.3 Apple-review blockers

| # | Blocker | State | Guideline |
|---|---|---|---|
| **A1** | **In-app account deletion** | **ABSENT.** No `DELETE /me`, no `/auth/delete-account`, no `/account/close`. Grepped `delete_account`, `account_deletion`, `close_account` → 0 hits. Deletion is wp-admin only (`Admin.php:2591`). **The cascade plumbing already exists** (`DataCascadeHandler.php:55,175-226` with before/after actions) — this is a REST route on top of working machinery, not new logic. | 5.1.1(v) — guaranteed rejection |
| **A2** | **Report / block another user** | **ABSENT as a concept.** `block_user`, `blacklist`, `report_user` → 0 hits. The dispute system is order-scoped and the moderation system is content-scoped; neither targets a user. Vendor suspension exists but is **wp-admin-only and vendor-only** — there is no way to eject an abusive *buyer* at all. The app carries UGC in six places (listings, reviews, order messages, portfolio, proposals, disputes). | 1.2 — UGC apps need report + block |
| **A3** | **Push delivery** | **Write-only stub.** Tokens are stored in `_wpss_push_devices`; grepping that key returns **5 hits, all inside `AuthController.php`**. Nothing ever reads a token. Zero FCM/APNs sender in either plugin. | not a blocker, but the app is inert without it |
| **A4** | **GDPR exporters/erasers** | **ABSENT.** `wp_privacy`, `gdpr`, `personal_data` → 0 hits across both `src/` trees. WordPress's Tools → Export/Erase Personal Data returns nothing for the 18 Free + 7 Pro custom tables. | not Apple, but it is a legal exposure and it is adjacent to A1 |

### 0.4 The app-config endpoint that does not exist

Grepped `app_config`, `app-config`, `app_enabled`, `min_app_version`, `app_version`, `feature_flags` → **0 files each**. There is no force-upgrade floor, no feature-flag payload, and no way to tell a client "this build is too old" or "this site is not licensed for the app."

**The seam already exists.** `GET /settings` (`API.php:150`) is anonymous and extensible via `apply_filters('wpss_api_public_settings', $settings)` (`API.php:376`). Hang the app contract there rather than adding a route:

```jsonc
{
  "contract_version": 1,
  "min_app_version": "1.0.0",
  "app_enabled": false,          // Pro flips it via the filter, when licensed. Fail closed.
  "branding": { "brand_name": "", "logo_url": "", "primary_color": "" },
  "legal": { "privacy_policy_url": "", "terms_url": "", "abuse_contact_email": "" },
  "features": { "disputes": true, "milestones": true, "tipping": true, "buyer_requests": true, ... }
}
```

Note **branding already exists in Pro but is admin-only**: `GET/PUT /white-label` is `check_admin_permissions` on both verbs (`WhiteLabelController.php:75`), so an app cannot read the site's brand without an admin token. Surface the public subset through the same filter.

### 0.5 Auth hardening (small, but do it before shipping)

- **`expires: null`** — tokens never expire. Revocation is manual only.
- **An existing Application Password is accepted as the `password` on `/auth/login`, minting another one.** Proven live: passing a WP-CLI-minted password as the login password returned a *new* credential. One leaked token escalates into unlimited long-lived credentials. Gate this.

---

## 4. Payments — the decision that shapes the whole app

**Apple Guideline 3.1.3(e) / 3.1.5(a): goods and services consumed *outside* the app must not use In-App Purchase.** WPSS sells freelance labour — logo design, writing, video editing — delivered by a person, off-platform. This is the same category Fiverr and Upwork ship in, and both use their own payment rails on iOS.

**So the app can and should use Stripe/PayPal, not IAP.** That is a genuine strategic advantage over a digital-content app, and it means the existing Free Stripe gateway is reusable.

Two viable implementations:

| Option | What it is | Trade-off |
|---|---|---|
| **Web checkout handoff (recommended for v1)** | Native everywhere except payment; open `[wpss_checkout]` in an in-app browser with the session, return via deep link, poll the order | Fastest. Reuses a checkout that already works, including Stripe Elements and PayPal. No new payment surface to certify. Slightly worse UX at the one moment it matters. |
| **Native Stripe SDK** | `POST /payments/create-intent` → Stripe PaymentSheet → `POST /payments/confirm` | Best UX. But the routes need verification — **Pro's `PaymentController` is dead** (it does `apply_filters('wpss_payment_gateways', array())` starting from an empty array, `PRO/src/API/PaymentController.php:233,439`, while Free applies that filter to an already-populated property, `Plugin.php:1668`). Use **Free's** `/payments/*`. Also `/payments/methods` is **registered twice** — Free with a permission check, Pro with `__return_true` (`PRO/src/API/PaymentController.php:42-49`). |

**Ship v1 on the handoff, plan native for v1.1.** Do not spend the first release re-implementing a payment flow that already works on the web.

**One flag:** *tipping*. Tips ride on a completed service order (`TippingService.php:171`), so they inherit the real-world-services exemption. But tips to *creators* for digital content do require IAP, and a reviewer may not read the distinction the way we do. Consider omitting tipping from v1 rather than arguing it at review.

---

## 5. Contract gaps the app must absorb (or the plugin should fix)

These are not blockers — they are the tax the app pays if the plugin does not change.

| # | Gap | Cost to the app |
|---|---|---|
| **C1** | **`/services/{id}` is byte-identical to a `/services` list item.** The detail response adds nothing — 16 identical keys. | A detail screen needs 4–6 extra calls: `/packages`, `/faqs`, `/reviews`, `/reviews/summary`, `/favorited`, `/vendors/{id}`. Mitigate with `/batch`; fix properly by fattening `/detail`. |
| **C2** | **The same concept is spelled 7 ways.** `/services` → `vendor{id,name,avatar}`; `/orders` → flat `vendor_id/vendor_name/vendor_avatar`; `/reviews` → flat but **no `vendor_avatar`**; `/conversations` → `other_user{}`; `/disputes` → `initiated_by{}`; `/buyer-requests` → `author{}`; `/proposals` → `vendor{}`. | A shared `User` model needs 7 adapters. |
| **C3** | **Two incompatible date formats.** `/services`, `/orders`, `/conversations` emit ISO-8601 with offset. `/notifications`, `/reviews`, `/buyer-requests`, `/proposals`, `/disputes`, `/vendors.member_since`, `/withdrawals` emit MySQL `Y-m-d H:i:s` **with no timezone**. | Two parsers, and the client must *guess* the zone for the second group. |
| **C4** | **`status_label` is `ucfirst()` of the slug.** Order 1 returns `"status_label":"Pending_payment"` — untranslated, underscore visible. | Ships to users verbatim unless the app re-maps every status itself. |
| **C5** | **Order detail omits everything a detail screen needs.** The DB has `subtotal`, `addons_total`, `platform_fee`, `vendor_earnings`, `payment_status`, `revisions_included`, `revisions_used`, `delivery_deadline`, `refunded_amount`. **None are exposed.** | No price breakdown, and **no revision counter** — so the app cannot tell the buyer "1 of 2 revisions left". |
| **C6** | **`/vendors` leaks WordPress login names anonymously**, including the administrator's: `{"id":1,"username":"varundubey"}`. WP core deliberately omits `user_login` from `/wp/v2/users` for exactly this reason. Also, **the admin appears in the public seller directory** because user 1 has a vendor-profile row. | Free username enumeration. Drop `username` from the public payload. |
| **C7** | **`/vendors/{id}` and `/vendors/{id}/stats` return contradictory values for the same fields.** Same vendor, same batch: `completed_orders` 320 vs 1; `rating_average: 0` vs `average_rating: 5` — and the key is spelled differently in each. `stats.member_since` is `""` while the profile's is a real date. | The app cannot show a trustworthy seller card. |
| **C8** | **Seller-level enum does not join.** `/seller-levels` returns `new\|rising\|top_rated`; `/vendors/55/level` returns `pro`; `/seller-levels/pro` → 404. | A client that fetches the catalogue then a vendor's level cannot resolve it. |
| **C9** | **`/favorites` count and body disagree.** `X-WP-Total: 1` with a body of `[]` — the header counts the meta array, the body is a filtered `WP_Query`. An orphaned ID produces a phantom count. | "Favorites (1)" badge over an empty list. |
| **C10** | **HTML in JSON, three ways.** `/conversations/{id}/messages` ships a ~1 KB server-rendered `html` blob per message (≈5× payload bloat on the chattiest endpoint). `/reviews` ships `review` **and** `review_html` **and** `created_human` ("3 weeks ago" — wrong the moment it is cached, and unlocalizable). Category names arrive HTML-encoded (`"Programming &amp; Tech"`). | Strip and decode everywhere; the relative times must be recomputed client-side. |
| **C11** | **`/categories` and `/tags` are unbounded** — no `per_page`, no `page`, no total. `/search` has `per_page` but **no `page` at all**, capped at 50 per type. | Fails the big-site rule. Search cannot paginate, full stop. |
| **C12** | **Packages have no `id`** — `/services/{id}/packages` returns name/price/delivery only, while `POST /cart/add` requires an integer `package_id`. It is the array index. | Reorder packages in admin and every saved cart and deep link silently points at a different tier. |
| **C13** | **`/services/grid` returns rendered HTML** (`{html, pagination}`) from the mobile namespace. | Not usable natively — ignore it; it is a block/shortcode endpoint sharing the API. |
| **C14** | **`/favorites` service cards use a *third* shape** — `thumbnail` (string) not `images[]`, flat `price` not `pricing{}`, flat `rating` not `rating{}`. | A fourth adapter for the same card. |
| **C15** | **404 is used for two authorization/feature states** — `rest_not_vendor` and `wpss_realtime_disabled`. And **`rest_no_route` is overloaded four ways**: bad path, wrong method, Pro-unlicensed, typo. `/moderation/*` and `/audit-log` return **403 when anonymous** where every other protected route returns 401. | A client branching 401→re-auth will not re-auth on those two. |

### Big-list offenders that will bite first (the app hits two of these on every launch)

| # | Endpoint | Defect |
|---|---|---|
| **P1** | `GET /dashboard` | `count( get_posts([ 'posts_per_page' => -1 ]) )` — loads every service ID the vendor owns just to show a number (`API.php:464-474`) |
| **P2** | `GET /vendors/{id}/stats` | Same unbounded scan — and it is **public and unauthenticated** (`VendorsController.php:750-761`), so anyone can force a full post scan per hit |
| **P5** | `GET /orders/{id}/deliverables` | Unbounded `SELECT *` **plus** a nested N+1: `wp_get_attachment_url()` + `get_the_title()` + `get_post_mime_type()` per attachment per delivery (`OrdersController.php:576-602`) |
| **P7** | `GET /paypal-payouts/pending` (Pro) | Textbook N+1 — one query per vendor, no cap, then `usort` over the whole set. 500 vendors = 500+ queries (`PayPalPayoutsController.php:391-416`) |

Full list of 14 in the audit transcript; P1/P2/P5/P7 are the four to fix first.

---

## 6. The app build

Expo / React Native, expo-router, React Query, App-Password auth — the same stack as the Listora and Jetonomy apps, so `@wbcom/mobile-core` extraction stays viable.

### Phase A — foundation

| Stage | Scope |
|---|---|
| **A1 Scaffold** | Expo + expo-router + React Query with an AsyncStorage persister, `components/ui/` kit + tokens, `.maestro/` harness, EAS profiles. Copy the Listora app's shape. |
| **A2 Connect + gate** | Site URL discovery (`GET /wp-json`, check `namespaces` includes `wpss/v1`), then `GET /wpss/v1/settings` for the app contract. **Fail closed** on `app_enabled !== true`, unreachable, or malformed. Re-check on cold start and on resume. |
| **A3 Auth** | `POST /auth/login` → store the returned `Basic` token in SecureStore, keyed per site. Wire `/auth/devices` on login and `DELETE` on logout. Handle `429 rate_limit_exceeded` as a first-class state. |
| **A4 Theme** | Accent + logo from the public branding block (Phase 0.4). Validate the hex before use; compute foreground from luminance — do not assume white-on-accent. |

### Phase B — buyer (this is the revenue path; build it first)

| Stage | Scope | Endpoints |
|---|---|---|
| **B1 Discover** | Service grid, category chips, sort, infinite scroll, typeahead | `/services`, `/categories`, `/search` (no paging — cap at 50 and say so) |
| **B2 Service detail** | Gallery, packages, FAQs, reviews, seller card, portfolio | `/services/{id}` + `/packages` + `/faqs` + `/reviews/summary` + `/vendors/{id}` — **batch these** (C1) |
| **B3 Favorites** | Optimistic heart through a shared cache | `/favorites`, `/favorites/{id}` — proven writable with no nonce |
| **B4 Cart + checkout** | Cart, then hand off to web checkout (§4) | `/cart`, `/cart/add`, `/cart/checkout` |
| **B5 My orders** | List, status filter, detail, requirements form, accept delivery, request revision | `/orders`, `/orders/{id}`, `/requirements`, `/deliverables`, `/orders/{id}/{action}` — drive the action bar off `available_actions` |
| **B6 Messaging** | Order conversation + direct inbox, attachments | `/conversations`, `/conversations/{id}/messages`, `/orders/{id}/conversation` — strip the `html` blob (C10) |
| **B7 Reviews** | Write after completion; the gates are status=`completed`, reviewer=customer, one per order, inside the window (`ReviewService.php:43-63`) | `/orders/{order_id}/review` |

### Phase C — seller

| Stage | Scope | Notes |
|---|---|---|
| **C1 Sales queue** | Orders as vendor, status filter | Gate on a **fixed** `is_vendor` (blocker B3) |
| **C2 Deliver** | Upload + submit a delivery, handle revisions | **`POST /orders/{id}/deliverables`**, never the `deliver` action verb |
| **C3 Earnings** | Summary, ledger, withdrawal request | `/earnings/summary`, `/earnings/history`, `/withdrawals` — vendor-only, buyers get 403 |
| **C4 Services** | List own services, pause/edit | Full creation is a **6-step wizard** (`ServiceWizard.php:78-111`) — v1 should link out to web rather than rebuild it |
| **C5 Portfolio** | Add/reorder/feature items | `/portfolio`, `/vendors/{id}/portfolio` |

### Phase D — marketplace + ship

| Stage | Scope |
|---|---|
| **D1 Buyer requests** | Browse briefs, post one, submit a proposal. **Blocked on B2** (the detail endpoint fatals when authenticated). |
| **D2 Disputes** | Open, respond, view timeline. **Blocked on B1** (all three routes fatal). |
| **D3 Notifications + push** | In-app list works today (`/notifications`); native push needs the sender built (A3). **Only verifiable on a real build — never the dev client.** |
| **D4 Account** | Profile, **delete account** (blocker A1), **report/block** (blocker A2), legal links |
| **D5 Ship** | EAS, store listings, review submission |

### Explicitly out of scope for v1

Milestones and extensions (paid sub-orders — extra payment surface), tipping (§4 flag), service creation wizard (link out), analytics/subscriptions/recurring (all Pro, all currently 404), admin surfaces (moderation, dispute resolution, withdrawal approval, vendor approval — **none are reachable over REST at all**, they live entirely in wp-admin).

---

## 7. Recommended order

```
Phase 0 plugin work — nothing else starts first
  B1  /disputes/{id} fatal                      S   ← permission callback fatals for everyone
  B2  /buyer-requests/{id} fatal                S   ← authenticated users blocked, anonymous fine
  B3  is_vendor split-brain                     S   ← /me lies to every real vendor
  0.4 app-config on /settings                   S   ← seam already exists, one filter
  0.2 Pro 404 → 403 with a real code            M   ← plus why is_valid() fails here
  A1  DELETE /me                                M   ← Apple 5.1.1(v); cascade already built
  A2  report + block users                      L   ← Apple 1.2; concept absent entirely
  P1/P2 unbounded scans on /dashboard, /stats   S   ← app hits both on launch
  0.5 token expiry + login-with-app-password    S

Then A1–A4 → B1–B7 (buyer) → C1–C5 (seller) → D1–D5
  A3  push sender                               L   ← greenfield; real-build verification only
  C1–C15 contract polish                        rolling
```

**The four Small plugin fixes (B1, B2, B3, 0.4) unblock most of the app.** Start there.

---

## 8. Honest gaps in this plan

- **Data volume is tiny** — 12 services, 60 orders, 7 vendors, 19 users. Every big-site claim here is read from the code, not observed under load. Seed 2000+ services and 500+ users before trusting any list screen.
- **I could not test whether a `pending`/`draft` service leaks anonymously** — all 12 seeded services are published. That needs a seeded draft to prove either way. Stated as untested, not as safe.
- **Pro was never exercised**, because all 57 of its routes 404 on this site. Everything said about wallet, Stripe Connect, analytics, subscriptions and payouts is from source only.
- **The existing plugin docs are unreliable and should not be used as a second opinion.** `REST_API_MAPPING.md` (43 KB, dated 2026-02-03) contains **zero** occurrences of the string `wpss/v1`. Pro's `audit/manifest.json` declares the namespace as `wpss-pro/v1` when all 10 of its controllers actually register into `wpss/v1` — a client built from that manifest would 404 on every Pro route. Free's manifest miscounts endpoints and lists a controller that registers none. `audit/DUPLICATE-FLOWS-ui.md` is stale on the `myaccount/` templates. Trust the code.
- **`plans/1.2.0-SERVICE-TYPES.md` (122 KB) is entirely unimplemented** — no `service_type`, no bookings, no rentals, no digital downloads exist in code. Do not plan app screens from it.
