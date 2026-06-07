# Stripe Connect Split Payments [PRO]

Stripe Connect pays vendors their share automatically at checkout. No manual payouts, no withdrawal queue.

## How it works

1. You connect the platform Stripe account in **Services > Settings > Stripe Connect**.
2. Each vendor onboards to Stripe from their dashboard (Stripe hosts the KYC flow).
3. When a buyer pays, Stripe splits the charge: the vendor share goes to the vendor's Stripe account, your commission stays in yours.

## Setup

1. Create a Stripe account and enable Connect in the Stripe dashboard.
2. Add your Connect client ID and API keys in **Services > Settings > Stripe Connect**.
3. Add the webhook endpoint shown on the settings screen to Stripe (payment + account events).
4. Vendors see a **Connect with Stripe** button in their earnings dashboard.

## Vendors who do not connect

Orders still work. Unconnected vendors accrue earnings in the standard ledger and request withdrawals manually, same as the free plugin.
