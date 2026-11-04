# What's New in 1.9.0

**Version**: 1.9.0 · **Released**: October 2026

Free and Pro ship in lockstep. Install both.

---

## Order Again

A finished order now has an **Order again** button on every checkout rail. It puts the same package and options back in the cart at today's price. On WooCommerce, EDD and FluentCart the button adds one, and the page tells you when your last order had a larger quantity. See [Order Tracking](../buyer-guide/buyer-order-tracking.md).

## Refunds follow the rail that collected the money

The rail that took the payment refunds it. Refunding stays admin-only; a site can open it to vendors with the `wpss_can_refund_order` filter. A refund that names its gig is booked automatically, and one that does not waits for an admin.

## Negative balances

An admin can write off a vendor's negative balance, and the REST API reports the balance state. The vendor sees one explanation, and the Earnings page, the Sales tile and the profile all show the same earned figure.

## Closed orders and phases

A cancelled, refunded or declined order can no longer be paid. A milestone phase stays locked until the previous phase is approved, and its page says so. See [Milestones](../order-management/milestones.md).

## Deliveries and messages

A delivery that includes a file the site refuses is refused whole and names the file. The message composer shows a notice when an attachment is refused. See [Deliveries and Revisions](../order-management/deliveries-revisions.md).

## Withdrawals

A rejected withdrawal shows its reason on the vendor's Earnings page and in the rejection email. A withdrawal marked paid sends its own Paid email and notification. See [Withdrawals](../earnings-wallet/withdrawals.md).

## Reviews and services

Helpful is a toggle that remembers the vote, and you cannot vote on your own review. A refused publish explains why on the service editor and the list. The Services list has Mark as featured bulk actions, and the My Services status tiles filter the list.

## Dates, dark mode and templates

Every date goes through one time helper and deadlines print their time zone. Contrast, focus rings and dark mode are fixed across the dashboard, checkout and order screens. Email templates carry a version, and outdated theme overrides are listed.

## Upgrading

Nothing is needed from you. Update Free and Pro together.
