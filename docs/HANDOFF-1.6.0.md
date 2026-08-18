# WP Sell Services 1.6.0 — Handoff

**Date:** 2026-08-13
**Branch:** `1.6.0` in both plugins, everything pushed
**Free tip:** `2307cd9` (26 commits today) · **Pro tip:** `bc2625b` (12 commits today)
**Basecamp:** Bugs column **empty**; 21 cards in Ready for Testing

This release is a **bug-fix release**. One feature card was deliberately kept out
of it (see *Deferred*, below).

---

## 1. The one thing worth knowing

Nearly every card in this sweep resolved to the **same defect wearing different
clothes: one flow implemented twice, with the copies drifting apart.**

| Flow | Copies found | What it cost |
|---|---|---|
| Rail status mapping | 3 private arrays that disagreed | orders showed different statuses depending on the platform |
| Member dashboard | 2 (`[wpss_dashboard]`, `[wpss_account]`) | sections present on one, missing on the other |
| Admin permission gate | 2 | denials answered with the wrong error code |
| Brand accent | 2 palettes | UI drifted between green and indigo |
| Message renderer | 2 | links clickable in one place, not the other |
| `package_id` | 2 meanings (stable id vs array index) | orders could show a package the buyer never bought |
| EDD localize block | 2 byte-identical copies | a string key was missing from **both**; "Required" untranslatable |
| Rail order providers | 4 implementations of an interface nothing called | ~700 lines of dead code |

**Before fixing any WPSS bug, grep for a second implementation.** Fixing the
symptom in one copy leaves the other to re-raise the same card. This is now
recorded in both `CLAUDE.md` files under *ONE FLOW, ONE IMPLEMENTATION*.

---

## 2. What shipped

### Money and orders (highest risk, highest value)

- **Vendors are now credited on every payment platform.** `StandaloneOrderProvider::mark_as_paid()`
  is the single path that fires `wpss_order_paid`; all four rails route to it and
  none re-implements crediting. Previously **EDD never marked orders paid at
  all** — the order never started and the vendor was never paid.
- **Hooks were audited against the real upstream plugin sources.** The result:
  EDD 9/13 real, FluentCart 2/13, **SureCart 0/9**. All 16 fabricated hook names
  were rebuilt on names that actually fire. *If you touch an adapter, clone the
  upstream plugin and confirm the hook exists — do not trust the existing name.*
- **Package identity is frozen on every order.** Packages carry a stable id
  (base **1000** via `wpss_package_id_base` — deliberately above any legacy array
  index so the two can never be confused). Existing orders were repaired by
  `backfill_package_snapshots()` / `backfill_package_ids()`, matched against what
  the buyer actually paid.

### New in Free

- **Offline payment receipts, end to end.** New `wpss_payment_receipts` table,
  `PaymentReceiptRepository`, `PaymentReceiptService`, REST route, admin review
  queue, 3 email types, printable receipt. **Off by default.**
  The claim is row-locked (`UPDATE … WHERE status='submitted'`), so two admins
  approving simultaneously credits exactly once.
- **Role-aware email preferences** (buyer 5 categories, vendor 8).
- **Configurable checkout billing fields**; name and email always collected.
- **Don't email someone who is actively on the site** (off by default).

### Tooling

- `bin/i18n-verify.py` now fails on **version drift** between the plugin header,
  POT `Project-Id-Version`, `package.json` and readme Stable tag. This is what
  let a 1.6.0 plugin ship a POT declaring 1.5.1 with the gate passing.
  The two copies (Free/Pro) are byte-identical and config-driven — **keep them
  that way**; the difference belongs in `.i18n-config.json`.
- 54 dead English JS fallbacks removed across both plugins.

---

## 3. What is NOT verified — read before tagging

1. **No end-to-end purchase was ever driven on EDD, FluentCart or SureCart.**
   The credit path was fixed and verified by reading code and auditing hooks
   against upstream sources, **not** by completing a real checkout. This is the
   largest untested surface in the release. WooCommerce and standalone were
   exercised; the other three were not.
2. **The indigo accent** has not been checked on BuddyX/Reign or in dark mode.
3. **Verifying a receipt sends the buyer several emails at once** — correct, but
   may read as noise. Worth a product decision.
4. **The app team must adopt the stable `package_id`.** The old index still
   resolves via fallback, but that fallback is the thing we want to retire.
5. A real file-upload round-trip on the messages thread was not re-run after the
   renderer consolidation.

---

## 4. Deferred, deliberately

**Delayed message email** (card 10199168939) moved to *Ready For Development*,
not built. Dropping a new background job into a release QA is already testing
would add new settings, a new cron surface and a new class of silent failure
next to 38 commits of bug fixes.

The design questions are answered on the card. The key finding: there is **no
per-message read state**, but `wpss_conversations.unread_counts` is already
per-conversation-per-user — so the grain is per-conversation (one job, not one
per message), and the job should **re-decide at fire time** rather than be
cancelled by every event that might invalidate it.

---

## 5. Two cards still to be written

The owner asked for cards on **offline payment method** and **manual orders**.
Not yet written, blocked on one question:

> Is "manual orders" hardening the existing `ManualOrderPage`, or designing
> admin-created orders as a first-class flow (admin raises an order for a buyer,
> sends it for payment, tracks it through)?

The second overlaps heavily with the offline-receipts work that just shipped,
and the card would be scoped very differently either way.

---

## 6. Also open

- PHPStan: ~138 runtime-dangerous errors still need classifying by reachability.
- `MigrationManager`: 539 lines of dead code containing a 1.4.0 backfill that
  never ran. Decide: run it, or delete it.
- 3 API response-shape Suggestion cards.

---

## 7. Process notes that cost time

- **Split the check from the commit.** Running `composer phpcs && git commit` in
  one shell command pushed 4 times with phpcs failing. Separate commands.
- **Insert new methods AFTER a closing brace, never before a declaration** —
  inserting before one steals the host's docblock. This happened 4 times.
- **Editing `assets/js/*.js`? Rebuild the `.min`.** `Assets.php` swaps to `.min`
  when present, so source edits are inert until rebuilt.
- **Basecamp column IDs:** Ready for Testing is **`9381846126`**. `9381846060`
  is *In Testing* — a card moved with the wrong ID skips the QA queue and sits
  unclaimed. `CLAUDE.md` had this wrong and is now corrected.
