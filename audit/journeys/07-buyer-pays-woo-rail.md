# Journey 07 - Buyer pays on the WooCommerce rail

**Roles:** guest, buyer, vendor on a plan, admin
**Rail:** woo
**Status:** rewritten 2026-10-18; walk pending (see .qa-results/walks.json)

## Steps

### 1. A guest's Add to cart is not lost (card:10407576066)
Logged out on the Woo rail with account-at-checkout off: open a service, choose a package, Add to cart, log in.

**Expect** you land back on the service page, choose again and the order proceeds; never an empty checkout. On the standalone rail the checkout opens with the selection intact.

### 2. Site cart link only where it means something (card:10407576283)
Settings > Checkout on the Woo rail, then on the standalone rail with WooCommerce installed.

**Expect** no Site cart link box on the Woo rail; on standalone, ticking it points the theme's cart icon at the marketplace cart, unticking it falls back to the automatic choice.

### 3. The carrier product sends visitors home (card:10407726798)
Logged out, open /product/service-order/ (the hidden carrier). Open /wp-sitemap-posts-product-1.xml.

**Expect** a redirect to the home page, not a 404; the sitemap does not list the carrier; the shop still does not show it.

### 4. A capped vendor is told where to upgrade (card:10408190581)
Vendor on a plan with max services reached, creating a service.

**Expect** the message links to the dashboard Subscription page and the link opens it.

### 5. Plan "Max featured" does something (card:10408191922)
Plan with Max featured 1: vendor edits own service, ticks Featured on one, then tries a second.

**Expect** the first saves, the second box is not offered (or is refused) at the limit, an already featured service can be unticked; a plan with 0 never shows the box. Subscribing the same vendor twice (admin, webhook) prints no database error.

### 6. Requirements panel reads on a dark Woo theme (card:10407649116)
Order-received and My Account > View order on a dark theme and on a light one, 1440 and 390.

**Expect** panel text, order number and the Submit requirements button are readable (4.5:1) in both; the panel takes the theme's colours and does not paint a white card.

### Woo cart lines show the package price (found in the 1.9.0 browser walk)
As a signed-in buyer on the Woo rail, add a service, then reload any page.

**Expect** the header mini cart reads "1 x $85.00" (the package price), never "1 x $0.00" beside a correct subtotal. check: wp-sell-services-pro/tests/test-woo-cart-session-price.php
