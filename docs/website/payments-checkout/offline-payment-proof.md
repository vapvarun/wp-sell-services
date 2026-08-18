# Offline Payment Proof and Receipts

Some of your buyers will pay by bank transfer, cash, or another method that
happens away from your site. The order sits at **Pending Payment** until someone
confirms the money arrived, and without a way to show that it did, confirming it
is a conversation over email.

This feature closes that loop: the buyer attaches proof, you review it, and
approving it marks the order paid and credits the vendor.

**Off by default.** A marketplace taking only card payments should not show
buyers an upload box it will never use.

## Turning it on

1. Go to **Sell Services > Settings > Orders & Disputes**.
2. Find **Offline Payment Proof**.
3. Tick **Let buyers upload proof of an offline payment for an admin to verify**.
4. Save.

You also need at least one offline payment method enabled under
**Settings > Payment Gateways**, with your bank details or instructions in it.
That text is what the buyer sees after ordering, so put everything they need to
pay you in it.

## How it works for the buyer

1. They order and choose the offline payment method at checkout.
2. The order is created at **Pending Payment**, and your payment instructions
   appear on the order page.
3. They pay you however your instructions say.
4. They return to the order and upload their proof - a bank transfer receipt, a
   screenshot, a reference number.
5. They wait for you to confirm.

The buyer can see the status of what they submitted at every point, so they are
not left wondering whether you received it.

## How it works for you

1. The submission appears for review, and you are notified by email.
2. Open it and check the proof against your bank statement.
3. **Approve** it, and the order is marked paid, the vendor is credited, and the
   work begins - exactly as if a card had been charged.
4. Or **reject** it with a reason. The buyer is told why and can submit again.

Rejecting is not an accusation. The most common reasons are an unreadable
screenshot or a transfer that has not cleared yet, and giving the reason saves
both of you an email.

## Things worth knowing

**Approving is the same action as a card payment succeeding.** The vendor is
credited through exactly one path in this plugin, whether the money arrived by
Stripe or by bank transfer. There is no separate "offline" accounting to
reconcile later.

**Two admins cannot double-credit an order.** If two of you open the same
submission and both approve it, the vendor is credited once. The claim is locked
at the database level rather than by asking you to coordinate.

**A paid order gets a printable receipt.** Once payment is confirmed - by any
method - the buyer can open a clean, printable receipt from their order and save
it as a PDF from their browser's print dialog. Nothing to configure.

**Uploads follow your WordPress media rules.** Size limits and allowed file
types are whatever your site already permits.

## When not to use it

If you only take card payments, leave this off. If you take bank transfer but
handle confirmation entirely outside the site - a bookkeeper marking orders paid
in the admin once a day - you can also leave it off and use **Mark as Paid** on
the order screen instead. This feature is for when you want the buyer to be able
to *show* you, and to see where their order stands while they wait.

## Related Docs

- [Standalone Mode](standalone-mode.md)
- [Other Gateways](other-gateways.md)
- [Order Lifecycle](../order-management/order-lifecycle.md)
