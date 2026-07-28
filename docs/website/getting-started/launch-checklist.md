# Launch Checklist

Everything between installing the plugin and taking real money, in order. Work
top to bottom -- each stage assumes the one above it is done.

Defaults in brackets are what the plugin ships with, so you only need to change
what does not suit you.

## 1. Install

- [ ] WordPress 6.4+, PHP 8.1+ -- [Installation](installation.md)
- [ ] Free plugin installed and activated
- [ ] Pro installed and **license activated** at **Sell Services > License** (if you bought Pro). Pro features do not load without it -- [License Activation](pro-license.md)
- [ ] Run `wp wpss preflight` if you have WP-CLI. Every check should print PASS

## 2. Core setup

- [ ] **Sell Services > Settings > General** -- platform name, currency, and how checkout runs (standalone or an e-commerce platform)
- [ ] **Settings > Pages** -- create and assign all **4** pages: Services, Dashboard, Become a Vendor, Checkout
- [ ] Add Services and Become a Vendor to your site menu (**Appearance > Menus**)
- [ ] Add service categories at **Sell Services > Categories**

Currency is worth deciding now. Everything is stored and settled in your base
currency, and changing it later does not convert existing records.

## 3. Money

Get this right before a single real order.

- [ ] **Settings > Payment Gateways** -- enable at least one gateway and complete its credentials
- [ ] Add the gateway's **webhook** endpoint. Without it, payments can succeed at the gateway and never mark the order paid
- [ ] Test in the gateway's sandbox mode, end to end, before switching to live keys
- [ ] **Settings > Commission & Tax** -- commission rate [10%], per-vendor overrides [on], tax [off]
- [ ] Decide the **tip commission rate**. Empty means tips are commissioned at your normal rate; `0` means vendors keep tips in full
- [ ] **Settings > Payouts** -- minimum withdrawal [$25], clearance period [**0 days**], wallet provider
- [ ] Decide how you will actually pay vendors -- [Paying Your Vendors](../earnings-wallet/vendor-payouts.md)

> The clearance period ships at **0**, meaning earnings are withdrawable as soon
> as an order completes. If you want a buffer against refunds and chargebacks,
> raise it now rather than after money is already owed.

## 4. Vendors

- [ ] **Settings > Vendors** -- registration mode: open, requires approval, or closed
- [ ] Max services per vendor [20], identity verification [off]
- [ ] Service moderation [**off**]. Left off, new listings publish immediately. Turn it on if you want to review every service first -- [Service Moderation](../admin-tools/service-moderation.md)
- [ ] **[PRO]** Vendor subscription plans, if vendors will pay to sell

## 5. Orders and policy

- [ ] **Settings > Orders & Disputes** -- auto-complete [3 days], requirements timeout [7 days], dispute window [14 days], auto-flag late orders [3 days]
- [ ] Confirm disputes are enabled [on] and you know who will mediate them

Revision limits are **not** here -- vendors set those per package.

## 6. Emails

- [ ] **Settings > Emails** -- send the **deliverability test** first. If it does not arrive, no notification will
- [ ] Install an SMTP plugin if the test fails. WordPress's default mail is unreliable on most hosts
- [ ] Review the 23 notification types [all on] and switch off any you do not want

## 7. Branding and display

- [ ] Place your marketplace elements -- [Shortcodes](../marketplace-display/shortcodes-reference.md) or [Blocks](../marketplace-display/gutenberg-blocks.md)
- [ ] Check the catalog, a service page, and the dashboard on a phone
- [ ] **[PRO]** White label -- do this **last**, since renaming the admin menu makes the rest of these docs stop matching your screen

## 8. Rehearse before launch

Do not let a customer find the first bug.

- [ ] Create a test vendor and publish a test service
- [ ] Order it as a **different** user, and pay with a real (small) live transaction
- [ ] Submit requirements, send a message with an attachment, deliver, request a revision, then complete
- [ ] Confirm every email arrived, for both parties
- [ ] Check the vendor's earnings and request a withdrawal
- [ ] Open and resolve a dispute on a second test order
- [ ] Refund a test order and confirm the ledger reversed

`wp wpss demo marketplace` seeds a full marketplace for rehearsal, and
`wp wpss demo delete` removes it without touching real content --
[WP-CLI Commands](../developer-guide/wp-cli-commands.md).

## 9. Go live

- [ ] Swap every gateway from sandbox to **live** keys, and re-point webhooks at the live endpoints
- [ ] Delete demo content
- [ ] Turn off debug mode in **Settings > Advanced**
- [ ] Place one real order yourself, then refund it
- [ ] Confirm your backups run and include the database -- orders, earnings, and the wallet ledger all live there

## After launch

- **Withdrawals** need a human. Approve and pay them on a schedule your vendors can predict.
- **Disputes** need answering quickly. A slow mediator loses both buyers and vendors.
- **Moderation queue**, if enabled, blocks vendors from selling until you clear it.
- **Renew Pro before it expires.** An expired license switches Pro features off.

## Related

- [Quick Setup Guide](initial-setup.md)
- [Dashboard Tour](dashboard-tour.md)
- [FAQ & Troubleshooting](../faq/faq-troubleshooting.md)
