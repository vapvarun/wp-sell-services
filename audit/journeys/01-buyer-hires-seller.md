# Journey 01 — Buyer hires a seller from a request

**Roles:** buyer (`subscriber`), two sellers (`wpss_vendor`), admin
**Rail:** standalone payments
**Status:** step 7 currently FAILS — Basecamp 10208094640

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

### 7. Pay the order — **CURRENTLY FAILING**
Click Pay → `?pay_order={id}` → choose **Offline** → Pay.

**Expect**
- Checkout line shows the request title.
- The form does **not** post `service_id={checkout page ID}`.
- After paying: `payment_method` recorded, status leaves `pending_payment`, and
  the order enters the offline awaiting-confirmation path — same as the cart
  Offline flow.

**Actual:** title reads "Service Checkout", form posts the checkout page ID, and
the order stays `pending_payment` with `payment_method = NULL`.

Start at `StandaloneCheckoutProvider::build_proposal_service_placeholder()`.

### 8. Admin sees the order
wp-admin → Orders → the new order.

**Expect** the request title. **Not** "Service Deleted", which is what
`service_id = 0` currently produces (card 10208199238 — same root cause as 7).

## Regression checks

- 390px on every screen above.
- Seller proposing on their **own** request notifies nobody.
