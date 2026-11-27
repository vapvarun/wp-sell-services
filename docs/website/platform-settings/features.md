# Features

Switch whole features on or off in one place. Go to **Sell Services > Settings > Features**.

The tab lists every feature that can be switched, grouped by what it is for: Selling, Money, Branding, Engagement and Operations. Each row says what the feature does and, where it has one, links to its own settings.

The tab appears once a feature is registered there. With the free plugin alone, nothing is registered yet, so there is no Features tab.

## The features

| Feature | Group | Default | What off means |
|---------|-------|---------|----------------|
| **Buyer tips** [PRO] | Money | On | Buyers see no tip option and past tips are hidden from order pages. A tip already paid is still credited to the vendor. |
| **Vendor subscriptions** [PRO] | Money | Off | No plans are offered and no plan limit stops a vendor creating services. Existing Stripe subscriptions are not cancelled. |
| **Tiered commission** [PRO] | Money | On | Commission tiers are not applied; every order uses the normal rate. |
| **White label** [PRO] | Branding | Off | WP Sell Services branding shows again. Your saved branding is kept. |
| **Display currency** [PRO] | Branding | Off | No converted price hint is shown. Buyers still pay in the base currency. |
| **Push notifications** [PRO] | Engagement | Off | No push messages are sent. Your saved Firebase credentials are kept. |
| **Analytics dashboard** [PRO] | Operations | On | The admin analytics dashboard and its REST routes go away. Vendor analytics on the vendor dashboard stay. |
| **Recurring services** [PRO] | Selling | Not yet available | Switched off in this version and cannot be turned on: renewals do not charge yet. Do not plan on it. |

Turning a feature off hides it for new use only. Nothing is deleted: its settings, its records and any money already moved stay as they were, and turning it back on picks up where it left off.

## Features that need another feature

Some features need another one on first. The row says **Needs ...** when that is the case.

- You cannot turn such a feature on while the one it needs is off. The tab says which one to turn on first.
- Turning off a feature that others need turns those others off too, and the tab redraws to show it.

## Upgrading from an earlier version

Each feature that had its own on/off checkbox keeps what it said. A site that had White Label ticked still has White Label on after the update, and a site that had switched Buyer Tips off still has it off. The old checkboxes are gone from the feature cards; each card now shows the feature's state and a link to this tab.

## For developers

The list comes from the `wpss_modules` filter, and every change fires `wpss_modules_changed`. Read a feature's state with `wpss_module_enabled( 'white_label' )`. The REST routes are `GET /wpss/v1/modules` and `POST /wpss/v1/modules` (admins only). See [Hooks & Filters](../developer-guide/hooks-filters.md).
