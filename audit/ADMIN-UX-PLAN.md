# Admin UX — one component set, no per-page CSS

> **Sequencing (owner, 2026-07-23):** cover ALL tasks; the Pro PayPal-payout
> rebuild goes LAST. So the order is: finish Phase 1 (scripts) → Phase 2-5 (UX
> consolidation) → Phase 6 (frontend) → refund policy (money T10/T9/T11) → the
> PayPal rebuild + rail seam as the final release-gating item. Nothing ships to
> customers until PayPal stops double-paying, but it is built last.


**The problem, in one line: every screen ships its own CSS for the same
components.** Sections, inputs, badges, tables and modals are each implemented
several times over, so a fix in one place doesn't reach the others and the
screens drift apart visually.

Audited 2026-07-23 with the `ux-audit` skill against `ux-foundation`: §1 grep
pass, §0 rendered pass over every admin page, plus a selector-level duplication
scan of all stylesheets and every inline `<style>` block.

---

## 1. The duplication, measured

**265 classes are defined in more than one stylesheet** (83 of them involve the
admin sheets). The worst:

| Class | Defined in |
|---|---|
| `.wpss-btn` | **7 stylesheets** — admin, design-system, frontend, service-wizard, single-service, unified-dashboard, vendor-dashboard |
| `.wpss-modal` | 6 |
| `.wpss-btn--sm` | 5 |
| `.wpss-card`, `.wpss-stat-card`, `.wpss-empty-state`, `.wpss-stats-grid`, `.wpss-btn--danger`, `.wpss-modal-close`, `.wpss-notice` | 4 each |
| `.wpss-badge`, `.wpss-badge--success`, `.wpss-badge--danger`, `.wpss-card__title`, `.wpss-card__body` | 3 each |

Whichever sheet loads last wins. That is the whole bug: nobody chose the winner.

### Competing roots for one idea

| Component | Distinct roots today | Classes |
|---|---|---|
| **Section / card** | **6** — `.wpss-card`, `.wpss-list-card`, `.wpss-detail-card`, `.wpss-panel`, `.wpss-section`, `.wpss-stat-card` | 66 |
| **Input / field** | **7** — `.wpss-input`, `.wpss-select`, `.wpss-textarea`, `.wpss-field`, `.wpss-form-field`, `.wpss-form-group`, `.wpss-form-row` | 36 |
| **Badge / status pill** | 3 — `.wpss-badge`, `.wpss-status`, `.wpss-tag` | 70 |
| **Table** | **9** — one per screen (`.wpss-vendors-table`, `.wpss-withdrawals-table`, `.wpss-disputes-table`, `.wpss-audit-table`, `.wpss-orders__table`, `.wpss-wallet__table`, …) | 23 |
| **Empty state** | 3 — `.wpss-empty-state`, `.wpss-no-items`, `.wpss-no-data` | 19 |
| **Button** | 2 — `.wpss-btn`, `.wpss-button` | 32 |
| **Modal** | 1 root, but defined in 6 files | 25 |

### Inline `<style>` blocks that re-declare shared classes

| File | Selectors declared inline | Already defined in shared CSS |
|---|---|---|
| `VendorsPage.php` | 30 | **21** — `.wpss-modal`, `.wpss-modal-content`, `.wpss-modal-close`, `.wpss-status-badge`, `.wpss-rating-stars`, `.wpss-no-items`, … |
| `SetupWizardPage.php` | 25 | 0 (all bespoke `.wpss-wizard-*`) |
| `UpgradePage.php` | 14 | 0 |
| `ProTeaser.php` | 8 | 0 |
| `ServiceModerationPage.php` | 7 | 2 |

VendorsPage is the clearest case: **21 classes defined twice**, once inline and
once in `admin.css`, silently fighting each other.

---

## 2. The target — one set, defined once

```
assets/css/admin.css        ← the ONLY place an admin component is defined
   tokens (:root)
   .wpss-card               section container  (+ __head __title __desc __body __foot, --info/--success/--warning/--danger)
   .wpss-btn                (+ --primary --secondary --ghost --danger, --sm --md --lg)
   .wpss-form-group         field wrapper      (+ __label __help __error, --error)
   .wpss-input .wpss-select .wpss-textarea     (+ --sm --md --lg, --error)
   .wpss-badge              (+ --neutral --info --success --warning --danger)
   .wpss-empty-state        (+ __icon __title __body __actions)
   .wpss-modal              (+ __dialog __close, .wpss-modal-actions)
   .wpss-table              base list table

per-page CSS may ONLY add:
   column widths            .wpss-vendors-table .column-earnings { width: 14%; }
   page-specific layout     grid/stack arrangements unique to that screen
   nothing else
```

### The rules

| # | Rule |
|---|---|
| C1 | **One definition per class, in one file.** A class defined in two stylesheets is a bug, not a style. |
| C2 | **Pages never define components.** A page stylesheet may set column widths and page layout only — never a card, input, button, badge, empty state or modal. |
| C3 | **No inline `<style>` in PHP.** (F1 gate; 24 violations today.) |
| C4 | **One root per component idea.** Six card roots collapse to `.wpss-card` + modifiers; seven input roots to `.wpss-form-group` + `.wpss-input`. |
| C5 | **Status pills are badge variants**, not their own system — map each status to `--neutral/--info/--success/--warning/--danger`. |
| C6 | **Tables share `.wpss-table`;** per-screen classes exist only to carry column widths. |
| C7 | **Dark mode and RTL are token overrides at root** — never per-component, never per-page. |

---

## 3. Migration plan

Ordered so nothing depends on a later phase, each phase independently shippable
and browser-verified. **Every phase keeps a back-compat shim for one release**,
so no screen breaks mid-migration.

### Phase 1 — stop the bleeding (inline `<style>`)

Move all 5 inline blocks into the shared sheet, **deleting rather than copying**
anything already defined there.

1. `VendorsPage.php` — 30 selectors; drop the 21 duplicates outright, move the 9
   genuinely page-specific ones (column widths) into `admin.css`.
