# QA guideline — test the expectation, not the implementation

**Written 2026-08-26**, from the 1.7.0 sweep: 36 cards, 12 product defects and a
107-page documentation audit.

This is the framing document. The runnable walks live in
[`../../audit/journeys/`](../../audit/journeys/); the release gate lives in
[`PRE_RELEASE_SMOKE.md`](PRE_RELEASE_SMOKE.md) where present. Read this once,
then work from the journeys.

---

## The one thing to take from this page

**Almost every defect this cycle passed a "does the code do what it says" test.**
They failed "does this match what the person expected."

Two examples, both real, both from 1.7.0:

> The "New Review" checkbox rendered, saved, and its value was read on every
> page load. A test that ticks the box and checks it stays ticked **passes**.
> The buyer still got the email, because a second code path never consulted it.

> Two carts existed, both correct. `/cart/` correctly showed the WooCommerce
> cart, which was correctly empty. `/service-cart/` correctly held the service.
> Every unit was right. The buyer clicked the cart icon and was told their cart
> was empty while the badge on the same page read **1**.

Nothing in either was broken code. QA that verifies implementations finds
neither. QA that asks *what did this person expect to happen* finds both.

---

## Before you open a browser: write the expectation down

For the surface you are about to test, answer these in one line each. Write them
**before** you look at the screen, so the screen cannot talk you into its own
logic.

1. **Who is here?** Buyer, vendor, dual-role, admin, logged-out. Not "a user".
2. **What did they come to do?** In their words. "See if I got paid", not
   "verify earnings summary renders".
3. **What would make them think the product is broken?** This is the real test
   case. Usually it is *silence* or *a number that reads as zero*.
4. **What would they do next if that happened?** If the answer is "open a
   support ticket", you have found the bug regardless of what the code does.

A surface passes when a person who came to do that thing gets it, without
having to know how the plugin is built.

---

## Level 1 — Code flow

Not unit tests. These are the questions that catch the defect class this
codebase actually produces: **one thing implemented twice, and the copies
drift.** Every 1.7.0 defect but one was this shape.

### The four contract questions

Ask these of every store, setting and toggle you touch.

| Question | What it caught in 1.7.0 |
|---|---|
| **Is this key written by anything?** | `notify_moderation` was read by `EmailService` and written by no control, so moderation emails could never be switched off. `auto_payout_to_wallet` gates a whole flow and nothing writes it. |
| **Is this key read by anything?** | Push tokens were stored from 1.1.0 and read by nothing until 1.6.0. `require_verification` was seeded on activation and read by nothing, ever. |
| **Is this answered in more than one place?** | Eight private copies of "is this caller a vendor". Two copies of the notification-type list. Two `PaymentGatewayInterface` declarations. Every one had drifted. |
| **Does the setting reach the thing it names?** | Cloud storage credentials save, the connection test passes, and no delivery file ever reaches the bucket. |

### How to actually run them

```bash
# Does anything write this key?
grep -rn "'wpss_the_key'" src/ | grep -iE "update_option|update_.*meta|insert|\[.*\] *="

# Does anything read it?
grep -rn "'wpss_the_key'" src/ | grep -iE "get_option|get_.*meta|SELECT"

# Is this logic duplicated?
grep -rn "function the_thing" src/ ../plugin-pro/src/
```

**A store with a reader and no writer, or a writer and no reader, is a bug even
if every screen looks right.** Report it.

### The toggle test

For any on/off control:

1. Turn it **off**. Save.
2. Do the thing it is supposed to suppress.
3. **Confirm the thing did not happen** — not that the box is still unticked.

Step 3 is the whole test. The review-email toggle passed steps 1 and 2 for
months.

### Watch for these while testing

- **A 5-minute per-recipient email cooldown** silently swallows repeat message
  emails. Testing the same recipient twice in five minutes will look like a
  failure that is not one. Clear the transient or use a different recipient.
- `rest_do_request()` behaves differently from a real HTTP call — `_fields` and
  application-password auth both differ. Test REST over HTTP.
- Pro registers **nothing** without a valid licence. An unlicensed sandbox
  cannot test any Pro path, and the settings will still save and report success.

---

## Level 2 — Browser

