# Journey 02 — Dispute, end to end

**Roles:** buyer (`subscriber`), seller (`wpss_vendor`), admin
**Status:** passing as of commit `4264144`

Guards cards 10208075086, 10208074988, 10208211608.

## Why this one matters

The dispute conversation used to live in **two stores** — the opening statement
and admin replies in `wpss_dispute_messages`, member evidence in the disputes
row's `evidence` JSON. Each surface read one of them, so the buyer was told
"No messages yet" about a thread the admin could read in full, **and** the admin
never saw member evidence. Both halves looked correct in isolation.

## Preconditions

- A completed or in-progress order with a buyer and a seller.
- Disputes enabled: `wpss_orders[allow_disputes]`.

## Steps

### 1. Buyer opens a dispute
From the order, open a dispute with a reason and an opening statement.

**Expect** dispute created, `status = open`.

### 2. Buyer sees their own opening statement
Dashboard → Disputes → the dispute.

**Expect**
- **Messages & evidence** shows the opening statement immediately.
- The text "No messages yet" must **not** appear.
- **Activity** shows the dispute opening plus the statement — and each message
  exactly **once**. Duplicates mean something is reading both the table and
  `get_evidence()` again.

### 3. Seller replies
As the seller, open the same dispute and post a reply through the on-page form.

**Expect** the reply appears in both parties' threads and in Activity.

### 4. Attach evidence
Attach a file or link with a caption.

**Expect** it renders as a file/image/link — not as raw text, and not as a
broken link. (`absint()` on a URL used to zero these.)

### 5. Admin reads the whole thread
wp-admin → Disputes → the dispute.

**Expect** **every** message from both parties, including member evidence.
Admin seeing only the opening statement is the old split resurfacing.

### 6. Order links back to the dispute
Open the order as buyer, then as seller.

**Expect**
- A **View Dispute** action in the order header.
- It is a real `<a href>` to `dashboard/disputes/?dispute={id}`.
- **Computed style:** 1px solid border in the danger colour at rest; solid fill
  on hover. Borderless red text means a theme rule outranked ours — BuddyX sets
  `border: 2px solid transparent` on anchor buttons.
- Clicking lands on that dispute.

### 7. Open Dispute is not offered twice
**Expect** with a dispute already open, the **Open Dispute** button is gone and
View Dispute has replaced it — not both, and not neither.

### 8. Admin order view
**Expect** the admin order screen also surfaces the open dispute
(card 10208211608 — still open at time of writing).

### 9. Resolution
Admin resolves the dispute with a note.

**Expect**
- Status reflects the resolution.
- The admin's status note does **not** appear as a blank row in the member
  thread. Status notes live in `wpss_disputes.evidence` as `status_note`
  entries — dispute *history*, not conversation — and `get_evidence()` must not
  return them.
- View Dispute still shows on the order, so "how did that end?" is answerable
  from the order.

## Upgrade check

On a site with pre-1.6.0 disputes, after upgrade:
- old evidence still appears in the thread
  (`migrate_evidence_to_messages()`, gated on `wpss_dispute_evidence_migrated`)
- admin status notes are **still there** — the migration must keep them
- running the migration twice moves 0 the second time
