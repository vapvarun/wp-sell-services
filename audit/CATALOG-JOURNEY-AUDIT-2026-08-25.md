# Catalog / first-install issue log — 2026-08-25

> **Before acting on this, reconcile it against the Basecamp board.** Two of its
> findings (ISS-002 `/become-vendor/` and ISS-004 seller-level dropdown) were
> re-filed as new cards on 2026-08-25 when both were already triaged — one was
> fixed, the other did not reproduce on a clean install. A third of the findings
> here were addressed during the 1.7.0 sweep. Re-running this audit without
> checking existing cards will regenerate work that is already done.


**Scope:** Find-only. UX issues, functionality gaps, UX gaps, API gaps, security/privacy/legal issues.  
**No fixes in this pass.** Development tracks work via Basecamp **Bugs**.  
**Site:** `http://wss.local` · Free **1.6.0** + Pro **1.6.0** · WooCommerce active · WPSS rail **standalone**  
**Method:** Playwright journeys (admin / buyer / vendor) + doc/code cross-check + parallel catalog agents (still running for remaining rows).

**Basecamp:** [Bugs column](https://app.basecamp.com/5798509/buckets/45156734/card_tables/columns/9381846253) · Project `45156734`

---

## How to read this log

| Field | Meaning |
|---|---|
| **Type** | `ux` · `func` · `docs` · `privacy` · `legal` · `api` · `security` |
| **Severity** | P0 blocker · P1 high · P2 medium · P3 low |
| **Owner expect** | What a site owner (or their members) reasonably assumes |
| **Actual** | What we observed |
| **Card** | Basecamp Bugs card URL |

Agents continue catalog rows (orders, tips, disputes, REST IDOR, etc.). New findings append under **Changelog** and get new Bugs cards.

---

## Issue register

### ISS-001 · Setup wizard creates 4 pages; Settings needs 6
- **Type:** ux / func · **Severity:** P1  
- **Who:** Site owner, first install  
- **Owner expect:** Finishing the Setup Wizard leaves every required marketplace page created and mapped.  
- **Actual:** Wizard Create Pages step only covers Services, Dashboard, Become a Vendor, Service Checkout. Settings → Pages also requires Vendors Directory + Service Cart. `initial-setup.md` still says “4 pages.”  
- **Repro:** Activate WPSS → Setup Wizard → Create Pages → compare Sell Services → Settings → Pages.  
- **Card:** https://app.basecamp.com/5798509/buckets/45156734/card_tables/cards/10235849730  

### ISS-002 · `/become-vendor/` 404 — real slug is `/become-a-vendor/`
- **Type:** ux / func · **Severity:** P1  
- **Who:** Member / support following intuitive URL or old docs  
- **Owner expect:** Become-vendor URL works or every link uses the real permalink.  
- **Actual:** `/become-vendor/` → Page not found. Installer slug is `become-a-vendor`.  
- **Repro:** Open `http://{site}/become-vendor/` vs Settings → Pages → Become a Vendor permalink.  
- **Card:** https://app.basecamp.com/5798509/buckets/45156734/card_tables/cards/10236298979  

### ISS-003 · Become a Vendor page promises unlimited listings + analytics
- **Type:** ux / docs · **Severity:** P1  
- **Who:** Buyer deciding to sell  
- **Owner expect:** Marketing matches Free capabilities; Pro benefits labeled.  
- **Actual:** Copy: “Build unlimited service listings…” and “Analytics dashboard…”. Free caps services (default 20); full analytics is Pro.  
- **Repro:** Open mapped Become a Vendor page as non-vendor.  
- **Card:** https://app.basecamp.com/5798509/buckets/45156734/card_tables/cards/10235849910  

### ISS-004 · Commission Seller Level options = Level 1 / Level 2
- **Type:** ux / func · **Severity:** P1  
- **Who:** Owner configuring Pro tiered commission  
- **Owner expect:** Dropdown names match vendor badges (New / Rising / Top Rated / Pro Seller).  
- **Actual:** Options show Level 1, Level 2, Top Rated.  
- **Repro:** Settings → Commission & Tax → Commission Rules → Seller Level.  
- **Card:** https://app.basecamp.com/5798509/buckets/45156734/card_tables/cards/10236299217  

### ISS-005 · Auto-withdrawal copy says vendors are “automatically paid”
- **Type:** ux · **Severity:** P1  
- **Who:** Owner configuring Free payouts  
- **Owner expect:** Copy matches behavior (schedule creates requests unless Connect/PayPal Payouts pay out).  
- **Actual:** UI says automatically paid / processes withdrawals; Free mainly creates withdrawal requests.  
- **Repro:** Settings → Payouts → Enable Auto-Withdrawal help text.  
- **Card:** https://app.basecamp.com/5798509/buckets/45156734/card_tables/cards/10235851403  

### ISS-006 · Woo + Standalone: empty `/cart/` vs filled `/service-cart/`
- **Type:** ux / func · **Severity:** P1  
- **Who:** Owner with Woo installed; buyers using theme Cart  
- **Owner expect:** One clear cart for service orders, or a clear warning.  
- **Actual:** Standalone rail: Woo `/cart/` empty; WPSS `/service-cart/` holds the item.  
- **Repro:** Platform=Standalone, Woo active → buyer Continue to checkout → compare `/cart/` and `/service-cart/`.  
- **Card:** https://app.basecamp.com/5798509/buckets/45156734/card_tables/cards/10235851469  

### ISS-007 · “Wallet Provider / Internal Wallet” naming
- **Type:** ux · **Severity:** P2  
- **Who:** Owner comparing Free vs Pro  
- **Owner expect:** Clear that Free already has earnings + withdrawals; Pro wallets are integrations.  
- **Actual:** Payouts “Wallet Provider = Internal Wallet”; vendor screen “Wallet Transactions.”  
- **Repro:** Settings → Payouts; vendor → Earnings & Payouts.  
- **Card:** https://app.basecamp.com/5798509/buckets/45156734/card_tables/cards/10235851532  

### ISS-008 · Pages dropdowns duplicate Checkout / Cart / Dashboard
- **Type:** ux · **Severity:** P1  
- **Who:** Owner mapping pages with Woo active  
- **Owner expect:** Can pick the WPSS page without confusing Woo pages.  
- **Actual:** Multiple Checkout (checkout, checkout-2, checkout-3), Cart vs Service Cart, two Dashboards.  
- **Repro:** Settings → Pages dropdowns with Woo active.  
- **Card:** https://app.basecamp.com/5798509/buckets/45156734/card_tables/cards/10235853234  

### ISS-009 · Catalog “Pro unlimited services per vendor” vs default 20
- **Type:** docs / func · **Severity:** P1  
- **Who:** Owner buying Pro for unlimited listings  
- **Owner expect:** Catalog/CAPABILITIES match product.  
- **Actual:** Catalog + Pro CAPABILITIES say Unlimited; shared `max_services_per_vendor` stays 20 unless changed; Pro does not auto-raise.  
- **Repro:** Read feature-catalog vs Settings → Vendors max services with Pro active.  
- **Card:** https://app.basecamp.com/5798509/buckets/45156734/card_tables/cards/10235853369  

### ISS-010 · Stale `marketing/comparison/free-vs-pro.md`
- **Type:** docs · **Severity:** P1  
- **Who:** Buyer before purchase  
- **Owner expect:** Marketing matches 1.6.0 canonical free-vs-pro.  
- **Actual:** Still SureCart, AI wizard features, tips commission-free, wrong license expiry, wrong video/seller-level claims.  
- **Repro:** Diff marketing file vs `docs/website/getting-started/free-vs-pro.md`.  
- **Card:** https://app.basecamp.com/5798509/buckets/45156734/card_tables/cards/10235853446  

### ISS-011 · Two “Vendors” destinations (settings vs list)
- **Type:** ux · **Severity:** P2  
- **Who:** Owner looking for registration / max services  
- **Owner expect:** Distinct labels for vendor settings vs vendor list.  
- **Actual:** Both titled Vendors; easy to open the list instead of settings.  
- **Repro:** Settings sidebar Vendors vs Sell Services → Vendors.  
- **Card:** https://app.basecamp.com/5798509/buckets/45156734/card_tables/cards/10235853527  

### ISS-012 · Cloud storage never receives delivery files
- **Type:** func · **Severity:** P1  
- **Who:** Owner who configured S3/GCS/DO Spaces  
- **Owner expect:** Deliverables go to configured cloud after settings save/test.  
- **Actual:** Settings save/test OK; uploads still local disk.  
- **Card:** https://app.basecamp.com/5798509/buckets/45156734/card_tables/cards/10236009182  

### ISS-013 · No WP privacy exporter/eraser — docs claim GDPR
- **Type:** legal / docs · **Severity:** P1  
- **Who:** Owner facing GDPR / privacy tools  
- **Owner expect:** Privacy export/erase tools work as documented.  
- **Actual:** No exporter/eraser registered; docs claim GDPR integration.  
- **Card:** https://app.basecamp.com/5798509/buckets/45156734/card_tables/cards/10236009758  

### ISS-014 · Order-requirement uploads in public uploads tree
- **Type:** privacy / security · **Severity:** P1  
- **Who:** Buyers uploading order requirements  
- **Owner expect:** Files private as docs state.  
- **Actual:** Land in public uploads tree.  
- **Card:** https://app.basecamp.com/5798509/buckets/45156734/card_tables/cards/10236010457  

### ISS-015 · Analytics Export PDF always HTTP 400
- **Type:** func · **Severity:** P1  
- **Who:** Owner/vendor exporting analytics  
- **Owner expect:** PDF download works.  
- **Actual:** Always HTTP 400 — filename rejected by download handler.  
- **Card:** https://app.basecamp.com/5798509/buckets/45156734/card_tables/cards/10236011401  

### ISS-016 · Menu Visibility docs inverted (tick-to-SHOW vs tick-to-hide)
- **Type:** docs / ux · **Severity:** P2  
- **Card:** https://app.basecamp.com/5798509/buckets/45156734/card_tables/cards/10236012656  

### ISS-017 · Docs invent `wpss_email_data`, omit real filter
- **Type:** docs / api · **Severity:** P2  
- **Card:** https://app.basecamp.com/5798509/buckets/45156734/card_tables/cards/10236013288  

### ISS-018 · hooks-filters.md says `wpss_extension_approved` dead — it fires
- **Type:** docs / api · **Severity:** P2  
- **Card:** https://app.basecamp.com/5798509/buckets/45156734/card_tables/cards/10236013836  

### ISS-019 · theme-integration.md snippet duplicates vendor card
- **Type:** docs · **Severity:** P2  
- **Card:** https://app.basecamp.com/5798509/buckets/45156734/card_tables/cards/10236014483  

### ISS-020 · README false Pro promises (recurring + REST rate limiting)
- **Type:** docs · **Severity:** P2  
- **Card:** https://app.basecamp.com/5798509/buckets/45156734/card_tables/cards/10236015292  

### ISS-021 · Vendor Analytics has no export (admin-only CSV/PDF)
- **Type:** ux / func · **Severity:** P2  
- **Who:** Vendor / owner expecting Pro “analytics with export” for sellers  
- **Owner expect:** Vendors can export their analytics, or docs say export is admin-only.  
- **Actual:** Admin Analytics has Export CSV/PDF; vendor `/dashboard/analytics/` has charts only.  
- **Repro:** Compare admin Sell Services → Analytics vs vendor Dashboard → Analytics.  
- **Card:** https://app.basecamp.com/5798509/buckets/45156734/card_tables/cards/10236312970  

### ISS-022 · License Deactivate has no Pro-unload warning
- **Type:** ux · **Severity:** P2  
- **Who:** Site owner on License screen  
- **Owner expect:** Clear warning that Pro features unload on deactivate/expiry (marketplace Free core continues).  
- **Actual:** Active + Deactivate only; no unload copy.  
- **Card:** https://app.basecamp.com/5798509/buckets/45156734/card_tables/cards/10236313127  

### ISS-023 · Non-vendor buyers can GET wallet/withdrawal APIs
- **Type:** security / api · **Severity:** P1  
- **Owner expect:** Wallet/withdrawal reads are vendor-only like earnings.  
- **Actual:** Login-only GETs return 200 empty/zeros; earnings is 403 `wpss_not_vendor`.  
- **Card:** https://app.basecamp.com/5798509/buckets/45156734/card_tables/cards/10236330376  

### ISS-024 · Pro `__return_true` dual-register on `/payments/methods`
- **Type:** security / api · **Severity:** P1  
- **Owner expect:** One auth callback; never public gateway listing.  
- **Actual:** Free+Pro dual permission callbacks; unauth 401 today (latent if order flips).  
- **Card:** https://app.basecamp.com/5798509/buckets/45156734/card_tables/cards/10236330517  

### ISS-025 · Inconsistent vendor-denial error codes
- **Type:** api · **Severity:** P2  
- **Card:** https://app.basecamp.com/5798509/buckets/45156734/card_tables/cards/10236330640  

### ISS-026 · Free vs Pro withdrawal list payload shapes differ
- **Type:** api · **Severity:** P2  
- **Card:** https://app.basecamp.com/5798509/buckets/45156734/card_tables/cards/10236330775  

### ISS-027 · Soft vs hard vendor gates on money GETs
- **Type:** api / ux · **Severity:** P2  
- **Card:** https://app.basecamp.com/5798509/buckets/45156734/card_tables/cards/10236330943  

### ISS-028 · Service add-ons in DB but not on single
- **Type:** func · **Severity:** P1  
- **Card:** https://app.basecamp.com/5798509/buckets/45156734/card_tables/cards/10236358463  

### ISS-029 · Dual-role My Orders → Sales Orders
- **Type:** ux · **Severity:** P1  
- **Card:** https://app.basecamp.com/5798509/buckets/45156734/card_tables/cards/10236358587  

### ISS-030 · Disputed order missing View Dispute link
- **Type:** func / ux · **Severity:** P1  
- **Card:** https://app.basecamp.com/5798509/buckets/45156734/card_tables/cards/10236358753  

### ISS-031 · Request UI HIRED vs DB open
- **Type:** ux / func · **Severity:** P2  
- **Card:** https://app.basecamp.com/5798509/buckets/45156734/card_tables/cards/10236358969  

---

## Catalog coverage status (in progress)

| Area | Status |
|---|---|
| First-install admin + become-vendor + checkout cart confusion | Done |
| Pro-only matrix | Done — [Pro QA](e8536569-bc2c-46db-a6fe-8151a427a223) |
| Surfaces + ops | Done — [Ops QA](3e14ccce-dfaa-47f5-aa95-e24eb8d23ca7) |
| Security / API IDOR | Done — [Security/API](e7bed0db-eb78-4f71-960f-f04fe32f13f0) |
| Selling & buying | Done — [Selling/Buying](642dbe27-e229-452c-bfa8-c2fadaee56dc); ISS-028–031 filed |
| Money (tips, milestones, refunds, withdrawals E2E) | Done — [Money](cd56093e-cc3c-4622-9059-259274f76a57); Free E2E PASS; dual-cart risk already ISS-006 |

---

## Changelog

| When | Change |
|---|---|
| 2026-08-25 | Initial log from first-install Playwright + Bugs sync. Agents still appending. |
| 2026-08-25 | ISS-002 / ISS-004 re-filed → cards 10236298979 / 10236299217. |
| 2026-08-25 | Process locked: find-only → audit log → Basecamp Bugs (repro + owner expect vs actual). No product fixes in this pass. |
| 2026-08-25 | Pro QA complete: ISS-021/022 added; unlimited-listings FAIL already covered by ISS-003/009. |
| 2026-08-25 | Ops QA complete: surfaces/ops largely PASS; dark/realtime PARTIAL (theme/config); admin `page=wpss` → 403 noted as QA trap only. |
| 2026-08-25 | Security/API complete: IDOR clean; ISS-023–027 filed (wallet soft gates, payments dual-register, error/payload inconsistency). |
| 2026-08-25 | Selling/Buying complete: ISS-028–031 (add-ons not rendered, dual-role orders nav, dispute deep-link, request status drift). |
| 2026-08-25 | Money complete: standalone checkout/tips/extensions/refunds/withdrawals PASS; dual-cart already ISS-006; no new P1. |
