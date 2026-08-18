# Journey 01 — Buyer hires a seller from a request

**Roles:** buyer (`subscriber`), two sellers (`wpss_vendor`), admin
**Rail:** standalone payments
**Status:** passes (steps 7 + 8 fixed 2026-08-17, Basecamp 10208094640 / 10208199238)

Guards cards 10208094430, 10208094503, 10208094640, 10208199238.

## Preconditions

- Buyer request open (`status = open`), owned by the buyer.
- Two sellers who have not yet proposed on it.
- `wpss_orders[allow_disputes]` irrelevant here.

## Steps

### 1. Seller A submits a proposal
Post a proposal with a price and delivery days.

**Expect**
- Proposal row created with `status = pending`.
- **Buyer receives exactly ONE email** (branded, from `EmailService`).
  Two emails means the type is missing from
  `NotificationService::is_email_service_handling()`'s `$covered_types`.

### 2. Buyer's notification inbox
Dashboard → Notifications.

**Expect**
- A **"New proposal received"** row naming the seller and the bid price.
- Icon is a **briefcase**, not a generic bell.
- The literal text "You have a new notification" must **not** appear — that is
  `NotificationService::send()`'s `default:` branch and means the type has no
  `case`.

### 3. The notification deep-links
Click the notification title.

**Expect**
- It is an `<a>`, not inert text (`action_url` populated).
- Lands on the buyer request showing its proposals.

### 4. Seller B also proposes
Second seller submits on the same request.

**Expect** buyer sees a second "New proposal received".

### 5. Buyer hires Seller A
Accept A's proposal.

**Expect**
- An order is created, `platform = 'request'`, `status = pending_payment`.
- **Seller A** gets "Your proposal was accepted", naming the request and the
  order number — *not* "Notification / You have a new notification".
- **Seller B** gets "Proposal not selected", and B's proposal status is
  `rejected`. Silence here is the old bug: `reject_other_proposals()` used to
  bulk-UPDATE and fire nothing.

> **Scale note:** every losing proposal currently produces one notification and
> one email at hire time. On a request with hundreds of bids that is a burst.
> Open question with the owner — see `HANDOFF-1.6.0-bugs.md`.

### 6. Order detail
Buyer opens the new order.

**Expect**
- Line title is the **request title**, from
  `meta.proposal_snapshot.request_title`.
- **Not** "Service Checkout" — that is the checkout *page* name leaking in
  because proposal orders carry `service_id = 0`.

### 7. Pay the order
Click Pay → `?pay_order={id}` → choose **Offline** → Pay.

**Expect**
- Checkout line shows the request title, not the checkout page's name.
- The form posts `service_id=0` — never the checkout page's ID.
- After paying: `payment_method = 'offline'` recorded. Status stays
  `pending_payment` (correct — offline money arrives out of band), and the
  buyer's order page shows the Payment Instructions.

Then confirm it is not a dead end: wp-admin → the order → the
**Offline Payment - Awaiting Confirmation** box must be present with
**Mark as Paid**. That box is gated on `payment_method === 'offline'`, so if the
checkout failed to record the rail the order can never be confirmed by anyone.
Mark as Paid → status `pending_requirements`, `payment_status = paid`.

Seller earnings are NOT credited here. `CommissionService::record()` runs on
transition to `completed` (escrow), so an empty `wpss_wallet_transactions` at
this point is correct.

### 8. Admin sees the order
wp-admin → Orders → the new order.

**Expect** the Service row reads `Request: {title}` and links to the buyer
request post. **Not** "Deleted" — that is what `service_id = 0` produced before
`get_service()` guarded the ID (card 10208199238, same root cause as 7).

Same check on the admin **Recent Orders** table and the admin Orders list: every
request order and every sub-order names its source, and only a genuinely removed
service post says "Deleted service #N".

## Regression checks

- 390px on every screen above.
- Seller proposing on their **own** request notifies nobody.
