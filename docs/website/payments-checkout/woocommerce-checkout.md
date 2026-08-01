# WooCommerce Checkout Integration **[PRO]**

If you already use WooCommerce, WP Sell Services Pro can plug right into it -- giving your marketplace access to WooCommerce's payment gateways, checkout, and order system.

## Why Use WooCommerce Mode?

| Benefit | Details |
|---------|---------|
| 100+ payment gateways | Use any WooCommerce payment extension |
| Familiar admin experience | Manage service orders alongside product orders |
| Extension ecosystem | Compatible with WooCommerce Subscriptions, Bookings, and more |
| HPOS compatible | Works with WooCommerce's High-Performance Order Storage |
| Zero product catalog bloat | One hidden carrier product handles all services |

---

## How It Works: The Virtual Carrier Approach

This is one of the smartest design decisions in the plugin. Instead of creating one WooCommerce product for every service listing (which would flood your product catalog with thousands of items), the plugin uses a **single virtual carrier product** that acts as a bridge between your services and WooCommerce's cart/checkout system.

### What the Carrier Product Is

When you activate WooCommerce mode, the plugin creates one hidden WooCommerce product called **"Service Order"**. This product:

- Is a **Simple, Virtual** product (no shipping, no inventory)
- Has a base price of **$0** (the actual price is set dynamically per cart item)
- Is **hidden from the shop catalog** -- buyers never see it on your WooCommerce shop page
- Is **not searchable** -- it will not appear in WooCommerce product searches
- Redirects to the homepage if someone accesses its URL directly

Every service purchase on your marketplace flows through this single carrier product. Whether you have 10 services or 10,000, there is still only one WooCommerce product.

### Why This Matters

| Approach | Products in WC | Catalog Clutter | Sync Required | Performance |
|----------|---------------|-----------------|---------------|-------------|
| **1 product per service** (how others do it) | 10,000 | Massive -- services mixed with real products | Constant -- every title/price change must sync | Slow -- WC product queries include service products |
| **Virtual carrier** (how WPSS does it) | 1 | Zero -- carrier is hidden | None -- service data stays in WPSS | Fast -- WC only manages the carrier |

This means:

- **Your WooCommerce product catalog stays clean.** If you sell physical products alongside services, buyers browsing your shop only see real products.
- **No sync headaches.** When a vendor updates their service title, price, or description, nothing needs to change in WooCommerce. The service data is read directly from WPSS at cart/checkout time.
- **Better performance.** WooCommerce product queries, exports, and admin listings are not bloated with thousands of service products.

### How Service Data Gets to WooCommerce

When a buyer clicks "Add to Cart" on a service, the plugin does not add the carrier product as-is. Instead, it attaches the service details as **cart item metadata**:

```
Cart Item
├── Product: "Service Order" (carrier)
├── Meta: wpss_service_id = 1234
├── Meta: wpss_package_id = 1 (Standard)
├── Meta: wpss_addons = [0, 2] (Extra Fast Delivery, Source Files)
└── Price: $180 (calculated from package $150 + addon $20 + addon $10)
```

WooCommerce's cart and checkout pages then display:

- **Product name:** The actual service title (e.g., "Professional Logo Design") -- not "Service Order"
- **Product image:** The service's featured image
- **Package info:** The selected tier (e.g., "Standard")
- **Price:** The package price plus any selected add-ons, calculated dynamically

The buyer sees a completely normal WooCommerce cart. They have no idea a carrier product is involved.

---

## Setting It Up

1. Install and activate WooCommerce (if you have not already)
2. Complete the WooCommerce setup wizard
3. Go to **Sell Services > Settings > General**
4. The plugin auto-detects WooCommerce -- no manual selection needed
5. The hidden carrier product is created automatically

That is it. Services are now purchasable through WooCommerce checkout.

**Important:** Do not delete the "Service Order" product from your Products list. If it disappears, just save your WP Sell Services settings again to recreate it.

---

## Cart and Checkout Experience

When buyers add services to their cart:

- The cart shows the **service title**, **package name** (Basic, Standard, or Premium), **vendor name**, and **selected add-ons**
- Pricing is calculated dynamically from the service data -- not stored on the WooCommerce product
- Buyers can purchase services from **multiple vendors** in a single checkout
- Tax is calculated based on your WooCommerce tax settings
- All WooCommerce payment gateways work as expected

After payment, the plugin splits the WooCommerce order into separate marketplace orders.

---

## Multi-Vendor Order Splitting

This is the second key piece of the architecture. When a buyer purchases services from multiple vendors in a single WooCommerce checkout:

**1 WooCommerce order → N marketplace orders (one per service/vendor)**

For example, if a buyer purchases a logo design from Vendor A and a website audit from Vendor B in one checkout:

