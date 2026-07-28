# What's New in 1.3.0

Version 1.3.0 is a large stability and polish release: payments are hardened across every gateway, dark mode now works on every surface, and first-run setup is lighter.

## Full dark mode on every surface

WP Sell Services now follows your **theme's** dark mode. When the active theme (BuddyX, BuddyX Pro, Reign, or any theme with a dark toggle) switches to dark, every plugin surface — dashboard, service pages, buyer requests, checkout — goes dark with it, at readable AA contrast. The plugin never darkens on top of a light theme, and it does not follow the OS setting independently of the theme.

Theme developers: see the [Theme Integration](../developer-guide/theme-integration.md) guide for the token system and how to match your palette.

## Lighter first-run setup

- The Setup Wizard no longer forces you to configure a payment gateway before you can finish. Offline/manual payment works out of the box, and the wizard guides you to add a gateway when you are ready.
- Fresh installs are sell-ready immediately: manual payment is enabled, default service categories are seeded, and new services publish without forced moderation.

## Role-based menu visibility

Show or hide dashboard sections per user role, so different roles see only the areas that apply to them. Developers can gate any section with the `wpss_can_access_dashboard_section` filter.

## Frontend dispute messaging

Buyers and vendors can now message each other and attach evidence directly on a dispute, without leaving the order.

## Payments hardened

- PayPal checkout sends the full context, handles multi-item carts correctly, and no longer shows a dead Pay button.
- Money is formatted to each currency's precision (including 0- and 3-decimal currencies), and refunds round to the currency's precision.
- The order call-to-action is guarded against zero-price or unpriced packages, paused services can no longer be ordered, and the purchased item is cleared from the cart after checkout.

## Quality of life

- A Log Out link now appears in the dashboard navigation.
- Add-to-cart continues straight to checkout so "Continue to Checkout" matches its label.
- Buyer request cards keep their actions on one line on mobile and tablet.
- Accessibility pass: visible keyboard focus, form-field labels, and ARIA labels on search and category controls.

## For developers

- A gateway-agnostic **CheckoutIntent** seam (resolve → charge → settle) so any gateway plugs in the same way, with the amount always server-computed. See [Custom Integrations](../developer-guide/custom-integrations.md).
- Base currency is authoritative for all stored amounts; catalog display goes through the `wpss_catalog_price_html` filter.
- New filters: `wpss_pro_upgrade_url`, `wpss_docs_url`, `wpss_catalog_price_html`, `wpss_can_access_dashboard_section`. See [Hooks and Filters](../developer-guide/hooks-filters.md).
- The plugin ships with zero `wp i18n make-pot` warnings; buyer-facing JavaScript strings are fully translatable.

See the full changelog in `readme.txt`. Pro users: install **WP Sell Services Pro 1.3.0** alongside this release — the two are lockstep.
