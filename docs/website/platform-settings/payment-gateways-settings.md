# Payment Gateways Settings

**Sell Services > Settings > Payment Gateways**
Direct link: `wp-admin/admin.php?page=wpss-settings#payments`

This tab is where you decide how buyers actually hand over money on the
**standalone** checkout. Each gateway is its own card, with its own enable
switch and its own Save button.

> **Settings pages are hash-routed.** All tabs render on one screen and the
> `#payments` fragment scrolls to this one. There is no `?tab=` parameter --
> `admin.php?page=wpss-settings&tab=payments` will land you on General.

---

## Before you configure anything: which rail is active?

These gateways power the **standalone** checkout only.

If WooCommerce, Easy Digital Downloads or FluentCart is active, that
platform owns checkout and payment end to end. Buyers pay through *its* gateways
and never see these. Configuring Stripe here on a WooCommerce site changes
nothing, and the screen will not warn you -- there is no "WooCommerce owns
payments" banner on this tab.

Check which rail is live on **Settings > General**, in the E-Commerce
Integration section: it prints **Currently Active: <name>** under the platform
selector. That line, not this tab, is the source of truth.

Switching rails never rewrites past orders. An order paid through Stripe keeps
its Stripe record and its refunds keep working, even after you move the
marketplace to WooCommerce.

---

## How the cards behave

- A gateway that is **disabled starts collapsed**, with a grey *Disabled*
  badge. Click the header to open it.
- Each card is an **independent form**. Saving Stripe does not save PayPal.
  Press the Save button inside the card you edited.
- Secret fields are masked. **Leaving a secret field blank keeps the saved
  value** -- it does not erase it. To rotate a key, paste the new one.
- After saving you stay on this tab; a toast confirms.

---

## Stripe

Card payments, and the gateway most marketplaces start with.

| Field | Type | Default | Notes |
|---|---|---|---|
| Enable Stripe | Checkbox | Off | |
| Test Mode | Checkbox | Off | Uses Stripe's test environment |
| Test Secret Key | Password | -- | Starts `sk_test_` |
| Test Publishable Key | Text | -- | Starts `pk_test_` |
| Live Secret Key | Password | -- | Starts `sk_live_` |
| Live Publishable Key | Text | -- | Starts `pk_live_` |
| Webhook Secret | Password | -- | Signing secret used to verify incoming events |
| Pass Gateway Fees to Buyer | Checkbox | Off | On: the fee is added to the buyer's total. Off: it comes out of vendor earnings |
| Gateway Fee (%) | Number, 0-10 | `2.9` | Stripe's percentage. Only used to compute the fee, never read back from Stripe |
| Gateway Fee (Fixed) | Number, 0-5 | `0.30` | Per-transaction fixed fee, in your currency |

The card includes a three-step **Stripe Setup Guide** covering API keys, the
webhook endpoint to register (`/wpss-payment/stripe/callback/`, listening for
`payment_intent.succeeded`, `payment_intent.payment_failed` and
`charge.refunded`), and the minimum permissions for a restricted key.

**Fee fields are for display and splitting, not billing.** Stripe charges what
Stripe charges. These two numbers only tell the plugin how to show and allocate
that cost. If your Stripe pricing differs from the US default, set them to your
real rate, or the vendor's earnings line will be slightly wrong.

---

## PayPal

| Field | Type | Default | Notes |
|---|---|---|---|
| Enable PayPal | Checkbox | Off | |
| Sandbox Mode | Checkbox | Off | |
| Sandbox Client ID / Client Secret | Text / Password | -- | From your PayPal sandbox app |
| Live Client ID / Client Secret | Text / Password | -- | From your PayPal live app |
| Webhook ID | Text | -- | Used to verify webhook signatures |
| Pass Gateway Fees to Buyer | Checkbox | Off | Same behaviour as Stripe |
| Gateway Fee (%) | Number, 0-10 | `2.9` | |
| Gateway Fee (Fixed) | Number, 0-5 | `0.30` | |

---

## Offline Payment

Bank transfer, cash, cheque, invoice -- anything settled outside the site. This
is enabled on a fresh install so a new marketplace can take an order on day one
with no gateway account. (Upgrades are never re-enabled.)

