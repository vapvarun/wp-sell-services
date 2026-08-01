# Commission & Tax Settings

**Sell Services > Settings > Commission & Tax**
Direct link: `wp-admin/admin.php?page=wpss-settings#commission`

Two things live here: **what the platform keeps** from every sale, and **whether
tax is added** at the standalone checkout. They are separate cards with separate
Save buttons.

> Settings are hash-routed. `#commission` scrolls to this tab; there is no
> `?tab=` parameter.

---

## Commission Settings

| Field | Type | Default | What it does |
|---|---|---|---|
| Commission Rate (%) | Number, 0-50, step 0.1 | `10` | The platform's cut of each order |
| Per-Vendor Rates | Checkbox | **On** | Allows a per-vendor rate to override the default |
| Tip Commission Rate (%) | Number, 0-50, step 0.1 | *empty* | The cut taken from tips |

### Commission Rate

The percentage the platform keeps. On a 20% rate and a $100 order, the platform
keeps $20 and the vendor earns $80.

Commission is calculated on the **subtotal plus add-ons, before tax**, and is
recorded on the order when it is created -- so a rate change never
retroactively alters an existing order. Change the rate whenever you like; only
new orders see it.

### Per-Vendor Rates

Leave this on unless you have a reason not to. With it enabled you can set a
different rate on an individual vendor's profile (**Sell Services > Vendors**),
which is how most marketplaces reward high performers or run an introductory
deal. Turn it off and every vendor pays the global rate, and per-vendor values
are ignored.

Pro adds a third layer above both -- see *Commission Rules* below.

### Tip Commission Rate

Tips are optional extra payments from a buyer to a vendor after good work, and
you get to decide whether the platform takes a cut of them.

- **Leave it empty** -- tips use the main commission rate. A vendor nets the
  same proportion as on any order.
- **Set it to `0`** -- vendors keep 100% of every tip.
- **Set a number** -- that rate applies to tips only.

`0` is the common choice: a tip is a gesture between two people, and taking a
cut of it tends to read badly to both.

> **Tips credit the vendor immediately.** The commission is taken at the moment
> the buyer pays the tip, not when anything completes. See
> [Money Flow](https://github.com/vapvarun/wp-sell-services/blob/main/docs/architecture/MONEY-FLOW.md).

---

## Commission Rules **[PRO]**

Pro adds a rules engine above the flat rate. Define rules that match on
**service category**, **seller level** or **sales volume**, each with its own
rate and priority.

Rules are evaluated in **priority order and the first match wins** -- they do
not stack. Order them so the most specific rule sits highest, or a broad rule
will shadow everything under it.

The rules table saves over AJAX, not with the tab's Save button. Use its own
Add / Save controls.

See [Tiered Commission](../earnings-wallet/tiered-commission.md).

---

## Tax Settings

| Field | Type | Default | What it does |
|---|---|---|---|
| Enable Tax | Checkbox | Off | Adds tax to standalone orders |
| Tax Label | Text | `Tax` | What buyers see -- VAT, GST, Sales Tax |
| Tax Rate (%) | Number, 0-50, step 0.01 | `0` | Applied to all services |
| Prices Include Tax | Checkbox | Off | Whether listed prices already contain tax |

### These settings apply to the standalone checkout only

If WooCommerce, EDD, FluentCart or SureCart runs your checkout, **that platform
calculates tax** using its own tax configuration, and everything in this card is
ignored. On WooCommerce, configure tax under **WooCommerce > Settings > Tax**.

### Prices Include Tax

- **Off (default)** -- listed prices are pre-tax and tax is added at checkout.
  A $100 service at 20% shows a $120 total.
- **On** -- listed prices already contain tax, and the checkout shows how much
  of the price is tax. A $100 service at 20% stays $100, of which $16.67 is tax.

Inclusive pricing is the norm in the UK and EU; exclusive is the norm in the US.

### One rate, all services

The plugin applies a single rate to everything. There is no per-category rate,
no per-country rate, and no VAT MOSS / digital-services handling. If you owe
different rates in different jurisdictions, run checkout on WooCommerce and use
its tax tables, or a dedicated tax plugin.

---

## Related documentation

- [Commission System](../earnings-wallet/commission-system.md)
- [Tiered Commission](../earnings-wallet/tiered-commission.md) **[PRO]**
- [Currency and Tax Configuration](../payments-checkout/currency-tax-config.md)
- [Payouts Settings](payouts-settings.md)
- [Payment Gateways Settings](payment-gateways-settings.md)
