# WP Sell Services Pro Overview

WP Sell Services Pro extends the free WP Sell Services marketplace with the earnings, payout, subscription, currency, and analytics features a production marketplace needs. It requires **WP Sell Services** (free) to be installed and active, and the two are released in lockstep — always run the same version of both.

For a feature-by-feature breakdown, see [Free vs Pro](free-vs-pro.md).

## What Pro adds

| Area | Capability |
|------|-----------|
| **Payouts** | Automated Stripe Connect payouts and PayPal mass payouts, plus per-vendor payout profiles. Free already lets you pay every vendor manually (export what is owed, mark it paid) — Pro automates that rail. |
| **Commission** | Tiered commission rules and subscription-plan overrides on top of Free's flat rate; the split is computed once and persisted per order across every payment rail. |
| **Vendor subscriptions** | Sell vendor membership plans billed through hosted Stripe Checkout, with plan switching and enforcement. |
| **Currency** | Display-only multi-currency hint so shoppers see prices in their currency while your base currency stays authoritative. |
| **Analytics** | Sales, earnings, and payout analytics with export. |
| **Storage** | DigitalOcean Spaces / S3-compatible delivery storage. |
| **White label** | Rebrand the marketplace admin surfaces. |

## The money journey

Pro owns the settlement engine end to end. A buyer's payment is split once into platform fee + vendor earnings, recorded on the order and the wallet ledger, and later paid out through whichever rail you choose. Refunds reverse the ledger and net against debt. The golden rule: an owner can always pay every vendor with **no integrations at all** — export what's owed, mark it paid — and automated rails are opt-in, never forced.

See [Paying Your Vendors](../earnings-wallet/vendor-payouts.md) for the full flow, and [Tiered Commission Rules](../earnings-wallet/tiered-commission.md) for the rules engine.

## Requirements

- WP Sell Services (free) **1.3.0**, active.
- PHP 8.1+, WordPress 6.4+.
- A valid Pro license key for updates and support.

## Installing

1. Install and activate WP Sell Services (free) first.
2. Upload and activate WP Sell Services Pro.
3. Enter your license key under the plugin's License screen.
4. Configure payouts, commission, and (optionally) subscriptions and display currency from the plugin settings.
