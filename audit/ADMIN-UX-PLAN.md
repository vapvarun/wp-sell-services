# Admin UX — one component set, no per-page CSS

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
| **Two list paradigms** | Service Moderation + Review Moderation are card grids; every other queue is a table. **Decision needed** — see §6. |
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

## 6. Decisions needed

1. **Moderation card grids** — deliberate (you want a visual preview of the
   service/review before judging) or an accident? If deliberate, keep them and
   record the exception; if not, convert both to `wp-list-table`.
2. **Vendors "Earnings" column** reads `vendor_profiles.total_earnings`
   (order-derived) while the vendor's dashboard reads the wallet ledger. They
   can disagree. Point it at the ledger, or relabel it precisely — but do not
   ship two numbers both called "earnings".

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
| 1.6 | `Admin.php` — 2 inline `<script>` + 1 inline `onclick` | small | |
| 1.7 | `VendorsPage` + `ServiceModerationPage` + `SetupWizardPage` inline `<script>` | medium | |
| 1.8 | `ServiceMetabox` — 8 inline `<script>` (worst single file) | large | |

### Phase 2 — collapse competing roots (one component per commit, shim for one release)

| # | Task | Roots | Status |
|---|---|---|---|
| 2.1 | Button — `.wpss-button` → `.wpss-btn` | 2 → 1 | |
| 2.2 | Badge / status — `.wpss-status-*`, `.wpss-tag` → `.wpss-badge--*` | 3 → 1 | |
| 2.3 | Empty state — `.wpss-no-items`, `.wpss-no-data` → `.wpss-empty-state` | 3 → 1 | |
| 2.4 | Input — `.wpss-field`, `.wpss-form-field`, `.wpss-form-row` → `.wpss-form-group` + `.wpss-input` | 7 → 2 | |
| 2.5 | Card — `.wpss-panel`, `.wpss-section`, `.wpss-detail-card` → `.wpss-card` + modifiers | 6 → 1+2 | |
| 2.6 | Table — introduce `.wpss-table`; per-screen classes keep widths only | 9 → 1 | |

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
| 4.5 | Moderation card grid → table, or record the exception (**owner decision**) | |
| 4.6 | Vendors Earnings → wallet ledger, or relabel (**owner decision**) | |

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
| 6.1 | Dashboard → Buyer Requests | requests with 0 and N offers, long titles | **card layout FIXED** (below) |
| 6.2 | Dashboard → My Orders / Sales Orders | orders in every status | |
| 6.3 | Dashboard → Earnings & Payouts | positive, zero and NEGATIVE balance; in-clearance | |
| 6.4 | Dashboard → My Services | 0, 1 and 20+ services; draft/pending/published | |
| 6.5 | Dashboard → Messages | 0 and 50+ conversations, long messages | |
| 6.6 | Dashboard → Portfolio / Analytics / Favorites / Disputes / Profile | empty + populated | |
| 6.7 | Service archive + single service | long titles, no image, many packages, no reviews | |
| 6.8 | Buyer request archive + single request | open/closed, with/without offers | |
| 6.9 | Checkout + cart + order confirmation | each gateway, plus no-gateway-configured | |
| 6.10 | Become a vendor / registration | open / approval / closed states | |
| 6.11 | Vendor public profile | new vendor with nothing; vendor with everything | |

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
