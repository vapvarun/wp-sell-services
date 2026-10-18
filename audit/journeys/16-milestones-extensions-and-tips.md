# Journey 16 - Milestones, paid extensions, deadline extensions and tips

**Roles:** buyer (`subscriber`), seller (`wpss_vendor`), admin
**Rail:** standalone (payment of a phase or tip), woo (pay-order URL)
**Status:** written 2026-10-18; walk pending (see docs/qa/walks.json)
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
