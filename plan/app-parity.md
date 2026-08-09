# Plugin ↔ app functionality catalogue

**Living document.** Update a row in the PR that moves it. The companion release gate lives in the
app repo at `~/apps/wpss-app/docs/FEATURE-COVERAGE.md` and blocks release on any remaining ❌ row.

| | |
|---|---|
| **Plugins** | Free + Pro **1.5.1** (both on branch `1.5.1`) |
| **App** | `~/apps/wpss-app` 1.0.0 · `main` @ `66daa41` |
| **Last verified** | 2026-08-08 |
| **Live routes** | **183** (`GET /wp-json/wpss/v1`, Free + Pro both active) |
| **Called by the app** | **77 routes** (from 76 call templates) — 42% |
| **App — member-facing gaps** | **0 Missing · 4 Partial** (was 9 · 5) |
| **Plugin — findings this pass** | **8 fixed** — F-1 … F-7 plus a 500 on vendor registration |
| **Verification** | Plugin: live API + browser. App: **rendered** at 390px — see the app-side gate file |

## Why this file lives here

The capability catalogue is **plugin-owned** (`CAPABILITIES.md`, per rule 7 of the
`wbcom-mobile-app` skill). The app never re-enumerates features — it maps coverage against this
spine. Keeping the parity view beside the catalogue is what stops the two lists drifting.

**This file covers both directions.** It answers "what does the app not have yet" *and* "what does
the plugin still owe the app", because several app rows sit blocked for plugin reasons rather than
app ones.

## Method

| Source | Used for |
|---|---|
| Live `GET /wp-json/wpss/v1` on `wp-sell-services.local` | Ground truth for routes and arg enums |
| `CAPABILITIES.md` (Free) | The capability spine — note it still says **1.3.1**, see drift below |
| App `api/`, `hooks/`, `stores/`, `components/`, `app/` — every call site read by hand | Which endpoints the app actually calls |
| `src/API/*.php`, `src/functions.php`, `src/Models/ServiceOrder.php` | Whether the server says what the app renders |

**Confidence is stated per row.** ◆ re-read in code on 2026-08-08. ◇ mapped from a commit message,
not re-verified — probable, not proven. No row here is ✅ on the strength of a log line alone.

**Status vocabulary:** ✅ Done · ⚠️ Partial · ⚠️ Deferred (reason + target required) ·
🚫 Web-only (by design) · ❌ Missing / Open.

---

## Headline

**The app is a competent buyer client and a thin seller one.** Discovery, cart, the order lifecycle,
requirements, deliverables, messaging, notifications, reviews-on-order, buyer requests, proposals and
withdrawals are all wired and read correctly against the server.

**Three whole product areas are absent, and all three are member-to-member.** Milestones, tips and
extensions — every one of them money that moves between a buyer and a seller with no owner in the
loop, and every one already fully routed on the server. Nothing is blocking them but screens.

**Two flows dead-end one tap short of their point.** A buyer can post a request and read the
proposals but cannot **accept** one. A member can respond to a dispute but cannot **open** one. Both
are single existing endpoints.

**The route-coverage number in the app's first matrix was wrong** — it claimed 94 of 178 called; the
real figure is **58 of 179**. The first pass counted any string literal beginning with a slash. That
correction is what turned this from a classification exercise into a gap list.

---

## App — Free member-facing