```
WooCommerce Order #1042 ($450)
├── WPSS Order #WPSS-1042 → Vendor A (Logo Design, $200)
│   ├── Commission: $20 (10%)
│   ├── Vendor earnings: $180
│   ├── Delivery deadline: 5 days
│   └── Own conversation, requirements, delivery tracking
│
└── WPSS Order #WPSS-1043 → Vendor B (Website Audit, $250)
    ├── Commission: $25 (10%)
    ├── Vendor earnings: $225
    ├── Delivery deadline: 7 days
    └── Own conversation, requirements, delivery tracking
```

Each marketplace order is completely independent:

- **Separate vendor assignment** -- each vendor only sees their own order
- **Separate delivery deadline** -- based on the package the buyer selected
- **Independent requirements gathering** -- each vendor asks for their own project details
- **Private conversation** -- buyer and vendor communicate without the other vendor seeing
- **Individual disputes** -- a problem with one order does not affect the other
- **Independent commission** -- per-vendor rates are supported

### Commission Calculation

Commission is calculated at order creation time and recorded immediately:

- The platform's global commission rate is applied (e.g., 10%)
- If the vendor has a **custom commission rate** (set in vendor management), that overrides the global rate
- Commission is calculated on the **subtotal + add-ons** (pre-tax)
- The vendor's earnings are: total - platform fee

---

## Paying a milestone, tip or extension

Everything above describes buying a service: the buyer fills a cart and checks
out. But a marketplace also has to charge for things that appear *after* the
first payment -- a milestone phase, a tip, a paid extension, an accepted
proposal. There is no cart for those. The buyer already has an order; they need
to pay one specific amount against it.

This is the **pay-order** path, and it is the part of WooCommerce mode most
worth understanding, because it is the only place a service marketplace needs
something WooCommerce does not natively have.

### The seam

```
wpss_get_pay_order_url( $wpss_order_id )      free — src/functions.php:3375
        │
        │  default: <checkout page>?pay_order=N     (standalone understands this)
        ▼
apply_filters( 'wpss_pay_order_url', $url, $order_id )
        │
        ▼
WCPayOrderResolver::filter_pay_order_url()     Pro — Integrations/WooCommerce/
        │
        ├─ reuse the linked WC order if it still needs payment
        └─ else create a NEW pending WC order for the amount owed
                │
                ▼
        $wc_order->get_checkout_payment_url()
                │
                ▼
        /checkout/order-pay/{wc_id}/?pay_for_order=true&key=wc_order_…
```

Every surface that offers a "pay this" link -- the order page, the milestone
list, the extension quote, the REST `checkout_url` field, and the emails --
calls `wpss_get_pay_order_url()`. None of them build the URL themselves. That
is deliberate: a link built inline is correct only on standalone.

### Why WooCommerce needs its own resolver

WooCommerce is cart-based. It has no notion of "pay this existing thing," so the
default `?pay_order=N` URL appended to a WooCommerce checkout drops the buyer on
an **empty cart, with no way to pay and no error message**. That was the actual
behaviour of every tip, milestone and extension link in WooCommerce mode before
1.4.0.

The resolver fixes it by meeting WooCommerce on its own terms: it creates a real
WooCommerce order for the amount owed and hands back WooCommerce's native
*order-pay* URL. That URL carries an order key, so it works from an email link
with no cart and no session, offers every gateway the store has enabled, and
produces one proper WooCommerce receipt per payment.

### What the resolver actually does

1. Bails out (returning the default URL) if WooCommerce is not loaded, the WPSS
   order does not exist, or it is already `paid`.
2. Looks for a WooCommerce order already linked to this WPSS row. Reuses it
   **only while it still needs payment**; if it was paid, cancelled or deleted,
   it makes a fresh one.
3. Creates a `pending` WooCommerce order for the buyer with a **single fee line
   item** -- not a product. The label is human-readable: `Milestone: <title>`,
   `Tip for your seller`, `Additional work on your order`, or `Order <number>`.
4. Writes both link keys, then returns `get_checkout_payment_url()`.

Two consequences worth planning around:

- **Asking for the URL creates the order.** Rendering an order page with three
  unpaid phases can mint three pending WooCommerce orders. This is idempotent --
  they are reused, not duplicated -- but a site owner will see pending orders in
  WooCommerce that nobody has paid yet. That is expected, not a bug.
