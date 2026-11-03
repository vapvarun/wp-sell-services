# Journey 18 - Vendors are paid out automatically: Stripe Connect and PayPal Payouts (Pro)

**Roles:** admin, vendor (`wpss_vendor`), buyer
**Rail:** standalone (the Connect card is hidden on store rails)
**Status:** written 2026-11-03; walk pending, money
**Docs:** docs/website/payments-checkout/stripe-connect.md, earnings-wallet/automated-payouts.md, platform-settings/payouts-settings.md

## Steps

### 1. Stripe Connect: owner setup
Settings > Payment Gateways > Stripe Connect: enable, set the platform fee (default 20%). Stripe itself must already be configured.

**Expect** the setting saves; with Stripe keys missing the card says so instead of pretending to work.

### 2. Vendor connects
As a vendor open Earnings & Payouts; the Stripe Connect rail is there (there is no separate dashboard section). Start onboarding, finish in Stripe's test flow, return.

**Expect** the return lands on Earnings with the account shown as connected; abandoning and using the refresh link resumes onboarding.

### 3. A sale to a connected vendor
As the buyer pay for that vendor's service with Stripe.

**Expect** the charge is a destination charge (vendor share transferred, platform fee kept); the vendor's wallet is not credited a second time; a cart holding two vendors' services is not split and pays through the wallet instead.

### 4. Refund
Refund the order as admin.

**Expect** the transfer is reversed and the platform fee is not refunded; if the reversal outcome is unknown the vendor share is recorded as a wallet debt, never lost.

### 5. Vendor not connected
Repeat step 3 for a vendor with no connected account.

**Expect** the platform keeps the full charge and the vendor is credited in the wallet as on Free.

### 6. PayPal Payouts: owner setup and vendor email
Settings > Payouts: enter PayPal Payouts credentials and enable the rail. As a vendor save a PayPal email (profile or the Earnings rail card).

**Expect** the email saves on either surface and shows on the other.

### 7. Batch and sync
As admin create a payout batch for selected vendors, then sync its status.

**Expect** only vendors with a PayPal email and an available balance are included; sync debits the ledger only for items PayPal reports SUCCESS, and syncing twice debits once. **Known gap to confirm on the walk:** sync exists only as `POST /paypal-payouts/batches/{id}/sync` and no admin screen calls it, and there is no PayPal webhook - if the walk confirms an owner cannot sync from the screen, file it.

Guards: `tests/pro/test-connect-refund-observation.php`, `tests/pro/test-payout-integrity.php`, `tests/test-paypal-payouts-vendor-paging.php`, `tests/test-payout-profile-contract.php`, `tests/test-payout-method-registry.php`.