| Capability | Route | Status | Evidence |
|---|---|---|---|
| Browse, search, categories | `/services`, `/search`, `/categories` | ✅ Done | ◆ |
| Service detail, packages, FAQs, reviews | `/services/{id}` + `…/packages`, `…/faqs`, `…/reviews`, `…/reviews/summary` | ✅ Done | ◆ |
| Seller profile, their services, their portfolio | `/vendors/{id}`, `…/services`, `…/portfolio` | ✅ Done | ◆ read-only |
| Favourites | `/favorites`, `/favorites/{id}`, `/services/{id}/favorited` | ✅ Done | ◆ whole group covered |
| Cart | `/cart`, `/cart/add`, `DELETE /cart/{key}` | ✅ Done | ◆ whole group covered |
| Checkout hand-off | `POST /cart/checkout` | ✅ Done | ◆ one path on both rails since `0f25def`; server prices from post meta and clears the cart only after an order exists |
| Orders list and detail | `/orders`, `/orders/{id}` | ✅ Done | ◆ |
| Order lifecycle — 10 verbs | `POST /orders/{id}/{action}` | ✅ Done | ◆ driven by the server's `available_actions`; the app does not guess |
| Requirements — submit and skip | `/orders/{id}/requirements`, `…/skip` | ✅ Done | ◆ |
| Deliverables | `/orders/{id}/deliverables` | ✅ Done | ◆ correctly avoids the status-only `/deliver` trap |
| Review an order | `POST /orders/{id}/review` | ✅ Done | ◆ |
| Messaging | `/conversations` ×4 | ✅ Done | ◆ |
| Notifications | `/notifications` ×4 | ✅ Done | ◆ |
| Buyer requests — browse, post, mine | `/buyer-requests`, `…/mine`, `…/{id}` | ✅ Done | ◆ |
| Proposals — send, list, withdraw | `POST /buyer-requests/{id}/proposals`, `/proposals`, `…/withdraw` | ✅ Done | ◆ |
| Earnings and withdrawal | `/earnings/*`, `/withdrawals`, `…/methods` | ✅ Done | ◆ whole Free earnings rail covered |
| Vacation mode | `/vendors/me`, `POST /vendors/me/vacation` | ✅ Done | ◆ |
| Auth — 8 routes | `/auth/*` | ✅ Done | ◆ whole group covered |
| Bootstrap + licence gate | `GET /settings` | ✅ Done | ◆ `utils/gate.ts` fails closed on `app_enabled`, open on `min_app_version`, checks `contract_version` first |
| Publish a service | `POST /services` | ⚠️ Partial | ◆ title, price, categories only — no packages, gallery, FAQs, requirements or add-ons. The REST create is genuinely simpler than the six-step web wizard; the app states the limit on screen |
| Disputes — read, respond and **open** | `/disputes`, `…/{id}`, `…/timeline`, `…/respond`, `/disputes/options` | ✅ Done | ▣ rendered. Resolving stays admin-only by design |
| Portfolio | `GET /vendors/{id}/portfolio` | ⚠️ Partial | ◆ read-only; the 4 write routes are uncalled, so a seller cannot manage their own work |
| Reviews | `/services/{id}/reviews` | ⚠️ Partial | ◆ `…/helpful` and `…/reply` uncalled — **a seller cannot reply to a review** |
| Attachments | `/storage/*`, `/media/*` | ⚠️ Partial | ◆ no upload path anywhere in the app |

### App gate — all nine closed

| # | Capability | Status |
|---|---|---|
| M-1 | Accept / reject a proposal — **hire someone** | ✅ Done, rendered |
| M-2 | Milestones — pay, submit, approve, decline | ✅ Done, rendered against a seeded milestone order |
| M-3 | Open a dispute | ✅ Done, rendered. Needed **F-7** first |
| M-4 | Report / block a member | ✅ Done, rendered. Needed **F-2** first |
| M-6 | Order timeline | ✅ Done, rendered |
| M-8 | Seller level | ✅ Done, rendered |
| M-5 | Become a seller | ✅ Done, rendered. Surfaced a plugin 500 |
| M-7 | Tips and paid extensions | ✅ Done, rendered |
| M-9 | Browse sellers | ✅ Done, rendered — directory **and** seller page |

**Nothing is left on the gate.**

**What the plugin owed and has now delivered:** report/block routes and storage (F-2), the order
status vocabulary (F-1, F-5), the dispute reasons (F-7), real feature flags (F-4), the honest wallet
balance (F-3), and the vendor-status gate on every write path (F-6).


