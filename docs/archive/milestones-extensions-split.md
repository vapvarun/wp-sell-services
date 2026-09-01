# Milestones vs Extensions — Product Split Plan

**Status:** Approved for build · **Branch:** 1.1.0 · **Date:** 2026-04-21

## Decision

Split by **order origin**, not by service flag.

| Order origin | Feature available |
|---|---|
| `platform === 'request'` (buyer posted custom scope) | **Milestones** only |
| Any other platform (`standalone`, `woocommerce`, `edd`, `fluentcart`, `surecart`) | **Extensions** only |

Mutually exclusive. Seller never sees both CTAs on the same order. Buyer never sees both request flows.

## Why this works

- **Request-mode = large custom project.** Buyer posted requirements, vendor sent a custom proposal, buyer accepted. Scope was negotiated, so it's natural to break delivery into paid phases the buyer approves individually. Milestones fit.
- **Catalog service = fixed-price small job.** Buyer paid full price upfront. If scope creeps mid-order, vendor quotes the extras — no submit/approve cycle needed, ad-hoc. Extensions fit.

No overlap in practice because the two worlds don't cross: a buyer-request order cannot be a catalog purchase, and vice versa.

## Backend guards

- `MilestoneService::propose()` — reject if parent order `platform !== 'request'`.
- `ExtensionOrderService::create_extension_request()` — reject if parent order `platform === 'request'`.

Server-side guards survive any UI drift.

## Frontend gating

`templates/order/order-view.php`:

```php
$is_request_order     = 'request' === $order->platform;
$can_propose_milestone = $is_vendor && $is_request_order     && in_array( $order->status, $active_statuses );
$can_request_extension = $is_vendor && ! $is_request_order   && in_array( $order->status, $active_statuses );
```

## Empty-state copy

- **Milestones empty state** (request orders): "This is a custom project — break it into paid phases so the buyer can approve each stage as you deliver."
- **Extensions empty state** (catalog orders): "This is a fixed-price service and the buyer has already paid. Quote any small extras here; buyer pays and you keep working."

## Email templates (currently missing — both features)

### Milestones (4)
| Trigger | Recipient | Template |
|---|---|---|
| `wpss_milestone_proposed` | Buyer | `emails/milestone-proposed.php` |
| `wpss_milestone_paid` | Vendor | `emails/milestone-paid.php` |
| `wpss_milestone_submitted` | Buyer | `emails/milestone-submitted.php` |
| `wpss_milestone_approved` | Vendor | `emails/milestone-approved.php` |

### Extensions (3)
| Trigger | Recipient | Template |
|---|---|---|
| `wpss_extension_request_created` | Buyer | `emails/extension-proposed.php` |
| `wpss_extension_approved` | Vendor | `emails/extension-approved.php` |
| `wpss_extension_rejected` | Vendor | `emails/extension-declined.php` |

Each requires:
- HTML template (uses `email-header.php` + `email-footer.php` partials like `tip-received.php`)
- Plain-text fallback under `templates/emails/plain/`
- `EmailService::send_*()` method
- Type constant + template map + `is_email_type_enabled` entry
- `Settings.php` admin toggle
- `Activator.php` default (enabled by default)

## Listener wiring

`Plugin::define_notification_hooks()` — each milestone/extension event already fires `NotificationService::send()` for in-app; add `EmailService::send_*()` alongside so email + in-app go out together (matching the tip pattern).

## Edge cases

