# Journey 10 - A seller is paid out, and is told the truth at each step

**Roles:** seller (`wpss_vendor`), admin
**Rail:** standalone payments, manual payout (PayPal email)
**Status:** written 2026-10-18; walk pending

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
- The seller gets "Withdrawal of X Paid" (card:10402904790), not "Approved". Body status cell reads Paid.
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