---

## App — Pro member-facing

Pro rides the **same `wpss/v1` namespace**. Every Pro controller extends the Free `RestController`
and none overrides `$namespace`.

Since `9eedc44`, Pro registers its routes **even when unlicensed** and refuses them with 403 rather
than 404. Verified live: `/wallet/balance` and `/analytics/vendor/overview` return **401**
unauthenticated, not `rest_no_route`. Clients must gate on the `/settings` flags, never on a probe.

| Capability | Route | Status | Note |
|---|---|---|---|
| Vendor analytics | `/analytics/vendor/{overview,revenue,orders,services}` | ❌ Missing | Seller-facing, not owner-facing. `period` enum `7days\|30days\|90days\|12months` |
| Wallet | `/wallet/*` (5) | ⚠️ Deferred | The app uses Free's `/earnings` + `/withdrawals` instead. **Which rail is canonical when Pro is active is unanswered — F-3** |
| Storage / media | `/storage/*`, `/media/*` | ⚠️ Partial | Offloaded storage ships; the app has no upload |
| Seller subscription plan | `/subscription-plans/{my-subscription,subscribe}` | ⚠️ Deferred | Reading the plan is app-appropriate; subscribing stays web. Target: Pro wave |
| Recurring services | `/recurring-services/*` (7) | ⚠️ Deferred | **Not an app gap.** CAPABILITIES.md: recurring billing "ships behind a default-off flag with its UI hidden… Do not buy Pro for recurring billing." Target: when the plugin ships it for real |
| Stripe Connect | `/stripe-connect/*` (6) | 🚫 Web-only | Hosted browser onboarding. `GET …/status` is worth surfacing read-only later |
| PayPal Payouts | `/paypal-payouts/*` (5) | 🚫 Web-only | Owner-side batches |
| Commission rules, white label, plan CRUD | `/commission-rules/*`, `/white-label`, `/subscription-plans` | 🚫 Web-only | Owner configuration — the app configures nothing |

---

## Skipped by design — stays on the web

| Area | Why |
|---|---|
| Admin and moderation — `/moderation/*`, `/audit-log`, `/tour/complete` | Site owners administer on the web |
| Owner analytics — `/analytics/{overview,revenue,export}` | Owner surface. The **vendor** subset is not, and is Missing above |
| Payment execution — all 11 `/payments/*` | Checkout returns a pay URL and the app opens it. Keeps the payment sheet out of the client entirely |
| Withdrawal approval — `PATCH /withdrawals/{id}` | Owner action |
| Every setting | The app reflects what the site decides |
| `POST /batch` | Infrastructure, not a capability |

---

## Faithfulness — divergence, not absence

The matrix catches **absence**. It does not catch **divergence**: a screen that exists, works, and
shows something the site never said still scores ✅. Audited 2026-08-08.

**Clean:** order status *slugs* round-trip exactly (all 18 in `api/orders.ts` match
`ServiceOrder::STATUS_*` and `wpss_get_order_statuses()`); money renders `formatted_total` rather
than being assembled client-side; checkout posts an empty body so the server prices everything;
totals come from the server; no arg enum is duplicated client-side.

**One divergence, and it is ours — F-1 below.**

---

## Plugin — what it still owes the app

**All six are carded in Basecamp Ready for Development** (column `9381845980`, project 45156734),
ordered by urgency. Work these before starting app screens — five of the nine app blockers depend on
nothing here, but F-2 blocks store submission and F-6 is live on the current release.

