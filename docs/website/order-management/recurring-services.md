# Recurring Services & Subscriptions [PRO]

Sell services that bill on a schedule: weekly, monthly, quarterly, or yearly. Think retainers, maintenance plans, or ongoing content work.

## Enable recurring billing on a service

### From the frontend Service Wizard

Vendors can enable recurring billing directly from the frontend wizard at **Dashboard → Create Service → Pricing step**. A **Recurring Billing** section appears in the Pricing step when both conditions are met:

- The Pro plugin is active.
- Recurring services are globally enabled in **Sell Services → Settings → Orders → Recurring Services**.

Toggle **Enable recurring billing** on, then pick a billing frequency: Weekly, Monthly, Quarterly, or Yearly.

### From wp-admin

1. Edit the service and open the pricing section.
2. Mark the package as **Recurring** and pick the interval.
3. Buyers see the interval on the service page and at checkout.

Recurring billing runs on Stripe. Each cycle creates a new order for the vendor automatically, so delivery and messaging work exactly like one-off orders.

## Managing subscriptions

- Buyers see and cancel their subscriptions from their dashboard.
- Vendors see active subscribers per service.
- Admins get a **Recurring Subscriptions** page (under Services in wp-admin) listing every subscription with status, next renewal, and cancel controls.

## Failed payments

Stripe retries per its dunning settings. The subscription pauses if retries fail; webhooks keep order status in sync.
