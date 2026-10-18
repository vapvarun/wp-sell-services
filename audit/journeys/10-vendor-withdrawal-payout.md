# Journey 10 - Seller is paid out and told the truth at each step

**Roles:** seller (`wpss_vendor`), admin
**Rail:** standalone (manual payout)
**Status:** rewritten 2026-10-18; walk pending (see .qa-results/walks.json)

Guards card 10402904790 (card:10402904790). Mark paid used to send the "Approved" email, so a seller who was just paid read "approved" and wrote in to ask where the money was.

## Preconditions

- A seller with at least the minimum withdrawal in available balance and no open request.
- Mailpit running (`wp eval 'echo ini_get("sendmail_path");'`).

## Steps

### 1. Seller requests a payout
Dashboard > Earnings > Request Withdrawal.

**Expect** one confirmation, the request listed as Pending, available balance reduced or held (never both unchanged and spendable), and an admin email.

### 2. Admin approves
Withdrawals > Approve.

**Expect** the seller gets "Withdrawal of X Approved" and the body says approved, being processed. Row shows Approved.

### 3. Admin marks paid
Withdrawals > Mark paid, confirm.

**Expect**
- The seller gets "Withdrawal of X Paid" (card:10402904790, template `emails/withdrawal-completed`), not "Approved". Body status cell reads Paid.
- Exactly one ledger debit; pressing Mark paid again changes nothing.
- The seller's in-app notification and Earnings history read Paid.
- Settings > Emails has a "Withdrawal Paid" switch; switching it off silences this email only.

### 4. Same at 390px
Earnings page and the Withdrawals admin list: no overlap, buttons at least 40px.

### 5. Automatic payouts stay on their date
Settings > Payouts: enable auto-withdrawal, Monthly. Tools > Scheduled Actions.

**Expect** (card:10403479953)
- One pending `wpss_process_auto_withdrawals` for the 1st at 02:00 site time, listed as a single run, not "every 30 days".
- Run it now: the next pending one is the 1st of the following month, never the 31st or 30th.
- Switch to Weekly: the pending one moves to the next Monday. Switch off: none pending.
- The Settings help text names the same dates the Scheduled Actions page shows.

### 6. A negative number is not a number
Settings > Payouts and Emails: type -5 into minimum withdrawal, clearance days and the message-email delay (a browser stops this, so also POST it).

**Expect** (card:10403454753) each saves as 0, never 5.

### 7. The other two withdrawal emails
- Approve: the seller gets `emails/withdrawal-approved`, saying approved and not yet paid.
- Reject: the seller gets `emails/withdrawal-rejected` with the reason the admin typed, and the held amount is back in their available balance.
- Automatic payout: the seller gets `emails/withdrawal-auto` on the scheduled date, naming the amount and the method.

**Expect** each mail reads correctly in light and dark, in RTL, and the status cell matches the withdrawal's real status.

### 8. A balance below zero (card:10244795017, card:10416610833)
Refund an order after its earnings were withdrawn so the vendor ledger goes negative.

**Expect** the vendor cannot request a withdrawal and an approved request cannot be marked paid; the Earnings page explains the debt in words, the figure is readable at 4.5:1 in light and dark, and it matches the ledger amount the admin sees (a withdrawal in progress is named, not hidden); Admin > Vendors lists the vendor and Write off, with a reason, brings the balance to exactly zero once, leaves Total earned unchanged, and a second click does nothing.

### Rejected withdrawal shows its reason (vendor sweep 1.9.0)
As admin reject a withdrawal with a note; as the vendor open Earnings.

**Expect** the history row reads "Reason: <note>" under the Rejected badge, and the email carries it too.