- **The lock-step guard does not apply here.** The resolver checks only that the
  row is unpaid. See
  [Milestone Contracts](../order-management/milestones-wpss.md#the-lock-step-rule-and-where-it-actually-holds).

### The two link keys

The link is stored on both sides, because neither side can find the other
without it.

| Key | Lives on | Contains | Why |
|---|---|---|---|
| `wc_pay_order_id` | The WPSS order -- a key inside the JSON `meta` column of `{prefix}wpss_orders`. **Not a column, not post meta.** | The WooCommerce order ID | Lets the resolver reuse an existing pay order instead of minting a new one on every render |
| `_wpss_pay_order_id` | WooCommerce order meta (HPOS-safe) | The WPSS order ID, as a string | Lets the payment-complete handler find the WPSS row when the WooCommerce order is paid |

The reverse key is not optional. A sub-order (tip, milestone, extension) stores
its **parent** order's id in `platform_order_id`, never a WooCommerce order id --
so the normal `platform_order_id = wc_id` lookup can never find one. Without
`_wpss_pay_order_id`, a paid tip would settle into nothing.

### What happens when the buyer pays

There is no custom webhook. WooCommerce's own status actions
(`woocommerce_order_status_processing` and `…_completed`) resolve the linked
WPSS row through `_wpss_pay_order_id` and call the shared `mark_as_paid()`, which
fires `wpss_order_paid`. That is what credits the vendor and moves the phase to
**In progress**.

Sub-orders are deliberately *not* pushed into `pending_requirements` when paid --
requirements belong to the parent order, not to each phase.

**Refunds (1.4.0):** refunding the WooCommerce order now reverses the tip,
milestone or extension too. Previously only the *paid* handler knew how to
resolve a sub-order, so a refunded or cancelled tip was silently ignored while
the buyer got their money back. The refunded amount is apportioned across every
linked WPSS order and written before the status change, then the vendor's share
is clawed back through the single refund formula.

### Platform support -- read this before promising it

The pay-order rail is **WooCommerce-only**. It is not a general capability of
"Pro e-commerce integrations."

| Platform | Pay-order rail | What a milestone / tip / extension pay link does |
|---|---|---|
| **Standalone** | Yes (native) | Opens the plugin's own checkout for that one order. Lock-step guard enforced here. |
| **WooCommerce** | Yes -- `WCPayOrderResolver` **[PRO]** | Opens a WooCommerce order-pay page. Works from email. |
| **EDD** | **No** | Falls back to `?pay_order=N` on the EDD checkout, which EDD does not understand. **Dead end.** |
| **FluentCart** | **No** | Same. **Dead end.** |
| **SureCart** | **No** | Same. **Dead end.** |

Exactly one implementation of the `wpss_pay_order_url` filter exists in the
whole codebase, and it is the WooCommerce one. EDD, FluentCart and SureCart
register no equivalent.

**Practical consequence:** if your marketplace relies on milestone contracts,
tipping, or paid extensions, run it on **WooCommerce or standalone**. On EDD,
FluentCart or SureCart the initial service purchase works, but every follow-on
payment link is a dead end for the buyer. This is a known gap, not a
configuration mistake -- there is nothing to switch on.

---

## Order Status Sync

Orders stay connected between WooCommerce and your marketplace with **bidirectional sync**:

### WooCommerce → Marketplace

| WC Status | WPSS Status | What Happens |
|-----------|-------------|-------------|
| Processing | Pending Payment | Awaiting payment confirmation |
| Completed | Pending Requirements | Payment confirmed, buyer needs to submit details |
| Cancelled | Cancelled | Order stopped, refund processed |
| Failed | Cancelled | Payment failed |
| Refunded | Cancelled | Full refund processed |
| On Hold | On Hold | Order paused pending investigation |

### Marketplace → WooCommerce

When **all** marketplace sub-orders from a single WooCommerce order are cancelled or refunded, the WooCommerce order is automatically cancelled too. This prevents a scenario where the WC order shows "completed" but all linked service orders are cancelled.

The sync includes infinite-loop prevention -- a status change triggered by the sync does not re-trigger the sync in the opposite direction.

---

## What Buyers and Vendors See

With WooCommerce active a buyer can reach something order-shaped in three
places, which looks like duplication until you know what each one is for. They
are not three copies of the same list — they answer different questions.

| Screen | Question it answers | Use it to |
|---|---|---|
| **WooCommerce → My Account → Orders** | *What did I pay, and when?* | Receipts, invoices, payment history |
| **My Account → Service Orders** | *What is happening with my service?* | A bridge — see status, jump into the job |
| **Dashboard → My Orders** | *Manage the job* | Requirements, messages, delivery, revisions, approval |

**The rule:** WooCommerce owns the money record. The Dashboard owns the work.
Service Orders is the doorway between them.

Anything that progresses the job — submitting requirements, replying to the
seller, approving a delivery, paying a milestone phase — happens in the
**Dashboard**, never on the WooCommerce orders screen. A phase or tip will not
appear there as something to act on.

**Vendors** see their dashboard with:

- Incoming orders and delivery management
- Earnings overview with commission breakdown
- Withdrawal requests
- Service listings and messaging

Vendors never interact with WooCommerce directly. Their entire experience
happens through the marketplace dashboard.

**Sellers: WooCommerce → My Account → Orders will look empty to you, and that is
correct.** That screen lists orders *you placed as a customer*. Your sales are
in **Dashboard → Sales Orders**. Seeing "No orders" there is not a sign anything
is broken.

---

## Existing WooCommerce Store Compatibility

If you already sell physical products on WooCommerce, services integrate seamlessly:

- **Mixed carts work.** A buyer can have both a physical product and a service in the same cart. WooCommerce handles the physical product normally, and the plugin handles the service order.
- **Shipping is not affected.** The carrier product is virtual, so services never trigger shipping calculations.
- **Tax settings carry over.** Your existing WooCommerce tax configuration applies to service purchases.
- **Payment gateways work as-is.** No additional gateway configuration needed -- services use whatever gateways you already have enabled.

---

## When to Choose WooCommerce vs Standalone

| Choose WooCommerce if you... | Choose Standalone if you... |
|----|-----|
| Already run a WooCommerce store | Want a lightweight setup with no extra plugins |
| Need a specific WooCommerce payment gateway | Only need Stripe, PayPal, or bank transfer |
| Want to sell physical products alongside services | Run a pure service marketplace |
| Use WooCommerce extensions (Subscriptions, etc.) | Want the fastest possible checkout |
| Need WooCommerce reporting and analytics | Prefer a simpler admin experience |

---

## Technical Reference (Developers)

For developers building custom integrations or debugging the WC integration:

### Key Classes

| Class | Purpose |
|-------|---------|
| `WCServiceCarrier` | Creates and manages the virtual carrier product |
| `WCProductProvider` | Implements `ProductProviderInterface` for WC |
| `WCCheckoutProvider` | Handles cart item data, dynamic pricing, checkout processing |
| `WCOrderProvider` | Splits WC orders into WPSS orders, handles status sync, marks sub-orders paid, apportions refunds |
| `WCPayOrderResolver` | **The pay-order rail.** Hooks `wpss_pay_order_url`; creates or reuses a WC order so a milestone, tip, extension or accepted proposal can be paid individually |
| `WCOrderBridge` | Cross-links the two systems for humans: "WooCommerce Order #N" on the WPSS order, a "what happens next" panel on the WC thank-you page, and links between the WC and WPSS order screens (front-end and admin). Also owns the forward/reverse lookup helpers |
| `WooCommerceAdapter` | Main adapter class, registers all WC hooks |

### Cart Item Meta Keys

| Key | Purpose |
|-----|---------|
| `_wpss_service_id` | Links cart/order item to the service CPT |
| `_wpss_package_id` | Index of the selected package (0, 1, or 2) |
| `_wpss_addons` | Array of selected add-on indices |

### Pay-order link keys

| Key | Stored on | Purpose |
|-----|-----------|---------|
| `wc_pay_order_id` | Key inside the JSON `meta` column of `{prefix}wpss_orders` | The WC order minted to pay this WPSS order |
| `_wpss_pay_order_id` | WooCommerce order meta | The WPSS order this WC order pays |

See [Paying a milestone, tip or extension](#paying-a-milestone-tip-or-extension).

### WooCommerce Hooks Used

The adapter hooks into these WooCommerce hooks:

- `woocommerce_add_cart_item_data` -- Attaches service metadata to cart item
- `woocommerce_get_item_data` -- Displays package name in cart
- `woocommerce_cart_item_name` -- Replaces carrier title with service title
- `woocommerce_cart_item_thumbnail` -- Replaces carrier image with service image
- `woocommerce_before_calculate_totals` -- Sets dynamic price from package + addons
- `woocommerce_checkout_create_order_line_item` -- Persists meta to order item
- `woocommerce_checkout_order_processed` -- Triggers WPSS order creation
- `woocommerce_order_status_{status}` -- Drives WC → WPSS status sync

### Carrier Product Option

The carrier product ID is stored as `wpss_wc_carrier_product_id` in `wp_options`. If this option is missing or the product is deleted, it is recreated automatically when settings are saved.

See [Building Custom Integrations](../developer-guide/custom-integrations.md) for the full adapter interface documentation.

---

## Related Docs

- [Standalone Mode](standalone-mode.md) -- Built-in checkout without WooCommerce
- [Alternative Platforms](alternative-platforms.md) **[PRO]** -- EDD, FluentCart, SureCart
- [Currency and Tax](currency-tax-config.md) -- Financial settings
- [Building Custom Integrations](../developer-guide/custom-integrations.md) -- Adapter interfaces and developer guide
