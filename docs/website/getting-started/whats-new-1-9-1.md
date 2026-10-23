# What's New in 1.9.1

**Version**: 1.9.1 · **Released**: October 2026

Free and Pro ship in lockstep. Install both.

---

## Import reviews for new vendors

Admins can add one review or import a CSV for a vendor's service from **Sell Services > Import Reviews** (needs Pro). The CSV shows a preview before anything is saved, and a file with a missing column is refused by name. Imported reviews carry an **Imported review** label, count in ratings and send no email. See [Reviews and Ratings](../reviews-ratings/review-system.md).

## Reviews

The review **Helpful** vote is removed. The reviews and the vendor rating already say how a vendor did. The vendor profile review list now matches the service page, and **View all reviews** loads the next pages from the server. Vendor and service ratings, the vendor card, REST stats and vendor levels all count the same reviews. A review held for moderation tells the vendor only once it is approved.

## Notifications

Vendors are notified when a buyer asks for a revision on a milestone phase, and buyers are told the outcome of a report they filed. Dispute response reminders read **Dispute response due**. Payment, proposal, milestone and extension emails always send, and the email preferences screen says so. A vendor gets one email per tip, and dispute escalations reach the site admin when no dispute email is set.

## Earnings and vendor profile

The Earnings ledger follows the chosen period, and the Pro CSV export uses the same period. Vendor profiles show the website, and vendors edit their social links from **Dashboard > Profile**. See [Vendor Profile and Portfolio](../vendor-system/vendor-profile-portfolio.md).

## Orders and milestones

- A suspended vendor can no longer start new pre-sale conversations and keeps their Sales Orders to finish paid work.
- Submitting a milestone phase twice notifies the buyer once, and a paid extension returns a late order to In Progress.
- Offline milestone, tip and extension pages show the bank instructions and receipt upload once, and a tip waiting for confirmation reads the same on every page.
- An unpaid milestone, tip or extension no longer credits the vendor's wallet when an admin marks the order completed.
- Custom-price manual orders read **Custom order**, and **Create Order** keeps a package order as a package.

## Re-cut fixes

- Admins are emailed when a member report or dispute is filed.
- Creating or updating a service over REST returns 200 or 201 with a `notices` array when a limit trims the request, instead of 400 after saving.
- Vendor order counts and the response rate ignore milestone, extension and tip rows, in Free and in Pro analytics.
- A dispute ruling for the vendor or by agreement on a completed order no longer fires completion twice.
- If Pro is older than Free requires, Pro stays switched off until it is updated.

## Settings and admin

Order amount limits and other number settings cannot be saved below their minimum. Withdrawals has a **Cancelled** tab, and the Orders and Withdrawals filters fit a phone. The cart tax line names the rate, such as **VAT (20%)**, the same as checkout.

## Pro

S3, Spaces, Google Cloud, PayPal Payouts and push credentials are stored encrypted. PayPal Payouts sends in the store currency, Razorpay processes every partial refund, and the WooCommerce rail no longer creates a duplicate order when two requests arrive together. My Services is paginated on WooCommerce and EDD.

## Upgrading

Nothing is needed from you. Update Free and Pro together. The upgrade removes the retired Helpful vote data.
