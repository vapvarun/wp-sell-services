# Journey 16 - Milestones, paid extensions, deadline extensions and tips

**Roles:** buyer (`subscriber`), seller (`wpss_vendor`), admin
**Rail:** standalone (payment of a phase or tip), woo (pay-order URL)
**Status:** written 2026-10-18; walk pending (see .qa-results/walks.json)
**Docs:** docs/website/order-management/milestones-wpss.md, extensions-wpss.md, tipping-extensions.md

A single order shows milestones or paid extensions, never both: milestones only on buyer-request orders, extensions only on catalog orders.

## Steps

### 1. Milestone contract
Seller proposes a milestone plan on a buyer request; buyer accepts; buyer pays phase 1; seller delivers; buyer approves; phase 2 unlocks.

**Expect** phase N is payable only after phase N-1 is approved, the parent completes when every phase is terminal, an ad-hoc phase unpaid after 48 hours cancels, and cancelling the parent cancels unpaid phases.

### 2. Paid extension
Seller quotes extra work and days on a catalog order; buyer accepts or declines.

**Expect** accept charges once, splits commission once, and pushes the parent deadline by the quoted days; decline changes nothing.

### 3. Deadline extension request
Seller requests up to 14 extra days with a reason.

**Expect** approve moves the deadline and a late order returns to In Progress; deny keeps the deadline; both parties are told.

### 4. Tip (Pro)
Buyer sends a tip on a completed order.

**Expect** charged once (double click safe), credited to the seller's earnings with commission per settings, receipt to the buyer, notification to the seller, refund reverses it.

### 5. REST and mail for each money-adjacent step
- Milestones: `GET`/`PUT /milestones/{id}`, `.../submit`, `.../approve`, `.../decline`, `.../request-revision`, `.../pay`. Only the owning seller submits, only the buyer approves, declines or requests a revision; a phase cannot be paid before the previous one is approved; every repeat call changes nothing. The proposal mail is `emails/milestone-proposed` and the buyer sees the plan in `order/milestone-view`.
- Extensions: `POST /orders/{order_id}/extension` quotes (catalog orders only), `POST /extensions/{id}/decline` declines. Mails: `emails/extension-proposed`, `emails/extension-approved`, `emails/extension-declined`; the buyer sees the quote in `order/extension-view`.
- Delivery: marking delivered sends `emails/delivery-ready` to the buyer.

**Expect** wrong role 403, a milestone route on a catalog order and an extension route on a request order are both refused, and each mail names the right order and amount.

### Order page after a tip (member sweep 1.9.0)
As the buyer, tip a completed order (pending or paid) and reload the order page.

**Expect** HTTP 200 and the tip receipt with the amount and its state; nothing in debug.log. Guard: `static:template-wpdb-scope`.

### Locked phase on a store rail (vendor sweep 1.9.0)
On the Woo rail open ?pay_order=<second phase id> as the buyer while phase one is unpaid.

**Expect** no payment page; the buyer lands on the order. Guard: `static:locked-phase-pay-seam`.

### A declined phase stays closed (combo walk 1.9.0)
As the buyer decline a phase, then open its old Woo pay link (and ?pay_order=<id>).

**Expect** no payment page, the Woo order is cancelled, and the order page says it is cancelled; a phase that is locked shows "unlocks once the previous phase is approved" and no Pay button. Guards: `static:closed-order-cannot-be-paid`, `static:pro-declined-phase-cancels-store-order`.

### Extra Work pending card inset (card:10417579719)
As buyer and vendor, open a catalog order with a pending extra-work quote at 1440, 1024 and 390, light and dark.

**Expect** `.wpss-extension-pending-card` is inset from `.wpss-order-section` by the same padding as `.wpss-order-section--extension-cta` (not a 1px flush). Measure the coloured box, not the inner text.

### Awaiting-confirmation extra-work heading (card:C-78)
As the buyer, submit an offline payment on an extra-work quote and open the extension sub-order while it still awaits confirmation.

**Expect** the heading is waiting / quote sent, never "Quote declined", and bank instructions or a single waiting notice are visible.
