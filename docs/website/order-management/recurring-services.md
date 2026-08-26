# Recurring Services & Subscriptions [PRO]

Sell services that bill on a schedule: weekly, monthly, quarterly, or yearly. Think retainers, maintenance plans, or ongoing content work.

> ## Not switched on in 1.3.0
>
> **Recurring services are switched off in the plugin and there is no setting to turn them on.** Their settings, wizard section and admin page are all hidden. If you cannot find any of the screens below, nothing is broken and nothing you can change will reveal them -- the feature is not finished. This page describes the intended behaviour.
>
> The code ships (including its REST routes and Stripe webhook handling) so the feature can be finished and turned on without a migration, but it is **not supported for production use** and we do not recommend selling recurring services on 1.3.0.
>
> Developers can opt in on a staging site:
>
> ```php
> add_filter( 'wpss_pro_recurring_feature_available', '__return_true' );
> ```
>
> Once the filter returns true, the settings appear under **Sell Services > Settings > Orders & Disputes** and the rest of this page applies. We will announce general availability in a release note; this banner comes down at that point.

## Enable recurring billing on a service

### From the frontend Service Wizard

Vendors can enable recurring billing directly from the frontend wizard at **Dashboard > Create Service > Pricing step**. A **Recurring Billing** section appears in the Pricing step when all of these are true:

- The Pro plugin is active.
- The `wpss_pro_recurring_feature_available` filter returns true (see the notice above).
- Recurring services are globally enabled in **Sell Services > Settings > Orders & Disputes**.

Toggle **Enable recurring billing** on, then pick a billing frequency: Weekly, Monthly, Quarterly, or Yearly.

### From wp-admin

1. Edit the service and open the pricing section.
2. Mark the package as **Recurring** and pick the interval.
3. Buyers see the interval on the service page and at checkout.

Recurring billing runs on Stripe. Each cycle creates a new order for the vendor automatically, so delivery and messaging work exactly like one-off orders.

## Managing subscriptions

- Buyers see and cancel their subscriptions from their dashboard.
- Vendors see active subscribers per service.
- Admins get a **Subscriptions** page under **Sell Services** in wp-admin, listing every subscription with status, next renewal, and cancel controls. This menu item only registers while the feature flag is on.

## Failed payments

Stripe retries per its dunning settings. The subscription pauses if retries fail; webhooks keep order status in sync.
