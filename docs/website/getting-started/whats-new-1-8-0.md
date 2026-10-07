# What's New in 1.8.0

**Version**: 1.8.0 · **Released**: October 2026

Free and Pro ship in lockstep. Install both.

---

## Every date is stored in UTC

Until now some dates were stored in your site's time zone, some in UTC, and some
in the database server's own clock. On a site where those differ, order timelines
and deadlines could disagree by hours.

1.8.0 stores every date in UTC and shows it in your site's time zone. Dates
written by earlier versions are converted once, in the background, after you
update. Nothing is needed from you on most sites.

If your database server runs in a different time zone from WordPress, preview the
conversion first:

```bash
wp wpss utc-migrate --dry-run
```

See [WP-CLI Commands](../developer-guide/wp-cli-commands.md). App developers:
about 20 REST date fields are now ISO 8601 with a `+00:00` offset.

## One payment, one order, on every path

A single payment can no longer be confirmed against two orders, whichever way it
arrives: the website, the REST API, or a gateway webhook. A payment that reaches
your site by webhook before the buyer's browser returns now builds the order
exactly as checkout would, with the right tax, add-ons and delivery time.

If a buyer is charged for a service that can no longer be ordered (the vendor
paused or deleted it during payment), the charge is refunded automatically.

Refunds are counted once per gateway refund, and a full refund now reaches the
order's paid extensions and tips and closes any open dispute.

## Safe to share a gateway account between sites

Stripe, PayPal and Razorpay send every payment event on an account to every site
connected to it. If your live site and a staging copy shared an account, one
could react to the other's payments.

Each site now acts only on payments it started itself. See
[Stripe Payments](../payments-checkout/stripe-payments.md) and
[PayPal, Razorpay, and Offline Payments](../payments-checkout/other-gateways.md).

PayPal receipts now show an invoice number beginning `WPSS-`. A PayPal payment
left unfinished at the moment you update is not charged; the buyer pays again.

## Members can report a service or a seller

A **Report this service** link on the service page and **Report this seller** on
the vendor profile open a short form. Every report lands on **Sell Services >
Member Reports**, where you close it and, if needed, suspend or close the
member's account. See [Member Reports](../admin-tools/member-reports.md).

## Express delivery

Vendors can offer a faster turnaround on any package, at its own price and
delivery time. **[PRO]** prices it on the WooCommerce checkout too.

## A Log In page, on your terms

The marketplace has its own sign-in form, `[wpss_login]`. Select a page under
**Settings > Pages > Log In** and every Log in link on the site goes to it.

Updating never creates this page or changes how your site signs in. A brand-new
install creates it only when the site still uses the standard WordPress login.
Choose **None (use this site's own login)** to switch it off. See
[Pages Setup](../platform-settings/pages-setup.md).

## Admin and dashboard, rebuilt for small screens

- **Settings** are regrouped into one card per concern, and warn you before unsaved changes are lost.
- **Orders** has triage tabs, shows payment and due date on each row, and filters by vendor and buyer.
- **The order screen** in wp-admin shows the payment, the activity and the money first.
- **Admin lists** hold their layout on tablets and stack cleanly on phones.
- **The member dashboard** uses an off-canvas menu on tablets and phones, and its earnings cards stay readable at every width.
- **Confirmation popups** open with Cancel selected, so Enter cannot approve a payout by reflex.

## Smaller changes worth knowing

- A vendor on vacation is left out of the catalog, search, Related services and every services grid until they return, and a service of theirs already in a buyer's cart cannot be paid for. See [Vacation Mode](../vendor-system/vacation-mode.md).
- A paused or deleted service in a buyer's cart is marked as unavailable and left out of the total.
- The reason and details entered when cancelling an order are shown to the buyer, the vendor and you, and are included in the Order Cancelled email.
- On a block theme the member dashboard takes the theme's wide width.
- Buyers can add and remove files when editing a request. Once a seller has sent a proposal, the files already attached stay.
- Vendors can cancel a pending withdrawal request, and sellers can withdraw a pending proposal.
- Deleting demo content removes the demo vendors too, and never removes your own categories. A demo service that a buyer has ordered is kept, with its seller, so the order is not lost.
- Commission is taken on the price without tax, in tax-inclusive mode too.
- If you charge tax and take payments through Stripe, some earlier orders may have recorded the tax twice when Stripe's webhook arrived before the buyer's browser. `wp wpss repair:stripe-tax` lists them, and corrects them with `--apply`.

## For developers

- New 409 codes: `wpss_payment_already_used`, `wpss_file_in_use`. See [REST error codes](../developer-guide/rest-error-codes.md).
- `GET /cart` lines carry `unavailable` and `unavailable_reason`; `PUT /buyer-requests/{id}` accepts `attachments`.
- New overridable templates: `order/payment.php`, `order/cancellation.php`, `partials/report-modal.php`, plus the dashboard sections, order row and order filters. See [Template Overrides](../marketplace-display/template-overrides.md).
- New action `wpss_enqueue_dashboard_assets`; the full list is in the [Hook Reference](../developer-guide/hooks-reference.md).
- The unused plain-text email templates were removed.