- **Test orders from this branch on catalog parents** (e.g. order #6, platform='standalone') have milestones from before this guard — they stay in DB for history; the server blocks NEW proposals and the CTA hides.
- **Vendor hits REST `/orders/{id}/milestones` on non-request parent** — controller returns 400 with the guard message from the service.
- **Request order with zero milestones** — vendor can still use the standard Deliver Work on the parent; milestones are optional even for request orders.
- **Parent order cancelled** — existing milestones remain for audit; no new proposals possible.

## UX gap audit (buyer + seller perspectives)

### Seller journey — request order with milestones

| Step | Seller sees | Confusion risk | Fix |
|---|---|---|---|
| Proposal accepted, order created | Order page with "+ Propose Milestone" CTA only | Where do I start work if I haven't proposed yet? | Empty state copy nudges them to break it into phases |
| Proposes phase 1 | Awaiting buyer payment state | Can I start work without payment? | State label explicit: "Awaiting buyer payment" |
| Buyer pays phase 1 | Email "Milestone paid — start work" + in-app notification + timeline shows In Progress + Submit Delivery button | How do I deliver? | Submit Delivery button is the only primary action visible on the phase |
| Submits phase 1 | Awaiting buyer approval | What if buyer ghosts? | Dispute flow available (same as main orders); auto-approve is v1.1 roadmap |
| Buyer approves | "Milestone approved" email + in-app | Is the order complete? | Timeline shows phase 1 ✓; parent order lifecycle is independent |
| Proposes phase 2 | Same cycle repeats | Will this conflict with phase 1? | Timeline shows all phases in order with clear state per-phase |

### Seller journey — catalog order with extensions

| Step | Seller sees | Confusion risk | Fix |
|---|---|---|---|
| Order received (regular checkout) | Active order page with "Buyer asked for extra work?" CTA | Is this the same as milestones? | Only one CTA visible; copy differentiates "small extras" vs "phases" |
| Sends quote for an extra | Awaiting buyer notice on order page | Do I still need to deliver original work? | Main Deliver Work button stays visible — extension is an add-on, not a blocker |
| Buyer pays extension | Email + in-app + $ lands in wallet | Is the deadline extended? | `+N days added to delivery` message + parent's due-date display updates |
| Buyer declines | Email "Extension declined" | Can I ask again? | Decline-reason shown in email; vendor can send a revised quote immediately |

### Buyer journey — request order

| Step | Buyer sees | Confusion risk | Fix |
|---|---|---|---|
| Accepted proposal, order starts | Order page shows empty milestone timeline | Why is nothing happening? | Info line: "Your seller will propose the first milestone." |
| First milestone proposed | Email + timeline card with deliverables, amount, Accept & Pay / Decline | Why am I paying again? I already paid for the order. | Empty-state explains pay-per-phase; parent order has no upfront payment on request orders |
| Pays milestone | Timeline shows Paid · seller working | Can I message the seller? | Conversation panel is visible on the parent order view, unchanged |
| Seller submits | Email + timeline card with Review & Approve | What if I don't like it? | "Request revision in chat" link scrolls to conversation; NO separate rejection flow (per product decision) |
| Approves | Timeline shows ✓ Approved; seller notified | Is this phase locked? | Yes — approval is terminal; dispute flow available for issues |
| Order has 3+ milestones | Scrollable timeline list with each state color-coded | Which one is current? | State labels + coloured left-border indicate phase status |

### Buyer journey — catalog order with extensions

| Step | Buyer sees | Confusion risk | Fix |
|---|---|---|---|
| Seller proposes extension | Email + order page card "Quote for extra work" | Did they ask for more money arbitrarily? | Quote card shows reason they typed + what's delivered for the money |
| Pays | Parent deadline updates visibly | Did my order restart? | No — status unchanged, just deadline pushed out by the days included in the quote |
| Declines | Parent order unchanged | Can seller pressure me? | Decline is one click; seller sees "declined" with buyer's note if provided |

## Known gaps (not fixed in this pass, tracked)

- **"Request revision" button** on buyer's milestone view that scrolls to chat and pre-fills a reference message. (Spec'd, not yet built — revisions happen via manual chat right now.)
- **Auto-approve timer** on submitted milestones (v1.1 — 7-day default matching `auto_complete_days`).
- **System messages in order chat** when a milestone is submitted / approved — gives a visible audit trail in the conversation. Tips do this already (`ConversationService::add_system_message`). Should replicate for milestones.
- **Attachments on milestone delivery** — schema supports, UI text-only for v1.

## Build order

1. Backend guards (both services) — ~15 min — ✅ done
2. CTA gating in `order-view.php` — ~15 min — ✅ done
3. 7 email templates + partials + service wiring — ~90 min — ✅ done
4. Settings toggles + Activator defaults — ~20 min — ✅ done
5. Listener wiring in `Plugin.php` — ~15 min — ✅ done
6. Browser verify end-to-end, both journeys — ~30 min — ✅ done (CLI smoke tests confirmed)

Total ~3 hours.

---

## v1.1 follow-up — Upwork-style contract model (user request 2026-04-21)

User refined the model: vendors should choose contract type **at proposal time**
(like Upwork). The buyer sees the full milestone plan before accepting, and
acceptance locks the predefined breakdown.

### Vendor proposal flow

When a vendor responds to a buyer's Request, they pick:

- **Fixed contract** — single price, single delivery. Existing flow.
- **Milestone contract** — multi-phase plan with title + description + amount + days
  per milestone. Buyer sees the breakdown before accepting.

### On proposal acceptance

- **Fixed contract:** create order, buyer pays full price upfront, standard flow.
- **Milestone contract:** create parent order at $0 base + bulk-insert all
  predefined milestones as `pending_payment` sub-orders linked to the parent
  in `sort_order` matching the proposal sequence. Buyer pays the first
  milestone to start work; vendor delivers; buyer approves; next milestone
  unlocks for payment; repeat.

### Mid-contract additions (kept)

Per user 2026-04-21: vendor can still propose **additional** milestones any
time during a milestone contract — same Propose Milestone button as today.
Customer may need extra things mid-flight; the predefined plan is the
starting commitment, not a hard cap.

### Schema impact

- `wp_wpss_proposals` — add `contract_type` enum (`fixed`|`milestone`) and
  `milestones` longtext (JSON of the predefined breakdown).
- `wp_wpss_orders` — no change. Pre-created milestone sub-orders use the
  existing `platform='milestone'` shape.

### UI impact

- Proposal form (vendor): add "Contract type" radio + repeater for the
  milestone breakdown (only shown when milestone selected).
