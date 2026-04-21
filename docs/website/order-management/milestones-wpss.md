# Milestone Contracts

**Milestone contracts** break a custom project into paid phases. The
vendor proposes the plan, the buyer approves it as a whole, then the
buyer pays and approves each phase one at a time. It's the right fit for
larger, scoped work where neither side wants to commit the full amount
up front.

![Milestone timeline on a buyer's order page](../images/order-management/milestone-timeline-wpss.png)

## When to use a milestone contract

Milestone contracts live on **buyer-request orders only** — projects
that started with a buyer posting a brief and a vendor responding with a
custom proposal. For a fixed-price catalog service, use a
[Paid Extension](extensions-wpss.md) instead. The two features are
mutually exclusive — a single order will only ever show one or the other.

Milestone contracts work well when:

- The project is large or open-ended (website build, editing a book, a
  phased consulting engagement).
- The work divides cleanly into stages that each deliver something the
  buyer can review.
- Both sides want progress payments rather than a lump sum upfront or
  everything at the end.

## The vendor journey

### Proposing a milestone contract at proposal time

When you reply to a buyer request, choose **Milestone** as the contract
type on the proposal form. You'll see a repeater where you enter each
phase:

- **Title** — a clear name the buyer will recognise (e.g. "Homepage
  wireframes").
- **Description** — what's delivered at the end of this phase.
- **Amount** — what the buyer pays for this phase.
- **Days** — how many days this phase takes.

The total of the phases is what the buyer sees as the project total.
There's no separate upfront fee — the parent order's base price is $0,
and all money flows through the phase payments.

Once you submit, the buyer can compare your proposal against others,
expand the phase list, and review the full breakdown before accepting.

### Adding ad-hoc phases later

Custom projects often grow. After the contract is under way you can
still propose additional phases from the order page using the same
**Propose Milestone** button used on v1.0. A later addition is treated
the same way as any predefined phase — the buyer pays, you deliver, the
buyer approves.

### Delivering a phase

When the buyer pays a phase:

1. You receive an email and in-app notification to start work.
2. The phase moves to **In progress**.
3. When you're done, hit **Submit Delivery** on that phase row. Attach
   files or notes the buyer needs.
4. The phase moves to **Awaiting approval**.
5. On approval, the money is already in your wallet — approval confirms
   delivery, it doesn't trigger the payout (payment happened when the
   buyer paid the phase).

If the buyer wants changes, they ask in the order chat and you
re-submit. Revisions aren't counted against the main order's revision
limit and there's no separate reject button — conversations happen in
chat.

## The buyer journey

### Reviewing a milestone proposal

On the buyer-request page, milestone proposals are tagged with a small
badge (`3 phases · $500`) next to the total. Expanding the proposal
shows the phase list with titles, amounts, and days. You see the whole
plan before accepting, so nothing is a surprise later.

### Accepting a milestone contract

When you accept, the order is created with all phases pre-populated in
the timeline and the project kicks off. You don't pay the base of the
order — it's $0 by design. Money only moves when you pay individual
phases.

### Paying in lock-step

The **lock-step rule** is the key thing to understand about milestone
contracts:

> Phase N only becomes payable after every earlier phase has been
> either approved or cancelled.

So on a 3-phase contract, phase 1 is payable immediately. Phases 2 and 3
show a disabled "Locked — pay phase 1 first" pill. As soon as phase 1 is
approved, phase 2 unlocks. This keeps both sides in rhythm — the vendor
isn't working on multiple phases in parallel with no payment history,
and you aren't stacking up prepayments on phases you haven't seen yet.

The same rule is enforced server-side. Even if you craft a URL to pay a
locked phase directly, the checkout refuses the request.

### Approving deliveries & requesting revisions

When the vendor submits a phase, you see a **Review & Approve** action
on that row. If the delivery is right, approve it and the next phase
unlocks. If you want changes, click **Request revision in chat** — it
drops you into the order conversation with a reference to that phase so
the vendor knows what you're talking about. There's no separate reject
status; revisions are a conversation.

![Review and approve a milestone delivery](../images/order-management/milestone-approve-wpss.png)

## Project completion

Once the last phase is approved, the whole project flips to **Completed**
automatically. Both parties receive the standard order-completed email,
a "Project complete" summary card appears at the top of the timeline
showing total phases paid and total spent, and the Rate Your Experience
CTA goes out to the buyer. The same completion hooks run as for any
other order type — vendor stats update, seller-level progress ticks up,
and the order moves to completed archives.

## Cancelling a milestone contract

Cancellation rules follow what's actually fair for split-phase work:

- **Paid and approved phases stand.** Those payments are earned and
  stay with the vendor.
- **Unpaid phases are auto-cancelled** when the parent is cancelled —
  no money has moved so there's nothing to unwind.
- **Paid but still open phases** (the vendor is mid-work or the
  delivery is awaiting your approval) don't cancel automatically.
  They route through the dispute flow so both parties can agree on
  what's fair (full or partial refund, extra revision, or mutual
  agreement that the work delivered was complete).

This matches how phased contracts work in the real world — completed
phases are settled, in-flight phases need to be talked through.

## Differences from catalog extensions

| | Milestone contract | Paid extension |
|---|---|---|
| Where it lives | Buyer-request orders only | Catalog (fixed-price) orders only |
| Who sets it up | Vendor at proposal time (+ ad-hoc later) | Vendor, on an already-paid order |
| What it charges for | The whole project, split into phases | Extra work on top of the already-paid scope |
| Payment order | Lock-step (pay phase N before N+1 unlocks) | Single quote, accept or decline |
| Terminal state | All phases approved | Quote paid or declined |

## Tips

**For vendors:**

- Keep phases deliverable — each one should produce something the
  buyer can look at and sign off. Three to five phases works well for
  most projects.
- Price phases independently. Don't save a "big" phase for the end —
  you want steady payments, not a back-loaded risk.
- Communicate in the order chat as you work. The timeline shows state;
  the chat shows context.

**For buyers:**

- Read the full phase breakdown on the proposal before accepting.
  What looks clear at proposal time is what you're committing to for
  the whole project.
- Approve promptly once you're satisfied with a phase. Approval
  unlocks the next phase — stalling one holds the whole contract.
- Use the chat for revision requests. It keeps a record and avoids a
  separate reject status.

## Related documentation

- [Paid Extensions](extensions-wpss.md)
- [Proposal Contracts (Fixed vs Milestone)](../buyer-requests/proposal-contracts-wpss.md)
- [Order Lifecycle & 11 Statuses](order-lifecycle.md)
- [Deliveries & Revisions](deliveries-revisions.md)
