# Journey 19 - Wallet provider and admin balance adjustments

**Roles:** admin, vendor
**Rail:** standalone, woo
**Status:** written 2026-11-03; walk pending
**Docs:** docs/website/earnings-wallet/wallet-system.md, platform-settings/payouts-settings.md

There is no "pay with wallet credit" at checkout: no wallet gateway exists in Free or Pro. The wallet is the vendor's earnings balance. TeraWallet, WooWallet and myCred are not offered (docs say so); a third party can add a provider through `wpss_wallet_providers`.

## Steps

### 1. Provider choice (the notice is Pro, licensed; card:10475574286)
Settings > Payouts > Wallet Provider. Seed an inactive provider with `wp option update wpss_wallet_provider terawallet`.

**Expect** Internal is offered and the vendor's balance is unchanged. With Pro active and licensed, the inactive provider falls back to Internal with an admin notice. Free alone has only the Internal provider and reads no other, so it shows no notice; that is correct, not a missing one.

### 2. Balance agrees everywhere
As a vendor with earnings open Earnings & Payouts; as admin open that vendor's user profile.

**Expect** the same balance on both, equal to `wpss_get_ledger_balance()` for that vendor.

### 3. Admin credit and debit
On the vendor's user profile, Adjust Balance: credit 10 with a reason, then debit 5, then debit more than the balance.

**Expect** each adjustment writes one ledger row "Admin adjustment by <admin>: <reason>" and one audit row naming the admin; the over-balance debit is refused with a message and writes nothing; a non-admin cannot reach either action (nonce + `manage_options`).

Guards: `tests/test-wallet-provider-contract.php`, `tests/test-admin-wallet-adjustment-leaves-trail.php`, `AdminActionAuthorityTest` (Pro wallet handlers).
