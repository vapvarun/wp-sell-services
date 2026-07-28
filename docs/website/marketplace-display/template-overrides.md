# Template Overrides

Change how service cards, vendor profiles, dashboards, and order screens look --
through your theme, without touching plugin files.

The plugin ships **98 templates**: 47 frontend templates plus 51 email
templates. Any of them can be overridden, and most of them also expose hooks so
you can add markup without overriding at all.

> **Read this first: prefer a hook.** An override is a copy that stops receiving
> updates. Every fix, accessibility change, and new feature in that template is
> now yours to merge by hand. If a hook can do the job, use the hook -- the
> [full hook list](#template-hooks) is on this page.

## How overriding works

Copy a template from the plugin into a `wp-sell-services/` folder in your theme,
keeping the same filename and subfolder path. Your copy wins.

Lookup order:

1. Child theme's `wp-sell-services/`
2. Parent theme's `wp-sell-services/`
3. The plugin's `templates/` (fallback)

```
wp-content/plugins/wp-sell-services/templates/content-service-card.php
                    |
                    v
wp-content/themes/your-child-theme/wp-sell-services/content-service-card.php
```

Subfolders are preserved: `templates/order/order-view.php` becomes
`your-theme/wp-sell-services/order/order-view.php`.

### Steps

1. Create `wp-sell-services/` in your **child** theme.
2. Copy the whole template across, keeping its path.
3. Edit your copy.
4. Clear every cache (object, page, CDN, browser) and test at desktop and 390px.

## Overridable templates

### Catalog and single pages

| Template | Controls |
|----------|----------|
| `archive-service.php` | The services catalog page |
| `archive-request.php` | The buyer requests board |
| `single-service.php` | A service detail page |
| `single-request.php` | A buyer request detail page |
| `content-service-card.php` | A service card in any grid |
| `content-request-card.php` | A request card in any list |
| `content-no-services.php` | Empty state for the catalog |
| `content-no-requests.php` | Empty state for the requests board |
| `wpss-fullwidth-template.php` | The full-width page wrapper |

### Service page parts

| Template | Controls |
|----------|----------|
| `partials/service-gallery.php` | Image and video gallery |
| `partials/service-packages.php` | The Basic/Standard/Premium tabs |
| `partials/service-faqs.php` | FAQ accordion |
| `partials/service-reviews.php` | Review list and summary |
| `partials/vendor-card.php` | Vendor summary card |
| `partials/vendor-portfolio.php` | Portfolio grid |
| `partials/notifications-list.php` | Notification list markup |
| `partials/billing-fields.php` | Billing form fields |
| `partials/billing-summary.php` | Billing recap at checkout |

### Vendor

| Template | Controls |
|----------|----------|
| `vendor/profile.php` | The public vendor profile |

### Orders

| Template | Controls |
|----------|----------|
| `order/order-view.php` | The order detail screen |
| `order/order-confirmation.php` | Post-checkout confirmation |
| `order/conversation.php` | Order messaging thread |
| `order/order-requirements.php` | Requirements screen |
| `order/requirements-form.php` | The requirements form itself |
| `order/milestone-view.php` | A milestone phase |
| `order/extension-view.php` | A paid extension |
| `order/tip-view.php` | A tip receipt |

### Dashboard

Each section of the unified dashboard is its own template under
`dashboard/sections/`:

`orders.php` · `sales.php` · `services.php` · `create.php` · `requests.php` ·
`create-request.php` · `edit-request.php` · `favorites.php` · `earnings.php` ·
`messages.php` · `notifications.php` · `disputes.php` · `portfolio.php` ·
`profile.php`

### Other

| Template | Controls |
|----------|----------|
| `cart/cart.php` | Shopping cart |
| `disputes/dispute-view.php` | Dispute detail screen |
| `myaccount/vendor-dashboard.php` | Legacy account-area vendor dashboard |
| `myaccount/vendor-services.php` | Legacy account-area service list |
| `myaccount/service-orders.php` | Legacy account-area order list |
| `myaccount/notifications.php` | Legacy account-area notifications |

The `myaccount/` templates serve the standalone account area. Most sites use the
unified dashboard instead -- check which one your pages actually render before
overriding.

### Emails

51 templates under `emails/`. They override the same way, but see
[Email Customization](../developer-guide/email-customization.md) first -- most
changes are better made through the email filters than by copying a template.

## Template hooks

**137 action hooks across 41 templates.** Almost every template opens and closes
with a `before`/`after` pair and exposes named slots in between, so you can
inject markup without copying anything.

```php
// Add a trust badge under every service card.
add_action( 'wpss_after_service_card', function ( $service_id ) {
    if ( get_post_meta( $service_id, '_my_verified', true ) ) {
        echo '<span class="my-badge">Verified</span>';
    }
} );
```

### Catalog

| Hook | Args |
|------|------|
| `wpss_before_service_archive` / `wpss_after_service_archive` | -- |
| `wpss_service_archive_header`, `wpss_service_archive_sidebar` | -- |
| `wpss_before_service_loop` / `wpss_after_service_loop` | -- |
| `wpss_before_request_archive` / `wpss_after_request_archive` | -- |
| `wpss_request_archive_header`, `wpss_request_archive_sidebar` | -- |
| `wpss_before_request_loop` / `wpss_after_request_loop` | -- |
| `wpss_no_services_content`, `wpss_no_requests_content` | -- |

### Cards

| Hook | Args |
|------|------|
| `wpss_before_service_card` / `wpss_after_service_card` | `$service_id` |
| `wpss_service_card_header`, `wpss_service_card_meta`, `wpss_service_card_footer` | `$service_id` |
| `wpss_service_card_image_overlay` | `$service_id` |
| `wpss_before_request_card` / `wpss_after_request_card` | `$request_id` |
| `wpss_request_card_header`, `wpss_request_card_meta`, `wpss_request_card_footer` | `$request_id` |
| `wpss_before_vendor_card` / `wpss_after_vendor_card` | `$vendor_id` |
| `wpss_vendor_card_meta` | `$vendor_id` |

### Single service

| Hook | Args |
|------|------|
| `wpss_before_single_service` / `wpss_after_single_service` | `$service` |
| `wpss_single_service_header`, `_gallery`, `_content`, `_faqs`, `_reviews`, `_sidebar`, `_portfolio`, `_related` | `$service` |
| `wpss_single_service_meta` | `$service_id` |
| `wpss_before_service_gallery` / `wpss_after_service_gallery` | `$service_id` |
| `wpss_before_service_packages` / `wpss_after_service_packages` | `$service_id` |
| `wpss_before_package_tab` / `wpss_after_package_tab` | `$service_id, $index, $package` |
| `wpss_package_features` | `$service_id, $index, $package` |
| `wpss_before_service_faqs` / `wpss_after_service_faqs` | `$service_id` |
| `wpss_before_service_reviews` / `wpss_after_service_reviews` | `$service_id` |
| `wpss_after_single_review` | `$review` |

### Single request

| Hook | Args |
|------|------|
| `wpss_before_single_request` / `wpss_after_single_request` | `$request_id` |
| `wpss_single_request_header`, `_content`, `_proposals`, `_sidebar` | `$request_id` |

### Vendor profile

| Hook | Args |
|------|------|
| `wpss_before_vendor_profile` / `wpss_after_vendor_profile` | `$vendor_id` |
| `wpss_vendor_profile_header`, `_bio`, `_services`, `_reviews`, `_stats`, `_sidebar` | `$vendor_id` |
| `wpss_before_vendor_portfolio` / `wpss_after_vendor_portfolio` | `$vendor_id` |

`wpss_vendor_profile_sidebar` is where Pro renders its analytics teaser -- a
useful precedent if you are adding your own panel.

### Orders

| Hook | Args |
|------|------|
| `wpss_before_order_view` / `wpss_after_order_view` | `$order` |
| `wpss_order_view_header`, `_actions`, `_details`, `_sidebar` | `$order` |
| `wpss_before_order_confirmation` / `wpss_after_order_confirmation` | `$order` |
| `wpss_order_confirmation_details` | `$order` |
| `wpss_before_conversation` / `wpss_after_conversation` | `$order` |
| `wpss_conversation_header`, `wpss_conversation_form` | `$order` |
| `wpss_after_message` | `$message, $order` |
| `wpss_before_requirements_form` / `wpss_after_requirements_form` | `$order` |
| `wpss_requirements_form_fields` | `$order` |
| `wpss_before_requirements_form_component` / `wpss_after_requirements_form_component` | `$order_id, $order` |
| `wpss_before_milestone_view` / `wpss_after_milestone_view` | `$current_order` |
| `wpss_before_extension_view` / `wpss_after_extension_view` | `$current_order` |
| `wpss_before_tip_view` / `wpss_after_tip_view` | `$current_order` |

### Dashboard

Every section fires the same pair, with the section slug as the first argument:

| Hook | Args |
|------|------|
| `wpss_dashboard_section_before` | `$section, $user_id` |
| `wpss_dashboard_section_after` | `$section, $user_id` |

Slugs: `orders`, `sales`, `services`, `create`, `requests`, `create_request`,
`edit_request`, `favorites`, `earnings`, `messages`, `notifications`,
`disputes`, `portfolio`, `profile`.

```php
add_action( 'wpss_dashboard_section_before', function ( $section, $user_id ) {
    if ( 'earnings' === $section ) {
        echo '<div class="notice">Payouts run on Fridays.</div>';
    }
}, 10, 2 );
```

Section-specific slots:

| Hook | Args |
|------|------|
| `wpss_earnings_summary`, `wpss_earnings_ledger_actions` | `$user_id` |
| `wpss_payout_methods` | `$user_id, $payout_method` |
| `wpss_orders_filters` | `$user_id` |
| `wpss_services_list_actions` | `$user_id` |
| `wpss_profile_form_fields` | `$user_id` |

Two sections pass a different second argument -- `profile` passes `$user` (a
`WP_User`) and `portfolio` passes `get_userdata( $user_id )`. If your callback
expects an id everywhere, guard for it.

### Disputes

| Hook | Args |
|------|------|
| `wpss_before_dispute_view` / `wpss_after_dispute_view` | `$dispute, $order` |
| `wpss_dispute_view_header`, `_evidence`, `_resolution` | `$dispute, $order` |

## Template filters

Change values without touching markup at all.

| Filter | Default | Purpose |
|--------|---------|---------|
| `wpss_archive_service_columns` | `3` | Catalog grid columns |
| `wpss_archive_request_columns` | `2` | Requests grid columns |
| `wpss_services_per_page` | `12` | Catalog page size |
| `wpss_requests_per_page` | `10` | Requests page size |
| `wpss_reviews_per_page` | `10` | Reviews shown per service |
| `wpss_service_card_classes` | `['wpss-service-card']` | CSS classes on a card |
| `wpss_request_card_classes` | `['wpss-request-card']` | CSS classes on a request card |
| `wpss_service_card_thumbnail_size` | `medium_large` | Card image size |
| `wpss_gallery_image_size` | `large` | Gallery image size |
| `wpss_package_price_html` | -- | Rendered package price markup |
| `wpss_package_button_text` | -- | Package CTA label |
| `wpss_order_status_label` | -- | Human status label |
| `wpss_order_actions` | -- | Buttons on the order screen |
| `wpss_tip_quick_amounts` | `[5, 10, 20, 50]` | Tip preset buttons |
| `wpss_allow_late_requirements_submission` | `false` | Accept requirements after the timeout |
| `wpss_requirements_form_args` | -- | Requirements form config |
| `wpss_vendor_profile_fields` | `[]` | Extra profile fields |
| `wpss_single_service_layout` | `default` | Layout variant |
| `wpss_single_request_layout` | `default` | Layout variant |
| `wpss_no_services_message` | -- | Empty-state copy |
| `wpss_no_requests_message` | -- | Empty-state copy |
| `wpss_get_template` | -- | Swap a resolved template path |
| `wpss_get_template_part` | -- | Swap a resolved template part |
| `wpss_template_args` | -- | Modify the args passed into a template |

`wpss_get_template` is the surgical option when you want a different file for
one case only, without a blanket override:

```php
add_filter( 'wpss_get_template', function ( $template, $name ) {
    if ( 'content-service-card.php' === $name && is_tax( 'wpss_service_category', 'premium' ) ) {
        return get_stylesheet_directory() . '/wpss-premium-card.php';
    }
    return $template;
}, 10, 2 );
```

## Guidance

- **Always use a child theme.** Overrides in a parent theme are lost when it updates.
- **Copy the whole file.** Partial templates fatal; the loader includes the file as-is.
- **Re-check after plugin updates.** If a default template changed, diff it against your copy and merge. Overrides are the main cause of "broken after update".
- **Do not strip functionality.** Keep nonces, form fields, and data attributes; restyle around them.
- **Test at 390px**, and in dark mode if your theme supports it.

## Troubleshooting

| Problem | Cause |
|---------|-------|
| Override ignored | Path must be exactly `your-theme/wp-sell-services/{same/path}.php`, case-sensitive |
| Still ignored | Object or page cache; flush both |
| Fatal after copying | Partial copy, or a missing variable the template expects |
| Broken after an update | Default template changed structurally -- diff and merge |
| Theme styles clash | Add CSS in the child theme rather than editing the template |

## Related

- [Hooks and Filters](../developer-guide/hooks-filters.md) -- the complete reference
- [Theme Integration](../developer-guide/theme-integration.md) -- design tokens and dark mode
- [Email Customization](../developer-guide/email-customization.md)
- [Shortcodes Reference](shortcodes-reference.md)
