# Payouts Settings

**Sell Services > Settings > Payouts**
Direct link: `wp-admin/admin.php?page=wpss-settings#payouts`

This tab controls **how vendors get their money out**: when earnings become
withdrawable, the minimum they can request, and whether high earners are paid
automatically.

It configures the rules. The actual batch work -- approving, exporting, marking
paid -- happens on **Sell Services > Withdrawals**, which this tab links to.

> Settings are hash-routed. `#payouts` scrolls to this tab; there is no `?tab=`
> parameter.

---

## Withdrawal Settings

| Field | Type | Default | What it does |
|---|---|---|---|
| Wallet Provider | Select | Internal Wallet | Which wallet holds vendor balances |
| Minimum Withdrawal | Number, 0-1000 | `25` on a fresh install | The floor for a withdrawal request |
| Clearance Period (Days) | Number, 0-90 | `0` | How long earnings are held before they can be withdrawn |

### Wallet Provider

Where vendor balances live. The built-in **Internal wallet** needs nothing else
installed and is the only choice the plugin ships. A developer can add another
provider with the `wpss_wallet_providers` filter.

Pick this once, at setup. Switching provider later does not migrate balances.

### Minimum Withdrawal

Vendors must reach this balance before they can request a payout. It exists to
stop a stream of $3 transfers, each of which costs you a bank fee and a minute
of admin.

$25-$100 suits most marketplaces. Set it too high and vendors feel their money
is trapped; too low and your payout run becomes tedious.

### Clearance Period (Days)

**The single most consequential field on this tab.** It is how many days
completed earnings are held before a vendor may withdraw them.

**Default is `0` -- pay out as soon as an order completes.** That is a
deliberate choice, not an oversight. Plenty of marketplaces pay immediately, and
whether to sit on a vendor's money is your business policy, not the plugin's.

Set `7`, `14` or `30` if you want a refund buffer:

| Value | Meaning |
|---|---|
| `0` | No hold. Money is withdrawable as soon as it is earned |
| `7` | Weekly hold |
| `14` | Fortnightly hold |
| `30` | Monthly hold |

**Match it to your dispute window.** Buyers can open a dispute for a number of days after an order completes (**Settings > Orders & disputes > Dispute Window (Days)**, 14 by default). With a shorter clearance period, a dispute can arrive after the vendor has already withdrawn the money, and a refund then comes out of your pocket until their future earnings cover it. The field's help text and the setup wizard's last screen quote your current dispute window so you can match it. The default stays `0`; the choice is yours.

**What a hold buys you.** If a refund arrives inside the window, the money is
still unpaid and nothing has to be clawed back.

**What happens with no hold.** You do not eat the loss -- the ledger records it
honestly. A refund on money the vendor has already withdrawn drives that
vendor's balance **negative**, and their future earnings pay it down
automatically. The balance is deliberately not clamped at zero. Clearance
avoids that conversation; the ledger survives it either way. Both are correct.

Two exceptions worth knowing:

- **Tips, milestone phases and paid extensions credit at payment**, not at
  completion, so the clearance clock starts from the payment.
- **Stripe Connect bypasses clearance entirely.** Connect splits at charge time
  and pays the vendor's Stripe account directly, so no hold applies to a Connect
  payment regardless of what you set here.

---

## Automatic Withdrawals

| Field | Type | Default | What it does |
|---|---|---|---|
| Enable Auto-Withdrawal | Checkbox | Off | Creates withdrawal requests automatically |
| Auto-Withdrawal Threshold | Number, 100-10000, step 50 | `500` | Balance above which a vendor is picked up |
| Auto-Withdrawal Schedule | Select | Monthly | Weekly (Mondays) / Bi-weekly (1st and 15th) / Monthly (1st) |

With this on, any vendor whose **available** balance clears the threshold on the
scheduled day has a withdrawal request created for them -- they do not have to
ask. Available balance means the ledger balance minus pending withdrawals minus
anything still in clearance.

**This creates the request; it does not move the money.** You still complete
each payout on the Withdrawals screen. That separation is on purpose: exporting
or queuing a payout must never claim money has been sent when it has not.

Saving this card reschedules the background job immediately.

---

## PayPal Payouts **[PRO]**

Bulk-pay vendors through PayPal's Payouts API.

| Field | Type | Default | What it does |
|---|---|---|---|
| Enable PayPal Payouts | Checkbox | Off | Turns on batch sending and adds a PayPal email card to each vendor's Payout Methods |
| PayPal Client ID / Client Secret | Text / Password | -- | From the PayPal Developer Dashboard |
| Sandbox Mode | Checkbox | Off | Test without moving real money |
| Minimum Payout Amount | Number | -- | Vendors below this are excluded from a batch |

The card also holds **Create batch payout** (pick vendors, send) and **Recent
Payout Batches**.

**Vendors must save a PayPal email first.** Until they do, the vendor table here
shows an empty state. With PayPal Payouts enabled, vendors enter it in their
Payout Methods. It is the same email their PayPal withdrawals use, so there is one
address to keep current. A vendor whose payout method is something else (bank
transfer, for example) is not included in a PayPal batch.

Before 2.0.0 this card had a second switch, *Show PayPal Payout Option to
Vendors*. It is folded into *Enable PayPal Payouts*: a site that had either one on
keeps PayPal Payouts on after the update.

This card saves over AJAX with its own **Save payout settings** button.

> This is not the PayPal *gateway* on the Payment gateways tab. That one takes
> money from buyers; this one sends money to vendors. They use separate
> credentials and either can run without the other.

---

## You can pay every vendor with no integration at all

This matters more than any option above: **a site with no gateway and no
Connect and no PayPal Payouts still has a complete payout flow.**

On **Sell Services > Withdrawals** you can filter the queue, export it to CSV
with the bank and PayPal bulk-upload columns your bank expects, pay however you
actually pay, and then **Mark paid**. That single step writes the ledger debit,
and marking twice debits once.

Exporting never changes a status. Export and mark-paid are two deliberate acts,
because an export that auto-marked would lie the moment a bank transfer bounced.

Stripe Connect and PayPal Payouts are conveniences on top of that. They are
never prerequisites.

---

## Related documentation

- [Vendor Payouts](../earnings-wallet/vendor-payouts.md)
- [Withdrawals](../earnings-wallet/withdrawals.md)
- [Automated Payouts](../earnings-wallet/automated-payouts.md) **[PRO]**
- [Wallet System](../earnings-wallet/wallet-system.md)
- [Withdrawal Approvals](../admin-tools/withdrawal-approvals.md)
- [Stripe Connect](../payments-checkout/stripe-connect.md) **[PRO]**
- [Money Flow](https://github.com/vapvarun/wp-sell-services/blob/main/docs/architecture/MONEY-FLOW.md)
