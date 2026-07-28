# Capabilities & Roles

WP Sell Services adds one role and eight capabilities. Everything the plugin
gates -- admin screens, REST routes, abilities -- goes through them, so this is
the page to read before building a custom role or restricting an area.

## The vendor role

`wpss_vendor` is registered on activation. Users get it when they become a
vendor, and it is additive: a subscriber who becomes a vendor keeps reading and
gains selling.

It carries `read`, `upload_files`, `edit_posts`, plus five plugin capabilities.

## The capabilities

| Capability | Grants |
|-----------|--------|
| `wpss_vendor` | Marker capability -- "this user is a vendor". Gates the selling side of the dashboard |
| `wpss_manage_services` | Create and edit own services; also gates vendor-side earnings reads |
| `wpss_manage_orders` | Act on orders (accept, deliver, complete). Gates the admin Orders screen |
| `wpss_view_analytics` | Analytics dashboards |
| `wpss_respond_to_requests` | Submit proposals on buyer requests |
| `wpss_manage_vendors` | Approve, suspend, and edit vendors (admin) |
| `wpss_manage_disputes` | Mediate and resolve disputes (admin) |
| `wpss_manage_settings` | Read and write plugin settings (admin) |

The first five are vendor-facing; the last three are administrative and are not
granted to vendors.

## Who gets what

Verified on a stock install:

| Role | Capabilities |
|------|-------------|
| **administrator** | All eight |
| **wpss_vendor** | `wpss_vendor`, `wpss_manage_services`, `wpss_manage_orders`, `wpss_view_analytics`, `wpss_respond_to_requests` |
| **shop_manager** (WooCommerce) | The same five as `wpss_vendor` |
| **author** | The same five as `wpss_vendor` |
| **editor**, **contributor**, **subscriber** | None |

> **Activation grants vendor capabilities to `author` and `shop_manager`.**
>
> This is deliberate -- a WooCommerce store manager can run the marketplace
> without a second account -- but the `author` case surprises people. **Every
> existing WordPress author on the site becomes a vendor the moment the plugin
> activates**, appearing in the vendor directory and able to publish services,
> regardless of your vendor-registration setting.
>
> On a blog with contributors who are not sellers, that is probably not what you
> want. Strip it after activation:
>
> ```php
> add_action( 'admin_init', function () {
>     $role = get_role( 'author' );
>     if ( ! $role || ! $role->has_cap( 'wpss_vendor' ) ) {
>         return;
>     }
>     foreach ( array( 'wpss_vendor', 'wpss_manage_services', 'wpss_manage_orders',
>                      'wpss_view_analytics', 'wpss_respond_to_requests' ) as $cap ) {
>         $role->remove_cap( $cap );
>     }
> } );
> ```
>
> Note that vendor registration mode does **not** gate this. Closing registration
> stops new sign-ups; it does not revoke capabilities an existing role was
> granted at activation.

## What each capability gates

| Surface | Capability |
|---------|-----------|
| Admin: Orders screen | `wpss_manage_orders` |
| Admin: Disputes screen | `wpss_manage_disputes` |
| Admin: Settings screen | `wpss_manage_settings` |
| Admin: Vendors screen | `wpss_manage_vendors` |
| Admin: top-level menu | `edit_posts` (deliberately low -- vendors reach their own screens) |
| REST: create/update service | `wpss_manage_services` |
| REST: analytics | `wpss_view_analytics` |
| Ability: `wpss/create-service` | `wpss_manage_services` |
| Ability: `wpss/view-earnings`, `wpss/request-withdrawal` | `wpss_manage_services` |
| Ability: `wpss/submit-proposal` | `wpss_respond_to_requests` |
| Ability: analytics / subscriptions / Connect **[PRO]** | `manage_options` (plus `wpss_view_analytics` for analytics) |

> **A quirk worth knowing:** vendor **earnings** reads are gated on
> `wpss_manage_services`, not on a dedicated earnings capability. If you strip
> `wpss_manage_services` from a role to stop it publishing services, you also
> remove its access to earnings and withdrawals. Add a role that keeps
> `wpss_manage_services` but restricts publishing another way if you need that
> split.

## Checking capabilities in code

Use `current_user_can()` as normal:

```php
if ( current_user_can( 'wpss_manage_disputes' ) ) {
    // Show mediation UI.
}
```

To ask "is this user a vendor?", prefer the plugin helper over checking the role
name directly -- it is filterable, so sites that grant vendor status another way
still work:

```php
if ( wpss_is_vendor( $user_id ) ) {
    // ...
}

// Override the answer, e.g. treat a membership level as vendor status.
add_filter( 'wpss_is_vendor', function ( $is_vendor, $user_id ) {
    return $is_vendor || my_plugin_has_seller_membership( $user_id );
}, 10, 2 );
```

## Customising

### Grant a capability to another role

```php
add_action( 'init', function () {
    $role = get_role( 'editor' );
    if ( $role && ! $role->has_cap( 'wpss_manage_disputes' ) ) {
        $role->add_cap( 'wpss_manage_disputes' );
    }
} );
```

Role capabilities are stored in the database, so this only needs to run once --
but guarding with `has_cap()` keeps it idempotent and cheap.

### Remove one

```php
add_action( 'init', function () {
    $role = get_role( 'shop_manager' );
    if ( $role ) {
        $role->remove_cap( 'wpss_manage_services' );
    }
} );
```

### Build a moderator role

A reviewer who mediates and moderates but cannot sell or change settings:

```php
add_action( 'init', function () {
    if ( get_role( 'marketplace_moderator' ) ) {
        return;
    }
    add_role( 'marketplace_moderator', 'Marketplace Moderator', array(
        'read'                 => true,
        'upload_files'         => true,
        'wpss_manage_orders'   => true,
        'wpss_manage_disputes' => true,
        'wpss_manage_vendors'  => true,
    ) );
} );
```

Deliberately no `wpss_manage_settings` (cannot change commission or gateways)
and no `wpss_vendor` (does not appear in the vendor directory).

## Gotchas

- **Roles persist.** `add_role()` and `add_cap()` write to the database. Removing your code does not remove the role -- clean up on your own deactivation hook.
- **Multisite super admins** pass `current_user_can()` for everything, so an admin-only screen is visible network-wide by design.
- **Verify after a migration.** `wp wpss preflight` checks that the expected capabilities exist; a role editor plugin can strip them without warning.

## Related

- [Database Schema](database-schema.md)
- [Abilities API](abilities-api.md)
- [REST API Controllers](rest-api-controllers.md)
- [Building Custom Integrations](custom-integrations.md)
- [Vendor Settings](../vendor-system/vendor-settings.md)
