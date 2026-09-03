# WP Sell Services — Feature scorecard

**Pass date:** 2026-09-03  
**Criteria:** [`PLUGIN-AUDIT-CRITERIA.md`](PLUGIN-AUDIT-CRITERIA.md)  
**Tree:** Free + Pro working copy (headers still `1.7.0`; behaviour is 1.7.1-level)  
**Levels used:** Code-flow + contract suite + Playwright browser smoke  
**Rails:** Standalone (default), WooCommerce (switched), EDD + FluentCart (activated + `wpss_pro_beta_rails`)

Scores: **Good** · **Acceptable** · **Bad** · **Improve** · **Skip**

---

## Executive summary

| Pillar | Verdict |
|--------|---------|
| **Stable (money/orders)** | Strong — contract suite green; refund seam, tax, mark-as-paid, disputes, Woo line money hold |
| **Plug-and-play** | Mixed — first-run surfaces OK; uninstall pages gap; preflight blocked by stale debug fatals; beta rails honest-but-incomplete |
| **Developer-friendly** | Mixed — hooks/OpenAPI mostly honest; REST docs still lie about FluentCart pay-order; dual notification maps |

**Release-blocking Bad (fix before calling 1.7.1 done):**

1. Vendor **Sales** “Active” stat ≠ “Active” chip (two definitions of Active on one screen)
2. **EDD beta** still cannot create downloads, refund, or pay-order
3. **FluentCart beta** still needs manual product link for catalog sales
4. **REST docs** say FluentCart has no pay-order (code has `FluentCartPayOrderResolver`)
5. **Notification toggles**: `cancellation_requested` → `notify_cancellation_requested` (email) vs `notify_order_cancelled` (in-app)
6. **CLI**: `wp wpss scale teardown` and Pro `wpsspro test:flow` can write/wipe without Free’s production Guard
7. **Uninstall “delete data”** leaves mapped `wpss_pages` pages
8. Vendor admin search / stats cron still keyed on legacy `_wpss_is_vendor` meta after profile became authority

---

## G0 — Install / upgrade / uninstall

| Feature | Code | Browser | Score | Evidence |
|---------|------|---------|-------|----------|
| Activate Free+Pro, no white screen | ✓ | ✓ front 200 | **Good** | Browser guest/services; plugins active |
| Schema / retired tables | ✓ preflight | — | **Acceptable** | 4 retired tables warn with leftover rows (buyer_requests, faqs, …) |
| Preflight clean | ✗ | — | **Improve** | FAIL: 20 stale fatals in `debug.log` (not live); 570 WPSS log lines from contract suite |
| Demo delete scope | ✓ | — | **Good** | `_wpss_demo_content` default; `--all` gated |
| CLI production Guard (seed/demo) | ✓ | — | **Good** | `CLI/Guard.php` |
| `scale teardown` | ✓ | — | **Bad** | Confirm only — no `Guard::writes` / `--force` |
| Uninstall deletes CPT | ✓ | — | **Acceptable** | Services/requests removed when toggle on |
| Uninstall deletes mapped pages | ✗ | — | **Bad** | `uninstall.php` never walks `wpss_pages` |
| Requirements/addons one store | ✓ | — | **Good** | Tables retired; meta is authority |

## G1 — Owner setup

| Feature | Code | Browser | Score | Evidence |
|---------|------|---------|-------|----------|
| Settings write↔read (timeout, decimals, min/max, review window) | ✓ | — | **Good** | Former orphans wired; launch-notices contract PASS |
| Become-a-vendor copy honest | ✓ | ✓ | **Good** | No “unlimited”; “up to 100 service listings…” |
| Registration page not wp-login | ✓ | — | **Good** | registration-page contract PASS |
| Offline named methods frozen on order | ✓ | — | **Good** | offline-methods contract PASS |
| Beta rails opt-in | ✓ | — | **Good** | Off by default; filter names in UI |

## G2 — Catalog & discovery

