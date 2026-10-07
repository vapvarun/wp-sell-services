# Wallet System

Vendor money in WP Sell Services lives in one place: the plugin's own wallet. Vendors earn from orders, wait out the clearance period, and request withdrawals. No other plugin is needed.

## What the wallet does

- Credits the vendor's share when an order completes. Tips, milestone phases and paid extensions credit when the buyer pays them.
- Holds new earnings for the clearance period before they can be withdrawn.
- Records every credit and debit, which the vendor sees under **Dashboard > Earnings & Payouts** and can export.
- Pays out through withdrawal requests you approve, or automatically with Stripe Connect or PayPal Payouts in Pro.

## Wallet plugins

TeraWallet, WooWallet and MyCred are **not** supported as places to keep vendor balances. They were listed as options before 1.7.1 and removed, because the plugin never wrote balances to them.

**Sell Services > Settings > Payouts > Wallet Provider** offers the built-in **Internal Wallet**. A developer can register another provider with the `wpss_wallet_providers` filter; that provider then appears in the list.

## Related Docs

- [Earnings Dashboard](earnings-dashboard.md) -- How vendors track income
- [Withdrawals](withdrawals.md) -- How vendors request payouts
- [Commission System](commission-system.md) -- How earnings are split
- [Automated Payouts](automated-payouts.md) -- Schedule automatic vendor payments