2. `ServiceModerationPage.php` — 7; drop 2 duplicates.
3. `SetupWizardPage.php` (25), `UpgradePage.php` (14), `ProTeaser.php` (8) —
   bespoke one-page designs; move to `admin-onboarding.css`, then fold what's
   really a card/button/badge into the shared component.

**Verify:** each page renders identically before/after at 1440 + 390, light and
dark. This phase alone removes the class of "two definitions, load order wins".

### Phase 2 — collapse the component roots

One PR per component, in this order (fewest dependents first):

1. **Button** — `.wpss-button` → `.wpss-btn`. 2 roots → 1.
2. **Badge + status** — `.wpss-status-*` and `.wpss-tag` → `.wpss-badge--*`, with
   a documented status→variant map. 3 roots → 1, 70 classes → ~12.
3. **Empty state** — `.wpss-no-items`, `.wpss-no-data` → `.wpss-empty-state`.
4. **Input** — `.wpss-field`, `.wpss-form-field`, `.wpss-form-row` →
   `.wpss-form-group` + `.wpss-input`. 7 roots → 2.
5. **Card** — `.wpss-panel`, `.wpss-section`, `.wpss-detail-card` → `.wpss-card`
   + modifiers. `.wpss-list-card` and `.wpss-stat-card` survive as genuine
   variants (`.wpss-card--list`, `.wpss-card--stat`). 6 roots → 1 + 2 modifiers.
6. **Table** — introduce `.wpss-table`; the 9 per-screen classes keep only widths.

Each PR: change the CSS, update every consumer, add the shim, screenshot before
and after.

### Phase 3 — one definition per class

With roots collapsed, resolve the remaining cross-sheet duplicates (`.wpss-btn`
in 7 files, `.wpss-modal` in 6, …): keep the definition in **one** sheet, delete
the rest, and make the other sheets `depends` on it at enqueue time.

**Gate:** a script that fails when any `.wpss-*` class is defined in more than
one non-RTL stylesheet. Wire it into `bin/ux-audit.sh` so it can't regress.

### Phase 4 — drop the shims and the drift

- Remove the back-compat shims from Phase 2.
- Collapse **17 distinct breakpoints** to 3.
- Add `:focus-visible` rings wherever `outline: none` removed one (~24 places).
- `ux-audit.sh` exits 0.

---

## 4. Screen anatomy (secondary, but do it while you're in there)

The rendered pass also found the screens are assembled differently. Fix these as
each screen is touched in Phases 1-3, not as separate work:

| Finding | Detail |
|---|---|
| **13 of 14 screens have no page header** | Only Settings renders `.wpss-page-header`. Everything else opens with a bare `<h1>`, so there is nowhere consistent for a description or the primary action. |
| **Stat cards follow no rule** | 7 (Dashboard), 4, 4, 4, 2, 2, 0, 0… Rule: show only totals that appear nowhere else. Vendors went 5 → 2 on 2026-07-23 because 3 restated the filter row. |
| **Two action paradigms** | Orders / Vendors / Disputes use `.row-actions`; Withdrawals uses a persistent Actions column. Standardise on `.row-actions`. |
| ~~Two list paradigms~~ | **WITHDRAWN — this finding was wrong.** Both moderation screens already use `wp-list-table`; the scan misread "queue is empty" as "no table". See D1 in §6. |
| **Search missing** | Withdrawals, Audit Log, My Notifications, Service Moderation — all grow without bound. |
| **Withdrawals sorts nothing**; **Audit Log has no filters** | A forensic trail you can't filter by actor or event is unusable past a few hundred rows. |

---

## 5. Honest gaps in this audit

- **Pagination and empty states could not be judged on 5 screens** — they render
  only past one page / with zero rows, and this site has 1-6 rows per screen.
  Needs the seeded dataset below.
- **Dark mode, RTL and 390px were verified on Withdrawals and Vendors only.**
  The other 12 screens are **pending** — that is a gap, not coverage.
- Seed before closing Phase 2: 2000+ withdrawals (recipe in HANDOFF, delete by
  `details = '{"seed":"t1-qa"}'`), 500+ vendors, 2000+ orders, 500+ audit rows,
  and one vendor with zero of everything for the empty states.

---

## 6. Decisions — MADE 2026-07-23 (owner delegated the presentation call)

### D1 — WITHDRAWN. The premise was wrong: moderation already uses tables.

**Correction (2026-07-23).** §4 originally reported "Service Moderation and
Review Moderation are card grids; every other queue is a table." That was
**false**. Both render `<table class="wp-list-table … wpss-moderation-table">` /
`wpss-review-table`, with `.row-actions` and per-column widths, exactly like
every other queue.

The rendered scan reported `table: false` for both because **both queues were
empty** on the audit site, so the empty state rendered and the table never did.
I read absence-of-rows as absence-of-table.

**This is the single best argument for the seeded-data rule in Phase 6.** An
audit run against an empty site invented a design problem that does not exist,
and would have spent a phase "converting" screens that were already correct.
Any screen whose state depends on data must be judged with data.

What remains true for these two screens: they duplicate CSS like every other
page (Phase 1.2), and their stat cards restate their filter rows (Phase 4.2).
No conversion, no paradigm split.

### D2 — Vendors "Earnings" reads the wallet ledger — **DONE (2026-07-24)**

