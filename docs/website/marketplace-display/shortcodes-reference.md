# Shortcodes Reference

WP Sell Services registers **19 shortcodes**, and WP Sell Services Pro adds one
more. Together they build every part of your marketplace -- catalog, vendor
directory, dashboard, buyer requests board, checkout -- with no code.

Paste a shortcode into any page or widget, publish, and it works. Every one is
also available as a block; see [Block Editor Elements](gutenberg-blocks.md).

The plugin auto-creates the most important pages during setup, so you may
already have most of these in place. See [Pages Setup](../platform-settings/pages-setup.md).

## Quick reference

| Shortcode | What it renders |
|-----------|-----------------|
| `[wpss_services]` | Services catalog grid |
| `[wpss_featured_services]` | Featured services only |
| `[wpss_service_search]` | Search form with category dropdown |
| `[wpss_service_categories]` | Category grid |
| `[wpss_vendors]` | Vendor directory grid |
| `[wpss_top_vendors]` | Highest-rated vendors |
| `[wpss_vendor_profile]` | One vendor's full profile |
| `[wpss_seller_card]` | One seller's card (avatar, rating, stats) |
| `[wpss_buyer_requests]` | Open buyer requests board |
| `[wpss_post_request]` | Form to submit a buyer request |
| `[wpss_dashboard]` | Unified buyer/vendor dashboard |
| `[wpss_my_orders]` | The user's order list |
| `[wpss_order_details]` | One order's detail view |
| `[wpss_service_wizard]` | Service creation wizard |
| `[wpss_login]` | Login form |
| `[wpss_register]` | Registration form |
| `[wpss_vendor_registration]` | Become-a-vendor form |
| `[wpss_cart]` | Shopping cart |
| `[wpss_checkout]` | Standalone checkout |
| `[wpss_account]` | Standalone account page |
| `[wpss_currency_switcher]` **[PRO]** | Shopper currency picker |

## Marketplace pages

### `[wpss_services]` -- Services catalog

A grid of published services with thumbnails, prices, ratings, and vendor info.
The main browsing page of your marketplace.

| Attribute | Default | Notes |
|-----------|---------|-------|
| `category` | *(empty)* | Limit to a category |
| `tag` | *(empty)* | Limit to a tag |
| `vendor` | *(empty)* | Limit to one vendor |
| `limit` | `12` | Services shown |
| `columns` | `4` | Grid columns |
| `orderby` | `date` | `date`, `title`, `price`, `rating`, `sales` |
| `order` | `DESC` | `ASC` or `DESC` |
| `featured` | *(empty)* | `true` to show featured only |

```
[wpss_services category="design" limit="8" columns="4" orderby="rating"]
```

### `[wpss_featured_services]` -- Featured services

Identical to `[wpss_services]` with `featured` forced on, so it accepts **all the
same attributes**. Use it for homepage spotlights and Editor's Picks.

```
[wpss_featured_services limit="4" columns="4"]
```

### `[wpss_service_search]` -- Search bar

A search form with a keyword field and category dropdown. Put it on your homepage
or above your services grid.

| Attribute | Default |
|-----------|---------|
| `placeholder` | `Search services...` |
| `show_categories` | `true` |
| `button_text` | `Search` |
| `action` | the service archive URL |

```
[wpss_service_search placeholder="What do you need?" button_text="Find a pro"]
```


### `[wpss_seller_card]` -- One seller's card

Renders a single seller card: avatar, name, rating, stats and a link through to
their profile. It wraps the `wpss/seller-card` block rather than re-rendering the
markup, so the block and the shortcode cannot drift apart.

With no attributes it uses the vendor whose profile is being viewed, which makes
it drop-in inside a vendor template. Pass `user_id` to pin it to someone
specific.

```
[wpss_seller_card]
[wpss_seller_card user_id="12" layout="horizontal" show_bio="false"]
```

| Attribute | Default | What it does |
|-----------|---------|--------------|
| `user_id` | `0` | Whose card to show. `0` uses the vendor being viewed. |
| `layout` | `vertical` | `vertical` or `horizontal`. |
| `show_bio` | `true` | Show the seller's short bio. |
| `show_stats` | `true` | Show order and completion stats. |
| `show_rating` | `true` | Show the star rating. |
| `show_services` | `true` | Show a count of their live services. |
| `show_button` | `true` | Show the button through to the full profile. |

### `[wpss_service_categories]` -- Category grid

Your categories as a visual grid with counts.

| Attribute | Default | Notes |
|-----------|---------|-------|
| `parent` | `0` | Parent term id; `0` for top level |
| `show_count` | `true` | Show the service count |
| `columns` | `4` | Grid columns |
| `hide_empty` | `true` | Hide categories with no services |
| `limit` | `12` | Categories shown |

### `[wpss_buyer_requests]` -- Buyer requests board

Open buyer requests so vendors can browse projects and submit proposals. Also
works as a compact sidebar listing -- just lower the `limit`.

| Attribute | Default |
|-----------|---------|
| `limit` | `10` |
| `category` | *(empty)* |
| `budget_min` | *(empty)* |
| `budget_max` | *(empty)* |

