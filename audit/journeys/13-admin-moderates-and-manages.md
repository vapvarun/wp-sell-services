# Journey 13 - Admin moderates services, reviews, members and vendors

**Roles:** admin, vendor (`wpss_vendor`), buyer
**Rail:** standalone, woo
**Status:** written 2026-10-18; walk pending (see .qa-results/walks.json)
**Docs:** docs/website/admin-tools

## Steps

### 1. Service moderation
Settings > Vendor Settings > Require Service Moderation on. The vendor publishes a service.

**Expect** it lands in Moderation as Pending and is hidden from buyers; Approve makes it live and emails the vendor; Reject returns it to draft with the reason in the email. The queue shows thumbnail, title, vendor, category, price, date.

### 2. Review moderation
Settings > Vendors > moderate reviews on. A buyer leaves a review.

**Expect** it waits in Review Moderation, is not public or counted in the average until approved, and the vendor is notified on approval.

### 3. Vendor management
Vendors list: filter All, Active, Pending, Suspended; approve a pending vendor (Require Verification on).

**Expect** stats match the list, filters keep paging, approval emails the vendor, and a suspended vendor cannot start new work (see journey 05 part 2).

### 4. Member reports
A buyer uses Report this service on a service page and Report this vendor on a profile.

**Expect** both land in Member Reports with the reporter and target; Uphold and Dismiss each close the report and record the call; Suspend member shows the badge and takes effect on every door (card:10384354109).

### 5. Orders, disputes, withdrawals and the audit log
**Expect** each list pages, filters and sorts at 2000+ rows without a timeout; every status change an admin makes appears in the Audit Log with actor and time; hold and resume work for admins only (journey 05 part 2).

### 6. Rejections, resolutions and the mail that goes with them
- `POST /moderation/{service_id}/reject` with a reason: the vendor sees `partials/service-rejection-notice` on that service and gets the moderation-response mail; the service is not public.
- A service entering the queue sends the admin `emails/moderation-pending`; the decision sends the vendor `emails/moderation-response`.
- Vendor application: approve sends `emails/vendor-approved`, reject sends `emails/vendor-rejected` with the reason; the Contact action sends `emails/vendor-contact`.
- `POST /reports/{id}/resolve` closes a member report once, records who resolved it, and tells the reporter.

**Expect** nothing in the queue is actionable twice, and each mail reaches the right person only.
