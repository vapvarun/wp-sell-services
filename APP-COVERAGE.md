# App coverage — what the mobile app reaches

Team-facing. For the buyer-facing "can it do X" answers, read
[`CAPABILITIES.md`](CAPABILITIES.md) instead — that one is written to be read
before purchase and deliberately carries no route detail.

**Measured 2026-08-09** from a running site with Free **and** Pro active.
Regenerate rather than edit — see [Re-measuring](#re-measuring) at the bottom.

| | |
|---|---|
| REST paths registered | **183** (260 distinct path+method pairs) |
| Called by the app | **76** — 41% of paths |
| **Genuine gaps** | **58** |
| Database tables | 26 — 19 free, 7 pro |
| App screens | 23, across 13 API modules |

---

## The 41% is mostly deliberate

Quoting coverage alone makes a boundary we chose on purpose look like neglect.
Of the 107 paths the app does not call:

| | Count | Why |
|---|---:|---|
| Owner-only | 25 | Moderation queues, commission rules, audit log, white-label, payout runs. A member's phone has no business calling them. |
| Payment rail | 12 | The app opens the marketplace's own hosted checkout instead of embedding a card form — which is also what keeps it outside In-App Purchase. |
| Alias | 6 | Duplicate paths the app reaches under another name. |
| Infrastructure | 3 | Namespace index, batch endpoint, realtime auth. |
| Web-only | 3 | Server-rendered grid, web dashboard stats, the onboarding tour. |
| **Gap** | **58** | Member-facing capability the plugins serve and the app cannot reach. |

Every one of the app's 87 call sites matched a real route with a method that
exists, so the app currently calls nothing that isn't there.

---

## Free — capability by capability

| Capability | App | Note |
|---|---|---|
| Service catalog & search | ✅ Done | Browse, search, categories, detail, packages, FAQs, reviews |
| Service add-ons | ❌ Missing | Add-ons exist on every service; a buyer in the app cannot select any paid extra |
| Create a service | ⚠️ Partial | Create only — no edit, no gallery upload, no add-on management |
| Buyer requests | ✅ Done | Browse, post, own requests, detail |
| Proposals | ✅ Done | Submit, accept, reject, withdraw |
| Order lifecycle (18 statuses) | ✅ Done | Labels come from the server's vocabulary, never mapped client-side |
| Requirements | ⚠️ Partial | Submit and skip; cannot remove an uploaded file |
| Deliveries & revisions | ✅ Done | Fetch and submit deliverables |
| Milestone contracts | ✅ Done | List, pay, submit, approve, decline. Lock-step enforced server-side |
| Paid extensions | ✅ Done | List, request, decline. Accepting is paying |
| Tipping | ✅ Done | Read and send |
| Messaging | ⚠️ Partial | Threads, read receipts, unread counts — **no attachments** |
| Reviews | ⚠️ Partial | Read and leave; no reply, no helpful |
| Disputes | ❌ Read-only | **Cannot be opened from the app.** No create, evidence, escalate or cancel |
| Vendor directory & profiles | ✅ Done | Paginated directory, seller page, services, portfolio |
| Portfolio management | ❌ Missing | A seller cannot add, reorder or feature their own work |
| Seller levels | ⚠️ Partial | Own level only; the ladder itself is never fetched |
| Vacation mode | ✅ Done | |
| Favorites | ✅ Done | |
| Notifications | ⚠️ Partial | List, read, read-all, counts; cannot delete |
| Cart & checkout | ✅ Done | Cart in-app, payment handed to the site's checkout |
| Earnings & withdrawals | ⚠️ Partial | Summary, history, methods, request; no single-withdrawal detail |
| Report & block members | ✅ Done | Report a listing or a person; block, unblock, managed list |
| Delete your account | ✅ Done | Apple 5.1.1(v). Two-step, password-confirmed |
| Account standing | — Server | Suspension/closure gate the API; no app surface wanted |
| Moderation · audit log · manual orders | — Owner | Correctly absent |

Free also ships 19 shortcodes, 8 blocks, 5 WP-CLI commands, 37 email templates
and a Pusher-compatible realtime layer (off by default) — all web-side.

---

## Pro — capability by capability

| Capability | App | Note |
|---|---|---|
| Vendor analytics | ❌ Missing | 4 routes, no screen. A seller cannot read their own numbers. Largest single gap |
| Wallet | ❌ Missing | Balance, transactions, providers, withdraw — across 4 wallet providers |
| Recurring services | ❌ Missing | 7 routes. A buyer cannot see, pause, resume or cancel a subscription they are billed for |
| Stripe Connect (seller) | ❌ Missing | A seller cannot connect the account they get paid into |
| Vendor subscription plans | ❌ Missing | Browse, subscribe, own subscription |
| PayPal payout profile | ❌ Missing | The seller's own payout details |
| Cloud storage | ❌ Missing | S3 / GCS / DO Spaces. Tied to the attachment gap |
| Cart adapters (Woo · EDD · FluentCart · SureCart) | — Server | Decides which rail owns payment; the app follows what the site publishes |
| Gateways (Stripe · PayPal · Razorpay) | — By design | Hosted checkout, not an in-app card form |
| Admin analytics · commission rules · white label · ledger export | — Owner | Correctly absent |

---

## The 58 gaps, grouped

| Group | Count |
|---|---:|
| Recurring subscriptions | 7 |
| Vendor detail & stats | 7 |
| Cloud storage & media | 5 |
| Wallet | 5 |
| Vendor analytics | 4 |
| Portfolio management | 4 |
| Dispute actions (incl. **create**) | 4 |
| Stripe Connect | 3 |
| Subscription plans | 3 |
| Reviews (reply, helpful, detail) | 3 |
| Seller levels | 2 |
| Service add-ons | 2 |
| Proposals | 2 |
| Odds and ends | 7 |

---

## Corrections

Kept rather than quietly fixed, because catching this kind of drift is the whole
point of the file.

- **A buyer cannot open a dispute from the app.** An earlier summary said they
  could. `POST /orders/{id}/dispute` is never called — the app reads, responds and
  renders the site's reasons, but a dispute can only be started on the web.
- **260, not 521, path+method pairs.** WordPress registers several internal
  handler entries per route (`/me` has four handlers but two methods), so summing
  `count($handler["methods"])` across them double-counts.

## Never verified

No run on a real device. No run on a localised site — which is the entire point of
the status-label and vocabulary work. No run on the EDD, FluentCart or SureCart
rails, where four features are supposed to disappear from the app. No dark-mode
pass. What has happened is browser rendering at 390px against a seeded local site,
plus route-level checks.

---

## Re-measuring

The numbers live in the app repo's `scripts/coverage.py`, which reads what the
server really registers and what the app really calls, and prints the split.

```bash
# 1. From a running site with BOTH plugins active:
wp eval '
  do_action("rest_api_init");
  foreach (rest_get_server()->get_routes() as $p => $hs) {
    if (strpos($p, "/wpss") !== 0) continue;
    $m = array();
    foreach ($hs as $h) foreach (array_keys($h["methods"]) as $k) $m[$k] = 1;
    echo implode(",", array_keys($m)) . " " . $p . "\n";
  }' --path=/path/to/wordpress | sort > routes.txt

# 2. In the app repo:
python3 scripts/coverage.py /path/to/routes.txt
```

Counts differ where different plugins are enabled — the four `wpss-pro/v1`
cart-adapter routes only exist when SureCart or FluentCart is installed, and are
not counted here.