- Proposal review (buyer): show contract type + milestone list with totals
  before the Accept button.
- Order page (request order with predefined milestones): timeline pre-populated
  on day 1 with all phases visible; first one "Awaiting your payment" for
  the buyer, others "Locked — pay phase 1 first".

### Acceptance hand-off

`ProposalsController::accept_item` (or the hook fired on acceptance) gains a
branch: when `contract_type === 'milestone'`, iterate the proposal's stored
milestones array and call `MilestoneService::propose()` once per row. Same
service code path that's already proven by the v1 build.

### Pricing model on milestone contracts

Parent order's base price is $0 — money flows entirely through milestone
sub-orders. This avoids the "did I just pay base + each phase?" confusion
that would happen if base was non-zero AND milestones charged on top.

### Open question for user

Should the predefined milestones lock-step (must pay phase N before phase
N+1 unlocks) or float (buyer can pay any pending phase any time)?
- **Lock-step** matches Upwork's "in-progress milestone" model and prevents
  the buyer accidentally paying out-of-order.
- **Float** is what we already implemented and gives more flexibility.

Recommend lock-step for v1.1 — closer to Upwork mental model. Easy upgrade
from existing code (one extra check on the Pay button).

### v1.1 decisions (signed off 2026-04-21)

| Question | Answer |
|---|---|
| Lock-step vs float | Lock-step. Milestone N's "Accept & Pay" only enables when every milestone with a lower `sort_order` is `completed` or `cancelled`. |
| Buyer comparing proposals | Total + small badge: "Fixed $500" / "5 phases · $600". Full breakdown on the proposal detail. |
| Auto-approve window | 7 days, configurable via existing `auto_complete_days` setting. |
| Dispute granularity | v1: parent-level dispute. Per-milestone dispute deferred to a later release. |
| Project completion | Auto-flip parent to `completed` when all milestones are terminal AND no `pending_payment` remain. |
| Mid-contract cancellation | Cancellation routes the in-progress phase through dispute; pending phases auto-cancel. |

### Money flow (audited)

**Parent order on a milestone contract:** `total = 0`, `status = in_progress`,
`payment_status = paid` (synthetic), `paid_at = now`. Buyer never sees a
parent checkout — they go straight to the order page where the milestone
timeline is the entire payment surface.

**Each milestone sub-order:** identical lifecycle to the existing milestone
flow — pay through `?pay_order=N` checkout, commission split at payment time,
vendor wallet credited at pay time, `type='milestone'` ledger row.

**No double-billing:** parent is $0 forever; all money flows through
sub-orders. Sum of milestones = vendor's total contract earnings.

### 7 guards the implementation must enforce

| Guard | Risk if missing | Fix in code |
|---|---|---|
| Skip 48h cleanup on contract milestones | Long projects could lose phases to abandon-cron if buyer is slow | Add `is_contract_milestone=true` to milestone meta; cleanup cron skips those rows |
| Validate milestones JSON on proposal submit | Vendor could submit empty/zero phases | Each row: `title` non-empty, `amount > 0`, `days >= 0`; reject otherwise |
| Transactional bulk milestone insert | Partial state if 3 of 5 inserts fail | Wrap acceptance handler's milestone loop in `START TRANSACTION` / rollback on any failure |
| Server-side lock-step on pay_order | Buyer crafts URL for a locked milestone | StandaloneCheckoutProvider's pay_order handler rejects when milestone has earlier non-terminal siblings |
| Auto-complete parent on final approval | Parent stuck `in_progress` after last phase done | Hook `wpss_milestone_approved` → if all milestones terminal, set parent to `completed` |
| Cascade cancel on parent cancellation | Parent cancelled but stale milestones linger | Hook `wpss_order_cancelled` (or analogous) → mark all linked `pending_payment` milestones cancelled immediately |
| Lock proposal edits after acceptance | Vendor changes terms after buyer agreed | Already enforced by existing `update()` status check; document so future maintainers don't loosen it |

### Build order for v1.1 (incorporating audit)

1. Schema: `contract_type` + `milestones` on `wp_wpss_proposals` ✅ done
2. ProposalService — submit/update/format accept and validate `milestones[]` shape
3. `BuyerRequestService::convert_to_order` — branch on `contract_type`; wrap milestone bulk-insert in DB transaction; set parent `total=0`, `status=in_progress`, `payment_status=paid` for milestone contracts
4. MilestoneService::get_for_parent — add `is_locked` computed field
5. MilestoneService::propose — accept and persist `is_contract_milestone` flag
6. StandaloneCheckoutProvider — server-side lock-step rejection on pay_order
7. MilestoneService::cleanup_abandoned_milestones — skip contract milestones
8. Plugin.php listeners — auto-complete parent on final approval; cascade cancel on parent cancel
9. Vendor proposal form template — radio + repeater + live total
10. Buyer proposal review template — breakdown + Accept confirm dialog
11. Order-view milestone timeline — render `is_locked` styling + "Pay phase N first" message
12. Browser verify the full flow end-to-end
