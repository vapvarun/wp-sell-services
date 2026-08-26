# Duplicate / divergent UI + TEMPLATE flows

Audit 2026-07-20. Companion to `DUPLICATE-FLOWS-money.md`. Same bug class:
**one customer-facing surface, many implementations, copies drift apart.**
See the "ONE FLOW, ONE IMPLEMENTATION" rule in `CLAUDE.md`.

**17 duplications / divergences found.** Status legend: ✅ fixed · ⚠️ partially
fixed · ❌ open.

---

## Re-audit 2026-07-28 — the concrete drift/bugs are now cleared

Re-checked every item at the code level. The **customer-visible defects** (a
wrong number, a broken link, a reduced view, dead code) are fixed; what remains
is the **"one renderer" consolidation** work (visual consistency) which is a
larger, separate effort.

**Fixed since the 07-20 audit:**
- **#3** search shortcode — the shortcode now emits `search`+`category` on the
  archive contract (no longer WP core search).
- **#5 (currency)** buyer-request `$` hardcode → `wpss_format_price()` (`8d66216`).
- **#7** orphaned `templates/myaccount/` family (5 files) deleted; subdir list
  trimmed (`6f3ef4f`).
- **#8** admin view-count key `_wpss_view_count`→`_wpss_views` (`8d66216`).
- **#9** Delivery is WPSS-internal — the payment rail must not source it. The
  EDD + SureCart order providers now set the WPSS order's `delivery_deadline`
  from the canonical `wpss_get_service_delivery_days()` at creation (like the Woo
  adapter); the rail-side cart/product reads were reverted (pro `41d9a75`).
- **#11** vendor "orders" sort now joins `wpss_vendor_profiles.completed_orders`
  instead of the never-written `_wpss_completed_orders` meta (`7c1b281`). The
  single-service reader already prefers the profiles table.
- **#14** standalone "Add New Service" → frontend wizard, not wp-admin (`6f3ef4f`).
- **#15** `[wpss_order_details]` → canonical `order-view.php`, not the missing
  `order/details.php` (reduced fallback) (`6f3ef4f`).

**Still open — consolidation / code-quality (each a real chunk, not one-line):**
- **#16** 5 inline `<style>` blocks in `StandaloneAccountProvider` → enqueued CSS.
- **#1** order-list ×4 (standalone lacks pagination/filter/routing parity).
- **#4** service card ×6 → one renderer (biggest visible: favourite/verified
  badge + two price formats). **#5 (structure)** request card ×4, **#6** vendor
  card ×4, **#13** disputes ×2, **#12** message thread ×2 (largest/riskiest).

Order of remaining work + the "one partial, every surface `require`s it" pattern
are in the sections below.


---

## Re-run 2026-08-26 (1.7.0 sweep)

Two more of the same class on the UI side, both fixed.

| Flow | The split | What it cost | Resolution |
|---|---|---|---|
| "Which dashboard section is the default?" | `wpss_append_dashboard_section()` dropped `orders` as implicit, while `resolve_current_section()` picked the landing section by role | The two disagreed from 1.2.0, when landing became role-aware. A member who both buys and sells clicked **My Orders** and landed on **Sales Orders** — reading "No sales yet" as "you have never bought anything", with 12 real orders one URL away | A link always names its section, so it does not depend on what the default is |
| "Where does the cart link point?" | Two carts exist on purpose (`/cart/` and `/service-cart/`), but which one the theme's icon pointed at was a checkbox that shipped off | A buyer with a service in their cart clicked the header icon and was told the cart was empty — with the cart badge showing 1 on the same page | Derived from the rail that owns checkout; the checkbox now overrides rather than decides |

**Deliberately NOT collapsed:** `/cart/` and `/service-cart/` stay separate.
The split exists so the marketplace does not fight WooCommerce for that URL, and
a future reader who sees two carts and "simplifies" them will reintroduce the
collision. Recorded on card #10236389213.

---

## 1. ✅ FIXED — Standalone account orders: fatal + 4 implementations

Fixed in `271b90e` (the TypeError). The duplication itself is still open.