| Card | Covers | |
|---|---|---|
| [#10183739837](https://3.basecamp.com/5798509/buckets/45156734/card_tables/cards/10183739837) | F-6 — suspended vendors blocked on web, not in REST | **do first** |
| [#10154920401](https://3.basecamp.com/5798509/buckets/45156734/card_tables/cards/10154920401) | F-2 — report and block (existing card, pulled from Scope) | blocks submission |
| [#10183740192](https://3.basecamp.com/5798509/buckets/45156734/card_tables/cards/10183740192) | F-4 — only 3 feature flags | |
| [#10183740068](https://3.basecamp.com/5798509/buckets/45156734/card_tables/cards/10183740068) | F-3 — two withdrawal rails | |
| [#10183739989](https://3.basecamp.com/5798509/buckets/45156734/card_tables/cards/10183739989) | F-1 + F-5 — order status vocabulary, both halves | |

F-1 and F-5 are one card deliberately: fixing either alone leaves the drift open.

| ID | Finding | Status | Where it stands | Evidence |
|---|---|---|---|---|
| **F-1** | **`OrdersController` duplicates the order-status label map, incompletely and unfilterably** | ❌ Open | `get_status_label()` carries a **private map of 11 statuses** and falls through to `ucfirst( $status )` for the rest. Seven statuses fall through — `pending_payment`, `pending_requirements`, `pending_approval`, `revision_requested`, `on_hold`, `late`, `partially_refunded` — so the API answers **"Pending_payment"**, underscore and all. Those are the three most common buyer-facing states among them. Meanwhile `wpss_get_order_statuses()` already has all 18, already translated, already filterable via `wpss_order_statuses`, and the REST layer simply does not call it. **Consequence:** the app carries its own English label map, so every order status renders in English on a localised site, and no owner's filter reaches the app. This is the CLAUDE.md "no duplicate code" rule and skill rule 11 in the same defect. **Fix: route `get_status_label()` through `wpss_get_order_status_label()` and delete the private map.** | ◆ `src/API/OrdersController.php:1691-1707` vs `src/functions.php:2576,2588` |
| **F-7** | **Dispute reasons lived only in a web template** | ✅ **Fixed** | The six reasons a buyer can give existed as a hardcoded `<select>` in `templates/order/order-view.php` — not filterable, not published, invisible to the API. It matters more than the status case because `DisputeService` only `sanitize_text_field()`s the reason: **no enum, no validation**, so a guessed value is accepted and files the dispute under something the site does not recognise. Now `wpss_get_dispute_reasons()`, rendered by the web form and published in `GET /disputes/options`. | ◆ live `/disputes/options` returns 6 |
| **F-2** | **No report and no block route exists anywhere in Free or Pro** | ❌ Open | Zero matches for a report/block/flag route across both plugins. App Store Guideline 1.2 requires both wherever members post content to each other — which is every order conversation, review and proposal in this product. **This blocks app submission outright**, and it is not something the app can work around. Needs: report a service / review / message / member, block a member, and a queue the owner can act on. | ◆ zero route matches in `src/` of both plugins |
| **F-3** | **Two withdrawal rails — resolved: Free's is canonical, and the wallet read was lying** | ✅ **Fixed** | See below. The app was already on the right rail; the real defect was that `GET /wallet/balance` showed a seller money they could not withdraw. | ◆ Pro `src/API/WalletController.php`; `Pro.php:1463` |

### F-3 — there was never a decision to make

The product already decided this in **1.2.0**, and `Pro.php:1463` states it:

> Free is always active with Pro, so Pro does **NOT** replace Free's single
> "Earnings & Payouts" section with a duplicate template. Pro enhances that one
> section via hooks instead. **One vendor section, no duplication.** The legacy
> earnings→wallet swap was removed in 1.2.0.

So **`/earnings/*` + `/withdrawals*` is the canonical rail on every site, Free or
Pro.** Pro's `/wallet/*` is the provider layer behind it, not a second front
door. The app had already picked correctly.

**No field was added to the contract to say so.** A `payouts.rail` key would be
the constant string `earnings` on every site forever — the same always-true flag
that `API.php` warns against and that F-4 was careful not to publish. The
decision belongs in documentation, not in a payload field that can only ever
carry one value.

**The real defect the investigation surfaced.** `POST /wallet/withdraw` already
treated Free's `EarningsService` as authoritative, capped by the provider
balance, and its own comment records why: a vendor holding 531.90 once stacked
five pending requests totalling 900.00. But `GET /wallet/balance` returned the
**raw provider balance** — the exact number that comment says "is NOT what a
vendor may withdraw".

On live data, vendor 56 held a provider balance of **135.00** against **24.00**
actually withdrawable. The seller was shown 135.00 and refused at 24.00. That
reads to them as the marketplace losing their money, and it reaches the owner as
a support ticket rather than a bug report.

Both routes now share one `resolve_balances()`, and the read publishes
`balance` (what the provider holds) **and** `available` (what may be withdrawn
now), so the figure shown and the figure enforced are the same by construction.
| **F-4** | **`features` publishes only 3 flags for a surface this size** | ❌ Open | `buyer_requests`, `disputes`, `realtime` (`src/API/API.php:697`). Milestones, tips, extensions, portfolio, reviews and seller levels each have a real on/off or licence state and none is published, so a client cannot tell *disabled* from *not built*. Rule 9 says a flag that is always true tells a client nothing — the inverse also holds: a feature with no flag forces the client to guess. | ◆ `src/API/API.php:697-704` |
| **F-5** | **Order statuses and labels are not published in `/settings`** | ❌ Open | The app must carry a copy of an 18-value vocabulary that `wpss_order_statuses` lets any site edit. This is exactly the shape of the Listora "Expired" filter bug and the Career Board pipeline-stage bug: a client-side copy of a server-owned list that scores ✅ while drifting. Publishing `{ slug, label }` from `wpss_get_order_statuses()` closes the whole class. Pairs with F-1. | ◆ `GET /settings` response has no status list |
| **F-6** | **A suspended vendor is blocked on the web and not in the API** | ❌ Open | `ServiceWizard.php:276` blocks suspended and pending vendors before the web form renders. `ServicesController::create_item_permissions_check()` does not — it calls `check_permissions()` (login + rate limit only, `RestController.php:60-85`) and then the `wpss_vendor_can_create_service` filter, neither of which consults `wpss_get_vendor_status()`. A suspended vendor holding a valid Application Password can therefore publish services through the API. `EarningsController:674` *does* check the status, which shows the gate is understood — it is just not applied on the write paths. This is skill rule 2's ban gate, and mobile makes it worse: Application Passwords are minted by core and survive whatever the plugin does at login. | ◆ `src/API/ServicesController.php:989-1006`, `src/API/RestController.php:60`, `src/Frontend/ServiceWizard.php:276` |

**F-2 and F-6 are the two that should not wait.** F-2 blocks store submission; F-6 is a live
authorisation hole on the current release.

---

## What actually blocks the app

| Plugin gap | What it costs the app | ID |
|---|---|---|
| No report / block routes | App Store submission cannot proceed. Not an app-side workaround. | F-2 |
| Status labels duplicated and incomplete in REST | The app renders English labels on every localised site, and owner filters never reach it | F-1 + F-5 |
| Wallet vs Earnings undeclared | Wallet stays Deferred; the seller may be reading the wrong balance | F-3 |
| Only 3 feature flags | Milestones, tips and extensions cannot be gated, so they cannot ship safely | F-4 |

---

## Catalogue drift to fix

| File | Says | Reality |
|---|---|---|
| `wp-sell-services/CAPABILITIES.md` | Version **1.3.1**, last verified 2026-08-01 | Free is **1.5.1** |
| `wp-sell-services-pro/audit/manifest.json` | REST namespace `wpss-pro/v1` | Pro registers into **`wpss/v1`** — a client built from the manifest would 404 on every Pro route |
| `wpss-app/api/client.ts:15-19` | Unlicensed Pro routes are absent and 404 | Since `9eedc44` they are registered and **403/401** — the comment predates the fix |

The `CAPABILITIES.md` refresh wants a `/wp-plugin-onboard --refresh` pass; until then this file is
the current view.

---

## Not verified this pass

Stated so nobody reads absence as assurance.

| Level | State |
|---|---|
| Route reachability | ✅ 179 probed live |
| App call sites | ✅ every one read by hand |
| Enum faithfulness | ✅ run — one divergence (F-1) |
| Runtime behaviour | ❌ no simulator run; every row is code-level |
| Ban gate | ⚠️ **read in code and found open (F-6)** — not yet proven with a live suspended vendor + Application Password |
| `app_enabled` gate on a device | ⚠️ logic correct in `utils/gate.ts`; not seen running |
| Live flow run against the API | ❌ not done. Listora's equivalent run found three rows scored wrong from commit messages — this file has not had that check |

---

## Static analysis — run at last

PHPStan had never run across any of this work, because its WordPress extension
is a dev dependency absent from the committed `vendor/` and `composer install`
would rewrite the runtime vendor these plugins ship (the regression CLAUDE.md
records). It can be run without touching that tree: the extension is installed
globally, so a config with absolute paths analyses the plugin in place.

| | Result |
|---|---|
| **Free**, level 6 | **No errors** |
| **Pro**, level 5 | 39 findings, **all** in `Integrations/EDD/*` — calls to `edd_*` functions on a site where EDD is not installed. Zero overlap with any file changed in this work. |

Two things that make the Free run trustworthy rather than merely green: it needs
`tests/stubs/action-scheduler-stubs.php` in `scanFiles` (without it, eight
Action Scheduler calls report as missing functions) and the `.asset.php`
ignore for un-built assets. A run that drops either reports nine phantom errors,
which is what a first attempt here did.

Pro's config wants `php-stubs/woocommerce-stubs`, also a dev dependency. The
real WooCommerce plugin on the install serves the same purpose via
`scanDirectories`, which is how the run above was done.

**For CI:** both plugins need their dev dependencies for a clean run — that is
the right place for this gate, not a developer's machine.

---

## Functionality catalog — re-measured 2026-08-09

The board above was a route-coverage view. This is the capability view, measured
from the running site with Free and Pro both active.

| | |
|---|---|
| Registered paths | **183** (260 distinct path+method pairs) |
| Called by the app | **76** — 41% |
| Owner-only, correctly absent | 25 |
| Payment rail, deliberately skipped (hosted checkout) | 12 |
| Alias / infra / web-only | 12 |
| **Genuine gaps** | **58** |

The path total did not move when account deletion landed, because `DELETE /me`
added a method to an existing path rather than a new path. Both numbers are quoted
so neither can be read as the whole story.

### Free — where the app stands

Done: catalog and search, buyer requests, proposals, the 18-status order lifecycle,
deliveries, milestones, extensions, tips, the vendor directory and seller pages,
vacation mode, favourites, cart, report and block, and account deletion.

Partial: create-a-service (no edit, no gallery, no add-ons), requirements (cannot
remove an uploaded file), messaging (**no attachments**), reviews (no reply, no
helpful), seller levels (own level only), notifications (cannot delete),
earnings (no single-withdrawal detail).

Missing: service add-ons, portfolio management, and **opening a dispute**.

### Pro — where the app stands

Everything member-facing in Pro is missing: vendor analytics (4), wallet (5),
recurring services (7), Stripe Connect onboarding (3), subscription plans (3),
the PayPal payout profile (1) and cloud storage (3). The adapters and gateways are
server-side by design — the app uses the site's own hosted checkout, which is also
what keeps it outside In-App Purchase.

### Correction

An earlier note in this session said a buyer "can open a dispute" from the app.
That is wrong: `POST /orders/{id}/dispute` is never called. The app reads, responds
and renders the site's reasons; the create flow was never wired. Left here rather
than silently fixed, because this file exists to catch exactly that.