| Feature | Code | Browser | Score | Evidence |
|---------|------|---------|-------|----------|
| Services archive | — | ✓ | **Good** | 12 cards, h1 “All Services”, no fatal |
| Vendors directory | — | ✓ | **Good** | 12 vendor cards |
| Single service packages | — | ✓ | **Good** | Basic/Standard/Premium |
| Add-ons visible without opening modal | — | ✓ | **Good** | “add-ons available from $25.00” on logo service |
| Admin vendor search via `_wpss_is_vendor` | ✗ | — | **Bad** | `API.php` still meta-keys after profile authority |
| Cron vendor stats meta-scoped | ✗ | — | **Bad** | `OrderWorkflowManager` `_wpss_is_vendor=1` only |

## G3 — Vendor lifecycle

| Feature | Code | Browser | Score | Evidence |
|---------|------|---------|-------|----------|
| `wpss_is_vendor()` = active profile | ✓ | ✓ | **Good** | Caps split live; vendor dash Selling nav |
| Proposals + Reviews sections | ✓ | ✓ | **Good** | Nav + pages render; vendor-dashboard-sections PASS |
| Sales filter + search | ✓ | ✓ | **Good** | Chips + search present |
| Sales Active card vs Active chip | ✗ | ✓ | **Bad** | Card: `in_progress`+`pending_approval` only (`OrderRepository:602`). Chip: 9 statuses (`orders.php:700-712`). Live: card **1**, chip **2** |
| Legacy `_wpss_is_vendor` still written | ✓ write-only | — | **Improve** | Residue invites second truth |
| Self-proposal on own request | — | ✓ | **Improve** | UI shows “Vendor self-request… Self proposal via REST” — self-deal still possible via REST |

## G4 — Checkout / rails

| Feature | Code | Browser | Score | Evidence |
|---------|------|---------|-------|----------|
| Standalone cart page | — | ✓ | **Good** | `/service-cart/` empty state, no fatal |
| Cart tax = charged tax | ✓ | — | **Good** | checkout-tax 34/34 |
| Guest checkout account seam | ✓ | — | **Good** | guest-checkout contract PASS |
| Woo line money (addons/coupons/refunds) | ✓ | — | **Good** | woo-line-money 21/21 |
| Woo pay-order read-only listing | ✓ | — | **Good** | pay-order-read-only 24/24 |
| Woo fee-only Subtotal $0.00 | ✓ (rail=woo) | — | **Good** | fee-only 6/6 |
| EDD create download | ✗ | — | **Bad** | No create path; beta incomplete |
| EDD refund / pay-order | ✗ | — | **Bad** | No seam |
| FluentCart pay-order | ✓ | — | **Acceptable** | Resolver exists; still beta |
| FluentCart auto product link | ✗ | — | **Bad** | Manual link required for catalog |
| FluentCart money units | ✓ | — | **Good** | beta-rails contract PASS |
| Docs: “EDD and FluentCart have no pay-order” | ✗ | — | **Bad** | `rest-api-controllers.md:219` lies about FC |

## G5 — Order machine

| Feature | Code | Browser | Score | Evidence |
|---------|------|---------|-------|----------|
| Buyer cannot refund self | ✓ AJAX | — | **Good** | Permission denied; allow map |
| mark_as_paid idempotent | ✓ | — | **Good** | contract PASS |
| Dispute close restores order | ✓ | — | **Good** | dispute-state-machine PASS |
| Dispute link on order | — | ✓ | **Good** | “View Dispute” on order 3579 |
| Revisions stamped on create | ✓ | — | **Good** | walk + create path |
| Order files private | ✓ | — | **Good** | order-files contract PASS |
| Tip commission dual formula | ✓ | — | **Improve** | TippingService local recalc fallback |

## G6 — Money & trust

| Feature | Code | Browser | Score | Evidence |
|---------|------|---------|-------|----------|
| Gateway refund seam (Stripe/PayPal/Razorpay/offline) | ✓ | — | **Good** | gateway-refund-seam ALL PASS |
| Partial refunds + locked commission | ✓ | — | **Good** | partial-refund-contract PASS |
| Ledger = profile earnings | ✓ | — | **Good** | ledger-balance-contract PASS |
| Payout integrity (Connect / PayPal Payouts / wallets) | ✓ | — | **Good** | payout-integrity 21/21 |
| Offline refund copy (manual pending) | ✓ | — | **Good** | walk regressions PASS |
| Cancellation email vs in-app toggle | ✗ | — | **Bad** | Email→`notify_cancellation_requested`; Notif→`notify_order_cancelled` |

