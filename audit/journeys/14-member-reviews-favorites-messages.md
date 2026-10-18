# Journey 14 - Buyer reviews, favourites and messages; seller replies

**Roles:** buyer (`subscriber`), seller (`wpss_vendor`)
**Rail:** standalone, woo
**Status:** written 2026-10-18; walk pending (see docs/qa/walks.json)
**Docs:** docs/website/reviews-ratings, docs/website/order-management/order-messaging.md

## Steps

### 1. Buyer reviews a completed order
Dashboard > My Orders > open a completed order > Leave Review.

**Expect** overall rating and written review are required, sub-ratings optional; submitting twice is refused; the review shows a Verified Purchase badge and the service rating updates.

### 2. Seller replies
Dashboard > Reviews > Reply.

**Expect** the reply shows under the review for both parties and the buyer is notified.

### 3. Favourites
Heart a service as the buyer; open Dashboard > Favorites; remove it.

**Expect** the list matches the hearts on the service pages, the remove control works with a keyboard and at 390px, and a deleted service disappears instead of leaving a dead card.

### 4. Messages
Buyer messages the seller from a service (pre-sale) and from an order.

**Expect** both threads appear for both parties, unread counts clear when read, attachments respect size and type limits, a suspended seller cannot start a new pre-sale thread but can still answer an order thread (card:10384354109).

### 5. Where reviews and FAQs show
A service page lists approved reviews (`partials/service-reviews`) and the seller's FAQs (`partials/service-faqs`); a service with neither shows no empty heading. Reviews held for moderation never appear.
