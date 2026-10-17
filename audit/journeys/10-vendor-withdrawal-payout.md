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
