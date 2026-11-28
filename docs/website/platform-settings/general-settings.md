# General Settings

Configure the basics of your marketplace -- your platform name, currency, and which e-commerce system powers your checkout.

---

## Platform Name

Give your marketplace a custom name that appears throughout the platform: in emails, page headers, notifications, and payment receipts.

1. Go to **Sell Services > Settings > General**
2. Enter your marketplace name in the **Platform Name** field
3. Click **Save Changes**

**Default:** Your WordPress site name is used if no custom name is set.

**Examples:** "Creative Hub Marketplace", "Expert Services Network", "DesignPro Market"

![General settings tab](../images/settings-general-tab.webp)

![Full general settings](../images/settings-general-tab.webp)

---

## Currency

Choose the currency for all transactions on your marketplace. This affects how prices are displayed on services, how orders are totaled, and how vendor earnings are calculated.

### Supported Currencies

The marketplace supports 148 currencies. See the [full list with codes, symbols and decimal places](../payments-checkout/currency-reference.md); it is generated from the plugin, so it is always current.

### Setting Your Currency

1. Go to **Settings > General**
2. Select your currency from the dropdown
3. Click **Save Changes**

**Default:** USD (US Dollar)

Your entire marketplace operates in one currency. All service prices, order totals, and vendor earnings use the same currency. Payment gateways handle any conversion on their end if a buyer pays from a different region.

**The currency locks after your first order.** Once any order exists, the Currency field is greyed out and the setup wizard keeps it as well. Service prices, vendor balances and past withdrawals store a number without a currency of their own, so changing the currency would relabel them without converting them. Orders keep the currency they were placed in, so an older order always shows its own currency. Developers can allow a deliberate change with the `wpss_currency_locked` filter (return `false`).

## How prices look

Four settings on the same screen control how every price is written, on service pages, at checkout, in emails, on dashboards and in the app's REST data:

| Setting | Options | Default |
|---|---|---|
| **Currency Symbol Position** | Before ($99), After (99$), Before with a space ($ 99), After with a space (99 $) | Before |
| **Thousand Separator** | Any character, including a space | `,` |
| **Decimal Separator** | Any character except the thousand separator | `.` |
| **Price Decimal Places** | 0 to 4 | The currency's own (2 for USD, 0 for JPY, 3 for KWD) |

The decimal places apply to your store currency. An order in another currency shows that currency's own decimals, and a currency without minor units (JPY, KRW) always shows whole amounts, so a yen price never shows `.00`.

**On WooCommerce, Easy Digital Downloads or FluentCart [PRO],** the shop plugin owns the currency and all four of these settings. The fields show "Set by WooCommerce" (or EDD, or FluentCart) and are read-only; change them in the shop's own settings.

**[PRO]** Multi-currency support is available in the Pro version -- currency detected from the shopper's timezone, live exchange rates, and localized price displays.

---

## E-Commerce Platform

Choose which system handles your marketplace checkout and payments.

### Available Options

| Platform | Availability |
|----------|-------------|
| **Standalone** (built-in checkout) | Free -- no extra plugins needed |
| **WooCommerce** | **[PRO]** |
| **Easy Digital Downloads** | **[PRO]** beta, listed only when the `wpss_pro_beta_rails` filter returns true |
| **FluentCart** | **[PRO]** beta, listed only when the `wpss_pro_beta_rails` filter returns true |

### Auto-Detect (Recommended)

The default setting automatically detects which platform is available and uses it. For most sites, this is the best choice.

- If only the free plugin is active, it uses the built-in Standalone checkout
- If Pro is active and WooCommerce (or another supported platform) is installed, it uses that platform

### Standalone Mode (Free)

The free version includes a complete built-in checkout system with Stripe, PayPal, and Offline payment support. No WooCommerce or any other e-commerce plugin is required. This is the simplest setup -- perfect if you want a clean, lightweight marketplace.

### WooCommerce and Other Platforms **[PRO]**

The Pro version lets you plug into WooCommerce, Easy Digital Downloads or FluentCart. This is useful if you already have an online store and want your marketplace orders to flow through the same checkout and payment system.

### Switching Platforms

You can change platforms at any time under **Settings > General**. Keep in mind:

- Existing orders stay with the original platform
- New orders use the new platform
- Payment gateway settings may need reconfiguration
- Test the checkout flow on a staging site before switching on a live marketplace

---

## Site cart link

Only appears when WooCommerce is active and the marketplace takes payment itself. When WooCommerce handles checkout its cart holds the order, so there is nothing to choose.

With WooCommerce installed there are genuinely two carts: WooCommerce's `/cart/`
and the marketplace's `/service-cart/`. That split is deliberate -- the
marketplace does not take over WooCommerce's cart page. The question this
setting answers is which one your **theme's cart icon** points at.

**You usually do not need to touch it.** The default is worked out for you:

| Your setup | Where the theme's cart link goes |
|---|---|
| WooCommerce is handling checkout | WooCommerce's cart -- it holds the order |
| Marketplace checkout, and you sell no WooCommerce products | The marketplace cart |
| Marketplace checkout, and you *do* sell WooCommerce products | WooCommerce's cart -- two real shops, so you decide |

Tick it to always use the marketplace cart. Left unticked, the plugin decides as
in the table. The third row is the one worth knowing about: if you sell both
services and WooCommerce products, only you can say which cart the header should
serve, so the plugin leaves it alone until you tick the box.

If buyers report adding a service and then finding an empty cart, this is the
setting -- turn it on.

---

## Troubleshooting

**Platform name not updating everywhere?**
Clear all caches (site, theme, hosting, CDN) after saving. Some email templates may cache the old name.

**Currency symbol not displaying?**
Check that your database uses UTF-8 encoding and your theme supports special characters.

**E-commerce platform not detected?**
Make sure the platform plugin (e.g., WooCommerce) is installed and activated. Refresh the WP Sell Services settings page after activating it.