`StandaloneAccountProvider.php:468`, `:595` passed a bare int where
`array $args` was expected → **TypeError, white screen** on standalone My Orders
and the vendor dashboard's Recent Orders.

Four order-list implementations remain:
`StandaloneAccountProvider.php:468/:595`, `templates/dashboard/sections/orders.php:82`,
`templates/myaccount/service-orders.php:78`, `Shortcodes.php:654`.

**Still open:** the standalone copy has no pagination, no status filter, and no
tip/extension/milestone routing that the unified dashboard has — so the same
buyer sees a poorer order list depending on which page they land on.

---

## 2. ✅ FIXED — Buyer Requests block matched a key nobody writes

Fixed in `271b90e`. Block required `_wpss_request_status`
(`Blocks/BuyerRequests.php:152`); `BuyerRequestService.php:204,416` writes
`_wpss_status`, and only `CLI/TestFlowCommand.php:239` ever wrote the other one.
Block rendered **"No requests" on every real site** while the
`[wpss_buyer_requests]` shortcode listed them fine.
Proven: old key → 0 results, new key → 1. Exact `_wpss_featured` twin.

---

## 3. ⚠️ PARTIAL — Service search: three different param contracts

The `wpss_search` → `search` fix landed in the **block only**. The shortcode is
still on a third contract.

| Source | Emits / reads |
|---|---|
| `Shortcodes.php:204,208` | `s` + `post_type` + `service_category` |
| `Blocks/ServiceSearch.php:139,163` | `search` + `category` ✅ |
| `ServiceArchiveView.php:687,700` (the reader) | `search` + `category` |

**Customer impact:** the shortcode submits to WP core search (`is_search`), not
the service archive, so `modify_archive_query()` returns early — **no
vacation-vendor exclusion, no moderation filter, no filter bar**, and the
category dropdown is silently ignored.

---

## 4. ❌ OPEN — Service card: 6 implementations

`templates/content-service-card.php:64` (BEM, canonical) vs
`Blocks/ServiceGrid.php:238` vs `Blocks/FeaturedServices.php:262` vs
`Shortcodes.php:899` (fallback) vs `templates/dashboard/sections/services.php:205`
vs `StandaloneAccountProvider.php:682`.

**Customer impact:** only the canonical template has the favourite toggle, the
verified-vendor badge and the category chip — the same service **loses its
heart and verified badge** when shown via any block or the standalone account.
Worse, blocks format money with `wpss_format_currency()` (`functions.php:552`)
while the template uses `wpss_format_price()` (`functions.php:38`), so **prices
can render in two different formats on the same page**.

**Fix:** make `content-service-card.php` the only renderer; blocks/shortcodes
pass args and `require` it (same pattern as `partials/notifications-list.php`).

---

## 5. ❌ OPEN — Buyer-request card: 4 implementations

`templates/content-request-card.php:92` vs `Blocks/BuyerRequests.php:231` vs
`Shortcodes.php:965` vs `templates/dashboard/sections/requests.php:105`.

**Customer impact:** the shortcode copy hardcodes a dollar sign —
`sprintf( '$%s - $%s' )` at `Shortcodes.php:983-991` — instead of the currency
helper, so **non-USD marketplaces show dollar budgets**. It also omits skills,
proposal count and expiry that the canonical template and the block show.

---

## 6-17. ❌ OPEN — remaining divergences