Walk a journey as a role. The format and the existing walks are in
[`../../audit/journeys/`](../../audit/journeys/) — start there. This section is
what to add to how you read them.

### Look where the person looks

The clearest miss this cycle: a card reported "the service page renders zero
add-ons". All three add-ons rendered correctly — one click away, inside the
order modal. The DOM was right. The *buyer browsing the page* still could not
see that extras existed.

So: **stand where the person stands.** If a buyer would not click Continue just
to find out what is on offer, then "it renders in the modal" is not a pass.

### The five questions that found real bugs

1. **Does the number on screen agree with the number beside it?** The cart page
   said empty while the badge said 1. Two numbers about the same thing, on the
   same screen, disagreeing.
2. **Does the label mean one thing?** Two menu items both called "Vendors", 40px
   apart. A dropdown offering "Cart" and "Service Cart" with nothing to tell
   them apart.
3. **Does the link go where its name says?** "My Orders" landed on Sales Orders
   for anyone who both buys and sells. It said "No sales yet" to someone with 12
   purchases.
4. **Does the screen tell you what it is about to cost you?** The License screen
   offered Deactivate with no statement of what stops working.
5. **Does a control that saves successfully actually do something?** Storage
   settings, connection test green, no effect.

### Roles to walk, not just "a user"

| Role | The thing that only breaks for them |
|---|---|
| **Dual-role** (buys and sells) | Landing sections, sidebar links, "which of my two identities is this screen about" |
| **Suspended vendor** | Access that should have been revoked. This one kept full access to every money route. |
| **Logged-out** | Public reads that should be gated, and gated reads that should be public (plan prices before signup) |
| **Non-vendor buyer** | Vendor-only surfaces answering 200 with zeros instead of refusing |
| **Owner with WooCommerce installed but not selling with it** | The single most confusing configuration this product has |

### Viewports and states

390px for anything that renders. Dark mode. RTL where the design system applies.
Every async surface has three states — empty, loading, error — and QA sees the
happy one by default. Ask for the other two.

---

## Level 3 — Read the documentation as part of the flow

**New in this cycle, and it found the worst defect of the sweep.**

Two dispute pages told admins to refund again in their payment gateway.
Refunds already settle automatically. An admin following our own documentation
**pays the buyer twice, in real money.** The code was correct. The instructions
were not, and nobody had tested the instructions.

So when you test a flow, open the page that documents it and follow it
literally.

- Does every step exist on the screen? (A "Create All Pages" button was
  documented on a screen that has no such button.)
- Do the stated defaults match the real ones? (10 MB documented as 50 MB,
  minimum withdrawal 50 documented as 25.)
- Does the instruction produce the outcome it promises? (Tick-to-hide
  instructions for a tick-to-**show** checkbox — following them granted the
  access you meant to revoke.)
- Does it promise a feature that exists? Four Pro features were advertised and
  do not ship.

**A wrong instruction is a product defect.** File it as one.

---

## Reporting

File to the Bugs column, and give the fix a chance of being right first time:

- **Who** you were, and **what you came to do**.
- **What you expected**, in the person's words.
- **What happened**, with the screen or the response body.
- **Where you looked** — URL, role, viewport, and whether Pro was licensed.
- If a doc is involved, **quote the line you followed**.

Two things that save a round trip:

**Say which environment.** Three of the four "first-install" cards this cycle did
not reproduce on a clean install, because they were found on a site carrying
legacy data. That is still worth reporting — but say so, or the fix targets the
wrong thing.

**Check the board before filing.** Two cards were re-filed from an audit
document while the originals were already triaged: one fixed, one that did not
reproduce. Re-running an audit without reconciling regenerates finished work.

---

## When a report is disputed

Site owners and end customers decide what is right — not QA, and not the
developer. If a bounce is a layout or preference call rather than a broken
function, the developer must reproduce it at your exact viewport, evaluate it as
the actual end user, and either fix it or push back with a reason and a
reference. A functional bug — a control that lies, data that persists wrong,
money that moves twice — is never in that category. Those get fixed.

---

## What this guideline is for

Not coverage for its own sake. Every question above is on this page because a
real defect got past everything else. If a future defect gets past all of them,
add the question that would have caught it — and write the journey while the
defect is still fresh.
