# What's New in 1.9.2

**Version**: 1.9.2 · **Released**: November 2026

Free and Pro ship in lockstep. Install both.

---

## Record payments taken outside the plugin

A site that collects money through something the plugin does not ship, such as Flutterwave, Mobile Money or a payment link, can now tell the plugin an order is paid. An administrator's automation calls `POST /wp-json/wpss/v1/orders/{id}/mark-paid`, which does what the **Mark as Paid** button does and is safe to repeat. A gateway the plugin runs itself, such as Stripe or PayPal, cannot be named there, because that gateway records its own payments. See [Take Payments Outside the Plugin](../developer-guide/payments-outside-the-plugin.md).

Placing a bank-transfer order now emails the buyer the payment instructions and tells the admin and the seller, once per order. If no instructions are set, the email and the order page tell the buyer the site owner will send them.

## Secrets in wp-config.php

Stripe, PayPal and realtime secrets can be set as constants in `wp-config.php`, such as `WPSS_STRIPE_LIVE_SECRET_KEY`. The constant wins everywhere the key is read, the settings field is locked, and saving the settings never stores it. Pro adds the same for Razorpay.

**Settings > System > Trusted proxies** lists the proxies or CDNs allowed to pass on a visitor's real address. The settings page now shows a validation error instead of always saying **Settings saved**.

## One look on every page

Marketplace colours follow the site's theme, and every public page and dashboard form uses one button style. The Vendors directory looks like the other discovery pages, and each vendor tile leads with the name. A vendor's level badge, New vendor included, looks the same on service cards, the directory and the profile. Filter and toolbar dropdowns size to their content on every theme, and dashboard sections line up on a phone and read in dark mode. Category cards show the saved icon, and a service title shows in full on its page. Reign shows one navigation on plugin pages.

## Orders and messages

- The order page lists the tips and extensions paid on top of the order, and shows a **Discount** or **Price adjustment** line when the lines do not add up to the total.
- A buyer-request order reads as a **Project**. Accepting a phased proposal sends the buyer one project-plan email instead of one per phase.
- Order chat picks up a conversation started after the page opened, and unread badges refresh without a reload. Message threads open on the newest message.
- A revision request notifies the vendor once, and each delivery and revision sends one notice.
- An extension quote cannot be paid while its order is disputed or otherwise not active.
- A tip, extension or milestone phase no longer takes a review of its own.
- Pausing a service keeps it listed and stops new orders everywhere.

## Browsing

**Lowest rated** and **Least sold** keep services with no reviews or no sales at the end of the list. A `?vendor=` link to a member who is not a vendor is kept out of search indexes.

## Admin and vendors

Notification lists page by number on the dashboard, My Account and admin. Dashboard order counts open an Orders view that shows the same number. Sales Orders tells a suspended vendor why the menu shrank. The owner is warned when the plugin's background jobs are more than an hour overdue. On a store rail, the **Payment Gateways** tab links to the store's own payment settings, and EDD and FluentCart checkout are labelled beta and off by default.

## Safety

- A service gallery video must be on YouTube or Vimeo, so a page view never contacts a host the vendor chose.
- Every way of saving a service meets the same moderation check, including republishing a draft and editing categories.
- Closing an unpaid sub-order in any way cancels its store order, and scheduled order sweeps change only the orders they checked.
- Email sent while an order is being saved waits until the save completes, and long emails are retried after a failure.
- Database lock names carry the site's table prefix, so sites that share a database server do not block each other.

## Pro

A vendor who cancels a paid plan keeps it until the paid period ends. Creating a service without a plan says once that a plan is required. Only one PayPal payout batch runs at a time, money in an unfinished batch cannot be withdrawn again, and the vendor list is paged so it reaches every vendor. Public plan lists no longer show the Stripe Price ID, or the commission rate to visitors. Display-currency rates are fetched in the background, the OpenExchangeRates App ID is stored as a secret, and Razorpay checkout saves the billing the buyer typed. The Pro dashboard, analytics and White Label follow the site colour.

## For developers

- New filter `wpss_gallery_video_first` opens the service gallery on the video.
- Audit log rows are written only by listeners on documented actions, in Free and in Pro.

## Upgrading

Nothing is needed from you. Update Free and Pro together. There is no database change.