**Landed.** The list column is now **"Earned"** and reads a correlated
sub-query over `wpss_wallet_transactions` (completed rows, debit types excluded)
whose SQL is byte-for-byte the vendor dashboard's `EarningsService::
get_summary()['total_earned']` — so the admin can no longer show a different
lifetime figure than the vendor's own dashboard. One indexed sub-query per row
(on `wallet_transactions.user_id`), sibling to `services_count`; NO per-row
`get_summary()` call. The `total_earned` sort maps to the sub-query alias so the
column sorts by the value shown. The summary stat card became **"Total Earned"**,
a single aggregate over the ledger **scoped to current `vendor_profiles`** so it
equals the sum of the listed column (not ledger rows for former vendors/buyers).
Both detail surfaces (the tabbed `render_vendor_detail` and the AJAX
`ajax_get_vendor_details`) now show **"Balance"** = `wpss_get_ledger_balance()`
(current, signed) instead of the denormalised `total_earnings`, so the list
answers "who is producing" and the drawer answers "what do I owe". Verified in
the browser: Earned $380 (order_earning 425 − order_reversal 45, matching the
dashboard), Total Earned $380, Balance $130 (380 − 250 withdrawn). WPCS-neutral
(baseline 21/37 unchanged).

The ledger is the money authority (`manifest.money_authorities.wallet_balance`).
The admin list must not be able to contradict the vendor's own dashboard, so the
column reads ledger credits, not `vendor_profiles.total_earnings`.

**Scale constraint, non-negotiable:** do NOT call `get_summary()` per row — that
is an N+1 that dies at 500 vendors. Add ONE grouped query
(`SELECT user_id, SUM(...) FROM wpss_wallet_transactions WHERE status='completed'
GROUP BY user_id`) joined into the existing vendor list query, exactly as
`services_count` and `total_orders` are already batched.

Label it **"Earned"** (lifetime credits) and keep the payout-relevant number —
current balance — in the detail drawer, so the list answers "who is producing"
and the drawer answers "what do I owe".

---

## 7. Task list (execution order — tick as they land)

Each task is one commit, browser-verified before/after. Phases don't overlap:
finishing Phase 1 makes Phase 2 safe, and so on.

### Phase 1 — remove per-page CSS/JS (the source of the duplication)

| # | Task | Size | Status |
|---|---|---|---|
| 1.1 | `VendorsPage` inline `<style>` — 30 selectors, 21 dup | 182 lines | **DONE** `e60c4da` |
| 1.2 | `ServiceModerationPage` inline `<style>` — 7 selectors, 2 dup | small | |
| 1.3 | `ProTeaser` inline `<style>` — 8 selectors | small | |
| 1.4 | `UpgradePage` inline `<style>` — 14 selectors | medium | |
| 1.5 | `SetupWizardPage` inline `<style>` — 25 selectors | medium | |
| 1.6 | `Admin.php` — 2 inline `<script>` + 1 inline `onclick` | small | **DONE** |
| 1.7a | `ServiceModerationPage` inline `<script>` (144 lines) → `admin-moderation.js` | medium | **DONE** |
| 1.7b | `SetupWizardPage` inline `<script>` (262 lines, 20 PHP interpolations) | medium | |
| 1.7c | `VendorsPage` inline `<script>` (695 lines, 32 interpolations) | large | |
| 1.8 | `ServiceMetabox` — 8 blocks were 4 DEAD duplicate `wp.template` markup + 4 live ones. Deleted the dead methods (337 lines). | large | **DONE** |

**ServiceMetabox was not a JS-extraction at all — it was dead-code + dedup.**
The 8 `<script>` blocks the audit flagged are ALL `<script type="text/html">`
`wp.template` markup, not executable JS (F2 false positives — the idiomatic
WordPress client-template pattern). But each template id
(`tmpl-wpss-package-item` etc.) was defined **twice**: once in a live
`render_*_content()` path (reached via the registered tabbed
`render_service_data_metabox`) and once in an orphaned `render_*_metabox()`
method left over from before the metabox was consolidated into tabs. Those four
old methods (packages/faqs/requirements/addons, 337 lines) were registered
nowhere and called nowhere — `wp.template` fetched the FIRST id and the second
was dead markup. Deleting them removed the duplicate-id bug and the F2 flags in
one move. Verified: metabox renders 2 seeded packages, "Add Package" instantiates
a 3rd from the surviving template, and a save persisted all three to the DB.

**Dead-enqueue bug, third instance.** `ServiceModerationPage::enqueue_scripts()`
compared against `'wp-sell-services_page_wpss-moderation'`; the real suffix is
`sell-services_page_…` (derived from the parent MENU TITLE), so the method was
**dead code** — which is exactly why the page printed its config and 120 lines
of JS inline. Same root cause as WithdrawalsPage and, before it, the enqueue on
UpgradePage. **Every `add_submenu_page()` caller must store the returned hook**
rather than reconstruct it; a grep for hardcoded `_page_wpss-` strings should
become a gate.

**F8 finding (new):** `admin-moderation.js` uses native `confirm()` and
`prompt()` for approve / reject / rejection-reason, not `wpssConfirm`. Now that
it is a real file the audit can see it. Folds into the modal work, Phase 2.

### Phase 2 — collapse competing roots (one component per commit, shim for one release)

| # | Task | Roots | Status |
|---|---|---|---|
| 2.1 | Button — `.wpss-button` → `.wpss-btn`; `--small`→`--sm`; define `--ghost --danger` composition | 2 → 1 | **DONE** |

**Phase 2.1 landed (2026-07-24).** Three button-drift fixes:
1. **Root collapse** — all 14 `.wpss-button` / `.wpss-button-primary/-secondary/
   -outline/-small` usages migrated to canonical `.wpss-btn` + `--primary/
   --secondary/--outline/--sm` (5 files). Legacy `.wpss-button*` rules kept in
   `blocks.css` as a one-release shim → **remove in Phase 4**.
2. **`--small` orphan → `--sm`** — 11 usages across portfolio/profile/JS. The
   old `--small` was defined NOWHERE, so those "small" buttons silently rendered
   full-size.
3. **Over-weighted Delete fixed at the source** — Portfolio and Buyer-Requests
   cards stacked `--link --outline --danger` (or `--link --danger`) on one
   button; `--danger`'s solid fill won on specificity, so Delete was a loud red
   block while its peers were plain text. Defined `.wpss-btn--ghost.wpss-btn--
   danger` ONCE in design-system.css (ghost layout + danger text, 0,2,0 beats
   the theme-proof 0,1,1 solid rule), then set both cards' actions to clean
   single-intent ghost/ghost-danger. Destructive emphasis now lives at confirm
   (both already use `tone:danger`). Verified in the browser — both cards read
   as balanced peer rows.

**Still open (Phase 3, recorded not fixed):** `.wpss-btn--link` (5 uses) is
defined only in `vendor-dashboard.css`, non-canonical. Either promote to the
design system or migrate to `--ghost`.
| 2.2 | Badge / status — `.wpss-status-*` unified on ONE token palette + `wpss_status_class()` render authority | 18 defs → 1 (admin) + 1 (design-system) | **ADMIN DONE (2026-07-24)** — frontend non-order status systems deferred to Phase 6 |

**Phase 2.2 landed for admin (2026-07-24).** Owner chose **(a)** — the
design-system token palette everywhere, one look across admin + frontend.

- **One render authority:** `wpss_status_class( $status )` (src/functions.php)
  emits `wpss-status-badge wpss-status-<hyphenated>` for every status. It
  replaces scattered `str_replace('_','-',$status)` inlines AND the drifting
  PHP hand-maps (OrdersListTable fell through to `pending` for refunded/
  delivered/accepted → **refunded rendered amber**; now purple).
- **One colour source:** the canonical `.wpss-status-*` block lives ONCE in
  `admin.css` (admin screens) and ONCE in `design-system.css` (frontend). Both
  reference the same `--wpss-status-*` tokens with identical hex fallbacks, so
  admin (tokens absent → fallback) and frontend (tokens present) render every
  pill the same colour. Added missing tokens `--wpss-status-pending-*` and
  `--wpss-status-completed-*`; added missing pills (refunded, partially-refunded,
  requirements-submitted, cancellation-requested) that previously rendered
  unstyled.
- **Divergent copies removed:** admin.css (2 blocks + 1 stray), frontend.css
  (the `.wpss-status-*` block + dead `.wpss-badge--pending/approved/completed/
  rejected` + the `.wpss-badge--status-*` order pills, migrated at their 6 call
  sites to `wpss_status_class()`). Also removed the inline
  `.wpss-badge--status-cancellation-requested` rule in order-view.php.
- **Admin render sites routed through the helper:** OrdersListTable,
  Admin.php (×2 order, ×1 dispute), VendorsPage (×9), OrderMetabox,
  BuyerRequestMetabox, WithdrawalsPage, ServiceModerationPage (dropped its
  inline-style pill), ReviewModerationPage. This fixed a latent bug: sites
  emitting raw `wpss-status-<?php echo $order->status ?>` produced UNDERSCORE
  classes (`wpss-status-in_progress`) that never matched the hyphenated CSS, so
  those statuses rendered unstyled on Vendors detail screens.
- **Verified (browser):** admin Orders list — refunded=purple, pending_requirements=amber,
  disputed=orange; frontend order-view header (order #112) — refunded=purple,
  matching admin. Light theme confirmed.

**Deferred to Phase 6 (frontend, needs seeded per-screen verification):** three
OTHER frontend status naming systems still exist and were intentionally NOT
swept blind — `.wpss-status .wpss-status--<status>` (double-dash; sales.php,
orders.php), `.wpss-badge .wpss-badge--<withdrawal-status>` (earnings.php), and
`.wpss-request-status-badge.wpss-status-*` (buyer requests, higher-specificity
compound — currently still wins in its own context, no regression). Plus:
`unified-dashboard.css` has NO design-system dependency (`array()`), so it
carries its OWN `.wpss-status-badge` + moderation-status copies and its
`var(--wpss-*)` rely on fallbacks only — add the `wpss-design-system` dep and
drop the copies during the dashboard screen pass. Dispute statuses
(open/pending_review/escalated/resolved/closed) are a self-consistent UNDERSCORE
subsystem (dispute-view.php inline CSS uses underscore selectors matching raw
markup); unifying them requires updating that inline CSS in lockstep.

**Original Phase 2.2 analysis (2026-07-24) — bigger than "remove dups", needs one owner call.**
Two compounding problems:

1. **The colours DISAGREE across sheets.** `.wpss-status-pending` is neutral
   grey in `admin.css`, warning-yellow in `unified-dashboard.css`, amber via
   token in `frontend.css` — so the same status is a different colour depending
   on the screen. It is defined **18 times**; most statuses 8×. design-system.css
   already holds canonical `--wpss-status-*` TOKENS and frontend.css consumes
   them; admin.css and unified-dashboard.css hardcode divergent values instead.

2. **TWO naming conventions in live code.** Frontend emits
   `wpss-status-<esc_attr($status)>` → UNDERSCORE; `Admin.php` / `order-view.php`
   emit `str_replace('_','-',$status)` → HYPHEN. So the CSS defines BOTH
   spellings for every multi-word status and neither copy is dead — they style
   different surfaces.

**Fix once decided:** (a) one colour source = the design-system tokens, delete
divergent copies; (b) one `wpss_status_class( $status )` helper so every render
path spells the class identically, collapsing the hyphen/underscore doubles.

**OWNER DECISION — what does a status badge look like?** Recommended: the
design-system token palette EVERYWHERE (soft tints: pending=amber,
completed=green, rejected/cancelled=red, in-progress=indigo). Consequence: admin
pills, now flat WP-grey, adopt the same tints the frontend uses — one look
across admin + frontend. Alternative: keep admin WP-native, unify each context
only internally. Visible repaint of ~10 screens; verified light + dark + both
contexts before shipping.
| 2.3 | Empty state — `.wpss-no-items`, `.wpss-no-data`, `.wpss-no-results` → `.wpss-empty-state` | 4 files → 1(ds)+1(admin) | audited 2026-07-24, mostly frontend → do in Phase 6 |
| 2.4 | Input — `.wpss-field`, `.wpss-form-field`, `.wpss-form-row` → `.wpss-form-group` + `.wpss-input` | 7 → 2 | |
| 2.5 | Card — `.wpss-panel`, `.wpss-section`, `.wpss-detail-card` → `.wpss-card` + modifiers | 6 → 1+2 | |
| 2.6 | Table — introduce `.wpss-table`; per-screen classes keep widths only | 9 → 1 | |

**Phase 2.3 audit (2026-07-24).** `.wpss-empty-state` is the de-facto standard
(25 markup uses) but defined in FOUR sheets — admin.css, frontend.css,
unified-dashboard.css, vendor-dashboard.css — and they diverge on sub-element
names (`__body` in admin, `__description` in vendor-dashboard, `__body`+`__text`
+bare `p`/`h2`/`h3` in frontend). frontend.css defines the base TWICE (2335 +
3739); admin's second copy (3094) is a legit `@media` override, not a dup.
`.wpss-no-items` = 0 uses (dead → delete from admin.css). `.wpss-no-data` = 6
uses. `.wpss-no-results` = 8 uses with NO CSS definition anywhere (renders
unstyled — a bug). design-system.css owns a separate `.wpss-empty` BEM component
(`__icon/__title/__text`) with only 2 uses. **Plan:** promote ONE canonical
`.wpss-empty-state` into design-system.css (frontend) + one in admin.css, settle
on `__icon/__title/__body/__actions`, migrate `__description`/`__text`/`no-data`/
`no-results` call sites, delete the 3 frontend copies + dead `.wpss-no-items`.
Three of four sheets are frontend, so this lands in the Phase 6 seeded per-screen
pass (each empty view rendered with a genuinely empty list), not blind.

### Phase 3 — one definition per class

| # | Task | Status |
|---|---|---|
| 3.1 | Resolve cross-sheet duplicates (`.wpss-btn` ×7, `.wpss-modal` ×6, …) — keep one, delete the rest, wire `depends` | |
| 3.2 | Add the gate to `bin/ux-audit.sh`: fail when any `.wpss-*` class is defined in >1 non-RTL sheet | |

### Phase 4 — screen anatomy (do while touching each screen)

| # | Task | Status |
|---|---|---|
| 4.1 | `render_page_header()` helper + adopt on all 14 screens | |
| 4.2 | Apply the stat-card rule everywhere (Dashboard 7 → 2-3; Moderation 4 → 0-2) | |
| 4.3 | Withdrawals: Actions column → `.row-actions`; add search; add sortable headers | |
| 4.4 | Audit Log: actor/event filters + search. My Notifications: read/unread filter + pagination | |
| 4.5 | ~~Moderation card grid → table~~ — **cancelled, finding was wrong (D1)** | N/A |
| 4.6 | Vendors Earnings → wallet ledger via ONE grouped query, label "Earned" (D2) | **done 2026-07-24** |

### Phase 6 — FRONTEND, screen by screen, on seeded data

**Same discipline as the admin, and the frontend matters more** — it is what the
site owner's customers and vendors actually see. Every screen gets: seeded data
first, then rendered at 1440 + 390, light + dark, LTR + RTL, in **both roles**
(buyer and vendor), across empty / populated / error states.

**Seed before starting.** Every bug below was invisible on a near-empty site;
the broken card only appeared because a real request existed. Required:
20+ services across categories, 10+ buyer requests with and without offers,
30+ orders across every status, a vendor with 0 of everything, long titles and
long vendor names (the overflow cases), and at least one order with milestones.

| # | Screen | Seeded state needed | Status |
|---|---|---|---|
| 6.1 | Dashboard → Buyer Requests | requests with 0 and N offers, long titles | **FIXED** |
| 6.2 | Dashboard → My Orders / Sales Orders | orders in every status | looked, ok at 1-3 rows; **still needs every-status seed** |
| 6.3 | Dashboard → Earnings & Payouts | positive, zero and NEGATIVE balance; in-clearance | looked, ok; negative-balance state verified earlier |
| 6.4 | Dashboard → My Services | 0, 1 and 20+ services; draft/pending/published | looked, ok at 4 |
| 6.5 | Dashboard → Messages | 0 and 50+ conversations, long messages | **looked, ok**; needs 50+ for the scroll case |
| 6.6 | Dashboard → Portfolio / Analytics / Favorites / Disputes / Profile | empty + populated | **all looked**; Portfolio + Analytics findings recorded |
| 6.7 | Service archive + single service | long titles, no image, many packages, no reviews | **DONE 2026-07-24 — 2 fixes** (below) |
| 6.8 | Buyer request archive + single request | open/closed, with/without offers | **DONE 2026-07-24 — 2 fixes** (below) |
| 6.9 | Checkout + cart + order confirmation | each gateway, plus no-gateway-configured | **PARTIAL 2026-07-24** — cart + empty checkout done, 2 fixes; paid flow still to do |
| 6.10 | Become a vendor / registration | open / approval / closed states | **DONE 2026-07-24 — 2 fixes** (below); approval/closed modes still to try |
| 6.11 | Vendor public profile | new vendor with nothing; vendor with everything | **DONE 2026-07-24 — 1 fix** (below) |

### 6.7 Service archive + single service — rendered 2026-07-24

**Seeded first** with the plugin's own `wp wpss demo marketplace --orders=60`
(6 vendors, 12 buyers, 12 services w/ images, 60 orders across 13 statuses,
5 reviews, 6 requests + 15 proposals, 23 conversations, 36 favorites), plus a
hand-built edge-case service (#121): 200-char title, NO image, 5 package tiers,
zero reviews.

**Archive — clean at 1440 and 390.** Filters sidebar, 14 cards, pagination.
Long titles clamp to 2 lines; a service with no image renders
`.wpss-service-card__placeholder` (icon on a tint) that preserves the 238px
media height so the grid stays aligned. At 390 the filter rail collapses to a
"Filters" toggle and cards go 1-up; no horizontal scroll.
*Method note:* full-page screenshots on this site must scroll first — lazy
images render as blank voids otherwise, which briefly looked like a missing
placeholder bug and was not one.

**Single service — two real bugs found and FIXED.**

1. **Extra package tiers were invisible AND unclickable.**
   `.wpss-package-tab { flex: 1 }` cannot shrink below its min-content width and
   the widget wrapper is `overflow: hidden`, so a service with more tiers than
   fit had its last tab(s) rendered *outside* the card and clipped — measured
   `scrollWidth 445` vs `clientWidth 338`, last tab 107px beyond the edge,
   `lastTabReachable: false`. A buyer could not see or select the vendor's top
   package. Reachable in the wild: the tier count is filterable
   (`wpss_service_max_packages`, default 3) and tier titles are vendor-authored
   free text (wizard "Tier title", no maxlength, hint suggests "Logo + 3
   revisions"). Fix: the strip scrolls (`overflow-x: auto`) and tabs use
   `flex: 1 0 33.333%` + `min-width: 0` + `border-box`, so three tiers still
   divide the strip exactly as before while a fourth+ scrolls into reach.
   *Rejected first attempt:* `min-width: 33.333%` — padding pushed each tab past
   a third, so the common 3-tier case started scrolling and cut off tier 3.
   Verified: 3 tiers unchanged (no scroll, all visible) at 1440 and 390;
   5 tiers scroll with every tier reachable; no page-level scroll.

2. **Wide sidebar content blew out the mobile layout.** `.wpss-service-sidebar`
   is a grid item left at `min-width: auto`, so its min-content size (driven by
   the tab strip) pushed it to 447px inside a 336px track at 390px — overflowing
   the viewport and getting silently clipped by the theme's `overflow-x: hidden`
   rather than scrolling inside its own widget. Its sibling
   `.wpss-service-main` already carried `min-width: 0`; the sidebar never got
   the same treatment. Fixed by matching it. This also hardens the sidebar
   against any other wide content (long vendor names, badges).

**Dedup:** `.wpss-packages-tabs` / `.wpss-package-tab` were defined in BOTH
`frontend.css` and `single-service.css` with different padding, font-weight and
active indicator. Consolidated into `frontend.css` (loads on every frontend
surface, so the widget can never render unstyled), keeping single-service's
visual treatment; the duplicate is gone.

### 6.8 Buyer request archive + single request — rendered 2026-07-24

Seeded: 6 requests with 2-3 proposals each, one with 0, one moved to `hired`
through `BuyerRequestService::update_status()` (not hand-forced meta).

**Archive — clean at 1440.** Filter rail, card list, budget / proposal count /
"Send Proposal" per card. It shows only `open`, unexpired requests **by design**
(documented meta_query in `BuyerRequestArchiveView`), so a hired request
correctly disappears from the listing. "6 requests found" vs 7 published was
traced to request #21, a leftover test artifact with **no `_wpss_*` meta at
all** — not a product bug; anything created through the real flow always has it.

**Single request — two real bugs found and FIXED.**

1. **Every buyer-request page rendered with NO visible title and an empty
   `<h1>`.** `ShellHeader::maybe_suppress_theme_title()` blanks the queried
   object's `the_title` inside the main loop so a theme's duplicate entry-title
   disappears on plugin surfaces. `single-request.php` renders its own heading
   with `the_title()` *inside that same loop* — so the plugin blanked its own
   `<h1>`. The buyer/vendor could not see which request they were reading, and
   the page shipped an empty heading to search engines and screen readers.
   Fixed by printing the raw post title (`get_post_field(..., 'raw')`), the same
   dodge `SingleServiceView::render_title()` already used (`$service->title`) —
   which is exactly why the single SERVICE page never showed this symptom.
   Verified: exactly one populated `<h1>`, theme duplicate still suppressed.

2. **"Proposals" meant two different numbers.** The archive card counts every
   proposal on the request and the buyer's own view counts every proposal, but
   the vendor-facing detail counted `status = 'pending'` only — so the same
   request read "2 proposals" in the listing and "1" on its own page. Made the
   non-buyer branch count them all: consistent across all three surfaces, and a
   vendor sizing up competition needs to know one was already accepted.

**Closed state verified** (`hired`): status badge flips, the Submit Proposal CTA
is replaced by "This request is no longer accepting proposals", title renders.
390px: no horizontal scroll, sidebar fits, title wraps cleanly.

### 6.9 Cart + checkout — PARTIAL, rendered 2026-07-24

**Two fixes landed.**

1. **Filled CTAs failed WCAG AA inside theme content — FIXED.** On the cart
   page an `<a class="wpss-btn wpss-btn--primary">` rendered near-black #111 on
   the indigo fill: **contrast 3.0**, under the 4.5 AA floor. design-system.css
   already had a block *claiming* to be "theme-proof at a specificity theme link
   rules cannot beat" — it is (0,1,1), and BuddyX ships
   `.entry-content a:not(.wp-element-button):not(.wp-block-button__link)
   :not(.button):not([class*="button"])` at (0,5,1), so it loses. Cart, checkout
   and every shortcode-driven page render inside `.entry-content`, so all their
   CTAs were affected. **The theme's own escape hatch matches
   `[class*="button"]` and our class is `wpss-btn` — "btn" is not "button" — so
   nothing exempted us.** Fixed with a narrowly-scoped `color: … !important`
   rule: anchors only, filled variants only (`:not(--ghost):not(--outline)`, so
   the Phase 2.1 `.wpss-btn--ghost.wpss-btn--danger` pattern is untouched), and
   `color` only (never `background`, whose `:hover` rules are not `!important`
   and would have been frozen). Verified 3.0 → **6.29**, ghost-danger Delete
   still red-on-transparent, hovers intact.

2. **Checkout's empty state was a red error — FIXED.** Arriving at checkout with
   an empty cart showed `<p class="wpss-alert wpss-alert-error">No service
   selected.</p>`: it blamed the buyer for an ordinary state and offered no way
   forward. Now mirrors the cart page's empty state (icon, title, explanation,
   "Browse Services" CTA).

**Corrected a wrong first read (recorded so it is not repeated).** `/checkout/`
appeared to render a completely blank page — it is actually **WooCommerce's**
checkout block (`wp-block-woocommerce-checkout … is-loading`), because WC is
active here and owns that slug. The plugin's mapped checkout page is
`/service-checkout/` (page 7) and renders correctly. The plugin links by page
ID, so this is not a product bug, but it is a good reminder that on a WC site
`/checkout/` is not ours.

**Buyer journey walked 2026-07-24** as a real buyer (logged out, then
`?autologin=wpss_buyer_noah` — the helper no-ops while another user is logged
in, which is why the first attempt silently stayed on the owner account).

Service → **Continue** → Order Options modal (package, delivery, total) →
**Continue to Checkout** → added-to-cart modal (View Cart / Checkout) →
`/service-checkout/89/`. All steps work. The checkout renders service details,
the full `billing_*` form (matching the account-and-billing standard), order
summary, payment method, trust row and a "What happens next?" stepper.
Submitting empty blocks correctly and focuses the first invalid field. With the
Stripe Payment Element mounted, **Pay** returns Stripe's own inline validation
("Your card number is incomplete") — correct behaviour, not a silent failure.

**Third fix from this pass: the phone field was a stunted 190px** in a column of
748px inputs on the checkout billing form. `frontend.css` gave `width: 100%` to
an *allowlist* of input types — `text`, `email`, `number` — so `type="tel"`
missed it entirely (and `url`, `password`, `search`, `date`… were queued up to
hit the same hole). Note the neighbouring `input:focus` rule was already
generic, which is exactly why the gap went unnoticed. Rewritten as an
**exclusion** (`input:not([type="checkbox"]):not([type="radio"])…`) so the whole
bug class dies at once; verified all 11 billing inputs now measure identically
at 1440 and 390, radios still 18×18, no page scroll.

**Card payment COMPLETED end to end 2026-07-24.** The Playwright aria snapshot
does reach inside Stripe's cross-origin iframe, so the Payment Element can be
driven directly (test card 4242…). Paying $75 created order **173**:
`payment_status: paid`, `total 75.00`, `vendor_earnings 67.50`,
`platform_fee 7.50` (10%), status `pending_requirements`, and the buyer was
routed to Submit Requirements with order number, total, expected delivery,
seller card and an upload field. **Money-correctness check passed: ZERO ledger
rows for the order and the vendor's balance still $0.00** — the wallet is
credited at completion, not at charge, exactly as the money model intends.

**Fourth fix: a purchase was filed under "Sales Orders".** Straight after
paying, the dashboard heading AND the highlighted nav item both read *Sales
Orders* for an order the user had just BOUGHT. `resolve_current_section()` fell
back to the role-aware default (active vendors land on `sales`) whenever the URL
carried no explicit `section` — and the post-payment URL carries only
`order_id` + `action`. Any member who both buys and sells hit this, which is
every vendor who orders anything and, notably, every buyer the instant they
register as a vendor. The section is now derived from the ORDER: `orders` when
the viewer is its customer, `sales` when they are its vendor, role default only
when neither. Verified with two real sessions on the same URL — buyer sees
"My Orders", vendor sees "Sales Orders".

**Still open for 6.9:** the other gateways, and the genuinely-no-gateway case —
this site has Stripe test keys, so the earlier "`wpss_payment_gateways` is `[]`"
reading was a different option and the fresh-install state remains untested.

**Recorded, not fixed (button vocabulary, feeds the Phase 2.1 follow-up):**
a THIRD button spelling is in wide use — single-dash `.wpss-btn-primary` (18),
`.wpss-btn-outline` (13), `.wpss-btn-block` (13), `.wpss-btn-secondary` (3),
`.wpss-btn-sm` (2) — alongside canonical `--primary` and the retired
`.wpss-button`. `.wpss-btn-primary` is defined in BOTH frontend.css and
single-service.css (another duplicate pair) and again scoped inside
archive-service.css. It renders correctly today (6.29 contrast on the single
service Continue CTA) so this is drift, not a live defect — but note the
theme-proof rule above targets `--primary`, so a single-dash **anchor** in
theme content would NOT be protected.

**Also recorded:** `templates/cart/cart.php` carries an inline `<style>` block
(F1 gate violation) and the empty-state centering lives inside it, which is why
the reused empty state on checkout renders left-aligned rather than centred.

### 6.10 Become a vendor — rendered 2026-07-24 (as a real buyer)

The landing page reads well (benefits list, one clear CTA) and registration is
one click in auto-approve mode: the buyer becomes a Seller, gains the SELLING
nav group, and lands on My Services with 0/0/0 stats and the onboarding tour.
The CTA is white-on-indigo here, which also confirms the 6.9 contrast fix
holding on a shortcode page inside `.entry-content`.

**Two fixes, both in the first thing a new vendor ever sees.**

1. **The frontend tour wore the wp-admin skin.** `wpss-tour.js` hardcoded
   `classes: 'wpss-shepherd wpss-shepherd--admin'` for BOTH contexts, even
   though `Tour.php` already screen-gates which step set to ship. So a brand new
   vendor's welcome dialog opened in WP-admin blue (#2271b1), 3px radius and the
   system font stack, on a dashboard built in the marketplace's indigo. PHP now
   reports `context` (`admin` / `frontend`), the JS picks the modifier, and a
   new `.wpss-shepherd--frontend` block dresses it in design-system tokens
   (indigo primary, muted secondary, `--wpss-radius-lg`, inherited font).

2. **The header grey was never the plugin's colour — in EITHER skin.** Shepherd
   ships `.shepherd-has-title .shepherd-content .shepherd-header
   { background:#e6e6e6 }` at (0,3,0), which had been quietly beating the
   plugin's own (0,2,0) header rule since the tour was written. Both variants
   now set it at a specificity that wins: admin gets the WP grey it always
   intended, frontend gets white.

Verified: `context: "frontend"`, class `wpss-shepherd--frontend`, header
`rgb(255,255,255)`, border `rgb(229,231,235)`, primary button `rgb(79,70,229)`.

**Not yet covered:** the approval-required and registration-closed modes (this
site runs auto-approve). The dialog's focus ring is still WP-admin blue — a
visible focus ring is correct a11y, only its colour is off-system; left alone.

### 6.11 Vendor public profile — rendered 2026-07-24

Route is `/provider/{user_nicename}/` (not `/vendor/…`).

**Public profiles 404'd for vendors created by any path that skips a legacy
meta — FIXED.** `TemplateLoader` gated the route on
`get_user_meta( $user->ID, '_wpss_is_vendor' )` — a THIRD source of truth
alongside the `wpss_vendor` role/capability (what the canonical
`wpss_is_vendor()` actually checks) and the `wpss_vendor_profiles` row. Every
seeded vendor has the role, the capability, a profile row, published services,
completed orders and reviews, but an EMPTY `_wpss_is_vendor` meta — so their
public profile was a hard 404 and every "About the seller" link on their service
pages pointed at a dead page. The gate now asks `wpss_is_vendor()`, so one
authority answers "is this a vendor" everywhere.

**Both states verified.** Populated (Aisha Khan): cover, avatar, Top Rated badge,
tagline, stats row, Contact Me, About, services grid, portfolio, reviews, and a
sidebar of response time / active services / orders. Empty (a buyer who
registered as a vendor minutes earlier): "New Seller" badge, 0/0 stats, a proper
"No services yet" empty state with a Browse services CTA, and — because it is
your own profile — a wallet card instead of Contact Me.

**Nit, not fixed:** the CONNECT card renders empty grey circles for social links
a vendor has not filled in (it is hidden entirely when none are set, so the
half-filled case is the odd one out).

### Dashboard tab sweep — all 12 tabs rendered 2026-07-23

**All 12 tabs seeded and rendered.** The four that were empty were seeded
through their real write paths (`FavoritesService::add()`, portfolio rows,
a conversation with three messages) — never by hand-forcing a view state.

| Tab | State | Findings |
|---|---|---|
| My Orders | 1 order | ok |
| Favorites | 1 saved service | ok — card grid, remove control top-right |
| Buyer Requests | 1 request | **card collapse FIXED** |
| My Services | 4 services | ok |
| Sales Orders | 3 orders | ok |
| Earnings & Payouts | populated | ok |
| Portfolio | 3 items, 1 featured | **Delete over-weighted; action row wraps badly** (below) |
| Analytics | 3 orders, impressions | **stat-card labels wrap 2-3 lines** (below) |
| Messages | 1 thread, 3 messages | ok — minor: relative time reads "48 seconds", missing "ago" |
| Notifications | populated | title missing (**FIXED**); 1 inline `<style>` → Phase 1 |
| Disputes | 1 dispute | title missing, raw slug, double heading (**all FIXED**) |
| Profile | form | ok |

**Portfolio action row.** Each card ends with a solid red **Delete** block while
Edit / Feature / Unfeature are plain text links — the destructive action is the
single loudest element on a card whose job is to show work. On the featured card
the row wraps mid-way ("FEATURED" + Edit on one line, "Unfeature" + Delete on
the next), so the actions read as two unrelated groups. Same over-weighted-
destructive pattern as the buyer-request card, and the same fix: Delete becomes
`.wpss-btn--ghost` with danger text, promoted to solid only inside a confirm.
Folds into Phase 2.1.

**Analytics stat cards.** Six cards in an auto-fit grid land 4-up then 2-up, and
at that width the labels wrap: "NET EARNINGS" over two lines, its sub-label
("Last 30 Days · after platform fee") over three. The row reads ragged and the
numbers stop being scannable. Needs a wider `minmax()` floor so cards hold their
label on one line, or shorter labels.

**The button vocabulary is the frontend's version of the same disease.** Across
these 12 tabs the dashboard ships:

- **two roots** — `.wpss-btn` (11 tabs) and `.wpss-button` (Disputes only)
- **two size conventions** — `--sm` (most) and `--small` (Portfolio, Profile),
  plus `.wpss-button-small` on Disputes
- **six variants** — `--primary`, `--secondary`, `--outline`, `--ghost`,
  `--link`, plus `.wpss-button-secondary`

The design system defines exactly `.wpss-btn` + `--primary/--secondary/--ghost/
--danger` + `--sm/--md/--lg`. So `--outline`, `--link`, `--small` and the whole
`.wpss-button` family are drift, and Disputes is on a different system entirely.
Folds into Phase 2.1.

**Known frontend findings so far**

- **FIXED — Buyer Requests card collapsed to one word per line.**
  `.wpss-request-card__main` had `min-width: 0` and **no grow factor**, while
  `__actions` had `flex-shrink: 0`; the five action links wanted ~490px of a
  ~500px column, so main computed to **0px** and the title wrapped behind the
  status badge. Root cause is the duplication theme again:
  `.wpss-request-card__actions` is declared in **two** stylesheets —
  `buyer-request.css` with `flex-flow: row wrap`, `unified-dashboard.css`
  without wrapping — and the dashboard loads the non-wrapping one. Fix: card
  wraps, main gets `flex: 1 1 260px` floor, actions wrap and align end.
- **OPEN — action styling is inconsistent on that card.** "Delete" is a solid
  red button while View Offers / Close / Edit are plain text links. Pick one
  ladder (`.wpss-btn--ghost` for secondary, `--danger` for destructive) — folds
  into Phase 2.1.

### Phase 5 — drift cleanup

| # | Task | Status |
|---|---|---|
| 5.1 | 17 breakpoints → 3 | |
| 5.2 | `:focus-visible` ring wherever `outline: none` removed one (~24 places) | |
| 5.3 | Drop the Phase 2 back-compat shims | |
| 5.4 | Seed the big dataset (§5) and verify pagination + empty states on all screens | |
| 5.5 | Per-screen records under `audit/screens/` — 1440 + 390, light + dark + RTL | |

---

## 8. Definition of done

- [ ] Zero inline `<style>` in admin PHP
- [ ] One root per component (card, input, button, badge, empty, table, modal)
- [ ] No `.wpss-*` class defined in more than one stylesheet, enforced by a gate
- [ ] Page CSS contains column widths and page layout only
- [ ] 3 breakpoints; `:focus-visible` everywhere `outline:none` appears
- [ ] `ux-audit.sh` exits 0
- [ ] Per-screen records under `audit/screens/` — 1440 + 390, light + dark + RTL
