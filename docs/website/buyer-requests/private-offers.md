# Private Offers

A private offer is a price a seller makes to one buyer, from a conversation they are already having. Only that buyer can see it and accept it. It is the way to agree a custom job without posting a public buyer request.

## Send an offer (sellers)

1. Open **Dashboard > Messages** and the conversation with the buyer. Offers are sent from a pre-sale conversation, the kind started from a service page or a profile, not from an order's thread.
2. Open **Send an offer**.
3. Fill in:
   - **What you will do**: a short title.
   - **Details**: what the buyer gets.
   - **Price**.
   - **Delivery (days)**: 7 by default.
   - **Revisions**: 2 by default.
   - **Offer valid for (days)**: 7 by default, up to 90.
4. Press **Send offer**.

The offer appears in the thread as a card. The buyer gets an email ("... sent you a private offer") and a notification, both linking to the conversation. Your card says **Waiting for an answer** until they reply.

Your **Dashboard > Proposals** lists each offer with a **Private offer** badge and a **View conversation** link. **Withdraw** takes back an offer that has not been answered.

A suspended seller cannot send offers, and nobody can send one to a member who blocked them.

## Answer an offer (buyers)

The card shows the price, delivery time, revisions and the date the offer ends.

- **Accept and pay** creates the order at those terms and opens checkout. Pay as for any order.
- **Decline** asks you to confirm, then closes the offer. It cannot be accepted afterwards, and the seller is told.

You need an account to receive an offer, because it lives in your conversations.

## When an offer ends

| State | When |
|-------|------|
| Waiting for an answer | Sent and still within its window |
| Accepted | The buyer accepted; the card links the order |
| Declined | The buyer declined |
| Withdrawn | The seller took it back |
| Expired | Its window passed. It can no longer be accepted, even before the daily clean-up runs |

To change the terms, send a new offer. The buyer cannot edit, reopen, publish or delete an offer; they accept or decline it. Only a site administrator can edit one.

## Who can see an offer

Only the buyer it was sent to, the seller who sent it and site administrators. It never appears in the buyer requests list, in search, or to other sellers, and no other seller can send a proposal on it. Anyone else who opens its link gets **Not found**.

The order an offer becomes is a normal order. Admin order lists show its source as **Private offer: <title>**.

## For developers

| What | Where |
|------|-------|
| Send an offer | `POST /wpss/v1/conversations/{id}/offers` with `title`, `description`, `price`, `delivery_days`, `revisions`, `expires_days`; optional `contract_type` (`fixed` or `milestone`) and `milestones`. Answers `201` with the offer. |
| Read an offer | The `offer` key on each message from `GET /wpss/v1/conversations/{id}/messages` (`null` for other messages): `state`, `price`, `delivery_days`, `revisions`, `expires_at`, `can_respond`, `accept_route`, `decline_route`, `order_url`. |
| Accept or decline | `POST` the offer's `accept_route` or `decline_route` (the buyer request proposal routes). Accept answers with `order_id` and `checkout_url`. |

Hooks: `wpss_private_offer_sent`, `wpss_private_offer_accepted`, `wpss_private_offer_declined`, `wpss_private_offer_expired`, and the `wpss_private_offer_fields` filter for the form's fields. See [Hooks & Filters](../developer-guide/hooks-filters.md).

## Related Guides

- **[Submitting Proposals](submitting-proposals.md)** -- Proposals on public buyer requests
- **[Proposal Contracts: Fixed vs Milestone](proposal-contracts.md)** -- Paying in one go or in phases
- **[Messaging](../order-management/order-messaging.md)** -- Conversations with buyers
