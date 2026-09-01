# What's New in 1.6.0

**Version**: 1.6.0 · **Released**: 18 August 2026

Free and Pro ship in lockstep. Install both.

---

## Money reaches the right vendor on every platform

Orders paid through **Easy Digital Downloads** never started and never credited
the vendor -- the payment completed and nothing happened. **FluentCart** listened
for events FluentCart does not send, with the same result. Both now listen on the
real events and credit correctly.

EDD also read a price the plugin does not store, so a service could be sold at
the wrong amount. Pricing now comes from the package.

If you run either platform, check your recent orders after upgrading. Anything
paid but not started needs a manual review.

## SureCart was removed

A hosted catalogue cannot act as a payment rail the way WooCommerce does, and
shipping it as though it could was the wrong half-measure. If you were using it,
move to WooCommerce, EDD or FluentCart, or run the built-in standalone checkout.

## Push notifications

Members can get the events they already receive in the app pushed to their
phones. Enter Firebase credentials under **Settings > Advanced**; it stays off
until you do.

Note that the companion app is what receives them, and every member-facing Pro
capability is still web-only.

## Package identity is frozen on the order

Packages now carry a stable id rather than a position, so editing or reordering
a service can no longer change what an existing order says the buyer bought.
Existing orders are repaired against what was actually paid.

## Offline payment receipts

A buyer can upload proof of an offline payment; you verify it and the order
starts. Three new emails cover submitted, verified and rejected. Off by default
-- enable it under **Settings > Orders & Disputes**.

Two admins approving the same receipt at once credits the vendor once.

## Mobile sign-in hardened

App tokens now expire -- 30 days idle, 90 days absolute -- and a token can no
longer be used as the account password to mint more tokens. `GET` and
`DELETE /auth/sessions` let a member see and revoke their own sessions.

## Smaller, still worth knowing

- **Checkout was full-bleed** on any theme that opens its content wrapper in a
  page template rather than `header.php`. Reproduced on stock Twenty
  Twenty-Four, so not theme-specific.
- **Service gallery** opens on the image, and a video's thumbnail now comes from
  the provider. YouTube is not contacted until someone asks for the embed.
- **Category choosers** offer parent categories in the wizard, with
  subcategories grouped consistently across every surface.
- **The site owner could be blocked by their own paywall** when vendor
  subscriptions were required. Fixed.
- **Email preferences** are role-aware -- buyers see 5 categories, vendors 8.
  The previous hardcoded list silently muted buyers' tips, withdrawals and
  proposal emails.

---

## Upgrading

1. Update **both** plugins together. Pro depends on Free.
2. If you ran SureCart, pick a different rail first.
3. If you run EDD or FluentCart, review orders that were paid but never started.
4. Database schema updates run automatically on first admin load.

---

Full detail in each plugin's `readme.txt` changelog. For what is in each tier,
see [Free vs Pro](free-vs-pro.md) and the [Feature catalog](../feature-catalog.md).