## G7 — Requests & proposals

| Feature | Code | Browser | Score | Evidence |
|---------|------|---------|-------|----------|
| Proposal accept once + tax | ✓ | — | **Good** | partial-refund + walk |
| Vendor proposals list | — | ✓ | **Good** | `/dashboard/proposals/` |
| Self-deal guard | partial | ✓ | **Improve** | Self proposal visible in list |

## G8 — Developer surface

| Feature | Code | Browser | Score | Evidence |
|---------|------|---------|-------|----------|
| Contract / walk suites runnable | ✓ | — | **Good** | All listed Free+Pro contracts PASS this pass |
| OpenAPI Free+Pro tagged | ✓ | — | **Acceptable** | Tier tags; still ships Pro in Free tree |
| Hooks docs mark unfired names | ✓ | — | **Acceptable** | Honest debt notes |
| REST controller docs vs FC | ✗ | — | **Bad** | See G4 |
| Dual notification type→setting maps | ✗ | — | **Bad** | EmailService vs NotificationService |
| User pref category maps duplicated | ✓ | — | **Improve** | Drift risk |

## PX — Pro overlays

| Feature | Code | Browser | Score | Evidence |
|---------|------|---------|-------|----------|
| License gate per Pro controller (Free payments live) | ✓ | — | **Good** | `Pro.php:878-908` |
| License lapse copy (marketplace keeps running) | ✓ | — | **Good** | License page |
| Cloud storage fail → WP_Error + key | ✓ | — | **Acceptable** | Drivers return errors; live bucket not exercised |
| Analytics cache / DO migrate / CSS tokens | ✓ | — | **Good** | pro-perf 10/10 |
| Razorpay settle shared path | ✓ | — | **Good** | settle-path core PASS (rail AJAX probe polluted by imagick stderr) |
| Pro `test:flow` no production guard | ✗ | — | **Bad** | Writes without Guard |
| Push tokens written Free / sent Pro | ✓ | — | **Acceptable** | Intentional split |

## Cross-cuts

| Feature | Score | Evidence |
|---------|-------|----------|
| 390px dashboard no horizontal overflow | **Good** | Playwright mobile |
| Buyer My Orders stats vs chips | **Good** | Total 28 = Active 10 + Completed 3 + Awaiting 5 + Disputed 2 + Cancelled 8 |
| Vendor Sales stats vs chips | **Bad** | Active 1 vs 2 (see G3) |
| Debug log protocol | **Improve** | Preflight FAIL on historical fatals — clear log before release gate |

---

## Contract suite snapshot (this pass)

**Free (all passed):** mark-as-paid, gateway-refund, checkout-tax, dispute-state, partial-refund, vendor-dashboard-sections, walk-regressions, ledger-balance, order-files, offline-methods, guest-checkout, launch-notices, vendor-benefits, registration-page  

**Pro (all passed when rail correct):** pay-order-read-only, woo-line-money, payout-integrity, beta-rails, pro-perf, fee-only (on `woocommerce`)

---

## Recommended fix order

1. **One “Active” definition** for vendor sales card + chips (use `wpss_get_order_status_groups()['active']` in `get_vendor_stats`)
2. **One notification map** shared by Email + Notification services
3. **CLI Guard** on `scale teardown` + Pro `test:flow`
4. **Uninstall** mapped pages when delete-data is on
5. **Vendor queries** use profile/`wpss_is_vendor()`, drop meta dependency
6. **Docs** — FluentCart pay-order line; refresh coverage-matrix past 1.3.0
7. **EDD/FluentCart** — either finish catalog/pay/refund or keep beta + docs that match reality

---

## Confidence

| Area | Confidence |
|------|------------|
| Money / refunds / tax / Woo pay-order | **High** (contracts + code-flow) |
| Vendor/buyer dashboard chrome | **High** (browser) |
| EDD/FluentCart end-to-end sale | **Low** — adapters load; full purchase not walked |
| Cloud storage download after license lapse | **Medium** — code + copy only |
| Fresh empty WP 15-minute first sale | **Not run this pass** — recommend dedicated G0 sandbox |

This is **not** “zero bugs.” It is a scored catalog with explicit Bad items and evidence.