| # | Duplication | Where | Customer impact |
|---|---|---|---|
| 6 | Vendor card ×4 | `Shortcodes.php:931`, `templates/partials/vendor-card.php:59`, `Blocks/SellerCard.php:156`, `SingleServiceView.php:718` | Same vendor shows different stats/badges per surface |
| 7 | Orphaned `templates/myaccount/` family | 5 templates, **zero** `include`/`locate_template` refs in `src/`, no `woocommerce_account_*` hook registered | Dead code that looks live; invites future edits to a file nobody renders |
| 8 | `_wpss_view_count` read, `_wpss_views` written | read `Admin/Metaboxes/ServiceMetabox.php:774,1473`; written `Frontend/SingleServiceView.php:196` | Admin service view-count always 0 |
| 9 | `_wpss_delivery_time` read, `_wpss_delivery_days` written | pro `SureCartProductProvider.php:88`, `EDDCheckoutProvider.php:41` vs free writers | SureCart/EDD products show no delivery time |
| 10 | `_wpss_extras` vs `_wpss_addons` | wizard writes one, metabox the other; `functions.php:1719` papers over it | Add-ons can vanish depending on which editor last saved |
| 11 | `_wpss_completed_orders` user meta | read `SingleServiceView.php:440`, `VendorsController.php:286` — **no writer anywhere** | Vendor "completed orders" always 0 |
| 12 | Message thread ×2 | `templates/dashboard/sections/messages.php` (375 lines) vs `templates/order/conversation.php` (560 lines) | Different messaging features depending on entry point |
| 13 | Disputes ×2 | dashboard section (189 lines) vs myaccount (128 lines) | Different columns/actions for the same dispute |
| 14 | Vendor services ×3 | dashboard, myaccount, standalone | **Standalone links vendors into wp-admin `post-new.php`** instead of the frontend wizard |
| 15 | `[wpss_order_details]` broken | `Shortcodes.php:780` locates `order/details.php`, which does not exist | Always renders the reduced inline fallback |
| 16 | Duplicated inline `<style>` | `StandaloneAccountProvider.php:706`, `:713` re-declare `.wpss-service-card` / `.wpss-orders-table` | Styling outside the design system, drifts from tokens |
| 17 | Notifications ×3 | ✅ **FIXED** — consolidated into `templates/partials/notifications-list.php` (`f009858`, `ecbb2d8`) | — |
| 18 | Order-status label map ×4 | ✅ **PARTLY FIXED** — see below | REST copy was returning `Pending_payment` for 7 of 18 statuses |

### #18 — the order-status label map, in four places

`wpss_get_order_statuses()` (`functions.php:2588`) is canonical: all 18 statuses,
translated, filterable via `wpss_order_statuses`. Three other copies existed.

| Copy | Covers | Status |
|---|---|---|
| `API/OrdersController::get_status_label()` | 11 of 18, `ucfirst()` for the rest | ✅ **Fixed** — delegates to the canonical map |
| `Admin/Admin.php:1630` (order detail) | 8 of 18, and calls `pending_requirements` **"Waiting for Requirements"** where the canonical map says "Pending Requirements" | ❌ **Open** — straight drift, but it is admin display copy so changing it needs a browser check |
| `Admin/Pages/VendorsPage.php:1840` (filter dropdown) | 7 of 18 | ❌ **Open** — a *deliberate* subset is defensible for a filter, but it omits `disputed`, `on_hold`, `late` and `revision_requested`, so an order in any of those cannot be filtered to. Needs a UX call, not just a rename. |
| `Admin/Pages/ManualOrderPage.php:802` | 5, with **intentionally different wording** — "In Progress (Skip Requirements)", "Pending Requirements (Payment Complete)" | ✅ **Leave alone.** These explain the consequence of picking a starting status when creating a manual order. Context copy, not drift. |

The REST copy was the one that reached customers hardest: it fell through to
`ucfirst( $status )` for the seven statuses it did not list — including the three
most common buyer-facing ones — so the API answered `Pending_payment`, underscore
and all, untranslated. Every client then kept its own label map to compensate,
which is why the mobile app renders English on localised sites.

---

## Suggested order of work

1. **#3 shortcode search contract** — small, and it silently disables moderation
   + vacation filtering today.
2. **#8/#9/#10/#11 key drift** — each is a one-line rename or a missing writer,
   and each currently shows a customer a wrong number (0 views, 0 completed
   orders, missing delivery time, vanishing add-ons).
3. **#4 service card → one renderer** — biggest visible inconsistency
   (missing favourite/verified badge, two price formats on one page).
4. **#5 request card** — includes the hardcoded `$` that breaks non-USD.
5. **#6 vendor card**, then #12/#13/#14 surface consolidation.
6. **#7 + #15** — dead template cleanup, do last so it does not collide with the
   consolidation above.

**Pattern to follow:** `templates/partials/notifications-list.php` — one partial,
every surface `require`s it. Established in `f009858`.