| Field | Type | Default | Notes |
|---|---|---|---|
| Enable Offline Payment | Checkbox | **On** for new installs | |
| Title | Text | "Manual / Offline Payment" | What the buyer sees at checkout |
| Description | Textarea | "Pay via bank transfer, cash, or other offline methods..." | Short line under the title |
| Payment Instructions | Rich text | Seeded placeholder | Shown **after** the order is placed. Used while you have not named any methods below |
| Auto-Cancel (Hours) | Number, 0-720 | `0` | Cancels unpaid orders after N hours. `0` disables |

**Payment Instructions supports placeholders:** `{order_number}`, `{order_id}`,
`{total}`, `{currency}`. Use them so the buyer can quote a reference on the
transfer.

### Naming your payment methods

Under the offline card there are four **Method** slots. Fill in as many as you
take payment by - bank transfer, cash, UPI, cheque - each with its own
instructions and its own on/off switch. Leave a slot blank to skip it.

| Field | Notes |
|---|---|
| Method name | What the buyer picks at checkout, e.g. "Bank Transfer" |
| Instructions | Everything they need to pay you by that method. Supports the same placeholders |
| Offer this method at checkout | Untick to keep a method without offering it |

Buyers choose from the enabled methods at checkout. When only one is enabled
they are not asked to choose - it is used automatically.

**What you name a method is recorded on each order that uses it.** Rename a
method later, or delete it, and orders already placed keep showing the name and
the instructions that were live when the buyer paid. You can correct your bank
details for future orders without changing what a past order told someone.

**The first time you fill these in, slot 1 arrives pre-filled** with your
existing Payment Instructions, so your own wording carries over. Saving moves
you onto named methods and the single Payment Instructions box above stops
being used for new orders.

If you never open this editor, nothing changes: buyers see one offline option
using the Payment Instructions box, exactly as before.

**Offline has no webhook and no automatic confirmation.** Nothing tells the site
the money arrived -- you mark the order paid yourself from the Orders screen.
Set **Auto-Cancel** so unpaid offline orders do not sit open forever; 48 or 72
hours suits most marketplaces.

---

## Test Gateway

A pass-through gateway for development. It only appears when `WP_DEBUG` is
`true`, so it cannot be left on by accident in production.

---

## Razorpay **[PRO]**

Cards, UPI, netbanking and wallets, primarily for India.

| Field | Type | Default |
|---|---|---|
| Enable Razorpay | Checkbox | Off |
| Test Mode | Checkbox | Off |
| Test Key ID / Key Secret | Text / Password | -- |
| Live Key ID / Key Secret | Text / Password | -- |
| Webhook Secret | Password | -- |
| Theme Color | Text (hex) | `#3399cc` |
| Pass Gateway Fees to Buyer | Checkbox | Off |
| Gateway Fee (%) | Number, 0-10 | `2.0` |
| Gateway Fee (Fixed) | Number, 0-50 | `0` |

---

## Stripe Connect **[PRO]**

Splits each payment at charge time: the platform's cut stays, the rest transfers
straight to the vendor's own Stripe account.

| Field | Type | Default | Notes |
|---|---|---|---|
| Enable Stripe Connect | Checkbox | Off | |
| Platform Fee (%) | Number, 0-100, step 0.1 | `20.0` | Leave blank to fall back to the general commission rate |

The card also lists vendors' connected accounts and their onboarding status.

> **Connect pays the vendor at charge, which bypasses the clearance window
> entirely.** If you rely on a hold period as your refund buffer, understand
> that Connect does not honour it. See
> [Stripe Connect](../payments-checkout/stripe-connect.md).

Unlike the core gateway cards, Connect saves over AJAX -- the button on the card
is the one to press.

---

## What is *not* on this tab

- **Commission and tax** -- [Commission & Tax Settings](commission-tax-settings.md)
- **Withdrawals, clearance and payouts** -- [Payouts Settings](payouts-settings.md)
- **Which e-commerce platform runs checkout** -- [General Settings](general-settings.md)
- **PayPal *Payouts*** (paying vendors) -- that is on the Payouts tab, and is a
  different PayPal integration from the one above

---

## Related documentation

- [Stripe Payments](../payments-checkout/stripe-payments.md)
- [Other Gateways](../payments-checkout/other-gateways.md)
- [Standalone Mode](../payments-checkout/standalone-mode.md)
- [WooCommerce Checkout](../payments-checkout/woocommerce-checkout.md) **[PRO]**
- [Money Flow](https://github.com/vapvarun/wp-sell-services/blob/main/docs/architecture/MONEY-FLOW.md)
