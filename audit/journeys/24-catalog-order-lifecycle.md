# Journey 24 - Buyer orders a service and it runs to a review

**Roles:** buyer (`subscriber`), vendor (`wpss_vendor`), admin
**Rail:** standalone, woo (the payment step differs; every step after payment is the same on both)
**Status:** written 2026-11-03; walk pending (see .qa-results/walks.json)
**Docs:** docs/website/order-management/ (order lifecycle, requirements, deliveries, settings), reviews-ratings/review-system.md

The most common flow on any marketplace: a catalog purchase, not a request. Journey 01 covers the request path, 06 and 07 the payment itself, 16 the paid add-ons to a running order. This one is the order between payment and review.

## Preconditions

- One published service by the vendor with two packages: Basic (2 revisions) and Standard (unlimited revisions, Express delivery on), and two requirement questions, one of them a file.
- A second published service by the same vendor with no requirement questions.
- Settings > Orders & Disputes: note the Auto-Complete Days value (default 3) and the review window (default 30 days).

## Steps

### 1. Buyer buys the Basic package
Open the first service, choose Basic, add one add-on, pay (standalone: 06; woo: 07).

**Expect** the order shows "Waiting for requirements" with the package's delivery time and 2 revisions; the requirements clock counts from the moment of payment, not from when the cart was made. REST twin: `GET /orders/{id}` returns the same status, revisions and due date.

### 2. Buyer answers the requirements
Answer both questions, attach the file, submit.

**Expect** the order moves to In Progress, the delivery clock starts now, the vendor gets one notification and one email, and the answers and the file are on the order for both people. Submitting again changes nothing. REST twin: `POST /orders/{id}/requirements`.

### 3. A buyer who has nothing to say
The website has no skip button (it went with the old checkout script in 1.0.x); skip is an API action for apps. On a second Basic order, call `POST /orders/{id}/requirements/skip` twice as the buyer.

**Expect** the first skip starts the order, the second is refused and writes nothing. Buy the service that has no questions: it starts at payment and never asks for a brief. A website buyer who never answers is handled by Requirements Timeout (Settings > Orders & Disputes): after that many days the order auto-starts or auto-cancels, as the owner chose. It is off (0) by default, so on a default site the order waits.

### 4. Vendor delivers, and a bad file is refused
As the vendor, open the order from Sales Orders. Deliver with a message and a file the site refuses (for example a `.php`), then with an allowed file.

**Expect** the first delivery is refused whole and names the file; nothing is saved and the buyer is not told. The second is saved, the order shows Delivered, the buyer gets one notification and one email. REST twin: `POST /orders/{id}/deliver`.

### 5. Buyer asks for a revision
As the buyer, request a revision with a note.

**Expect** the vendor's bell rings once and the email quotes the note; the order shows "Revision requested" with its own deadline; remaining revisions read 1. The count comes from the package bought, so editing the service's revisions afterwards does not change this order. REST twin: `POST /orders/{id}/revision`.

### 6. The limit holds
Vendor redelivers; buyer requests the second revision; vendor redelivers; buyer tries a third.

**Expect** the third is refused: the button is gone on the page and the REST call answers an error. On a Standard order (unlimited), the button stays after any number of revisions.

### 7. Accept needs a delivery
On a fresh order still In Progress, call `POST /orders/{id}/complete` as the buyer.

**Expect** refused: nothing delivered, nothing to accept. On the delivered order, Accept moves it to Completed, credits the vendor's earnings once, and sends one completion email to each side.

### 8. Or it completes on its own
On another delivered order, leave it. Run the late-order sweep after Auto-Complete Days (move the delivery date back, then `wp action-scheduler run --hooks=wpss_auto_complete_orders` or wait for the queue).

**Expect** it completes once, counted from the latest delivery: an order redelivered after a revision is not completed off the older delivery. With Auto-Complete Days set to 0 nothing completes until the buyer acts.

### 9. Review and reply
As the buyer, leave a review on the completed order. As the vendor, reply. Try a second reply, and a reply as a different vendor over REST.

**Expect** the review shows on the service and the vendor profile, the rating average moves, the vendor gets one notification. One reply per review; another vendor is refused. After the review window ends, the page and `POST /reviews` both refuse a new review and quote the same window.

### 10. Express delivery
Buy Standard with Express.

**Expect** the due date uses the Express time, not the normal one, and the price adds Express once (on the order, the receipt and in REST).

### 11. Hold and dispute
As admin, put a running order on hold; as the buyer, open a dispute on it.

**Expect** the hold shows to both sides with no delivery clock running; the buyer can still dispute (then follow journey 02).

### 12. Admin sees the same order
Sell Services > Orders, open each order above.

**Expect** status, revisions used, deliveries, requirements and the timeline match what the buyer and vendor saw. `GET /orders/{id}/timeline` lists the same events in the same order.

Check 390px on the order page for the buyer and the vendor at steps 2, 4 and 5.

Guards: `tests/test-requirements-clock-from-payment.php`, `tests/test-requirements-one-store.php`, `tests/test-requirements-skip-once.php`, `tests/test-no-requirements-starts-order.php`, `tests/test-delivery-refuses-bad-file.php`, `tests/test-revisions-from-snapshot.php`, `tests/test-revision-one-bell.php`, `tests/test-package-revisions-unlimited-input.php`, `tests/test-hold-and-revision-clock.php`, `tests/test-accept-needs-delivery.php`, `tests/test-auto-complete-latest-delivery.php`, `tests/test-review-window-and-reply.php`, `tests/test-express-delivery.php`, `tests/test-order-write-integrity.php`.