```
[wpss_buyer_requests limit="5" budget_min="500"]
```

### `[wpss_post_request]` -- Post a request form

The form buyers use to submit a new request. Requires the user to be logged in.
No attributes.

## Vendor elements

### `[wpss_vendors]` -- Vendor directory

A grid of vendor profiles with names, avatars, ratings, and review counts.

| Attribute | Default | Notes |
|-----------|---------|-------|
| `limit` | `12` | Vendors shown |
| `columns` | `4` | Grid columns |
| `orderby` | `rating` | `rating`, `date`, `name`, `sales` |
| `order` | `DESC` | `ASC` or `DESC` |

### `[wpss_top_vendors]` -- Top vendors

`[wpss_vendors]` with `orderby="rating"` and `order="DESC"` forced. Accepts
`limit` and `columns`.

```
[wpss_top_vendors limit="6" columns="3"]
```

### `[wpss_vendor_profile]` -- Vendor profile

One vendor's full profile page.

| Attribute | Default |
|-----------|---------|
| `id` | the `vendor_id` query var |

On a dedicated profile page the id comes from the URL, so you can usually omit
it. If no id is found, the shortcode renders "Vendor not found."

## User pages

### `[wpss_dashboard]` -- Unified dashboard

The single most important element. One page that adapts to the visitor's role:

- **Buyers** see orders, requests, messages, favorites, and profile settings
- **Vendors** see services, sales orders, earnings, analytics, messages, and portfolio
- **Dual-role users** see both

One page, one shortcode, serves everyone. No attributes.

### `[wpss_my_orders]` -- Order list

| Attribute | Default | Notes |
|-----------|---------|-------|
| `type` | `customer` | `customer` (bought) or `vendor` (sold) |
| `status` | *(empty)* | Filter to one status |
| `limit` | `20` | Orders per page |

```
[wpss_my_orders type="vendor" status="in_progress"]
```

### `[wpss_order_details]` -- Order detail

The full details of one order. The order id comes from the URL. Only the buyer,
the vendor, or an admin on that order can view it. No attributes.

### `[wpss_service_wizard]` -- Service creation wizard

The multi-step form vendors use to create a service.

| Attribute | Default | Notes |
|-----------|---------|-------|
| `id` | `0` | Pass a service id to **edit** instead of create |

## Accounts and checkout

### `[wpss_login]` -- Login form

| Attribute | Default |
|-----------|---------|
| `redirect` | *(empty)* |

Shows an "already logged in" message to authenticated users.

### `[wpss_register]` -- Registration form

Username, email, and password. **Requires WordPress registration to be enabled**
in Settings > General ("Anyone can register") -- otherwise the form cannot create
accounts. No attributes.

### `[wpss_vendor_registration]` -- Become a vendor

The vendor-specific registration form. Different from `[wpss_register]` -- use
this on your "Become a Vendor" page. What it does depends on your registration
mode (open, requires approval, or closed); see
[Vendor Settings](../vendor-system/vendor-settings.md). No attributes.

### `[wpss_cart]` -- Shopping cart

Selected services, packages, and add-ons with a total, before checkout. Buyers
can remove items or continue. No attributes.

### `[wpss_checkout]` -- Standalone checkout

Billing details, order review, payment method, and Place Order. This is the
checkout for **standalone mode** -- not used when WooCommerce or another
e-commerce platform handles checkout. No attributes.

### `[wpss_account]` -- My account

Account management for standalone mode: profile, saved addresses, settings.
Separate from the vendor dashboard. No attributes.

## Pro

### `[wpss_currency_switcher]` **[PRO]**

A currency picker for shoppers. The selection is a **display-only** hint -- every
order and payout stays in your base currency. See
[Display Currency](../payments-checkout/display-currency.md). No attributes.

## Where to use what

| Goal | Recommended elements |
|------|---------------------|
| Main browsing page | `[wpss_services]` + `[wpss_service_search]` |
| Homepage | `[wpss_featured_services]` + `[wpss_service_categories]` + `[wpss_top_vendors]` |
| User account area | `[wpss_dashboard]` |
| Vendor recruitment page | `[wpss_vendor_registration]` |
| Buyer request marketplace | `[wpss_buyer_requests]` + `[wpss_post_request]` |
| Vendor directory page | `[wpss_vendors]` |
| Sidebar | `[wpss_service_search]`, `[wpss_top_vendors]`, `[wpss_buyer_requests limit="5"]` |

## Tips

- **Start with the auto-created pages.** Setup creates the essentials; customise from there.
- **Combine elements** on one page -- search bar, then categories, then the grid.
- **All of these work in widgets**, so sidebars and footers are fair game.
- **Prefer blocks if you use the block editor** -- same features, visual controls. See [Block Editor Elements](gutenberg-blocks.md).

## Related

- [Block Editor Elements](gutenberg-blocks.md)
- [Pages Setup & Auto-Creation](../platform-settings/pages-setup.md)
- [Template Overrides](template-overrides.md)
